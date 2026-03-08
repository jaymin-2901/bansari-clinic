/**
 * Admin Reminder API Routes
 * 
 * POST /api/admin/reminders/send     — Send manual reminder for specific appointment
 * GET  /api/admin/reminders/status   — Get reminder status for an appointment
 * GET  /api/admin/reminders/stats    — Get reminder system statistics
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';
import {
  verifyAdminAuth,
  checkRateLimit,
  recordRateLimitAttempt,
  validateAppointmentId,
  errorResponse,
  successResponse,
} from '@/lib/middleware';
import { sendAppointmentReminder } from '@/lib/reminder-service';

export const dynamic = 'force-dynamic';

/**
 * POST — Send manual reminder
 */
export async function POST(request: NextRequest) {
  // Authenticate admin
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  try {
    const body = await request.json();
    const appointmentId = parseInt(body.appointmentId);

    if (!appointmentId || isNaN(appointmentId)) {
      return errorResponse('Valid appointment ID is required');
    }

    // Validate appointment exists
    const isValid = await validateAppointmentId(appointmentId);
    if (!isValid) {
      return errorResponse('Appointment not found', 404);
    }

    // Check rate limit (max 3 manual reminders per appointment per hour)
    const rateLimit = await checkRateLimit(auth.adminId!, appointmentId);
    if (!rateLimit.allowed) {
      return NextResponse.json(
        {
          success: false,
          error: 'Rate limit exceeded. Too many reminder attempts.',
          resetAt: rateLimit.resetAt?.toISOString(),
        },
        { status: 429 }
      );
    }

    // Check if appointment is in valid state for reminders
    const appointment = await prisma.appointment.findUnique({
      where: { id: appointmentId },
      include: { patient: true },
    });

    if (!appointment) {
      return errorResponse('Appointment not found', 404);
    }

    if (['completed', 'cancelled'].includes(appointment.status)) {
      return errorResponse(`Cannot send reminder for ${appointment.status} appointment`);
    }

    if (appointment.confirmation_status === 'confirmed') {
      return errorResponse('Appointment is already confirmed');
    }

    // Reset reminder_sent flag for re-sending
    if (appointment.reminder_sent) {
      await prisma.appointment.update({
        where: { id: appointmentId },
        data: { reminder_sent: false },
      });
    }

    // Record rate limit attempt
    await recordRateLimitAttempt(auth.adminId!, appointmentId);

    // Send the reminder
    const result = await sendAppointmentReminder(appointmentId);

    return successResponse({
      message: 'Reminder sent successfully',
      result,
      remaining: rateLimit.remaining - 1,
    });
  } catch (error) {
    console.error('[Admin Reminder] Error:', error);
    return errorResponse(
      error instanceof Error ? error.message : 'Failed to send reminder',
      500
    );
  }
}

/**
 * GET — Get reminder status/stats
 */
export async function GET(request: NextRequest) {
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  const action = request.nextUrl.searchParams.get('action') || 'stats';
  const appointmentId = parseInt(request.nextUrl.searchParams.get('appointmentId') || '0');

  try {
    if (action === 'status' && appointmentId) {
      // Get reminder status for a specific appointment
      const logs = await prisma.reminderLog.findMany({
        where: { appointment_id: appointmentId },
        orderBy: { created_at: 'desc' },
      });

      const appointment = await prisma.appointment.findUnique({
        where: { id: appointmentId },
        select: {
          reminder_sent: true,
          reminder_sent_at: true,
          confirmation_status: true,
          reply_source: true,
          confirmed_at: true,
        },
      });

      return successResponse({ appointment, logs });
    }

    // Default: Get system-wide stats
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const tomorrow = new Date(today.getTime() + 24 * 60 * 60 * 1000);

    const [
      totalReminders,
      todayReminders,
      pendingConfirmation,
      confirmed,
      cancelled,
      noResponse,
    ] = await Promise.all([
      prisma.reminderLog.count(),
      prisma.reminderLog.count({
        where: { created_at: { gte: today } },
      }),
      prisma.appointment.count({
        where: { confirmation_status: 'reminder_sent' },
      }),
      prisma.appointment.count({
        where: { confirmation_status: 'confirmed' },
      }),
      prisma.appointment.count({
        where: { confirmation_status: 'cancelled' },
      }),
      prisma.appointment.count({
        where: { confirmation_status: 'no_response' },
      }),
    ]);

    // Upcoming appointments needing reminders
    const upcomingNeedingReminder = await prisma.appointment.count({
      where: {
        status: { in: ['pending', 'confirmed'] },
        reminder_sent: false,
        appointment_date: { gte: today, lte: tomorrow },
      },
    });

    return successResponse({
      stats: {
        totalReminders,
        todayReminders,
        pendingConfirmation,
        confirmed,
        cancelled,
        noResponse,
        upcomingNeedingReminder,
      },
    });
  } catch (error) {
    console.error('[Admin Reminder Stats] Error:', error);
    return errorResponse('Failed to fetch stats', 500);
  }
}
