<?php
/**
 * Bansari Homeopathy Clinic – Database Connection (PDO)
 * 
 * This file loads the appropriate config based on environment:
 * - Local development: uses clinic_config.php (localhost database)
 * - Production (InfinityFree): uses production_config.php
 * 
 * Set environment variable USE_PRODUCTION=true to force production config
 * or it will automatically use clinic_config for local development.
 */

$useProduction = getenv('USE_PRODUCTION') === 'true';

if ($useProduction && file_exists(__DIR__ . '/production_config.php')) {
    require_once __DIR__ . '/production_config.php';
} elseif (file_exists(__DIR__ . '/clinic_config.php')) {
    // Load clinic_config for local development (localhost database)
    require_once __DIR__ . '/clinic_config.php';
} else {
    require_once __DIR__ . '/config.php';
}

function getClinicDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (Throwable $e) {
        // Log the actual error
        error_log("Database connection failed: " . $e->getMessage());
        
        // Try SQLite fallback only if it's a PDOException
        if ($e instanceof PDOException) {
            $sqlitePath = dirname(__DIR__, 2) . '/database.sqlite';
            if (file_exists($sqlitePath)) {
                try {
                    $sqliteDsn = 'sqlite:' . $sqlitePath;
                    $pdo = new PDO($sqliteDsn);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    return $pdo;
                } catch (PDOException $sqliteErr) {
                    error_log("Database connection failed (both MySQL and SQLite): " . $sqliteErr->getMessage());
                }
            }
        }
        
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * CORS headers for API endpoints.
 * Uses centralized CORSHandler for strict origin validation.
 * Falls back to FRONTEND_URL-based header if CORSHandler unavailable.
 */
function setCORSHeaders(): void
{
    // Load centralized security CORS + headers if available
    $bootstrapPath = __DIR__ . '/../security/CORSHandler.php';
    $headersPath = __DIR__ . '/../security/SecurityHeaders.php';
    $envPath = __DIR__ . '/../security/bootstrap.php';

    if (file_exists($bootstrapPath)) {
        // Load env_loader FIRST so FRONTEND_URL constant is available
        if (file_exists(__DIR__ . '/env_loader.php')) {
            require_once __DIR__ . '/env_loader.php';
        }
        // Load clinic_config to ensure FRONTEND_URL is defined
        if (file_exists(__DIR__ . '/production_config.php')) {
            require_once __DIR__ . '/production_config.php';
        }
        require_once $bootstrapPath;
        require_once $headersPath;

        $cors = new CORSHandler();
        $cors->handle();
        SecurityHeaders::apply();
        return;
    }

    // Fallback: use FRONTEND_URL (backward compatibility)
    $allowed = defined('FRONTEND_URL') ? FRONTEND_URL : 'http://localhost:3000';
    
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get JSON POST body
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Validate required fields
 */
function validateRequired(array $data, array $fields): ?string
{
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return "Field '{$field}' is required.";
        }
    }
    return null;
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
/**
 * Get current clinic status or status for a specific date/time
 */
function getClinicStatus(?string $checkDateTime = null): array
{
    try {
        $db = getClinicDB();
        $targetTime = $checkDateTime ?: date('Y-m-d H:i:s');
        
        // Find if there's any active record for this specific time
        $stmt = $db->prepare("SELECT message, status, start_datetime, end_datetime 
                               FROM clinic_status 
                               WHERE is_active = 1 
                               AND ? BETWEEN start_datetime AND end_datetime
                               ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$targetTime]);
        $result = $stmt->fetch();
        
        if ($result) {
            return [
                'closed' => ($result['status'] === 'closed'),
                'status' => $result['status'],
                'message' => $result['message'],
                'start' => $result['start_datetime'],
                'end' => $result['end_datetime']
            ];
        }
    } catch (Exception $e) {
        error_log("Clinic status check error: " . $e->getMessage());
    }
    
    return ['closed' => false, 'message' => '', 'status' => 'open'];
}
