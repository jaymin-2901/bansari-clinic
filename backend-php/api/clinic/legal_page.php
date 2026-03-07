<?php
/**
 * Bansari Homeopathy – Public Legal Page API
 * Serves privacy policy and terms & conditions to the frontend
 */
require_once __DIR__ . '/../../config/clinic_db.php';

// Set CORS headers
setCORSHeaders();

$slug = $_GET['slug'] ?? null;

if (!$slug || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid page slug']);
    exit;
}

try {
    $db = getClinicDB();
    $stmt = $db->prepare("SELECT title, slug, content, updated_at FROM legal_pages WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();

    if (!$page) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Page not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'title' => $page['title'],
            'slug' => $page['slug'],
            'content' => $page['content'],
            'updated_at' => $page['updated_at']
        ]
    ]);
} catch (PDOException $e) {
    error_log('Legal page API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
