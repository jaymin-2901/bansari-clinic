<?php
/**
 * ============================================================
 * MediConnect – Production Configuration for InfinityFree
 * File: backend-php/config/production_config.php
 * ============================================================
 * 
 * Database credentials for InfinityFree hosting
 * 
 * NOTE: Use defined() checks to avoid conflicts when loading
 * multiple config files. For local development, use clinic_config.php
 * instead of this file.
 */

// ─── Timezone ───
date_default_timezone_set('Asia/Kolkata');

// ─── Database (InfinityFree Credentials) ───
if (!defined('DB_HOST')) define('DB_HOST', 'sql311.infinityfree.com');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'if0_41335076_clinic');
if (!defined('DB_USER')) define('DB_USER', 'if0_41335076');
if (!defined('DB_PASS')) define('DB_PASS', 'Jaymin2006');

// ─── Reminder Settings ───
if (!defined('REMINDER_WINDOW_BEFORE_MINS')) define('REMINDER_WINDOW_BEFORE_MINS', 5);
if (!defined('REMINDER_WINDOW_AFTER_MINS')) define('REMINDER_WINDOW_AFTER_MINS', 5);

// ─── Logging ───
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../logs');
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }
}

// ─── Country Code ───
if (!defined('DEFAULT_COUNTRY_CODE')) define('DEFAULT_COUNTRY_CODE', '91');

// ─── Application ───
if (!defined('APP_NAME')) define('APP_NAME', 'Bansari Homeopathy Clinic');
if (!defined('APP_ENV')) define('APP_ENV', 'production');

// ─── CORS Settings ───
if (!defined('FRONTEND_URL')) define('FRONTEND_URL', 'https://bansari-clinic.vercel.app');
if (!defined('ALLOWED_ORIGINS')) define('ALLOWED_ORIGINS', 'https://bansari-clinic.vercel.app');

// ─── Clinic Timings (Hardcoded) ───
if (!defined('CLINIC_MORNING_OPEN')) define('CLINIC_MORNING_OPEN',  '09:30');
if (!defined('CLINIC_MORNING_CLOSE')) define('CLINIC_MORNING_CLOSE', '13:00');
if (!defined('CLINIC_EVENING_OPEN')) define('CLINIC_EVENING_OPEN',  '17:00');
if (!defined('CLINIC_EVENING_CLOSE')) define('CLINIC_EVENING_CLOSE', '20:00');
if (!defined('CLINIC_CLOSED_DAY')) define('CLINIC_CLOSED_DAY',    0);
if (!defined('NEW_PATIENT_DURATION')) define('NEW_PATIENT_DURATION', 30);
if (!defined('OLD_PATIENT_DURATION')) define('OLD_PATIENT_DURATION', 15);
