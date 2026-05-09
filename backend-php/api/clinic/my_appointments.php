<?php
/**
 * Bansari Homeopathy – My Appointments API
 * GET: ?patient_id=123  → returns appointments for that patient
 */
require_once __DIR__ . '/../../security/bootstrap.php';

// Require authentication
$user = SecurityBootstrap::authenticatedEndpoint('my_appointments');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// For patients, always use their own ID from the token
if (($user['role'] ?? 'patient') === 'patient') {
    $patientId = (int)$user['sub'];
} else {
    // Admins/staff can specify a patient_id
    $patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
}

if ($patientId <= 0) {
    jsonResponse(['error' => 'Unauthorized or invalid patient ID'], 401);
}

// Verify the patient exists before returning data
try {
    $db = getClinicDB();

    $checkStmt = $db->prepare("SELECT id FROM patients WHERE id = ? LIMIT 1");
    $checkStmt->execute([$patientId]);
    if (!$checkStmt->fetch()) {
        jsonResponse(['error' => 'Patient not found'], 404);
    }

    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.consultation_type AS appointment_type,
            a.status,
            a.created_at,
            c.chief_complaint
        FROM appointments a
        LEFT JOIN complaints c ON c.appointment_id = a.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$patientId]);
    $appointments = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data'    => $appointments,
        'count'   => count($appointments),
    ]);

} catch (PDOException $e) {
    error_log("My appointments error: " . $e->getMessage());
    jsonResponse(['error' => 'Server error. Please try again.'], 500);
}
