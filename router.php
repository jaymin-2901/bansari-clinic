<?php
/**
 * PHP Built-in Server Router
 * Routes all requests to appropriate locations
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

// Route API requests to backend-php/api/
if (str_starts_with($path, '/api/')) {
    // __DIR__ is d:/bansari-homeopathy
    // We need: d:/bansari-homeopathy/backend-php/api/
    $file = __DIR__ . '/backend-php' . $path;
    
    if (file_exists($file) && is_file($file)) {
        require $file;
        return true;
    }
    http_response_code(404);
    echo 'API endpoint not found: ' . $file;
    return true;
}

// Serve assets from clinic-admin-php/assets/
if (str_starts_with($path, '/assets/')) {
    $filePath = __DIR__ . '/clinic-admin-php' . $path;
    if (file_exists($filePath) && is_file($filePath)) {
        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'svg' => 'image/svg+xml',
            'css' => 'text/css',
            'js' => 'application/javascript',
        ];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        return true;
    }
    // If asset doesn't exist, return a simple 1x1 transparent GIF instead of 404
    // This prevents logging errors for missing placeholders
    if (strpos($path, 'placeholder') !== false) {
        header('Content-Type: image/gif');
        header('Cache-Control: public, max-age=86400');
        // 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return true;
    }
    http_response_code(404);
    echo 'Asset not found';
    return true;
}

// Handle root path "/" - redirect to admin login
if ($path === '/' || $path === '') {
    header('Location: /clinic-admin-php/index.php');
    exit;
}

// Serve upload files from public/uploads/
if (str_starts_with($path, '/uploads/')) {
    $filePath = __DIR__ . '/public' . $path;
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

// Serve files from clinic-admin-php if they exist
$docRoot = __DIR__ . '/clinic-admin-php';
$file = $docRoot . $path;
if (file_exists($file) && is_file($file)) {
    // Serve the file from the correct directory
    chdir($docRoot);
    include $file;
    return true;
}

// Default: let PHP built-in server handle the request
return false;

