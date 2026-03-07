<?php
/**
 * API: Get Testimonials (Public)
 * GET /backend/api/clinic/testimonials.php
 */

require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $db = getClinicDB();
    $stmt = $db->prepare("
        SELECT id, 
               CASE WHEN is_anonymous = 1 THEN 'Anonymous Patient' ELSE patient_name END AS patient_name,
               is_anonymous,
               treatment_description,
               testimonial_text,
               before_image,
               after_image,
               rating
        FROM testimonials 
        WHERE display_status = 'active' 
        ORDER BY sort_order ASC, created_at DESC
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();

    // Prepend upload path to images
    foreach ($testimonials as &$t) {
        if ($t['before_image']) {
            $t['before_image'] = '/uploads/testimonials/' . $t['before_image'];
        }
        if ($t['after_image']) {
            $t['after_image'] = '/uploads/testimonials/' . $t['after_image'];
        }
    }

    jsonResponse(['success' => true, 'data' => $testimonials]);

} catch (PDOException $e) {
    error_log("Testimonials fetch error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch testimonials'], 500);
}
