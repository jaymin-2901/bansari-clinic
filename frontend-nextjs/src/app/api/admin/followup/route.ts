/**
 * Admin Follow-up API Route
 * 
 * POST /api/admin/followup — Create follow-up appointment
 * GET  /api/admin/followup — Get follow-up suggestions
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';
import { verifyAdminAuth, errorResponse, successResponse } from '@/lib/middleware';

export const dynamic = 'force-dynamic';

/**
 * GET — Get appointments that should have follow-ups created
 */
export async function GET(request: NextRequest) {
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  try {
    // Find confirmed appointments that haven't created a follow-up yet
    const suggestions = await prisma.appointment.findMany({
      where: {
        status: 'confirmed',
        confirmation_status: 'confirmed',
        followup_created: false,
        is_followup: false,
      },
      include: {
        patient: {
          select: {
            id: true,
            full_name: true,
            mobile: true,
            email: true,
          },
        },
      },
      orderBy: { appointment_date: 'asc' },
      take: 20,
    });

    return successResponse({ suggestions });
  } catch (error) {
    console.error('[FollowUp] Error:', error);
    return errorResponse('Failed to fetch follow-up suggestions', 500);
  }
}

/**
 * POST — Create a follow-up appointment
 */
export async function POST(request: NextRequest) {
  const auth = await verifyAdminAuth(request);
  if (!auth.authenticated) {
    return errorResponse(auth.error || 'Unauthorized', 401);
  }

  try {
    const body = await request.json();
    const { appointmentId, followupDate, followupTime, notes } = body;

    if (!appointmentId) {
      return errorResponse('Appointment ID is required');
    }

    const parentAppointment = await prisma.appointment.findUnique({
      where: { id: parseInt(appointmentId) },
      include: { patient: true },
    });

    if (!parentAppointment) {
      return errorResponse('Parent appointment not found', 404);
    }

    if (parentAppointment.followup_created) {
      return errorResponse('Follow-up already created for this appointment');
    }

    // Calculate follow-up date (default: 2 weeks from appointment)
    const fDate = followupDate
      ? new Date(followupDate)
      : new Date(new Date(parentAppointment.appointment_date).getTime() + 14 * 24 * 60 * 60 * 1000);

    // Skip Sunday
    if (fDate.getDay() === 0) {
      fDate.setDate(fDate.getDate() + 1);
    }

    const followup = await prisma.appointment.create({
      data: {
        patient_id: parentAppointment.patient_id,
        consultation_type: parentAppointment.consultation_type,
        form_type: parentAppointment.form_type,
        appointment_date: fDate,
        appointment_time: followupTime
          ? new Date(`1970-01-01T${followupTime}`)
          : parentAppointment.appointment_time,
        status: 'pending',
        confirmation_status: 'pending',
        is_followup: true,
        parent_appointment_id: parentAppointment.id,
        admin_notes: notes || `Follow-up from appointment #${parentAppointment.id}`,
      },
    });

    // Mark parent as having created follow-up
    await prisma.appointment.update({
      where: { id: parentAppointment.id },
      data: { followup_created: true },
    });

    return successResponse({
      message: 'Follow-up appointment created',
      followup: {
        id: followup.id,
        date: followup.appointment_date,
        patientName: parentAppointment.patient.full_name,
      },
    });
  } catch (error) {
    console.error('[FollowUp] Create error:', error);
    return errorResponse(
      error instanceof Error ? error.message : 'Failed to create follow-up',
      500
    );
  }
}
