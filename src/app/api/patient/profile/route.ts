/**
 * Patient Profile API Route
 *
 * GET  /api/patient/profile?patient_id=xxx  — Fetch patient profile with appointment summary
 * PATCH /api/patient/profile                — Update patient profile fields
 *
 * Security:
 *   - patient_id is validated server-side
 *   - Email is readonly (cannot be changed via PATCH)
 *   - Input is sanitized and validated
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';
import { z } from 'zod';

export const dynamic = 'force-dynamic';

// ─── Validation Schema ─────────────────────────────────────

const updateProfileSchema = z.object({
  patient_id: z.number().int().positive(),
  full_name: z
    .string()
    .min(2, 'Name must be at least 2 characters')
    .max(150, 'Name must be at most 150 characters')
    .transform((v) => v.trim().replace(/\s+/g, ' '))
    .optional(),
  mobile: z
    .string()
    .regex(/^[6-9]\d{9}$/, 'Enter a valid 10-digit Indian mobile number')
    .optional(),
  age: z
    .number()
    .int()
    .min(0, 'Age must be 0 or above')
    .max(120, 'Age must be 120 or below')
    .optional(),
  gender: z.enum(['male', 'female', 'other']).optional(),
  city: z
    .string()
    .min(1, 'City is required')
    .max(100)
    .transform((v) => v.trim())
    .optional(),
  address: z
    .string()
    .max(500)
    .transform((v) => v.trim())
    .optional()
    .nullable(),
});

// ─── Sanitize string helper ─────────────────────────────────

function sanitize(input: string): string {
  return input
    .replace(/[<>]/g, '') // strip angle brackets
    .replace(/javascript:/gi, '')
    .replace(/on\w+=/gi, '')
    .trim();
}

// ─── GET: Fetch patient profile with appointment summary ────

export async function GET(request: NextRequest) {
  try {
    const patientIdParam = request.nextUrl.searchParams.get('patient_id');

    if (!patientIdParam) {
      return NextResponse.json(
        { success: false, error: 'Missing patient_id parameter' },
        { status: 400 }
      );
    }

    const patientId = parseInt(patientIdParam, 10);
    if (isNaN(patientId) || patientId <= 0) {
      return NextResponse.json(
        { success: false, error: 'Invalid patient_id' },
        { status: 400 }
      );
    }

    // Fetch patient with appointments (relational query — no data duplication)
    const patient = await prisma.patient.findUnique({
      where: { id: patientId },
      include: {
        appointments: {
          select: {
            id: true,
            appointment_date: true,
            appointment_time: true,
            consultation_type: true,
            status: true,
            confirmation_status: true,
            is_followup: true,
            followup_done: true,
            created_at: true,
          },
          orderBy: { appointment_date: 'desc' },
        },
      },
    });

    if (!patient) {
      return NextResponse.json(
        { success: false, error: 'Patient not found' },
        { status: 404 }
      );
    }

    // Compute appointment summary
    const now = new Date();
    const todayStr = now.toISOString().slice(0, 10);

    const upcomingAppointments = patient.appointments.filter((a: any) => {
      const dateStr = new Date(a.appointment_date).toISOString().slice(0, 10);
      return dateStr >= todayStr && a.status !== 'cancelled';
    });

    const completedAppointments = patient.appointments.filter(
      (a: any) => a.status === 'completed'
    );

    const followUpAppointments = patient.appointments.filter(
      (a: any) => a.is_followup && a.status !== 'cancelled'
    );

    const nextAppointment = upcomingAppointments.length > 0
      ? upcomingAppointments[upcomingAppointments.length - 1] // earliest upcoming
      : null;

    return NextResponse.json({
      success: true,
      patient: {
        id: patient.id,
        full_name: patient.full_name,
        mobile: patient.mobile,
        age: patient.age,
        gender: patient.gender,
        city: patient.city,
        address: (patient as any).address ?? null,
        email: patient.email,
        is_registered: patient.is_registered,
        created_at: patient.created_at,
      },
      summary: {
        total_appointments: patient.appointments.length,
        upcoming_count: upcomingAppointments.length,
        completed_count: completedAppointments.length,
        followup_count: followUpAppointments.length,
        next_appointment: nextAppointment,
      },
      appointments: patient.appointments,
    });
  } catch (error) {
    console.error('[Profile GET] Error:', error);
    return NextResponse.json(
      { success: false, error: 'Internal server error' },
      { status: 500 }
    );
  }
}

// ─── PATCH: Update patient profile ──────────────────────────

export async function PATCH(request: NextRequest) {
  try {
    const body = await request.json();

    // Validate input using Zod
    const parsed = updateProfileSchema.safeParse(body);
    if (!parsed.success) {
      const errors = parsed.error.errors.map((e) => ({
        field: e.path.join('.'),
        message: e.message,
      }));
      return NextResponse.json(
        { success: false, error: 'Validation failed', details: errors },
        { status: 400 }
      );
    }

    const { patient_id, ...updateFields } = parsed.data;

    // Verify patient exists
    const existingPatient = await prisma.patient.findUnique({
      where: { id: patient_id },
      select: { id: true, is_registered: true },
    });

    if (!existingPatient) {
      return NextResponse.json(
        { success: false, error: 'Patient not found' },
        { status: 404 }
      );
    }

    if (!existingPatient.is_registered) {
      return NextResponse.json(
        { success: false, error: 'Only registered patients can update their profile' },
        { status: 403 }
      );
    }

    // Build update data — sanitize strings
    const data: Record<string, any> = {};

    if (updateFields.full_name !== undefined) {
      data.full_name = sanitize(updateFields.full_name);
    }
    if (updateFields.mobile !== undefined) {
      data.mobile = updateFields.mobile;
    }
    if (updateFields.age !== undefined) {
      data.age = updateFields.age;
    }
    if (updateFields.gender !== undefined) {
      data.gender = updateFields.gender;
    }
    if (updateFields.city !== undefined) {
      data.city = sanitize(updateFields.city);
    }
    if (updateFields.address !== undefined) {
      data.address = updateFields.address ? sanitize(updateFields.address) : null;
    }

    if (Object.keys(data).length === 0) {
      return NextResponse.json(
        { success: false, error: 'No fields to update' },
        { status: 400 }
      );
    }

    // Check mobile uniqueness if changed
    if (data.mobile) {
      const existing = await prisma.patient.findFirst({
        where: {
          mobile: data.mobile,
          id: { not: patient_id },
        },
        select: { id: true },
      });
      if (existing) {
        return NextResponse.json(
          { success: false, error: 'This mobile number is already registered to another patient' },
          { status: 409 }
        );
      }
    }

    // Perform update
    const updatedPatient = await prisma.patient.update({
      where: { id: patient_id },
      data,
    });

    return NextResponse.json({
      success: true,
      message: 'Profile updated successfully',
      patient: {
        id: updatedPatient.id,
        full_name: updatedPatient.full_name,
        mobile: updatedPatient.mobile,
        age: updatedPatient.age,
        gender: updatedPatient.gender,
        city: updatedPatient.city,
        address: (updatedPatient as any).address ?? null,
        email: updatedPatient.email,
        is_registered: updatedPatient.is_registered,
        created_at: updatedPatient.created_at,
        updated_at: updatedPatient.updated_at,
      },
    });
  } catch (error) {
    console.error('[Profile PATCH] Error:', error);
    return NextResponse.json(
      { success: false, error: 'Internal server error' },
      { status: 500 }
    );
  }
}
