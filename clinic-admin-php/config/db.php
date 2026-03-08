<?php
/**
 * Bansari Homeopathy Clinic – Database Configuration
 * Supports both MySQLi and PDO connections
 */

// Database credentials - Update these for your server
// For production, use environment variables or a secure config file
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: '3307';
$db_name = getenv('DB_NAME') ?: 'bansari_clinic';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// Timezone
date_default_timezone_set('Asia/Kolkata');

// ============================================================
// MySQLi Connection
// ============================================================

// Create MySQLi connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, (int)$db_port);

// Check connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Please contact administrator.'
    ]);
    exit;
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

// ============================================================
// PDO Connection (for existing APIs)
// ============================================================

$pdo = null;
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db_host, $db_port, $db_name);
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdoOptions);
} catch (PDOException $e) {
    error_log("PDO Database connection failed: " . $e->getMessage());
}

/**
 * Get MySQLi database connection
 * @return mysqli
 */
function getDB(): mysqli {
    global $conn;
    return $conn;
}

/**
 * Get PDO database connection (for existing APIs)
 * @return PDO
 */
function getClinicDB(): PDO {
    global $pdo;
    return $pdo;
}

/**
 * Set CORS headers for API endpoints
 */
function setCORSHeaders(): void {
    $allowed = getenv('FRONTEND_URL') ?: '*';
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * JSON response helper for API endpoints
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get JSON input from POST body
 * @return array
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Sanitize string input
 * @param string $input
 * @return string
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 * @param array $data
 * @param array $fields
 * @return string|null Error message or null if valid
 */
function validateRequired(array $data, array $fields): ?string {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return "Field '{$field}' is required.";
        }
    }
    return null;
}

