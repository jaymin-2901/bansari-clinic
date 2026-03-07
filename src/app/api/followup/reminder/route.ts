/**
 * Follow-Up Reminder API Route
 * 
 * POST /api/followup/reminder — Send WhatsApp or Email reminder for a follow-up appointment
 *   body: { appointmentId: number, channel: 'whatsapp' | 'email' | 'both' }
 * GET  /api/followup/reminder — Get followup reminder stats
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';
import {
  verifyAdminAuth,
  checkRateLimit,
  recordRateLimitAttempt,
  errorResponse,
  successResponse,
  logReminderAction,
} from '@/lib/middleware';
import { sendWhatsAppTextMessage } from '@/lib/whatsapp';
import { sendReminderEmail, generateToken } from '@/lib/email';

export const dynamic = 'force-dynamic';

/** CORS preflight */
export async function OPTIONS() {
  return new NextResponse(null, {
    status: 204,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, x-admin-api-key, x-admin-id, x-cron-secret',
    },
  });
}

/**
 * POST — Send follow-up reminder via specified channel
 */
export async function POST(request: NextRequest) {
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  try {
    const body = await request.json();
    const appointmentId = parseInt(body.appointmentId);
    const channel: string = body.channel || 'both'; // 'whatsapp' | 'email' | 'both'

    if (!appointmentId || isNaN(appointmentId)) {
      return errorResponse('Valid appointment ID is required');
    }

    if (!['whatsapp', 'email', 'both'].includes(channel)) {
      return errorResponse('Channel must be "whatsapp", "email", or "both"');
    }

    // Fetch appointment with patient
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

    // Rate limit: max 3 per appointment per 10 minutes
    const rateLimit = await checkRateLimit(auth.adminId!, appointmentId, 'followup_reminder', 3, 10);
    if (!rateLimit.allowed) {
      return NextResponse.json(
        {
          success: false,
          error: 'Rate limit exceeded. Please wait before sending another reminder.',
          resetAt: rateLimit.resetAt?.toISOString(),
        },
        { status: 429 }
      );
    }

    // Check duplicate send within 10 minutes
    if (appointment.reminder_sent && appointment.reminder_sent_at) {
      const tenMinutesAgo = new Date(Date.now() - 10 * 60 * 1000);
      if (appointment.reminder_sent_at > tenMinutesAgo) {
        return errorResponse('Reminder was already sent within the last 10 minutes. Please wait.');
      }
    }

    const patient = appointment.patient;
    const dateStr = formatDate(appointment.appointment_date);
    const timeStr = formatTime(appointment.appointment_time);

    const result = {
      appointmentId,
      channel,
      whatsapp: { sent: false, error: '', skipped: false } as { sent: boolean; messageId?: string; error?: string; skipped: boolean },
      email: { sent: false, error: '', skipped: false } as { sent: boolean; messageId?: string; error?: string; skipped: boolean },
    };

    // ── WhatsApp ──
    if (channel === 'whatsapp' || channel === 'both') {
      if (patient.mobile) {
        try {
          const message = `Hello ${patient.full_name},\nThis is a reminder for your follow-up appointment at Bansari Homeopathy Clinic scheduled on ${dateStr} at ${timeStr}.\nPlease reply YES to confirm or NO to cancel.`;

          const waResult = await sendWhatsAppTextMessage(patient.mobile, message);

          result.whatsapp = {
            sent: waResult.success,
            messageId: waResult.messageId,
            error: waResult.error,
            skipped: false,
          };

          await logReminderAction(
            appointmentId,
            'whatsapp',
            waResult.success ? 'sent' : 'failed',
            patient.mobile,
            waResult.messageId,
            waResult.error
          );

          if (waResult.success && waResult.messageId) {
            await prisma.appointment.update({
              where: { id: appointmentId },
              data: { whatsapp_message_id: waResult.messageId },
            });
          }
        } catch (error) {
          const errorMsg = error instanceof Error ? error.message : 'WhatsApp send failed';
          result.whatsapp = { sent: false, error: errorMsg, skipped: false };
          await logReminderAction(appointmentId, 'whatsapp', 'failed', patient.mobile, undefined, errorMsg);
        }
      } else {
        result.whatsapp = { sent: false, error: 'No mobile number on file', skipped: false };
      }
    } else {
      result.whatsapp = { sent: false, error: '', skipped: true };
    }

    // ── Email ──
    if (channel === 'email' || channel === 'both') {
      if (patient.email) {
        try {
          const confirmToken = generateToken();
          const cancelToken = generateToken();
          const expiresAt = new Date(Date.now() + 48 * 60 * 60 * 1000);

          await prisma.confirmationToken.createMany({
            data: [
              { appointment_id: appointmentId, token: confirmToken, action: 'confirm', expires_at: expiresAt },
              { appointment_id: appointmentId, token: cancelToken, action: 'cancel', expires_at: expiresAt },
            ],
          });

          const emailResult = await sendReminderEmail(
            patient.email,
            patient.full_name,
            dateStr,
            timeStr,
            confirmToken,
            cancelToken
          );

          result.email = {
            sent: emailResult.success,
            messageId: emailResult.messageId,
            error: emailResult.error,
            skipped: false,
          };

          await logReminderAction(
            appointmentId,
            'email',
            emailResult.success ? 'sent' : 'failed',
            patient.email,
            emailResult.messageId,
            emailResult.error
          );

          if (emailResult.success && emailResult.messageId) {
            await prisma.appointment.update({
              where: { id: appointmentId },
              data: { email_message_id: emailResult.messageId },
            });
          }
        } catch (error) {
          const errorMsg = error instanceof Error ? error.message : 'Email send failed';
          result.email = { sent: false, error: errorMsg, skipped: false };
          await logReminderAction(appointmentId, 'email', 'failed', patient.email!, undefined, errorMsg);
        }
      } else {
        result.email = { sent: false, error: 'No email on file', skipped: false };
      }
    } else {
      result.email = { sent: false, error: '', skipped: true };
    }

    // ── Update appointment reminder status ──
    const anySent = result.whatsapp.sent || result.email.sent;
    if (anySent) {
      const source = result.whatsapp.sent ? 'manual_whatsapp' : 'manual_email';
      await prisma.appointment.update({
        where: { id: appointmentId },
        data: {
          reminder_sent: true,
          reminder_sent_at: new Date(),
          confirmation_status: 'reminder_sent',
          reply_source: source as any,
        },
      });
    }

    // Record rate limit
    await recordRateLimitAttempt(auth.adminId!, appointmentId, 'followup_reminder');

    return successResponse({
      message: anySent ? 'Follow-up reminder sent successfully' : 'Failed to send reminder',
      result,
      remaining: rateLimit.remaining - 1,
    });
  } catch (error) {
    console.error('[Followup Reminder] Error:', error);
    return errorResponse(
      error instanceof Error ? error.message : 'Failed to send follow-up reminder',
      500
    );
  }
}

