<?php
/**
 * API: Check Booking Availability for specific date/time
 * POST /backend/api/clinic/check-availability.php
 */
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = getJsonInput();
$appointmentDate = $data['date'] ?? '';
$appointmentTime = $data['time'] ?? '';

if (!$appointmentDate) {
    jsonResponse(['available' => false, 'message' => 'Date is required.'], 400);
}

$checkDateTime = $appointmentTime ? "$appointmentDate $appointmentTime" : "$appointmentDate 00:00:00";

$status = getClinicStatus($checkDateTime);

if ($status['closed']) {
    jsonResponse([
        'available' => false,
        'message' => 'Clinic is closed at selected date and time: ' . $status['message']
    ]);
}

jsonResponse([
    'available' => true,
    'message' => 'Clinic is open.'
]);
?>
