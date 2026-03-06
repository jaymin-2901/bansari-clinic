/**
 * Appointment Confirmation API Route
 * 
 * GET /api/appointment/confirm?token=xxx
 * 
 * Called when patient clicks the "Confirm" button in reminder email
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';
import { confirmAppointment } from '@/lib/reminder-service';

export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest) {
  const token = request.nextUrl.searchParams.get('token');

  if (!token) {
    return renderResponse('Invalid Link', 'The confirmation link is invalid or missing.', 'error');
  }

  try {
    // Find the token
    const confirmationToken = await prisma.confirmationToken.findUnique({
      where: { token },
      include: {
        appointment: {
          include: { patient: true },
        },
      },
    });

    if (!confirmationToken) {
      return renderResponse('Invalid Link', 'This confirmation link is invalid or has already been used.', 'error');
    }

    if (confirmationToken.used) {
      return renderResponse(
        'Already Processed',
        `This appointment has already been ${confirmationToken.action === 'confirm' ? 'confirmed' : 'cancelled'}.`,
        'info'
      );
    }

    if (new Date() > confirmationToken.expires_at) {
      return renderResponse('Link Expired', 'This confirmation link has expired. Please contact the clinic directly.', 'error');
    }

    if (confirmationToken.action !== 'confirm') {
      return renderResponse('Invalid Action', 'This link is not a confirmation link.', 'error');
    }

    // Confirm the appointment
    await confirmAppointment(confirmationToken.appointment_id, 'email');

    // Mark token as used
    await prisma.confirmationToken.update({
      where: { id: confirmationToken.id },
      data: { used: true, used_at: new Date() },
    });

    // Also mark the cancel token as used (if exists)
    await prisma.confirmationToken.updateMany({
      where: {
        appointment_id: confirmationToken.appointment_id,
        action: 'cancel',
        used: false,
      },
      data: { used: true, used_at: new Date() },
    });

    // Update reminder log
    await prisma.reminderLog.updateMany({
      where: {
        appointment_id: confirmationToken.appointment_id,
        channel: 'email',
      },
      data: {
        status: 'replied',
        patient_reply: 'CONFIRMED (via email link)',
        reply_received_at: new Date(),
      },
    });

    const patientName = confirmationToken.appointment.patient.full_name;
    const appointmentDate = new Date(confirmationToken.appointment.appointment_date).toLocaleDateString('en-IN', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });

    return renderResponse(
      'Appointment Confirmed! ✓',
      `Thank you, ${patientName}! Your appointment on ${appointmentDate} has been confirmed. We look forward to seeing you!`,
      'success'
    );
  } catch (error) {
    console.error('[Confirm] Error:', error);
    return renderResponse('Error', 'Something went wrong. Please contact the clinic directly.', 'error');
  }
}

function renderResponse(title: string, message: string, type: 'success' | 'error' | 'info'): NextResponse {
  const colors = {
    success: { bg: '#d1fae5', border: '#059669', text: '#065f46', icon: '✓' },
    error: { bg: '#fee2e2', border: '#dc2626', text: '#991b1b', icon: '✗' },
    info: { bg: '#dbeafe', border: '#2563eb', text: '#1e40af', icon: 'ℹ' },
  };
  const c = colors[type];
  const clinicName = process.env.CLINIC_NAME || 'Bansari Homeopathy Clinic';
  const clinicPhone = process.env.CLINIC_PHONE || '+91 98765 43210';

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${title} – ${clinicName}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #fff; border-radius: 20px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
    .icon { width: 80px; height: 80px; border-radius: 50%; background: ${c.bg}; border: 3px solid ${c.border}; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 24px; color: ${c.text}; }
    h1 { color: #1a2332; font-size: 24px; font-weight: 700; margin-bottom: 16px; }
    p { color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 24px; }
    .clinic { color: #9ca3af; font-size: 14px; }
    .clinic a { color: #004A99; text-decoration: none; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">${c.icon}</div>
    <h1>${title}</h1>
    <p>${message}</p>
    <p class="clinic">${clinicName}<br><a href="tel:${clinicPhone}">${clinicPhone}</a></p>
  </div>
</body>
</html>`;

  return new NextResponse(html, {
    status: 200,
    headers: { 'Content-Type': 'text/html; charset=utf-8' },
  });
}
