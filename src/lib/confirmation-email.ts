/**
 * Appointment Confirmation Email Service
 *
 * Sends a professional confirmation email when an appointment
 * status changes to "confirmed". Ensures email is sent only once
 * by tracking `confirmation_email_sent` + `confirmation_email_sent_at`.
 */
import nodemailer from 'nodemailer';
import prisma from './prisma';

interface ConfirmationEmailResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

interface ConfirmationEmailData {
  patientName: string;
  patientEmail: string;
  appointmentDate: string;
  appointmentTime: string;
  clinicName: string;
  clinicPhone: string;
  clinicAddress: string;
}

function getSmtpConfig() {
  return {
    host: process.env.SMTP_HOST || 'smtp.gmail.com',
    port: parseInt(process.env.SMTP_PORT || '587'),
    secure: process.env.SMTP_SECURE === 'true',
    user: process.env.SMTP_USER || '',
    pass: process.env.SMTP_PASS || '',
    from: process.env.EMAIL_FROM || 'Bansari Homeopathy Clinic <noreply@bansarihomeopathy.com>',
    replyTo: process.env.EMAIL_REPLY_TO || 'noreply@bansarihomeopathy.com',
  };
}

/**
 * Send a confirmation email for a confirmed appointment.
 * Only sends if confirmationEmailSent is false for that appointment.
 */
export async function sendConfirmationEmail(
  appointmentId: number
): Promise<ConfirmationEmailResult> {
  try {
    // Fetch appointment with patient data (relational — no duplication)
    const appointment = await prisma.appointment.findUnique({
      where: { id: appointmentId },
      include: { patient: true },
    });

    if (!appointment) {
      return { success: false, error: `Appointment ${appointmentId} not found` };
    }

    // Guard: only send for confirmed appointments
    if (appointment.status !== 'confirmed') {
      return { success: false, error: 'Appointment is not confirmed' };
    }

    // Guard: don't send duplicate confirmation emails
    if ((appointment as any).confirmation_email_sent) {
      console.log(`[ConfirmEmail] Already sent for appointment ${appointmentId}`);
      return { success: true, messageId: 'already_sent' };
    }

    // Guard: patient must have email
    const patientEmail = appointment.patient.email;
    if (!patientEmail) {
      return { success: false, error: 'Patient has no email address' };
    }

    const config = getSmtpConfig();
    if (!config.user || !config.pass) {
      console.error('[ConfirmEmail] Missing SMTP configuration');
      return { success: false, error: 'Email service not configured' };
    }

    const clinicName = process.env.CLINIC_NAME || 'Bansari Homeopathy Clinic';
    const clinicPhone = process.env.CLINIC_PHONE || '+91 98765 43210';
    const clinicAddress = process.env.CLINIC_ADDRESS || 'Ahmedabad, Gujarat';

    const dateStr = formatDate(appointment.appointment_date);
    const timeStr = formatTime(appointment.appointment_time);

    const emailData: ConfirmationEmailData = {
      patientName: appointment.patient.full_name,
      patientEmail,
      appointmentDate: dateStr,
      appointmentTime: timeStr,
      clinicName,
      clinicPhone,
      clinicAddress,
    };

    // Create transporter
    const transporter = nodemailer.createTransport({
      host: config.host,
      port: config.port,
      secure: config.secure,
      auth: {
        user: config.user,
        pass: config.pass,
      },
    });

    // Send email
    const info = await transporter.sendMail({
      from: config.from,
      replyTo: config.replyTo,
      to: patientEmail,
      subject: `Appointment Confirmed – ${clinicName}`,
      html: buildConfirmationEmailHTML(emailData),
      text: buildConfirmationEmailText(emailData),
    });

    console.log(`[ConfirmEmail] Sent to ${patientEmail}, ID: ${info.messageId}`);

    // Mark as sent — ensures idempotency
    await prisma.appointment.update({
      where: { id: appointmentId },
      data: {
        confirmation_email_sent: true,
        confirmation_email_sent_at: new Date(),
      } as any,
    });

    return { success: true, messageId: info.messageId };
  } catch (error) {
    const errorMsg = error instanceof Error ? error.message : 'Unknown error';
    console.error(`[ConfirmEmail] Failed for appointment ${appointmentId}:`, errorMsg);
    return { success: false, error: errorMsg };
  }
}

