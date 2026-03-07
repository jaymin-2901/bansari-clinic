<?php
/**
 * Bansari Homeopathy – Patient Login API
 * POST: { mobile, password }  OR  { email, password }
 *
 * Returns structured JSON:
 *   success → { success: true, message, patient }
 *   failure → { success: false, type, message }
 */
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'type' => 'method', 'message' => 'Method not allowed'], 405);
}

$data = getJsonInput();

// ─── Input validation ───
$password = trim($data['password'] ?? '');
$mobile   = !empty($data['mobile']) ? preg_replace('/[^0-9+]/', '', $data['mobile']) : null;
$email    = !empty($data['email']) ? trim($data['email']) : null;

if (!$mobile && !$email) {
    jsonResponse(['success' => false, 'type' => 'validation', 'field' => 'identifier', 'message' => 'Please enter your mobile number or email address.'], 400);
}
if (empty($password)) {
    jsonResponse(['success' => false, 'type' => 'validation', 'field' => 'password', 'message' => 'Please enter your password.'], 400);
}
if ($mobile && strlen($mobile) < 10) {
    jsonResponse(['success' => false, 'type' => 'validation', 'field' => 'mobile', 'message' => 'Please enter a valid mobile number (at least 10 digits).'], 400);
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'type' => 'validation', 'field' => 'email', 'message' => 'Please enter a valid email address.'], 400);
}

try {
    $db = getClinicDB();

    // Step 1: Find patient by email or mobile (include plain_password for debugging)
    if ($email) {
        $stmt = $db->prepare("SELECT id, full_name, mobile, email, password, plain_password, is_registered FROM patients WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
    } else {
        $stmt = $db->prepare("SELECT id, full_name, mobile, email, password, plain_password, is_registered FROM patients WHERE mobile = ? LIMIT 1");
        $stmt->execute([$mobile]);
    }
    $patient = $stmt->fetch();

    // Step 2: Check if patient exists
    if (!$patient) {
        $identifier = $email ? 'email address' : 'mobile number';
        jsonResponse(['success' => false, 'type' => 'credentials', 'message' => "No account found with this $identifier."], 401);
    }

    // Step 3: Check if patient is registered
    if (!$patient['is_registered']) {
        jsonResponse(['success' => false, 'type' => 'credentials', 'message' => 'This account is not yet registered. Please sign up first.'], 401);
    }

    // Step 4: Check if password exists
    if (empty($patient['password'])) {
        jsonResponse(['success' => false, 'type' => 'credentials', 'message' => 'No password set for this account. Please sign up or contact admin.'], 401);
    }

    // Step 5: Verify password (bcrypt compare — input first, hash second)
    $passwordHash = $patient['password'];
    $plainPassword = $patient['plain_password'] ?? '';
    error_log("DEBUG LOGIN: mobile=$mobile, stored_hash=$passwordHash, plain_pass=$plainPassword, input_password_length=" . strlen($password));
    
    // Try normal password verify first
    $verifyResult = password_verify($password, $passwordHash);
    
    // If failed, try with leading space (legacy bug - passwords saved with leading space)
    if (!$verifyResult && !empty($plainPassword)) {
        // Try plain password match
        $verifyResult = ($password === $plainPassword);
        error_log("DEBUG LOGIN: tried plain password match, result=" . ($verifyResult ? 'true' : 'false'));
    }
    
    // If still failed, try with leading space on plain password
    if (!$verifyResult && !empty($plainPassword)) {
        $verifyResult = ($password === $plainPassword) || ($password === ltrim($plainPassword));
        error_log("DEBUG LOGIN: tried plain password with trim, result=" . ($verifyResult ? 'true' : 'false'));
    }
    
    if (!$verifyResult) {
        error_log("DEBUG LOGIN: password_verify FAILED for mobile=$mobile");
        // Return more debug info for troubleshooting
        jsonResponse([
            'success' => false, 
            'type' => 'credentials', 
            'message' => 'Incorrect password. Please try again.',
            'debug' => [
                'input_len' => strlen($password),
                'hash_prefix' => substr($passwordHash, 0, 20),
                'mobile_len' => strlen($mobile)
            ]
        ], 401);
    }

    // Step 6: Authentication successful — create session
    session_start();
    $_SESSION['patient'] = [
        'id'     => (int)$patient['id'],
        'name'   => $patient['full_name'],
        'mobile' => $patient['mobile'],
    ];

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'patient' => [
            'id'     => (int)$patient['id'],
            'name'   => $patient['full_name'],
            'mobile' => $patient['mobile'],
            'email'  => $patient['email'],
        ]
    ]);

} catch (PDOException $e) {
    error_log('Patient login DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'type' => 'server', 'message' => 'A server error occurred. Please try again later.'], 500);
}
