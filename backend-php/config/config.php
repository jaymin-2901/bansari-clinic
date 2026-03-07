<?php
/**
 * ============================================================
 * MediConnect – Master Configuration
 * File: backend/config/config.php
 * ============================================================
 * 
 * All environment-specific settings in one place.
 * On production, replace values or load from environment variables.
 */

// ─── Timezone ───
date_default_timezone_set('Asia/Kolkata');

// ─── Database ───
define('DB_HOST', getenv('MC_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('MC_DB_PORT') ?: '3307');
define('DB_NAME', getenv('MC_DB_NAME') ?: 'mediconnect');
define('DB_USER', getenv('MC_DB_USER') ?: 'root');
define('DB_PASS', getenv('MC_DB_PASS') ?: '');

// ─── Reminder Settings ───
// Time window around 24h mark (in minutes). Cron looks for appointments between NOW+23h55m and NOW+24h05m
define('REMINDER_WINDOW_BEFORE_MINS', 5);  // 23h 55m
define('REMINDER_WINDOW_AFTER_MINS', 5);   // 24h 05m

// ─── Logging ───
define('LOG_DIR', __DIR__ . '/../logs');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// ─── Country Code (for phone formatting) ───
define('DEFAULT_COUNTRY_CODE', '91'); // India

// ─── Application ───
define('APP_NAME', 'MediConnect');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // 'development' or 'production'
