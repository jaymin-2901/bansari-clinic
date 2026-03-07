<?php
/**
 * Bansari Homeopathy Clinic – Master Configuration
 */

date_default_timezone_set('Asia/Kolkata');

// ─── Database ───
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'bansari_clinic');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ─── Paths ───
define('BASE_PATH', dirname(__DIR__, 2));
define('UPLOAD_PATH', BASE_PATH . '/public/uploads');
define('TESTIMONIAL_UPLOAD_PATH', UPLOAD_PATH . '/testimonials');
define('ABOUT_UPLOAD_PATH', UPLOAD_PATH . '/about');

// ─── Upload Settings ───
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ─── CORS (for Next.js frontend) ───
// In production, set this to your Vercel frontend URL
// You can also set via environment variable FRONTEND_URL or ALLOWED_ORIGINS
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'https://bansari-clinic.vercel.app');

// ─── Clinic Timings (Hardcoded) ───
define('CLINIC_MORNING_OPEN',  '09:30');
define('CLINIC_MORNING_CLOSE', '13:00');
define('CLINIC_EVENING_OPEN',  '17:00');
define('CLINIC_EVENING_CLOSE', '20:00');
define('CLINIC_CLOSED_DAY',    0); // 0 = Sunday
define('NEW_PATIENT_DURATION', 30); // minutes
define('OLD_PATIENT_DURATION', 15); // minutes

// ─── Logging ───
define('LOG_DIR', dirname(__DIR__) . '/logs');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
