/**
 * WhatsApp Cloud API Service
 * Uses Meta's official WhatsApp Business Cloud API
 */

interface WhatsAppConfig {
  apiUrl: string;
  phoneNumberId: string;
  accessToken: string;
  templateName: string;
  templateLanguage: string;
}

interface SendMessageResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

interface WhatsAppWebhookMessage {
  from: string;
  id: string;
  timestamp: string;
  text?: { body: string };
  type: string;
}

function getConfig(): WhatsAppConfig {
  return {
    apiUrl: process.env.WHATSAPP_API_URL || 'https://graph.facebook.com/v18.0',
    phoneNumberId: process.env.WHATSAPP_PHONE_NUMBER_ID || '',
    accessToken: process.env.WHATSAPP_ACCESS_TOKEN || '',
    templateName: process.env.WHATSAPP_TEMPLATE_NAME || 'appointment_reminder',
    templateLanguage: process.env.WHATSAPP_TEMPLATE_LANGUAGE || 'en',
  };
}

/**
 * Send a WhatsApp template message for appointment reminder
 */
export async function sendWhatsAppReminder(
  phoneNumber: string,
  patientName: string,
  appointmentDate: string,
  appointmentTime: string
): Promise<SendMessageResult> {
  const config = getConfig();

  if (!config.phoneNumberId || !config.accessToken) {
    console.error('[WhatsApp] Missing API configuration');
    return { success: false, error: 'WhatsApp API not configured' };
  }

  // Format phone number for WhatsApp (ensure country code, remove spaces/dashes)
  const formattedPhone = formatPhoneNumber(phoneNumber);

  try {
    const url = `${config.apiUrl}/${config.phoneNumberId}/messages`;

    const payload = {
      messaging_product: 'whatsapp',
      recipient_type: 'individual',
      to: formattedPhone,
      type: 'template',
      template: {
        name: config.templateName,
        language: { code: config.templateLanguage },
        components: [
          {
            type: 'body',
            parameters: [
              { type: 'text', text: patientName },
              { type: 'text', text: appointmentDate },
              { type: 'text', text: appointmentTime },
            ],
          },
        ],
      },
    };

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${config.accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      console.error('[WhatsApp] API error:', data);
      return {
        success: false,
        error: data.error?.message || `HTTP ${response.status}`,
      };
    }

    const messageId = data.messages?.[0]?.id;
    console.log(`[WhatsApp] Message sent to ${formattedPhone}, ID: ${messageId}`);

    return { success: true, messageId };
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error('[WhatsApp] Send failed:', errorMessage);
    return { success: false, error: errorMessage };
  }
}

/**
 * Send a free-form text message (for custom messages)
 */
export async function sendWhatsAppTextMessage(
  phoneNumber: string,
  message: string
): Promise<SendMessageResult> {
  const config = getConfig();

  if (!config.phoneNumberId || !config.accessToken) {
    return { success: false, error: 'WhatsApp API not configured' };
  }

  const formattedPhone = formatPhoneNumber(phoneNumber);

  try {
    const url = `${config.apiUrl}/${config.phoneNumberId}/messages`;

    const payload = {
      messaging_product: 'whatsapp',
      recipient_type: 'individual',
      to: formattedPhone,
      type: 'text',
      text: { preview_url: false, body: message },
    };

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${config.accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      return {
        success: false,
        error: data.error?.message || `HTTP ${response.status}`,
      };
    }

    return { success: true, messageId: data.messages?.[0]?.id };
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    return { success: false, error: errorMessage };
  }
}

/**
 * Parse incoming WhatsApp webhook payload
 */
export function parseWhatsAppWebhook(body: any): WhatsAppWebhookMessage | null {
  try {
    const entry = body?.entry?.[0];
    const changes = entry?.changes?.[0];
    const value = changes?.value;

    if (!value?.messages?.length) return null;

    const message = value.messages[0];
    return {
      from: message.from,
      id: message.id,
      timestamp: message.timestamp,
      text: message.text,
      type: message.type,
    };
  } catch {
    return null;
  }
}

/**
 * Verify WhatsApp webhook signature
 */
export function verifyWebhookSignature(
  payload: string,
  signature: string,
  appSecret: string
): boolean {
  if (!signature || !appSecret) return false;

  try {
    const crypto = require('crypto');
    const expectedSignature = crypto
      .createHmac('sha256', appSecret)
      .update(payload)
      .digest('hex');

    return `sha256=${expectedSignature}` === signature;
  } catch {
    return false;
  }
}

/**
 * Format phone number for WhatsApp API (needs country code, no + prefix)
 * Handles Indian numbers: 10-digit → 91XXXXXXXXXX
 */
function formatPhoneNumber(phone: string): string {
  // Remove all non-digit characters
  let cleaned = phone.replace(/\D/g, '');

  // If it's a 10-digit Indian number, add country code
  if (cleaned.length === 10) {
    cleaned = '91' + cleaned;
  }

  // Remove leading + if present after cleaning
  if (cleaned.startsWith('+')) {
    cleaned = cleaned.substring(1);
  }

  return cleaned;
}

/**
 * Check if a reply indicates confirmation or cancellation
 */
export function parsePatientReply(text: string): 'confirm' | 'cancel' | 'unknown' {
  const normalized = text.trim().toLowerCase();

  const confirmWords = ['yes', 'confirm', 'ok', 'ha', 'haa', 'haan', 'ji', 'sure', 'y', '1'];
  const cancelWords = ['no', 'cancel', 'na', 'nahi', 'naa', 'n', '0', '2'];

  if (confirmWords.some(w => normalized === w || normalized.startsWith(w + ' '))) {
    return 'confirm';
  }
  if (cancelWords.some(w => normalized === w || normalized.startsWith(w + ' '))) {
    return 'cancel';
  }

  return 'unknown';
}
