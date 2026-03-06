/**
 * Admin API Middleware
 * Protects API routes with authentication and rate limiting
 */
import { NextRequest, NextResponse } from 'next/server';
import prisma from './prisma';

interface AdminAuthResult {
  authenticated: boolean;
  adminId?: number;
  error?: string;
}

/**
 * Verify admin authentication via API key or session cookie
 */
export async function verifyAdminAuth(request: NextRequest): Promise<AdminAuthResult> {
  // Method 1: API Key header (for server-to-server calls from PHP admin)
  const apiKey = request.headers.get('x-admin-api-key');
  if (apiKey && apiKey === process.env.ADMIN_API_KEY) {
    // Extract admin ID from header if provided
    const adminId = parseInt(request.headers.get('x-admin-id') || '1');
    return { authenticated: true, adminId };
  }

  // Method 2: Cron secret (for automated cron jobs)
  const cronSecret = request.headers.get('x-cron-secret');
  if (cronSecret && cronSecret === process.env.CRON_SECRET) {
    return { authenticated: true, adminId: 0 }; // System user
  }

  return { authenticated: false, error: 'Unauthorized: Invalid credentials' };
}

/**
 * Verify cron job authentication
 */
export function verifyCronAuth(request: NextRequest): boolean {
  const cronSecret =
    request.headers.get('x-cron-secret') ||
    request.nextUrl.searchParams.get('secret');

  return cronSecret === process.env.CRON_SECRET;
}

/**
 * Rate limit check for manual reminder sending
 * Returns true if within limits, false if rate limited
 */
export async function checkRateLimit(
  adminId: number,
  appointmentId: number,
  action: string = 'send_reminder',
  maxAttempts: number = 3,
  windowMinutes: number = 60
): Promise<{ allowed: boolean; remaining: number; resetAt?: Date }> {
  const windowStart = new Date(Date.now() - windowMinutes * 60 * 1000);

  const recentAttempts = await prisma.reminderRateLimit.count({
    where: {
      admin_id: adminId,
      appointment_id: appointmentId,
      action,
      attempted_at: { gte: windowStart },
    },
  });

  if (recentAttempts >= maxAttempts) {
    const oldestInWindow = await prisma.reminderRateLimit.findFirst({
      where: {
        admin_id: adminId,
        appointment_id: appointmentId,
        action,
        attempted_at: { gte: windowStart },
      },
      orderBy: { attempted_at: 'asc' },
    });

    const resetAt = oldestInWindow
      ? new Date(oldestInWindow.attempted_at.getTime() + windowMinutes * 60 * 1000)
      : new Date(Date.now() + windowMinutes * 60 * 1000);

    return { allowed: false, remaining: 0, resetAt };
  }

  return { allowed: true, remaining: maxAttempts - recentAttempts };
}

/**
 * Record a rate limit attempt
 */
export async function recordRateLimitAttempt(
  adminId: number,
  appointmentId: number,
  action: string = 'send_reminder'
): Promise<void> {
  await prisma.reminderRateLimit.create({
    data: {
      admin_id: adminId,
      appointment_id: appointmentId,
      action,
    },
  });
}

/**
 * Validate appointment ID
 */
export async function validateAppointmentId(id: number): Promise<boolean> {
  if (isNaN(id) || id <= 0) return false;

  const appointment = await prisma.appointment.findUnique({
    where: { id },
    select: { id: true },
  });

  return !!appointment;
}

/**
 * Create a JSON error response
 */
export function errorResponse(message: string, status: number = 400): NextResponse {
  return NextResponse.json({ success: false, error: message }, { status });
}

/**
 * Create a JSON success response
 */
export function successResponse(data: any, status: number = 200): NextResponse {
  return NextResponse.json({ success: true, ...data }, { status });
}

/**
 * Log reminder action for audit trail
 */
export async function logReminderAction(
  appointmentId: number,
  channel: 'whatsapp' | 'email',
  status: string,
  recipient: string,
  messageId?: string,
  errorMessage?: string
): Promise<void> {
  try {
    await prisma.reminderLog.create({
      data: {
        appointment_id: appointmentId,
        channel,
        status: status as any,
        recipient,
        message_id: messageId,
        error_message: errorMessage,
        sent_at: status === 'sent' || status === 'delivered' ? new Date() : null,
      },
    });
  } catch (error) {
    console.error('[LogAction] Failed to log reminder action:', error);
  }
}
