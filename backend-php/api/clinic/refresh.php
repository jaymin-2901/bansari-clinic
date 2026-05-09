<?php
/**
 * Bansari Homeopathy – Token Refresh API
 */
require_once __DIR__ . '/../../security/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$refreshToken = $input['refresh_token'] ?? '';

if (empty($refreshToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Refresh token is required']);
    exit;
}

$jwt = SecurityBootstrap::getJWT();
if ($jwt === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Authentication system not configured']);
    exit;
}

try {
    // Validate refresh token
    $payload = $jwt->validateRefreshToken($refreshToken);
    
    // Generate new token pair
    $userId = (int) $payload['sub'];
    $role = $payload['role'] ?? 'patient';
    
    $tokens = $jwt->generateTokens($userId, $role, [
        'name'  => $payload['name'] ?? '',
        'email' => $payload['email'] ?? '',
    ]);

    echo json_encode([
        'success'      => true,
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'],
        'expires_in'   => $tokens['expires_in']
    ]);

} catch (\Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired refresh token']);
}
