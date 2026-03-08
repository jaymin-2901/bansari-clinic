<?php
/**
 * ============================================================
 * MediConnect – Production Configuration for InfinityFree
 * File: backend-php/config/production_config.php
 * ============================================================
 * 
 * Database credentials for InfinityFree hosting
 */

// ─── Timezone ───
date_default_timezone_set('Asia/Kolkata');

// ─── Database (InfinityFree Credentials) ───
define('DB_HOST', 'sql311.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_41335076_clinic');
define('DB_USER', 'if0_41335076');
define('DB_PASS', 'Jaymin2006');

// ─── Reminder Settings ───
define('REMINDER_WINDOW_BEFORE_MINS', 5);
define('REMINDER_WINDOW_AFTER_MINS', 5);

// ─── Logging ───
define('LOG_DIR', __DIR__ . '/../logs');
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// ─── Country Code ───
define('DEFAULT_COUNTRY_CODE', '91');

// ─── Application ───
define('APP_NAME', 'Bansari Homeopathy Clinic');
define('APP_ENV', 'production');

// ─── CORS Settings ───
define('FRONTEND_URL', 'https://bansari-clinic.vercel.app');
define('ALLOWED_ORIGINS', 'https://bansari-clinic.vercel.app');

// ─── Clinic Timings (Hardcoded) ───
define('CLINIC_MORNING_OPEN',  '09:30');
define('CLINIC_MORNING_CLOSE', '13:00');
define('CLINIC_EVENING_OPEN',  '17:00');
define('CLINIC_EVENING_CLOSE', '20:00');
define('CLINIC_CLOSED_DAY',    0); // 0 = Sunday
define('NEW_PATIENT_DURATION', 30); // minutes
define('OLD_PATIENT_DURATION', 15); // minutes


