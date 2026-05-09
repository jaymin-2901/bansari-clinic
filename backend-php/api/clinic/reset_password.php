<?php
/**
 * Bansari Homeopathy – Reset Password API
 */
require_once __DIR__ . '/../../security/bootstrap.php';

SecurityBootstrap::publicEndpoint('reset_password');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$password = $input['password'] ?? '';

if (empty($token) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token and password are required']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long']);
    exit;
}

try {
    $db = getClinicDB();

    // Verify token
    $stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();

    if (!$resetData) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
        exit;
    }

    $email = $resetData['email'];
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Update password in patients table
    $stmt = $db->prepare("UPDATE patients SET password = ? WHERE email = ? AND is_registered = 1");
    $stmt->execute([$hashedPassword, $email]);

    // Delete the token
    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);

} catch (Exception $e) {
    error_log('[Reset Password] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
