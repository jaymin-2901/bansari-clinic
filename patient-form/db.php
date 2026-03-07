<?php
/**
 * ============================================================
 * MediConnect – Patient Form Database Connection
 * File: patient-form/db.php
 * ============================================================
 * PDO connection with prepared statements, UTF-8, exception mode
 */

// ─── Timezone ───
date_default_timezone_set('Asia/Kolkata');

// ─── Database Configuration ───
// Change these values for your hosting environment
define('PF_DB_HOST', 'localhost');
define('PF_DB_PORT', '3307');       // Default MySQL port is 3306, change if needed
define('PF_DB_NAME', 'mediconnect');
define('PF_DB_USER', 'root');
define('PF_DB_PASS', '');

// ─── Upload Configuration ───
define('PF_UPLOAD_DIR', __DIR__ . '/uploads/');
define('PF_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('PF_ALLOWED_TYPES', ['application/pdf']);
define('PF_ALLOWED_EXT', ['pdf']);

// ─── Application Settings ───
define('PF_APP_NAME', 'Bansari\'s Homeopathy Clinic');
define('PF_APP_ENV', 'development'); // 'development' or 'production'

// ─── Create uploads directory if not exists ───
if (!is_dir(PF_UPLOAD_DIR)) {
    mkdir(PF_UPLOAD_DIR, 0755, true);
}

/**
 * Get PDO database connection
 * 
 * @return PDO
 * @throws PDOException
 */
function getPFConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        PF_DB_HOST,
        PF_DB_PORT,
        PF_DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, PF_DB_USER, PF_DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        if (PF_APP_ENV === 'development') {
            throw $e;
        }
        die('Database connection error. Please try again later.');
    }
}

/**
 * Generate unique submission ID
 * Format: BHC-YYYYMMDD-XXXX (e.g., BHC-20260228-0042)
 * 
 * @return string
 */
function generateUniqueId(): string
{
    $db = getPFConnection();
    $date = date('Ymd');
    
    // Get today's count
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM form_submissions WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch()['cnt'] + 1;
    
    return 'BHC-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Sanitize input string
 * 
 * @param string $input
 * @return string
 */
function sanitizeInput(string $input): string
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Generate CSRF token
 * 
 * @return string
 */
function generateFormCSRF(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['pf_csrf_token'])) {
        $_SESSION['pf_csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['pf_csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token
 * @return bool
 */
function validateFormCSRF(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $sessionToken = $_SESSION['pf_csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token)) {
        return false;
    }
    
    return hash_equals($sessionToken, $token);
}

/**
 * Format date to Indian format
 * 
 * @param string $date
 * @return string
 */
function formatIndianDate(string $date): string
{
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}

/**
 * Format datetime to Indian format
 * 
 * @param string $datetime
 * @return string
 */
function formatIndianDateTime(string $datetime): string
{
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    return date('d/m/Y h:i A', $timestamp);
}
