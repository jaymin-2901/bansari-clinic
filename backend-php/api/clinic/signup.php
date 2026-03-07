<?php
/**
 * Bansari Homeopathy – Patient Signup API
 * POST: { full_name, mobile, email?, password, age?, gender?, city? }
 */
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = getJsonInput();
$required = validateRequired($data, ['full_name', 'mobile', 'password']);
if ($required) {
    jsonResponse(['error' => $required], 400);
}

$mobile   = preg_replace('/[^0-9+]/', '', $data['mobile']);
$password = $data['password'];

if (strlen($mobile) < 10) {
    jsonResponse(['error' => 'Invalid mobile number'], 400);
}
if (strlen($password) < 6) {
    jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
}

try {
    $db = getClinicDB();

    // Check if mobile already registered
    $stmt = $db->prepare("SELECT id, is_registered FROM patients WHERE mobile = ? LIMIT 1");
    $stmt->execute([$mobile]);
    $existing = $stmt->fetch();

    if ($existing && $existing['is_registered']) {
        jsonResponse(['error' => 'This mobile number is already registered. Please login.'], 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $fullName = trim($data['full_name']);
    $email = trim($data['email'] ?? '');
    $city = trim($data['city'] ?? '');

    if ($existing) {
        // Patient exists from appointment but not registered – upgrade to registered
        $stmt = $db->prepare("UPDATE patients SET full_name = ?, email = ?, password = ?, age = ?, gender = ?, city = ?, is_registered = 1 WHERE id = ?");
        $stmt->execute([
            $fullName,
            $email,
            $hashedPassword,
            (int)($data['age'] ?? 0) ?: null,
            in_array($data['gender'] ?? '', ['male', 'female', 'other']) ? $data['gender'] : null,
            $city,
            $existing['id']
        ]);
        $patientId = $existing['id'];
    } else {
        // New patient
        $stmt = $db->prepare("INSERT INTO patients (full_name, mobile, email, password, age, gender, city, is_registered) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $fullName,
            $mobile,
            $email,
            $hashedPassword,
            (int)($data['age'] ?? 0) ?: null,
            in_array($data['gender'] ?? '', ['male', 'female', 'other']) ? $data['gender'] : null,
            $city
        ]);
        $patientId = $db->lastInsertId();
    }

    // Start session
    session_start();
    $_SESSION['patient'] = [
        'id'    => (int)$patientId,
        'name'  => $fullName,
        'mobile' => $mobile,
    ];

    jsonResponse([
        'success' => true,
        'message' => 'Account created successfully',
        'patient' => [
            'id'   => (int)$patientId,
            'name' => $fullName,
            'mobile' => $mobile,
            'email' => $email,
        ]
    ], 201);

} catch (PDOException $e) {
    error_log("Signup error: " . $e->getMessage());
    jsonResponse(['error' => 'Server error. Please try again.'], 500);
}
