/**
 * Core Reminder Service
 * Handles the business logic for sending reminders via WhatsApp and Email
 */
import prisma from './prisma';
import { sendWhatsAppReminder } from './whatsapp';
import { sendReminderEmail, generateToken } from './email';
import { logReminderAction } from './middleware';
import { sendConfirmationEmail } from './confirmation-email';

interface ReminderResult {
  appointmentId: number;
  whatsapp: { sent: boolean; messageId?: string; error?: string };
  email: { sent: boolean; messageId?: string; error?: string };
}

/**
 * Send reminders for a single appointment (both WhatsApp + Email)
 */
export async function sendAppointmentReminder(appointmentId: number): Promise<ReminderResult> {
  const appointment = await prisma.appointment.findUnique({
    where: { id: appointmentId },
    include: { patient: true },
  });

  if (!appointment) {
    throw new Error(`Appointment ${appointmentId} not found`);
  }

  if (appointment.reminder_sent) {
    throw new Error(`Reminder already sent for appointment ${appointmentId}`);
  }

  const patient = appointment.patient;
  const dateStr = formatAppointmentDate(appointment.appointment_date);
  const timeStr = formatAppointmentTime(appointment.appointment_time);

  const result: ReminderResult = {
    appointmentId,
    whatsapp: { sent: false },
    email: { sent: false },
  };

  // 1. Send WhatsApp reminder
  if (patient.mobile) {
    try {
      const waResult = await sendWhatsAppReminder(
        patient.mobile,
        patient.full_name,
        dateStr,
        timeStr
      );

      result.whatsapp = {
        sent: waResult.success,
        messageId: waResult.messageId,
        error: waResult.error,
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
      result.whatsapp = { sent: false, error: errorMsg };
      await logReminderAction(appointmentId, 'whatsapp', 'failed', patient.mobile, undefined, errorMsg);
    }
  }

  // 2. Send Email reminder (only if patient has email)
  if (patient.email) {
    try {
      // Generate confirmation tokens
      const confirmToken = generateToken();
      const cancelToken = generateToken();
      const expiresAt = new Date(Date.now() + 48 * 60 * 60 * 1000); // 48 hours

      // Store tokens
      await prisma.confirmationToken.createMany({
        data: [
          {
            appointment_id: appointmentId,
            token: confirmToken,
            action: 'confirm',
            expires_at: expiresAt,
          },
          {
            appointment_id: appointmentId,
            token: cancelToken,
            action: 'cancel',
            expires_at: expiresAt,
          },
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
      result.email = { sent: false, error: errorMsg };
      await logReminderAction(appointmentId, 'email', 'failed', patient.email!, undefined, errorMsg);
    }
  }

  // 3. Update appointment status
  const anySent = result.whatsapp.sent || result.email.sent;
  if (anySent) {
    await prisma.appointment.update({
      where: { id: appointmentId },
      data: {
        reminder_sent: true,
        reminder_sent_at: new Date(),
        confirmation_status: 'reminder_sent',
      },
    });
  }

  return result;
}

/**
 * Process automatic reminders — called by cron job
 * Finds appointments within 24 hours that haven't received reminders
 */
export async function processAutomaticReminders(): Promise<{
  processed: number;
  sent: number;
  failed: number;
  results: ReminderResult[];
}> {
  const now = new Date();
  const reminderHours = parseInt(process.env.REMINDER_HOURS_BEFORE || '24');
  const cutoffTime = new Date(now.getTime() + reminderHours * 60 * 60 * 1000);

  // Find eligible appointments:
  // - Status is pending or confirmed (not completed/cancelled)
  // - Appointment is within the next 24 hours
  // - Reminder not yet sent
  const eligibleAppointments = await prisma.$queryRaw<Array<{
    id: number;
    appointment_date: Date;
    appointment_time: Date | null;
  }>>`
    SELECT id, appointment_date, appointment_time 
    FROM appointments 
    WHERE status IN ('pending', 'confirmed')
      AND reminder_sent = 0
      AND CONCAT(appointment_date, ' ', COALESCE(appointment_time, '09:00:00')) <= ${cutoffTime.toISOString().slice(0, 19).replace('T', ' ')}
      AND CONCAT(appointment_date, ' ', COALESCE(appointment_time, '09:00:00')) > ${now.toISOString().slice(0, 19).replace('T', ' ')}
    ORDER BY appointment_date ASC, appointment_time ASC
  `;

  const results: ReminderResult[] = [];
  let sent = 0;
  let failed = 0;

  for (const apt of eligibleAppointments) {
    try {
      const result = await sendAppointmentReminder(apt.id);
      results.push(result);

      if (result.whatsapp.sent || result.email.sent) {
        sent++;
      } else {
        failed++;
      }

      // Small delay between sends to avoid rate limits
      await new Promise(resolve => setTimeout(resolve, 500));
    } catch (error) {
      failed++;
      console.error(`[Cron] Failed to send reminder for appointment ${apt.id}:`, error);
      results.push({
        appointmentId: apt.id,
        whatsapp: { sent: false, error: String(error) },
        email: { sent: false, error: String(error) },
      });
    }
  }

  console.log(`[Cron] Processed ${eligibleAppointments.length} appointments: ${sent} sent, ${failed} failed`);

  return {
    processed: eligibleAppointments.length,
    sent,
    failed,
    results,
  };
}

/**
 * Handle appointment confirmation (from WhatsApp reply or Email link)
 */
export async function confirmAppointment(
  appointmentId: number,
  source: 'whatsapp' | 'email' | 'manual'
): Promise<void> {
  const appointment = await prisma.appointment.findUnique({
    where: { id: appointmentId },
    include: { patient: true },
  });

  if (!appointment) {
    throw new Error('Appointment not found');
  }

  if (appointment.confirmation_status === 'confirmed') {
    return; // Already confirmed
  }

  await prisma.appointment.update({
    where: { id: appointmentId },
    data: {
      status: 'confirmed',
      confirmation_status: 'confirmed',
      confirmed_at: new Date(),
      reply_source: source,
    },
  });

  // Send confirmation email (only once — idempotent)
  try {
    await sendConfirmationEmail(appointmentId);
  } catch (emailError) {
    console.error(`[Confirm] Failed to send confirmation email for appointment ${appointmentId}:`, emailError);
    // Don't throw — confirmation itself succeeded, email is best-effort
  }

  // If this is a follow-up appointment and it's confirmed, create next follow-up suggestion
  if (appointment.is_followup && !appointment.followup_created) {
    await createFollowUpSuggestion(appointmentId);
  }
}

/**
 * Handle appointment cancellation
 */
export async function cancelAppointment(
  appointmentId: number,
  source: 'whatsapp' | 'email' | 'manual'
): Promise<void> {
  const appointment = await prisma.appointment.findUnique({
    where: { id: appointmentId },
  });

  if (!appointment) {
    throw new Error('Appointment not found');
  }

  await prisma.appointment.update({
    where: { id: appointmentId },
    data: {
      status: 'cancelled',
      confirmation_status: 'cancelled',
      reply_source: source,
    },
  });
}

/**
 * Create a follow-up placeholder appointment after confirmation
 */
async function createFollowUpSuggestion(appointmentId: number): Promise<void> {
  const appointment = await prisma.appointment.findUnique({
    where: { id: appointmentId },
    include: { patient: true },
  });

  if (!appointment) return;

  // Create follow-up appointment 2 weeks from the current appointment
  const followUpDate = new Date(appointment.appointment_date);
  followUpDate.setDate(followUpDate.getDate() + 14);

  // Skip Sunday (day 0)
  if (followUpDate.getDay() === 0) {
    followUpDate.setDate(followUpDate.getDate() + 1);
  }

  try {
    await prisma.appointment.create({
      data: {
        patient_id: appointment.patient_id,
        consultation_type: appointment.consultation_type,
        form_type: appointment.form_type,
        appointment_date: followUpDate,
        appointment_time: appointment.appointment_time,
        status: 'pending',
        confirmation_status: 'pending',
        is_followup: true,
        parent_appointment_id: appointmentId,
        admin_notes: `Auto-created follow-up from appointment #${appointmentId}`,
      },
    });

    // Mark original as having created follow-up
    await prisma.appointment.update({
      where: { id: appointmentId },
      data: { followup_created: true },
    });

    console.log(`[FollowUp] Created follow-up for appointment ${appointmentId}`);
  } catch (error) {
    console.error(`[FollowUp] Failed to create follow-up for ${appointmentId}:`, error);
  }
}

/**
 * Format appointment date for display
 */
function formatAppointmentDate(date: Date): string {
  return new Date(date).toLocaleDateString('en-IN', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format appointment time for display
 */
function formatAppointmentTime(time: Date | null): string {
  if (!time) return 'To be confirmed';

  const d = new Date(time);
  return d.toLocaleTimeString('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}
