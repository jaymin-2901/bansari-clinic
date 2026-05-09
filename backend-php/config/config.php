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

// ─── Database (Local Development) ───
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('MC_DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('MC_DB_PORT') ?: '3306');
    define('DB_NAME', getenv('MC_DB_NAME') ?: 'bansari_clinic');
    define('DB_USER', getenv('MC_DB_USER') ?: 'root');
    define('DB_PASS', getenv('MC_DB_PASS') ?: '');
}

// ─── Reminder Settings ───
if (!defined('REMINDER_WINDOW_BEFORE_MINS')) {
    define('REMINDER_WINDOW_BEFORE_MINS', 5);
    define('REMINDER_WINDOW_AFTER_MINS', 5);
}

// ─── Logging ───
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../logs');
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
}

// ─── Country Code (for phone formatting) ───
if (!defined('DEFAULT_COUNTRY_CODE')) {
    define('DEFAULT_COUNTRY_CODE', '91');
}

// ─── Application ───
if (!defined('APP_NAME')) {
    define('APP_NAME', 'MediConnect');
    define('APP_ENV', getenv('APP_ENV') ?: 'development');
}