/**
 * GET — Get followup appointments needing reminders
 */
export async function GET(request: NextRequest) {
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  try {
    const now = new Date();
    const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);

    const followups = await prisma.appointment.findMany({
      where: {
        status: { in: ['pending', 'confirmed'] },
        appointment_date: { gte: now },
      },
      include: {
        patient: {
          select: { id: true, full_name: true, mobile: true, email: true },
        },
      },
      orderBy: { appointment_date: 'asc' },
      take: 50,
    });

    const needsReminder = followups.filter(
      (a) => !a.reminder_sent && a.appointment_date <= tomorrow
    ).length;

    return successResponse({
      followups,
      stats: {
        total: followups.length,
        needsReminder,
        reminderSent: followups.filter((a) => a.reminder_sent).length,
        confirmed: followups.filter((a) => a.confirmation_status === 'confirmed').length,
      },
    });
  } catch (error) {
    console.error('[Followup Stats] Error:', error);
    return errorResponse('Failed to fetch followup stats', 500);
  }
}

function formatDate(date: Date): string {
  return new Date(date).toLocaleDateString('en-IN', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format appointment time for display in emails
 * Handles MySQL TIME column which can be returned as Date object or string
 */
function formatTime(time: Date | string | null): string {
  if (!time) return 'To be confirmed';
  
  let hours: number;
  let minutes: number;
  
  if (typeof time === 'string') {
    // Handle string format like "15:00:00" or "15:00"
    const parts = time.split(':').map(Number);
    hours = parts[0];
    minutes = parts[1] || 0;
  } else if (time instanceof Date) {
    // Handle Date object from Prisma (MySQL TIME column)
    // Get the time components directly - MySQL TIME stores as Date with time portion
    // Use UTC hours to avoid timezone shift - MySQL TIME stores time in UTC
    hours = time.getUTCHours();
    minutes = time.getUTCMinutes();
  } else {
    return 'To be confirmed';
  }
  
  // Validate the parsed values
  if (isNaN(hours) || isNaN(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
    return 'To be confirmed';
  }
  
  // Format in 12-hour format with AM/PM
  const period = hours >= 12 ? 'PM' : 'AM';
  const displayHours = hours % 12 || 12;
  const displayMinutes = minutes.toString().padStart(2, '0');
  
  return `${displayHours}:${displayMinutes} ${period}`;
}
