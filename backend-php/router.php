<?php
/**
 * PHP Built-in Server Router
 * Routes /uploads/* requests to the public/uploads directory
 * Routes /api/* requests to backend-php/api/
 * Usage: php -S localhost:8000 router.php
 * 
 * For Render.com: php -S 0.0.0.0:$PORT router.php
 */

// Get PORT from environment (Render.com provides this)
$PORT = getenv('PORT') ?: 8000;

// Health check endpoint - respond before any processing
if ($_SERVER['REQUEST_URI'] === '/api/health' || $_SERVER['REQUEST_URI'] === '/health') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'Backend running successfully',
        'timestamp' => time(),
        'environment' => getenv('APP_ENV') ?: 'development'
    ]);
    exit;
}

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve upload files from public/uploads/
if (str_starts_with($path, '/uploads/')) {
    $filePath = dirname(__DIR__) . '/public' . $path;
    if (file_exists($filePath) && is_file($filePath)) {
        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
        ];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        return true;
    }
    http_response_code(404);
    echo 'File not found';
    return true;
}

// Route API requests to backend-php/api/
if (str_starts_with($path, '/api/')) {
    // Request: /api/clinic/slots.php -> d:/bansari-homeopathy/backend-php/api/clinic/slots.php
    // __DIR__ = d:/bansari-homeopathy/backend-php
    // We need: d:/bansari-homeopathy/backend-php/api/xxx
    $file = __DIR__ . str_replace('/api/', '/api/', $path);
    
    // Debug: log the path
    error_log("API request: path=$path, file=$file, exists=" . (file_exists($file) ? 'yes' : 'no'));
    
    if (file_exists($file) && is_file($file)) {
        require $file;
        return true;
    }
    http_response_code(404);
    echo 'API endpoint not found: ' . $file;
    return true;
}

// Handle root path "/" - redirect to admin login
if ($path === '/' || $path === '') {
    header('Location: /clinic-admin-php/index.php');
    exit;
}

// Serve files from clinic-admin-php if they exist
$docRoot = dirname(__DIR__) . '/clinic-admin-php';
$file = $docRoot . $path;
if (file_exists($file) && is_file($file)) {
    // Let PHP handle the file (default behavior)
    return false;
}

// Default: let PHP built-in server handle the request
return false;
