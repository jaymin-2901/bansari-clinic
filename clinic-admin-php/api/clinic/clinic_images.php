<?php
/**
 * API: Clinic Images Management
 * 
 * Endpoints:
 * GET    /api/clinic/clinic_images.php     - Get all clinic images
 * POST   /api/clinic/clinic_images.php     - Upload new clinic image
 * DELETE /api/clinic/clinic_images.php?id=X - Delete clinic image by ID
 */

require_once __DIR__ . '/../../../backend-php/config/clinic_db.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    setCORSHeaders();
    exit;
}

setCORSHeaders();

// JSON response helper (only if not already defined)
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// Get the base URL for images - use relative path for mobile compatibility
function getImageBaseUrl(): string {
    // Return relative path so it works from any device/mobile
    return '/uploads/clinic-images';
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid image ID'], 400);
    }
    
    try {
        $db = getClinicDB();
        
        // Get image path before deleting
        $stmt = $db->prepare("SELECT image_path FROM clinic_images WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        
        if (!$image) {
            jsonResponse(['error' => 'Image not found'], 404);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM clinic_images WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete physical file - use the public/uploads path
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/clinic-images/';
        $filepath = $uploadDir . $image['image_path'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        jsonResponse(['success' => true, 'message' => 'Image deleted successfully']);
        
    } catch (PDOException $e) {
        error_log("Delete clinic image error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete image'], 500);
    }
}

// Handle POST request (upload new image)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded
    if (!isset($_FILES['clinic_image']) || $_FILES['clinic_image']['error'] !== UPLOAD_ERR_OK) {
        // Try 'image' as well
        $fileKey = isset($_FILES['clinic_image']) ? 'clinic_image' : (isset($_FILES['image']) ? 'image' : null);
        if (!$fileKey || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'No image uploaded or upload error'], 400);
        }
    } else {
        $fileKey = 'clinic_image';
    }
    
    $file = $_FILES[$fileKey];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowedTypes)) {
        jsonResponse(['error' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF'], 400);
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => 'File too large. Maximum size is 5MB'], 400);
    }
    
    // Create upload directory if it doesn't exist - use clinic-admin-php/uploads path
    $uploadDir = __DIR__ . '/../../../uploads/clinic-images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'clinic_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        jsonResponse(['error' => 'Failed to save image'], 500);
    }
    
    // Save to database
    try {
        $db = getClinicDB();
        $stmt = $db->prepare("INSERT INTO clinic_images (image_path) VALUES (?)");
        $stmt->execute([$filename]);
        $imageId = $db->lastInsertId();
        
        $baseUrl = getImageBaseUrl();
        
        jsonResponse([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => [
                'id' => $imageId,
                'image_path' => $baseUrl . '/' . $filename
            ]
        ], 201);
        
    } catch (PDOException $e) {
        // Delete uploaded file if database insert fails
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        error_log("Upload clinic image error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to save image record'], 500);
    }
}

// Handle GET request (fetch all images)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = getClinicDB();
        $stmt = $db->query("SELECT id, image_path, created_at FROM clinic_images ORDER BY created_at DESC");
        $images = $stmt->fetchAll();
        
        $baseUrl = getImageBaseUrl();
        
        // Convert to full URLs
        $result = [];
        foreach ($images as $image) {
            $result[] = [
                'id' => $image['id'],
                'image_path' => $baseUrl . '/' . $image['image_path'],
                'created_at' => $image['created_at']
            ];
        }
        
        jsonResponse([
            'success' => true,
            'data' => $result
        ]);
        
    } catch (PDOException $e) {
        error_log("Fetch clinic images error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch images'], 500);
    }
}

// Method not allowed
jsonResponse(['error' => 'Method not allowed'], 405);

