/**
 * Email Reminder Service
 * Uses Nodemailer for SMTP-based email delivery
 */
import nodemailer from 'nodemailer';
import { randomBytes } from 'crypto';

interface EmailConfig {
  host: string;
  port: number;
  secure: boolean;
  user: string;
  pass: string;
  from: string;
  replyTo: string;
}

interface SendEmailResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

function getConfig(): EmailConfig {
  return {
    host: process.env.SMTP_HOST || 'smtp.gmail.com',
    port: parseInt(process.env.SMTP_PORT || '587'),
    secure: process.env.SMTP_SECURE === 'true',
    user: process.env.SMTP_USER || '',
    pass: process.env.SMTP_PASS || '',
    from: process.env.EMAIL_FROM || 'Bansari Homeopathy Clinic <noreply@bansarihomeopathy.com>',
    replyTo: process.env.EMAIL_REPLY_TO || 'info@bansarihomeopathy.com',
  };
}

/**
 * Get clinic address from database settings API
 * Always returns the new correct address (hardcoded to ensure consistency)
 */
async function getClinicAddress(): Promise<string> {
  // Always use the new correct address
  const newAddress = '212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.';
  
  return newAddress;
}

let transporter: nodemailer.Transporter | null = null;

function getTransporter(): nodemailer.Transporter {
  if (transporter) return transporter;

  const config = getConfig();
  transporter = nodemailer.createTransport({
    host: config.host,
    port: config.port,
    secure: config.secure,
    auth: {
      user: config.user,
      pass: config.pass,
    },
    pool: true,
    maxConnections: 3,
    maxMessages: 100,
    rateDelta: 1000,
    rateLimit: 5,
  });

  return transporter;
}

/**
 * Generate a secure confirmation token
 */
export function generateToken(): string {
  return randomBytes(32).toString('hex');
}

/**
 * Send appointment reminder email with confirm/cancel buttons
 */
export async function sendReminderEmail(
  to: string,
  patientName: string,
  appointmentDate: string,
  appointmentTime: string,
  confirmToken: string,
  cancelToken: string
): Promise<SendEmailResult> {
  const config = getConfig();

  if (!config.user || !config.pass) {
    console.error('[Email] Missing SMTP configuration');
    return { success: false, error: 'Email service not configured' };
  }

  const appUrl = process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000';
  const confirmUrl = `${appUrl}/api/appointment/confirm?token=${confirmToken}`;
  const cancelUrl = `${appUrl}/api/appointment/cancel?token=${cancelToken}`;
  const clinicName = process.env.CLINIC_NAME || 'Bansari Homeopathy Clinic';
  const clinicPhone = process.env.CLINIC_PHONE || '+91 63543 88539';
  
  // Get clinic address from database settings API
  const clinicAddress = await getClinicAddress();

  const html = buildReminderEmailHTML({
    patientName,
    appointmentDate,
    appointmentTime,
    confirmUrl,
    cancelUrl,
    clinicName,
    clinicPhone,
    clinicAddress,
  });

  try {
    const transport = getTransporter();

    const info = await transport.sendMail({
      from: config.from,
      replyTo: config.replyTo,
      to,
      subject: `Appointment Reminder – ${clinicName}`,
      html,
      text: buildReminderEmailText({
        patientName,
        appointmentDate,
        appointmentTime,
        confirmUrl,
        cancelUrl,
        clinicName,
        clinicPhone,
      }),
    });

    console.log(`[Email] Reminder sent to ${to}, ID: ${info.messageId}`);
    return { success: true, messageId: info.messageId };
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error('[Email] Send failed:', errorMessage);
    return { success: false, error: errorMessage };
  }
}

/**
 * Build professional HTML email template
 */
function buildReminderEmailHTML(data: {
  patientName: string;
  appointmentDate: string;
  appointmentTime: string;
  confirmUrl: string;
  cancelUrl: string;
  clinicName: string;
  clinicPhone: string;
  clinicAddress: string;
}): string {
  return `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointment Reminder</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
          
          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#004A99 0%,#002F6C 100%);padding:32px 40px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
                ${data.clinicName}
              </h1>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">
                Appointment Reminder
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
                This is a friendly reminder about your upcoming appointment at ${data.clinicName}.
              </p>

              <!-- Appointment Details Card -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f7ff;border-radius:12px;border:1px solid #dbeafe;margin:0 0 32px;">
                <tr>
                  <td style="padding:24px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding:8px 0;color:#6b7280;font-size:14px;font-weight:600;width:120px;">
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
                          📍 Location:
                        </td>
                        <td style="padding:8px 0;color:#1a2332;font-size:14px;">
                          ${data.clinicAddress}
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px;color:#4b5563;font-size:15px;line-height:1.6;">
                Please confirm or cancel your appointment using the buttons below:
              </p>

              <!-- Action Buttons -->
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding:0 0 16px;">
                    <table cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding-right:12px;">
                          <a href="${data.confirmUrl}" 
                             style="display:inline-block;padding:14px 36px;background:#059669;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;letter-spacing:0.3px;">
                            ✓ Confirm Appointment
                          </a>
                        </td>
                        <td>
                          <a href="${data.cancelUrl}" 
                             style="display:inline-block;padding:14px 36px;background:#dc2626;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;letter-spacing:0.3px;">
                            ✗ Cancel Appointment
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <p style="margin:24px 0 0;color:#9ca3af;font-size:13px;line-height:1.5;text-align:center;">
                If the buttons don't work, copy and paste these links:<br>
                Confirm: <a href="${data.confirmUrl}" style="color:#004A99;">${data.confirmUrl}</a><br>
                Cancel: <a href="${data.cancelUrl}" style="color:#dc2626;">${data.cancelUrl}</a>
              </p>
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
                This is an automated reminder. Please do not reply to this email.
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

/**
 * Build plain text fallback for email
 */
function buildReminderEmailText(data: {
  patientName: string;
  appointmentDate: string;
  appointmentTime: string;
  confirmUrl: string;
  cancelUrl: string;
  clinicName: string;
  clinicPhone: string;
}): string {
  return `
APPOINTMENT REMINDER - ${data.clinicName}

Dear ${data.patientName},

This is a reminder for your upcoming appointment:

  Date: ${data.appointmentDate}
  Time: ${data.appointmentTime}

To CONFIRM your appointment, visit:
${data.confirmUrl}

To CANCEL your appointment, visit:
${data.cancelUrl}

If you have any questions, please call us at ${data.clinicPhone}.

Thank you,
${data.clinicName}

---
This is an automated reminder. Please do not reply to this email.
`.trim();
}
