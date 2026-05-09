<?php
/**
 * Bansari Homeopathy Clinic – Master Configuration
 */

date_default_timezone_set('Asia/Kolkata');

// ─── Database (Local Development) ───
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'bansari_clinic');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// ─── Paths ───
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', BASE_PATH . '/public/uploads');
}
if (!defined('TESTIMONIAL_UPLOAD_PATH')) {
    define('TESTIMONIAL_UPLOAD_PATH', UPLOAD_PATH . '/testimonials');
}
if (!defined('ABOUT_UPLOAD_PATH')) {
    define('ABOUT_UPLOAD_PATH', UPLOAD_PATH . '/about');
}
if (!defined('HERO_UPLOAD_PATH')) {
    define('HERO_UPLOAD_PATH', UPLOAD_PATH . '/hero');
}

// ─── Upload Settings ───
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
}

// ─── CORS (for Next.js frontend) ───
if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'http://localhost:3000');
}

// ─── Clinic Timings (Hardcoded) ───
if (!defined('CLINIC_MORNING_OPEN')) {
    define('CLINIC_MORNING_OPEN', '09:30');
}
if (!defined('CLINIC_MORNING_CLOSE')) {
    define('CLINIC_MORNING_CLOSE', '13:00');
}
if (!defined('CLINIC_EVENING_OPEN')) {
    define('CLINIC_EVENING_OPEN', '16:30');
}
if (!defined('CLINIC_EVENING_CLOSE')) {
    define('CLINIC_EVENING_CLOSE', '21:00');
}
if (!defined('CLINIC_CLOSED_DAYS')) {
    define('CLINIC_CLOSED_DAYS', [0]); // Sunday
}

// ─── JWT & Security ───
if (empty(getenv('JWT_SECRET'))) {
    putenv('JWT_SECRET=31696e0731f6a960a0f13521577199fd5deb488a45c6967c33922d53becbc315');
}
if (empty(getenv('JWT_REFRESH_SECRET'))) {
    putenv('JWT_REFRESH_SECRET=e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7');
}
if (empty(getenv('JWT_ISSUER'))) {
    putenv('JWT_ISSUER=bansari-homeopathy');
}
if (empty(getenv('JWT_AUDIENCE'))) {
    putenv('JWT_AUDIENCE=bansari-homeopathy-api');
}
if (empty(getenv('RECAPTCHA_SECRET'))) {
    putenv('RECAPTCHA_SECRET=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'); // Test secret
}
