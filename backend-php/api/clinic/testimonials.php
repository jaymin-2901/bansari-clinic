<?php
/**
 * API: Public Testimonials 
 * GET /api/clinic/testimonials.php - Get all ACTIVE testimonials
 * 
 * Returns: JSON with active testimonials (display_status='active')
 * Image paths formatted for frontend getImageUrl(): /public/uploads/testimonials/FILENAME
 */

require_once __DIR__ . '/../../../backend-php/config/clinic_db.php';
require_once __DIR__ . '/../../security/CORSHandler.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $db = getClinicDB();
    
    // Fetch ACTIVE testimonials only, ordered by sort_order then newest first
    $stmt = $db->prepare("
        SELECT id, patient_name, is_anonymous, treatment_description, testimonial_text, 
               before_image, after_image, rating, display_status, sort_order, created_at
        FROM testimonials 
        WHERE display_status = 'active' 
        ORDER BY sort_order DESC, created_at DESC
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format image paths consistently for frontend getImageUrl()
    $baseImagePath = '/public/uploads/testimonials/';
    foreach ($testimonials as &$testimonial) {
        $testimonial['before_image'] = $testimonial['before_image'] ? $baseImagePath . $testimonial['before_image'] : null;
        $testimonial['after_image'] = $testimonial['after_image'] ? $baseImagePath . $testimonial['after_image'] : null;
    }
    
    jsonResponse([
        'success' => true, 
        'count' => count($testimonials),
        'data' => $testimonials
    ]);
    
} catch (PDOException $e) {
    error_log("Testimonials API error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch testimonials'], 500);
}

// Helper function (if not defined elsewhere)
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
?>

