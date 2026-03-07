<?php
/**
 * ============================================================
 * MediConnect – Alias for send_24h_reminders.php
 * File: backend/cron/send_scheduled_24h_reminders.php
 * ============================================================
 * 
 * This file redirects to the primary cron script.
 * Kept for backward compatibility if crontab still references this file.
 */

require_once __DIR__ . '/send_24h_reminders.php';
