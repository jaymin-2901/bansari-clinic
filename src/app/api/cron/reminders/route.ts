/**
 * Cron Job API Route — Process Automatic 24-Hour Reminders
 * 
 * GET /api/cron/reminders?secret=xxx
 * 
 * This should be called every 30 minutes by an external cron service
 * (e.g., Vercel Cron, cron-job.org, or a system crontab)
 */
import { NextRequest, NextResponse } from 'next/server';
import { verifyCronAuth } from '@/lib/middleware';
import { processAutomaticReminders } from '@/lib/reminder-service';

export const dynamic = 'force-dynamic';
export const maxDuration = 60; // Allow up to 60 seconds for processing

export async function GET(request: NextRequest) {
  // Verify cron authentication
  if (!verifyCronAuth(request)) {
    return NextResponse.json(
      { success: false, error: 'Unauthorized' },
      { status: 401 }
    );
  }

  try {
    console.log('[Cron] Starting automatic reminder processing...');
    const startTime = Date.now();

    const result = await processAutomaticReminders();

    const duration = Date.now() - startTime;
    console.log(`[Cron] Completed in ${duration}ms`);

    return NextResponse.json({
      success: true,
      timestamp: new Date().toISOString(),
      duration: `${duration}ms`,
      ...result,
    });
  } catch (error) {
    console.error('[Cron] Error processing reminders:', error);
    return NextResponse.json(
      {
        success: false,
        error: error instanceof Error ? error.message : 'Internal server error',
        timestamp: new Date().toISOString(),
      },
      { status: 500 }
    );
  }
}
