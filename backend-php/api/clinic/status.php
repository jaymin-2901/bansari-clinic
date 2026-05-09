<?php
/**
 * API: Get Clinic Status
 * GET /backend/api/clinic/status.php
 */
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

$status = getClinicStatus();

echo json_encode([
    'success' => true,
    'closed' => $status['closed'],
    'message' => $status['message'] ?? '',
    'start' => $status['start'] ?? null,
    'end' => $status['end'] ?? null,
    'is_open' => !$status['closed']
]);
?>
