<?php
header('Content-Type: application/json');

// Test database connection and API
// Load production config for InfinityFree database credentials and CORS
require_once __DIR__ . '/config/production_config.php';
require_once __DIR__ . '/config/clinic_db.php';

// Try to get database
try {
    echo json_encode([
        'test' => 'Database connection',
        'db_host' => DB_HOST,
        'db_port' => DB_PORT,
        'db_name' => DB_NAME,
        'frontend_url' => FRONTEND_URL,
    ]);
    
    $db = getClinicDB();
    echo json_encode(['status' => 'success', 'message' => 'Database connected']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
