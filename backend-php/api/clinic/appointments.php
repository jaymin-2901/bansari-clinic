<?php
/**
 * API: Book Appointment
 * POST /backend/api/clinic/appointments.php
 *
 * Handles both short (offline) and full (online) appointment forms.
 */

require_once __DIR__ . '/../../config/clinic_config.php';
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = getJsonInput();

// ─── Validate basic patient info ───
$error = validateRequired($data, ['full_name', 'mobile', 'age', 'gender', 'city', 'appointment_date', 'consultation_type']);
if ($error) {
    jsonResponse(['error' => $error], 400);
}

$consultationType = $data['consultation_type']; // offline or online
$formType = ($consultationType === 'offline') ? 'short' : 'full';

try {
    $db = getClinicDB();
    $db->beginTransaction();

    // ─── 0. SLOT VALIDATION ───
    $appointmentDate = sanitize($data['appointment_date']);
    $appointmentTime = sanitize($data['appointment_time'] ?? '');

    // (a) Reject past dates
    if ($appointmentDate < date('Y-m-d')) {
        jsonResponse(['error' => 'Cannot book appointments for past dates.'], 400);
    }

    // (b) Check clinic is open on this day (Sunday closed)
    $dow = (int)date('w', strtotime($appointmentDate));
    if ($dow === CLINIC_CLOSED_DAY) {
        jsonResponse(['error' => 'Clinic is closed on Sunday. Please choose another date.'], 400);
    }

    // (c) Validate time is within clinic hours (Morning: 9:30-1:00, Evening: 5:00-8:00)
    if ($appointmentTime) {
        $timeSec    = strtotime($appointmentTime);
        $morningOpen  = strtotime(CLINIC_MORNING_OPEN);
        $morningClose = strtotime(CLINIC_MORNING_CLOSE);
        $eveningOpen  = strtotime(CLINIC_EVENING_OPEN);
        $eveningClose = strtotime(CLINIC_EVENING_CLOSE);

        $inMorning = ($timeSec >= $morningOpen && $timeSec < $morningClose);
        $inEvening = ($timeSec >= $eveningOpen && $timeSec < $eveningClose);

        if (!$inMorning && !$inEvening) {
            jsonResponse(['error' => 'Selected time is outside clinic hours. Morning: 9:30 AM – 1:00 PM, Evening: 5:00 PM – 8:00 PM.'], 400);
        }

        // (d) Prevent double booking
        $dblStmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
        $dblStmt->execute([$appointmentDate, $appointmentTime]);
        if ($dblStmt->fetchColumn() > 0) {
            jsonResponse(['error' => 'This slot is already booked. Please choose another time.'], 409);
        }

        // (e) If booking today, reject past time slots (with 30-min buffer)
        if ($appointmentDate === date('Y-m-d') && $timeSec < (time() + 1800)) {
            jsonResponse(['error' => 'This time slot has already passed. Please choose a later time.'], 400);
        }
    }

    // ─── 1. Create or find patient ───
    $patientId = null;

    // If a logged-in patient_id is provided, use it directly
    if (!empty($data['patient_id'])) {
        $stmt = $db->prepare("SELECT id FROM patients WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$data['patient_id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            $patientId = (int)$existing['id'];
            $stmt = $db->prepare("UPDATE patients SET full_name=?, age=?, gender=?, city=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                sanitize($data['full_name']),
                (int)$data['age'],
                sanitize($data['gender']),
                sanitize($data['city']),
                $patientId
            ]);
        }
    }

    // Fallback: look up by mobile or create new
    if (!$patientId) {
        $stmt = $db->prepare("SELECT id FROM patients WHERE mobile = ? LIMIT 1");
        $stmt->execute([sanitize($data['mobile'])]);
        $patient = $stmt->fetch();

        if ($patient) {
            $patientId = $patient['id'];
            $stmt = $db->prepare("UPDATE patients SET full_name=?, age=?, gender=?, city=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                sanitize($data['full_name']),
                (int)$data['age'],
                sanitize($data['gender']),
                sanitize($data['city']),
                $patientId
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO patients (full_name, mobile, age, gender, city) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                sanitize($data['full_name']),
                sanitize($data['mobile']),
                (int)$data['age'],
                sanitize($data['gender']),
                sanitize($data['city'])
            ]);
            $patientId = $db->lastInsertId();
        }
    }

    // ─── 2. Create appointment (no patient_type stored) ───
    $stmt = $db->prepare("INSERT INTO appointments (patient_id, consultation_type, form_type, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $patientId,
        $consultationType,
        $formType,
        $appointmentDate,
        $appointmentTime ?: '10:00:00'
    ]);
    $appointmentId = $db->lastInsertId();

    // ─── 3. Save form-specific data ───
    // Note: patient_type is auto-detected at runtime, not stored in DB
    if ($formType === 'short') {
        // Short form (offline)
        $stmt = $db->prepare("INSERT INTO complaints (appointment_id, chief_complaint, complaint_duration, major_diseases, current_medicines, allergy, declaration_accepted) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $appointmentId,
            sanitize($data['chief_complaint'] ?? ''),
            sanitize($data['complaint_duration'] ?? ''),
            json_encode($data['major_diseases'] ?? []),
            sanitize($data['current_medicines'] ?? ''),
            sanitize($data['allergy'] ?? ''),
            $data['declaration_accepted'] ?? 0
        ]);
    } else {
        // Full form (online)

        // Main complaints (dynamic array)
        if (!empty($data['main_complaints']) && is_array($data['main_complaints'])) {
            $stmt = $db->prepare("INSERT INTO main_complaints (appointment_id, complaint_text, duration, severity, sort_order) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['main_complaints'] as $i => $c) {
                $stmt->execute([
                    $appointmentId,
                    sanitize($c['text'] ?? ''),
                    sanitize($c['duration'] ?? ''),
                    sanitize($c['severity'] ?? 'moderate'),
                    $i
                ]);
            }
        }

        // Past diseases
        if (!empty($data['past_diseases']) && is_array($data['past_diseases'])) {
            $stmt = $db->prepare("INSERT INTO past_diseases (appointment_id, disease_name, details, year_diagnosed, treatment_taken, is_current) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($data['past_diseases'] as $d) {
                $stmt->execute([
                    $appointmentId,
                    sanitize($d['name'] ?? ''),
                    sanitize($d['details'] ?? ''),
                    sanitize($d['year'] ?? ''),
                    sanitize($d['treatment'] ?? ''),
                    $d['is_current'] ?? 0
                ]);
            }
        }

        // Family history
        if (!empty($data['family_history']) && is_array($data['family_history'])) {
            $stmt = $db->prepare("INSERT INTO family_history (appointment_id, relation, disease, details) VALUES (?, ?, ?, ?)");
            foreach ($data['family_history'] as $f) {
                $stmt->execute([
                    $appointmentId,
                    sanitize($f['relation'] ?? ''),
                    sanitize($f['disease'] ?? ''),
                    sanitize($f['details'] ?? '')
                ]);
            }
        }

        // Physical generals
        if (!empty($data['physical_generals'])) {
            $pg = $data['physical_generals'];
            $stmt = $db->prepare("INSERT INTO physical_generals (appointment_id, appetite, thirst, stool, urine, sweat, sleep_quality, sleep_position, thermal, cravings, aversions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $appointmentId,
                sanitize($pg['appetite'] ?? 'good'),
                sanitize($pg['thirst'] ?? 'normal'),
                sanitize($pg['stool'] ?? 'regular'),
                sanitize($pg['urine'] ?? 'normal'),
                sanitize($pg['sweat'] ?? 'normal'),
                sanitize($pg['sleep_quality'] ?? 'sound'),
                sanitize($pg['sleep_position'] ?? ''),
                sanitize($pg['thermal'] ?? 'ambithermal'),
                sanitize($pg['cravings'] ?? ''),
                sanitize($pg['aversions'] ?? '')
            ]);
        }

        // Mental profile
        if (!empty($data['mental_profile'])) {
            $mp = $data['mental_profile'];
            $stmt = $db->prepare("INSERT INTO mental_profile (appointment_id, temperament, fears, dreams, stress_factors, emotional_state, hobbies, social_behavior, additional_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $appointmentId,
                sanitize($mp['temperament'] ?? ''),
                sanitize($mp['fears'] ?? ''),
                sanitize($mp['dreams'] ?? ''),
                sanitize($mp['stress_factors'] ?? ''),
                sanitize($mp['emotional_state'] ?? ''),
                sanitize($mp['hobbies'] ?? ''),
                sanitize($mp['social_behavior'] ?? ''),
                sanitize($mp['additional_notes'] ?? '')
            ]);
        }
    }

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Appointment booked successfully!',
        'appointment_id' => $appointmentId,
        'patient_id' => $patientId,
        'consultation_type' => $consultationType,
        'form_type' => $formType
    ], 201);

} catch (PDOException $e) {
    if (isset($db)) $db->rollBack();
    error_log("Appointment booking error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to book appointment. Please try again.'], 500);
}
