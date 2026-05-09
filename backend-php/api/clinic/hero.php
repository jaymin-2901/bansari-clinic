<?php
/**
 * Public API: Get Active Hero Images
 */
require_once __DIR__ . '/../../security/bootstrap.php';
SecurityBootstrap::headersOnly();

try {
    $db = getClinicDB();
    $stmt = $db->query("SELECT id, desktop_image, mobile_image FROM hero_images WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $heroes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add full path/url info
    foreach ($heroes as &$hero) {
        $hero['desktop_url'] = 'uploads/hero/' . $hero['desktop_image'];
        $hero['mobile_url'] = $hero['mobile_image'] ? 'uploads/hero/' . $hero['mobile_image'] : null;
    }

    echo json_encode(['success' => true, 'data' => $heroes]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
