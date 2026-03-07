<?php
/**
 * ============================================================
 * MediConnect – Frontend Booking API
 * File: backend/api/book_from_frontend.php
 * ============================================================
 * 
 * Accepts booking data from the React frontend and:
 *   1. Creates or finds the patient in the users table
 *   2. Inserts the consultation
 *   3. Returns the consultation ID
 * 
 * POST /backend/api/book_from_frontend.php
 * Body (JSON):
 *   {
 *     "patientName": "Rahul Sharma",
 *     "email": "rahul@test.com",
 *     "mobile": "+919876543210",
 *     "gender": "male",
 *     "preferredDate": "2026-02-28",
 *     "preferredTime": "10:00",
 *     "symptoms": "Headache and fever",
 *     "symptomDuration": "3 days",
 *     "previousTreatment": "None",
 *     "allergies": "None",
 *     "consultationType": "offline",
 *     "urgencyLevel": "normal",
 *     "bookingRef": "MC-XXXX"
 *   }
 */

require_once __DIR__ . '/../security/bootstrap.php';

// ── Security: CORS + Rate Limiting (public booking endpoint) ──
SecurityBootstrap::publicEndpoint('book');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit(0);
}

// ── Parse Input ──
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit(0);
}

// ── Extract Fields ──
$patientName    = trim($input['patientName'] ?? '');
$email          = trim($input['email'] ?? '');
$mobile         = trim($input['mobile'] ?? '');
$gender         = trim($input['gender'] ?? '');
$preferredDate  = trim($input['preferredDate'] ?? '');
$preferredTime  = trim($input['preferredTime'] ?? '');
$symptoms       = trim($input['symptoms'] ?? '');
$symptomDuration = trim($input['symptomDuration'] ?? '');
$previousTreatment = trim($input['previousTreatment'] ?? '');
$allergies      = trim($input['allergies'] ?? '');
$consultationType = trim($input['consultationType'] ?? 'offline');
$urgencyLevel   = trim($input['urgencyLevel'] ?? 'normal');
$bookingRef     = trim($input['bookingRef'] ?? '');
$address        = trim($input['address'] ?? '');

// ── Validate Required Fields ──
if (empty($patientName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Patient name is required']);
    exit(0);
}
if (empty($email) && empty($mobile)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email or mobile is required']);
    exit(0);
}
if (empty($preferredDate) || empty($preferredTime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Preferred date and time are required']);
    exit(0);
}

// ── Build appointment_datetime ──
$appointmentDatetime = $preferredDate . ' ' . $preferredTime . ':00';
// Validate the resulting datetime
$dtCheck = DateTime::createFromFormat('Y-m-d H:i:s', $appointmentDatetime);
if (!$dtCheck) {
    // Try without seconds
    $dtCheck = DateTime::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $preferredTime);
    if ($dtCheck) {
        $appointmentDatetime = $dtCheck->format('Y-m-d H:i:s');
    } else {
        $appointmentDatetime = $preferredDate . ' ' . $preferredTime;
    }
}

// ── Validate consultation type ──
if (!in_array($consultationType, ['online', 'offline'])) {
    $consultationType = 'offline';
}
if (!in_array($urgencyLevel, ['normal', 'urgent', 'emergency'])) {
    $urgencyLevel = 'normal';
}

// ── Split patient name ──
$nameParts = explode(' ', $patientName, 2);
$firstName = $nameParts[0];
$lastName  = isset($nameParts[1]) ? $nameParts[1] : '';

// ── Clean mobile (ensure it's in a standard format) ──
$cleanMobile = preg_replace('/[^0-9+]/', '', $mobile);

try {
    $db = getDBConnection();

    // ══════════════════════════════════════════
    // 1. Find or Create Patient
    // ══════════════════════════════════════════
    $patientId = null;

    // Try to find by email first
    if (!empty($email)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND role = 'patient' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $found = $stmt->fetch();
        if ($found) {
            $patientId = (int) $found['id'];
        }
    }

    // Try to find by phone if not found by email
    if (!$patientId && !empty($cleanMobile)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = :phone AND role = 'patient' LIMIT 1");
        $stmt->execute([':phone' => $cleanMobile]);
        $found = $stmt->fetch();
        if ($found) {
            $patientId = (int) $found['id'];
        }
    }

    // Create new patient if not found
    if (!$patientId) {
        $insertEmail = !empty($email) ? $email : $cleanMobile . '@placeholder.mediconnect.in';
        $stmt = $db->prepare("
            INSERT INTO users (role, first_name, last_name, email, phone, password, created_at)
            VALUES ('patient', :fname, :lname, :email, :phone, :pass, NOW())
        ");
        $stmt->execute([
            ':fname' => $firstName,
            ':lname' => $lastName,
            ':email' => $insertEmail,
            ':phone' => $cleanMobile,
            ':pass'  => password_hash('mediconnect_default_2026', PASSWORD_BCRYPT),
        ]);
        $patientId = (int) $db->lastInsertId();

        writeLog('booking.log', "NEW PATIENT CREATED: User #{$patientId} | {$patientName} | {$email} | {$cleanMobile}");

        // Save address if provided
        if (!empty($address)) {
            $addrStmt = $db->prepare("INSERT INTO patient_address (user_id, address) VALUES (:uid, :addr)");
            $addrStmt->execute([':uid' => $patientId, ':addr' => $address]);
        }
    }

    // ══════════════════════════════════════════
    // 2. Insert Consultation
    // ══════════════════════════════════════════
    $symptomsText = $symptoms;
    if (!empty($symptomDuration)) $symptomsText .= "\nDuration: " . $symptomDuration;
    if (!empty($previousTreatment)) $symptomsText .= "\nPrevious Treatment: " . $previousTreatment;
    if (!empty($allergies)) $symptomsText .= "\nAllergies: " . $allergies;

    $stmt = $db->prepare("
        INSERT INTO consultations 
            (patient_id, consultation_type, urgency_level, symptoms, preferred_date, preferred_time, 
             appointment_datetime, booking_ref, status, 
             followup_done, created_at, updated_at)
        VALUES 
            (:pid, :ctype, :urgency, :symptoms, :pref_date, :pref_time, 
             :appt_dt, :bref, 'pending', 
             0, NOW(), NOW())
    ");
    $stmt->execute([
        ':pid'       => $patientId,
        ':ctype'     => $consultationType,
        ':urgency'   => $urgencyLevel,
        ':symptoms'  => $symptomsText,
        ':pref_date' => $preferredDate,
        ':pref_time' => $preferredTime,
        ':appt_dt'   => $appointmentDatetime,
        ':bref'      => $bookingRef ?: ('MC-' . strtoupper(base_convert(time(), 10, 36))),
    ]);

    $consultationId = (int) $db->lastInsertId();

    writeLog('booking.log', "FRONTEND BOOKING: Consultation #{$consultationId} | Patient #{$patientId} ({$patientName}) | Appt: {$appointmentDatetime} | Ref: {$bookingRef}");

    // ══════════════════════════════════════════
    // 3. Return Success
    // ══════════════════════════════════════════
    http_response_code(201);
    echo json_encode([
        'success'         => true,
        'consultation_id' => $consultationId,
        'patient_id'      => $patientId,
        'booking_ref'     => $bookingRef,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    writeLog('api_errors.log', 'Frontend Booking API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    writeLog('api_errors.log', 'Frontend Booking API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
