/**
 * Admin Appointment Confirm API Route
 *
 * POST /api/admin/confirm
 *
 * Allows admin to manually confirm an appointment.
 * Automatically triggers confirmation email to patient.
 *
 * Auth: x-admin-api-key header required
 */
import { NextRequest } from 'next/server';
import prisma from '@/lib/prisma';
import { verifyAdminAuth, errorResponse, successResponse } from '@/lib/middleware';
import { confirmAppointment } from '@/lib/reminder-service';

export const dynamic = 'force-dynamic';

export async function POST(request: NextRequest) {
  // Verify admin authentication
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  try {
    const body = await request.json();
    const { appointment_id } = body;

    if (!appointment_id || isNaN(Number(appointment_id))) {
      return errorResponse('Valid appointment_id is required');
    }

    const appointmentId = Number(appointment_id);

    // Verify appointment exists
    const appointment = await prisma.appointment.findUnique({
      where: { id: appointmentId },
      include: { patient: true },
    });

    if (!appointment) {
      return errorResponse('Appointment not found', 404);
    }

    if (appointment.status === 'confirmed') {
      return successResponse({
        message: 'Appointment is already confirmed',
        appointment_id: appointmentId,
        already_confirmed: true,
      });
    }

    if (appointment.status === 'cancelled') {
      return errorResponse('Cannot confirm a cancelled appointment', 400);
    }

    // Confirm the appointment (this also triggers confirmation email)
    await confirmAppointment(appointmentId, 'manual');

    // Fetch updated appointment
    const updatedAppointment = await prisma.appointment.findUnique({
      where: { id: appointmentId },
      select: {
        id: true,
        status: true,
        confirmation_status: true,
        confirmed_at: true,
        patient: {
          select: {
            full_name: true,
            email: true,
            mobile: true,
          },
        },
      },
    });

    return successResponse({
      message: 'Appointment confirmed successfully',
      appointment: updatedAppointment,
    });
  } catch (error) {
    console.error('[AdminConfirm] Error:', error);
    return errorResponse('Internal server error', 500);
  }
}
