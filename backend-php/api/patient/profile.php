<?php
/**
 * Patient Profile API
 * Handles GET /api/patient/profile?patient_id=X (fetch profile + summary)
 * PATCH /api/patient/profile (update profile)
 */

require_once __DIR__ . '/../../config/clinic_db.php'; // Loads getClinicDB(), jsonResponse(), etc.

try {
    setCORSHeaders();
    $db = getClinicDB();
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        // Fetch patient profile + appointment summary
        $patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
        if (!$patient_id) {
            $patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        }

        if ($patient_id <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid patient ID'], 400);
        }

        try {
            // Get patient details
            $stmt = $db->prepare("
                SELECT id, full_name, mobile, age, gender, city, email, address, is_registered, created_at
                FROM patients 
                WHERE id = ?
            ");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch();

            if (!$patient) {
                jsonResponse(['success' => false, 'error' => 'Patient not found'], 404);
            }
        } catch (PDOException $e) {
            error_log("Profile API - Patient Query Error: " . $e->getMessage());
            throw $e;
        }

        try {
            // Appointment summary
            $stmt = $db->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total_appointments,
                    COALESCE(SUM(CASE WHEN status IN ('pending', 'confirmed') AND appointment_date >= CURDATE() THEN 1 ELSE 0 END), 0) as upcoming_count,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_count,
                    COALESCE(SUM(CASE WHEN is_followup = 1 THEN 1 ELSE 0 END), 0) as followup_count,
                    NULL as next_appointment
                FROM appointments 
                WHERE patient_id = ?
            ");
            $stmt->execute([$patient_id]);
            $summary = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Profile API - Summary Query Error: " . $e->getMessage());
            throw $e;
        }

        try {
            // Recent appointments
            $stmt = $db->prepare("
                SELECT id, appointment_date, appointment_time, consultation_type, status, 
                       confirmation_status, 
                       COALESCE(is_followup, 0) as is_followup, 
                       COALESCE(followup_done, 0) as followup_done, 
                       created_at
                FROM appointments 
                WHERE patient_id = ?
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$patient_id]);
            $appointments = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Profile API - Appointments Query Error: " . $e->getMessage());
            throw $e;
        }

        jsonResponse([
            'success' => true,
            'patient' => $patient,
            'summary' => $summary,
            'appointments' => $appointments
        ]);

    } elseif ($method === 'PATCH') {
        // ... (unchanged)
        $input = getJsonInput();
        $required = ['patient_id', 'full_name', 'mobile'];
        $error = validateRequired($input, $required);

        if ($error) {
            jsonResponse(['success' => false, 'error' => $error], 400);
        }

        $patient_id = (int)$input['patient_id'];
        if ($patient_id <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid patient ID'], 400);
        }

        // Validate mobile (10 digits, India)
        if (!preg_match('/^[6-9]\d{9}$/', $input['mobile'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid mobile number format'], 400);
        }

        // Check mobile unique (exclude self)
        $stmt = $db->prepare("SELECT id FROM patients WHERE mobile = ? AND id != ?");
        $stmt->execute([$input['mobile'], $patient_id]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'Mobile number already registered'], 400);
        }

        // Prepare update fields
        $updates = ['full_name = ?', 'mobile = ?', 'updated_at = NOW()'];
        $params = [$input['full_name'], $input['mobile']];

        if (isset($input['age']) && is_numeric($input['age'])) {
            $updates[] = 'age = ?';
            $params[] = (int)$input['age'];
        }
        if (!empty($input['gender'])) {
            $updates[] = 'gender = ?';
            $params[] = $input['gender'];
        }
        if (!empty($input['city'])) {
            $updates[] = 'city = ?';
            $params[] = substr($input['city'], 0, 100);
        }
        if (isset($input['address'])) {
            $updates[] = 'address = ?';
            $params[] = empty($input['address']) ? null : substr($input['address'], 0, 500);
        }
        if (isset($input['email']) && filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $updates[] = 'email = ?';
            $params[] = $input['email'];
        }

        $params[] = $patient_id;

        $stmt = $db->prepare("UPDATE patients SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['success' => false, 'error' => 'Patient not found or no changes made'], 404);
        }

        // Return updated patient
        $stmt = $db->prepare("SELECT id, full_name, mobile, age, gender, city, email, address FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $updated = $stmt->fetch();

        jsonResponse(['success' => true, 'patient' => $updated]);

    } else {
        jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }

} catch (Throwable $e) {
    error_log("Patient Profile API Error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
?>

