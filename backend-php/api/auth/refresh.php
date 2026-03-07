<?php
/**
 * ============================================================
 * MediConnect – Token Refresh API
 * File: backend/api/auth/refresh.php
 * ============================================================
 *
 * Exchanges a valid refresh token for a new access + refresh token pair.
 *
 * POST /backend/api/auth/refresh.php
 * Body: { "refresh_token": "eyJ..." }
 *
 * Success Response (200):
 *   {
 *     "success": true,
 *     "access_token": "eyJ...",
 *     "refresh_token": "eyJ...",
 *     "expires_in": 900,
 *     "token_type": "Bearer"
 *   }
 */

require_once __DIR__ . '/../../security/bootstrap.php';

// Rate limit refresh attempts
SecurityBootstrap::publicEndpoint('login');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$refreshToken = trim($input['refresh_token'] ?? '');

if (empty($refreshToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'refresh_token is required']);
    exit;
}

try {
    $jwt = SecurityBootstrap::getJWT();
    if ($jwt === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'JWT not configured']);
        exit;
    }

    $tokens = $jwt->refreshAccessToken($refreshToken);

    http_response_code(200);
    echo json_encode(array_merge(['success' => true], $tokens), JSON_UNESCAPED_UNICODE);

} catch (\RuntimeException $e) {
    AuditLogger::authFailure('refresh_failed', $e->getMessage());
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired refresh token']);
} catch (\Exception $e) {
    error_log('[Auth Refresh] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
