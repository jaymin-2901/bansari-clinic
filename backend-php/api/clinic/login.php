<?php
/**
 * Bansari Homeopathy – Secure Patient Login API (JWT)
 */
require_once __DIR__ . '/../../security/bootstrap.php';

// Apply public security (CORS + headers + strict rate limiting for login)
SecurityBootstrap::publicEndpoint('login');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$email    = trim($input['email'] ?? '');
$mobile   = trim($input['mobile'] ?? '');
$password = $input['password'] ?? '';
$captchaToken = $input['captcha_token'] ?? '';

// Validate input
if (empty($email) && empty($mobile)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email or mobile is required']);
    exit;
}
if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

// ─── reCAPTCHA Verification ───
if (!SecurityBootstrap::verifyCaptcha($captchaToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Security check failed. Please solve CAPTCHA.']);
    exit;
}

// Clean mobile number
if (!empty($mobile)) {
    $mobile = preg_replace('/[^0-9+]/', '', $mobile);
}

try {
    $db = getClinicDB();
    
    // Find patient by email or mobile
    if (!empty($email)) {
        $stmt = $db->prepare("SELECT id, full_name, email, mobile, password, is_registered FROM patients WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
    } else {
        $stmt = $db->prepare("SELECT id, full_name, email, mobile, password, is_registered FROM patients WHERE mobile = ? LIMIT 1");
        $stmt->execute([$mobile]);
    }
    
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient || !$patient['is_registered']) {
        AuditLogger::authFailure('patient_not_found', $email ?: $mobile);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials or account not registered']);
        exit;
    }

    // Verify password
    if (empty($patient['password']) || !password_verify($password, $patient['password'])) {
        AuditLogger::authFailure('invalid_password', $email ?: $mobile);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // Authentication successful — generate JWT tokens
    $jwt = SecurityBootstrap::getJWT();
    if ($jwt === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Authentication system not configured']);
        exit;
    }

    $userId = (int) $patient['id'];
    $role = 'patient';
    $name = $patient['full_name'];

    $tokens = $jwt->generateTokens($userId, $role, [
        'name'  => $name,
        'email' => $patient['email'],
        'mobile' => $patient['mobile']
    ]);

    // Log success
    AuditLogger::authSuccess($userId, $role);

    echo json_encode(array_merge([
        'success' => true,
        'user' => [
            'id'    => $userId,
            'name'  => $name,
            'email' => $patient['email'],
            'mobile' => $patient['mobile'],
            'role'  => $role
        ]
    ], $tokens));

} catch (Exception $e) {
    error_log('[Patient Login] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
