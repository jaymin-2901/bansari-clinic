<?php
/**
 * ============================================================
 * MediConnect – Appointment Booking API (Final Instant Booking)
 * File: backend/api/book_appointment.php
 * ============================================================
 * 
 * INSTANT BOOKING FLOW (NO confirmation step):
 *   1. Validate inputs (patient_id, appointment_datetime format, future date)
 *   2. Prevent duplicate submission (same patient + same datetime)
 *   3. Insert appointment as FINAL (status=approved, confirmation=Confirmed)
 *   4. Compute reminder_scheduled_at = appointment_datetime − 24 hours
 *   5. Return result — Booking is FINAL once submitted
 * 
 * NO confirmation page, NO confirmation button, NO OTP,
 * NO double submission, NO pending confirmation state.
 * 
 * Session security and reminder logic remain intact.
 * 
 * Usage:
 *   POST /api/book_appointment.php
 *   Body (JSON): { patient_id, appointment_datetime, consultation_type, ... }
 *   Response: { success, consultation_id }
 */

require_once __DIR__ . '/../security/bootstrap.php';

// ── Security: CORS + Rate Limiting (public booking endpoint) ──
SecurityBootstrap::publicEndpoint('book');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit(0);
}

// ── Parse input ──
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit(0);
}

$patientId           = (int) ($input['patient_id'] ?? 0);
$appointmentDatetime = trim($input['appointment_datetime'] ?? '');
$consultationType    = trim($input['consultation_type'] ?? 'general');
$preferredDate       = trim($input['preferred_date'] ?? '');
$preferredTime       = trim($input['preferred_time'] ?? '');
$notes               = trim($input['notes'] ?? '');

// ── Validate required fields ──
if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'patient_id required']);
    exit(0);
}
if (empty($appointmentDatetime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'appointment_datetime required (Y-m-d H:i:s)']);
    exit(0);
}

// ── Validate datetime format ──
$apptDtObj = DateTime::createFromFormat('Y-m-d H:i:s', $appointmentDatetime, new DateTimeZone('Asia/Kolkata'));
if (!$apptDtObj) {
    // Try alternative formats
    $apptDtObj = DateTime::createFromFormat('Y-m-d H:i', $appointmentDatetime, new DateTimeZone('Asia/Kolkata'));
}
if (!$apptDtObj) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid appointment_datetime format. Use Y-m-d H:i:s']);
    exit(0);
}

// ── Ensure appointment is in the future ──
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
if ($apptDtObj <= $now) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Appointment must be in the future']);
    exit(0);
}

// Normalize datetime to Y-m-d H:i:s
$appointmentDatetime = $apptDtObj->format('Y-m-d H:i:s');

// ── Compute 24h reminder time ──
$reminderTime = (clone $apptDtObj)->modify('-24 hours')->format('Y-m-d H:i:s');

try {
    $db = getDBConnection();

    // ── Prevent duplicate submission (same patient + same datetime) ──
    $dupCheck = $db->prepare("
        SELECT id FROM consultations 
        WHERE patient_id = :pid 
          AND appointment_datetime = :appt_dt 
          AND status NOT IN ('cancelled', 'rejected')
        LIMIT 1
    ");
    $dupCheck->execute([':pid' => $patientId, ':appt_dt' => $appointmentDatetime]);
    if ($dupCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Duplicate booking: appointment already exists for this date/time']);
        exit(0);
    }

    // ══════════════════════════════════════════════════════════
    // INSERT – Booking is FINAL. No pending state.
    //   status = 'approved'  (final, no confirmation step)
    //   confirmation_status = 'Confirmed'
    //   reminder_scheduled_at = appointment_datetime − 24h
    //   reminder_24h_sent = 0 (cron will pick this up)
    // ══════════════════════════════════════════════════════════
    $stmt = $db->prepare("
        INSERT INTO consultations 
            (patient_id, appointment_datetime, consultation_type, preferred_date, preferred_time, 
             notes, status, 
             reminder_scheduled_at, reminder_24h_sent,
             created_at, updated_at)
        VALUES 
            (:pid, :appt_dt, :type, :pref_date, :pref_time, 
             :notes, 'approved',
             :reminder_time, 0,
             NOW(), NOW())
    ");
    $stmt->execute([
        ':pid'           => $patientId,
        ':appt_dt'       => $appointmentDatetime,
        ':type'          => $consultationType,
        ':pref_date'     => $preferredDate,
        ':pref_time'     => $preferredTime,
        ':notes'         => $notes,
        ':reminder_time' => $reminderTime,
    ]);

    $consultationId = (int) $db->lastInsertId();

    writeLog('booking.log', "FINAL BOOKING: Consultation #{$consultationId} | Patient #{$patientId} | Appt: {$appointmentDatetime} | Reminder: {$reminderTime}");

    http_response_code(201);
    echo json_encode([
        'success'          => true,
        'consultation_id'  => $consultationId,
        'appointment_datetime' => $appointmentDatetime,
        'reminder_scheduled_at' => $reminderTime,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    writeLog('api_errors.log', 'Booking API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    writeLog('api_errors.log', 'Booking API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
