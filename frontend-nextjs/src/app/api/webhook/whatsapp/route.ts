/**
 * WhatsApp Webhook API Route
 * 
 * GET  /api/webhook/whatsapp — Verification (Meta webhook setup)
 * POST /api/webhook/whatsapp — Incoming messages (patient replies)
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from '@/lib/prisma';
import { parseWhatsAppWebhook, parsePatientReply } from '@/lib/whatsapp';
import { confirmAppointment, cancelAppointment } from '@/lib/reminder-service';

export const dynamic = 'force-dynamic';

/**
 * Webhook Verification (Meta sends GET request during setup)
 */
export async function GET(request: NextRequest) {
  const mode = request.nextUrl.searchParams.get('hub.mode');
  const token = request.nextUrl.searchParams.get('hub.verify_token');
  const challenge = request.nextUrl.searchParams.get('hub.challenge');

  const verifyToken = process.env.WHATSAPP_VERIFY_TOKEN;

  if (mode === 'subscribe' && token === verifyToken) {
    console.log('[WhatsApp Webhook] Verification successful');
    return new NextResponse(challenge, { status: 200 });
  }

  return NextResponse.json({ error: 'Verification failed' }, { status: 403 });
}

/**
 * Handle incoming WhatsApp messages (patient replies)
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();

    // Parse the webhook payload
    const message = parseWhatsAppWebhook(body);

    if (!message || !message.text?.body) {
      // Acknowledge receipt even if we can't process
      return NextResponse.json({ success: true });
    }

    const fromPhone = message.from; // Format: 91XXXXXXXXXX
    const replyText = message.text.body;
    const messageId = message.id;

    console.log(`[WhatsApp Webhook] Reply from ${fromPhone}: "${replyText}"`);

    // Parse the reply intent
    const intent = parsePatientReply(replyText);

    if (intent === 'unknown') {
      console.log(`[WhatsApp Webhook] Unknown reply intent from ${fromPhone}: "${replyText}"`);
      return NextResponse.json({ success: true });
    }

    // Find the most recent appointment for this phone number that has a reminder sent
    // Strip country code for matching (91XXXXXXXXXX → XXXXXXXXXX or keep with 91)
    const phoneVariants = [
      fromPhone,
      fromPhone.startsWith('91') ? fromPhone.substring(2) : fromPhone,
      '+' + fromPhone,
      '+91' + (fromPhone.startsWith('91') ? fromPhone.substring(2) : fromPhone),
    ];

    const appointment = await prisma.appointment.findFirst({
      where: {
        reminder_sent: true,
        confirmation_status: 'reminder_sent',
        status: { in: ['pending', 'confirmed'] },
        patient: {
          mobile: { in: phoneVariants },
        },
      },
      orderBy: { appointment_date: 'asc' },
      include: { patient: true },
    });

    if (!appointment) {
      console.log(`[WhatsApp Webhook] No pending appointment found for phone ${fromPhone}`);
      return NextResponse.json({ success: true });
    }

    // Process the reply
    if (intent === 'confirm') {
      await confirmAppointment(appointment.id, 'whatsapp');
      console.log(`[WhatsApp Webhook] Appointment ${appointment.id} CONFIRMED by patient`);
    } else if (intent === 'cancel') {
      await cancelAppointment(appointment.id, 'whatsapp');
      console.log(`[WhatsApp Webhook] Appointment ${appointment.id} CANCELLED by patient`);
    }

    // Update reminder log with the reply
    await prisma.reminderLog.updateMany({
      where: {
        appointment_id: appointment.id,
        channel: 'whatsapp',
      },
      data: {
        status: 'replied',
        patient_reply: replyText,
        reply_received_at: new Date(),
      },
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('[WhatsApp Webhook] Error:', error);
    // Always return 200 to prevent Meta from retrying
    return NextResponse.json({ success: true });
  }
}
