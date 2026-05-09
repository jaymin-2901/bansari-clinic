<?php
/**
 * PHP Built-in Server Router - FIXED for Windows paths
 * Routes /uploads/* → d:/bansari-homeopathy/uploads/
 * Routes /api/* → backend-php/api/
 */

$PORT = getenv('PORT') ?: 8000;

// Health check
if ($_SERVER['REQUEST_URI'] === '/api/health' || $_SERVER['REQUEST_URI'] === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'OK', 'timestamp' => time()]);
    exit;
}

// Global Error/Exception Logger
set_exception_handler(function ($e) {
    $logFile = __DIR__ . '/logs/api_errors.log';
    $msg = "[" . date('Y-m-d H:i:s') . "] UNCAUGHT EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    error_log($msg, 3, $logFile);
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    $logFile = __DIR__ . '/logs/api_errors.log';
    $msg = "[" . date('Y-m-d H:i:s') . "] PHP ERROR ({$severity}): {$message} in {$file} on line {$line}\n";
    error_log($msg, 3, $logFile);
    return false; // Let standard error handler run too
});

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// **FIXED uploads serving**
if (str_starts_with($path, '/uploads/')) {
    $rootDir = realpath(dirname(__DIR__));  // d:/bansari-homeopathy
    $publicUploadsDir = realpath(__DIR__ . '/public/uploads');
    $relativePath = substr($path, strlen('/uploads/'));
    $filePath = $publicUploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    
    error_log("UPLOAD: '$path' → public symlink '$filePath' exists=" . (file_exists($filePath) ? 'YES' : 'NO'));
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        error_log("Fallback to root uploads");
        $filePath = $rootDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        error_log("Fallback path: '$filePath' exists=" . (file_exists($filePath) ? 'YES' : 'NO'));
    }
    
    if (file_exists($filePath) && is_file($filePath)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo '404 File not found: ' . htmlspecialchars($filePath);
    exit;
}

// API routing
if (str_starts_with($path, '/api/')) {
    $apiPath = substr($path, strlen('/api'));
    $apiFile = __DIR__ . '/api' . $apiPath;
    if (substr($apiPath, -4) !== '.php') {
        $apiFile .= '.php';
    }
    error_log("API REQUEST: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
    $query = $_SERVER['QUERY_STRING'] ?? '';
    if ($query) error_log("QUERY: " . $query);

    if (file_exists($apiFile)) {
        error_log("ROUTING TO: " . $apiFile);
        require $apiFile;
        exit;
    }
    http_response_code(404);
    echo 'API not found';
    exit;
}

// Admin redirect
if ($path === '/' || $path === '') {
    header('Location: /clinic-admin-php');
    exit;
}

return false; // Default PHP server behavior

