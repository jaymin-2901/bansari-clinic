<?php
/**
 * Bansari Homeopathy – Forgot Password API
 */
require_once __DIR__ . '/../../security/bootstrap.php';

SecurityBootstrap::publicEndpoint('forgot_password');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$captchaToken = $input['captcha_token'] ?? '';

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (!SecurityBootstrap::verifyCaptcha($captchaToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Security check failed. Please solve CAPTCHA.']);
    exit;
}

try {
    $db = getClinicDB();
    
    // Check if user exists in patients table
    $stmt = $db->prepare("SELECT id FROM patients WHERE email = ? AND is_registered = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate Token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete any existing tokens for this email
        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // Store new token
        $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expiresAt]);

        // Logic to send email would go here
        // For now we just return success
        // In a real app: sendResetEmail($email, $token);
        
        // LOG FOR DEBUGGING (Temporary)
        error_log("[Reset Password] Token for $email: $token");
    }

    // Always return success for security (prevent email enumeration)
    echo json_encode([
        'success' => true, 
        'message' => 'If an account exists with this email, you will receive a reset link shortly.'
    ]);

} catch (Exception $e) {
    error_log('[Forgot Password] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
