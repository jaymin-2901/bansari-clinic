<?php
/**
 * ============================================================
 * MediConnect – Communication Configuration
 * File: backend/config/comm_config.php
 * ============================================================
 * Central configuration for SMS, Email, Queue.
 * Loads values from .env file for security.
 * ============================================================
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/config.php';

// ─── SMS Configuration (Fast2SMS) ───────────────────────────
define('SMS_GATEWAY',             env('SMS_GATEWAY', 'fast2sms'));
define('FAST2SMS_API_KEY',        env('FAST2SMS_API_KEY', ''));
define('FAST2SMS_API_URL',        'https://www.fast2sms.com/dev/bulkV2');
define('FAST2SMS_SENDER_ID',      env('FAST2SMS_SENDER_ID', 'BNSHMC'));
define('FAST2SMS_ROUTE',          env('FAST2SMS_ROUTE', 'dlt'));
define('FAST2SMS_DLT_TE_ID',      env('FAST2SMS_DLT_TE_ID', ''));
define('SMS_RATE_LIMIT_PER_HOUR', (int) env('SMS_RATE_LIMIT_PER_HOUR', 5));
define('SMS_OTP_RATE_LIMIT',      (int) env('SMS_OTP_RATE_LIMIT_PER_HOUR', 3));

// ─── Email SMTP Configuration ───────────────────────────────
define('SMTP_PROVIDER',    env('SMTP_PROVIDER', 'gmail'));
define('SMTP_HOST',        env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT',        (int) env('SMTP_PORT', 587));
define('SMTP_ENCRYPTION',  env('SMTP_ENCRYPTION', 'tls'));
define('SMTP_USERNAME',    env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD',    env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL',  env('SMTP_FROM_EMAIL', 'noreply@bansarihomeopathy.com'));
define('SMTP_FROM_NAME',   env('SMTP_FROM_NAME', 'Bansari Homeopathy Clinic'));
define('SMTP_REPLY_TO',    env('SMTP_REPLY_TO', ''));
define('EMAIL_RATE_LIMIT', (int) env('EMAIL_RATE_LIMIT_PER_HOUR', 10));

// ─── Queue & Retry Settings ─────────────────────────────────
define('QUEUE_BATCH_SIZE',           (int) env('QUEUE_BATCH_SIZE', 20));
define('QUEUE_MAX_RETRIES',          (int) env('QUEUE_MAX_RETRIES', 3));
define('QUEUE_RETRY_DELAY_MINS',     (int) env('QUEUE_RETRY_DELAY_MINS', 5));
define('QUEUE_PROCESSING_TIMEOUT',   (int) env('QUEUE_PROCESSING_TIMEOUT_MINS', 10));

// ─── Clinic Info (for messages) ─────────────────────────────
define('CLINIC_NAME',        env('CLINIC_NAME', 'Bansari Homeopathy Clinic'));
define('CLINIC_PHONE',       env('CLINIC_PHONE', '+91 63543 88539'));
define('CLINIC_ADDRESS',     env('CLINIC_ADDRESS', '212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.'));
define('CLINIC_WEBSITE',     env('CLINIC_WEBSITE', ''));
define('CLINIC_DOCTOR_NAME', env('CLINIC_DOCTOR_NAME', 'Dr. Bansari'));