// ─── Date / Time Formatting ─────────────────────────────────

function formatDate(date: Date): string {
  return new Date(date).toLocaleDateString('en-IN', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function formatTime(time: Date | null): string {
  if (!time) return 'To be confirmed';
  const d = new Date(time);
  return d.toLocaleTimeString('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

// ─── HTML Template ──────────────────────────────────────────

function buildConfirmationEmailHTML(data: ConfirmationEmailData): string {
  return `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointment Confirmed</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#059669 0%,#047857 100%);padding:32px 40px;text-align:center;">
              <div style="font-size:48px;margin-bottom:12px;">✅</div>
              <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
                Appointment Confirmed!
              </h1>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:14px;">
                ${data.clinicName}
              </p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 20px;color:#1a2332;font-size:16px;line-height:1.6;">
                Dear <strong>${data.patientName}</strong>,
              </p>

              <p style="margin:0 0 24px;color:#4b5563;font-size:15px;line-height:1.6;">
                We are pleased to confirm your appointment at <strong>${data.clinicName}</strong>. 
                Please find your appointment details below.
              </p>

              <!-- Appointment Details Card -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0;margin:0 0 32px;">
                <tr>
                  <td style="padding:24px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;width:140px;">
                          👤 Patient:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:16px;font-weight:700;">
                          ${data.patientName}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;">
                          📅 Date:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:16px;font-weight:700;">
                          ${data.appointmentDate}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;">
                          🕐 Time:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:16px;font-weight:700;">
                          ${data.appointmentTime}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;">
                          🏥 Clinic:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:14px;">
                          ${data.clinicName}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;">
                          📍 Location:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:14px;">
                          ${data.clinicAddress}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;">
                          📞 Contact:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:14px;">
                          ${data.clinicPhone}
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Thank You Message -->
              <div style="background:#eff6ff;border-radius:12px;padding:20px;border:1px solid #bfdbfe;margin-bottom:24px;">
                <p style="margin:0;color:#1e40af;font-size:15px;line-height:1.6;text-align:center;">
                  🙏 Thank you for choosing ${data.clinicName}.<br>
                  We look forward to providing you the best homeopathic care.
                </p>
              </div>

              <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.6;">
                <strong>Please note:</strong>
              </p>
              <ul style="margin:8px 0 0;padding-left:20px;color:#6b7280;font-size:13px;line-height:1.8;">
                <li>Please arrive 10 minutes before your scheduled time.</li>
                <li>Bring any previous medical reports or prescriptions.</li>
                <li>If you need to reschedule, please contact us at <strong>${data.clinicPhone}</strong>.</li>
              </ul>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;padding:24px 40px;border-top:1px solid #e2e8f0;text-align:center;">
              <p style="margin:0 0 8px;color:#6b7280;font-size:13px;">
                ${data.clinicName} | ${data.clinicPhone}
              </p>
              <p style="margin:0;color:#9ca3af;font-size:12px;">
                ${data.clinicAddress}
              </p>
              <p style="margin:12px 0 0;color:#9ca3af;font-size:11px;">
                This is an automated confirmation email. Please do not reply to this email.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>`;
}

// ─── Plain Text Fallback ────────────────────────────────────

function buildConfirmationEmailText(data: ConfirmationEmailData): string {
  return `
APPOINTMENT CONFIRMED - ${data.clinicName}

Dear ${data.patientName},

Your appointment has been confirmed. Here are the details:

  Patient:  ${data.patientName}
  Date:     ${data.appointmentDate}
  Time:     ${data.appointmentTime}
  Clinic:   ${data.clinicName}
  Location: ${data.clinicAddress}
  Contact:  ${data.clinicPhone}

Please arrive 10 minutes before your scheduled time and bring any 
previous medical reports or prescriptions.

If you need to reschedule, please contact us at ${data.clinicPhone}.

Thank you for choosing ${data.clinicName}.
We look forward to providing you the best homeopathic care.

---
This is an automated confirmation email. Please do not reply.
`.trim();
}
