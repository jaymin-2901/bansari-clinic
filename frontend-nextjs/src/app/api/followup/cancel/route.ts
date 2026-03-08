/**
 * Follow-Up Appointment Cancellation
 * 
 * GET /api/followup/cancel?id=appointmentId
 * 
 * Cancels a follow-up appointment (from email link or direct).
 * Updates status to cancelled with timestamp.
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';

export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest) {
  const appointmentId = parseInt(request.nextUrl.searchParams.get('id') || '0');
  const token = request.nextUrl.searchParams.get('token') || '';

  if (!appointmentId && !token) {
    return renderPage('error', 'Invalid cancellation link.');
  }

  try {
    let aptId = appointmentId;

    // If token provided, look up appointment from token
    if (token) {
      const tokenRecord = await prisma.confirmationToken.findUnique({
        where: { token },
      });

      if (!tokenRecord) {
        return renderPage('error', 'Invalid or expired cancellation link.');
      }

      if (tokenRecord.used) {
        return renderPage('info', 'This appointment has already been cancelled.');
      }

      if (new Date() > tokenRecord.expires_at) {
        return renderPage('error', 'This link has expired. Please contact the clinic.');
      }

      if (tokenRecord.action !== 'cancel') {
        return renderPage('error', 'Invalid cancellation link.');
      }

      aptId = tokenRecord.appointment_id;

      // Mark token as used
      await prisma.confirmationToken.update({
        where: { id: tokenRecord.id },
        data: { used: true, used_at: new Date() },
      });
    }

    // Fetch appointment
    const appointment = await prisma.appointment.findUnique({
      where: { id: aptId },
      include: { patient: true },
    });

    if (!appointment) {
      return renderPage('error', 'Appointment not found.');
    }

    if (appointment.status === 'cancelled') {
      return renderPage('info', 'This appointment has already been cancelled.');
    }

    if (appointment.confirmation_status === 'confirmed') {
      return renderPage('info', 'This appointment is already confirmed. Please contact the clinic to cancel.');
    }

    // Cancel the appointment
    await prisma.appointment.update({
      where: { id: aptId },
      data: {
        status: 'cancelled',
        confirmation_status: 'cancelled',
        reply_source: 'email',
      },
    });

    const dateStr = new Date(appointment.appointment_date).toLocaleDateString('en-IN', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });

    return renderPage(
      'cancelled',
      `Your follow-up appointment on <strong>${dateStr}</strong> has been cancelled. If you'd like to reschedule, please contact the clinic.`,
      appointment.patient.full_name
    );
  } catch (error) {
    console.error('[Followup Cancel] Error:', error);
    return renderPage('error', 'An error occurred. Please contact the clinic directly.');
  }
}

function renderPage(
  type: 'cancelled' | 'error' | 'info',
  message: string,
  patientName?: string
): NextResponse {
  const colors = {
    cancelled: { bg: '#fef2f2', icon: '🔴', heading: 'Appointment Cancelled' },
    error: { bg: '#fef2f2', icon: '❌', heading: 'Error' },
    info: { bg: '#eff6ff', icon: 'ℹ️', heading: 'Information' },
  };
  const config = colors[type];

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${config.heading} – Bansari Homeopathy Clinic</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
    .card { background: white; border-radius: 16px; padding: 2.5rem; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .icon { font-size: 3.5rem; margin-bottom: 1rem; }
    h1 { color: #1a2332; font-size: 1.5rem; margin-bottom: 0.75rem; }
    .message { color: #4b5563; line-height: 1.6; margin-bottom: 1.5rem; }
    .patient-name { font-weight: 600; color: #dc2626; }
    .clinic-info { background: ${config.bg}; border-radius: 12px; padding: 1rem; margin-top: 1rem; }
    .clinic-info p { color: #374151; font-size: 0.9rem; margin: 0.25rem 0; }
    .clinic-name { font-weight: 700; color: #004A99; font-size: 1rem; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">${config.icon}</div>
    <h1>${config.heading}</h1>
    ${patientName ? `<p class="patient-name">${patientName}</p>` : ''}
    <p class="message">${message}</p>
    <div class="clinic-info">
      <p class="clinic-name">Bansari Homeopathy Clinic</p>
      <p>📞 Contact us to reschedule your appointment</p>
    </div>
  </div>
</body>
</html>`;

  return new NextResponse(html, {
    status: 200,
    headers: { 'Content-Type': 'text/html; charset=utf-8' },
  });
}
