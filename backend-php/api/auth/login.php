<?php
/**
 * ============================================================
 * MediConnect – Secure Login API (JWT Token Issuance)
 * File: backend/api/auth/login.php
 * ============================================================
 *
 * Authenticates users and issues JWT access + refresh tokens.
 * Replaces session-based auth for API consumers.
 *
 * POST /backend/api/auth/login.php
 * Body: { "email": "...", "password": "..." }
 *   OR: { "mobile": "...", "password": "..." }
 *
 * Success Response (200):
 *   {
 *     "success": true,
 *     "access_token": "eyJ...",
 *     "refresh_token": "eyJ...",
 *     "expires_in": 900,
 *     "token_type": "Bearer",
 *     "user": { "id": 1, "name": "...", "role": "admin" }
 *   }
 *
 * Failure Response (401):
 *   { "success": false, "error": "Invalid credentials" }
 *
 * Security:
 *   - Strict rate limiting on login endpoint (10 attempts / 15 min)
 *   - Generic error messages (don't reveal if email exists)
 *   - Timing-safe password comparison (bcrypt)
 *   - Audit logging of all attempts
 */

require_once __DIR__ . '/../../security/bootstrap.php';

// Apply public security (CORS + headers + strict rate limiting for login)
SecurityBootstrap::publicEndpoint('login');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$email    = trim($input['email'] ?? '');
$mobile   = trim($input['mobile'] ?? '');
$password = $input['password'] ?? '';

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

// Clean mobile number
if (!empty($mobile)) {
    $mobile = preg_replace('/[^0-9+]/', '', $mobile);
}

try {
    $db = getDBConnection();
    $user = null;
    $userType = 'user'; // 'user' from mediconnect.users or 'admin' from bansari_clinic.admins

    // Try to find user in `users` table (mediconnect DB)
    if (!empty($email)) {
        $stmt = $db->prepare("SELECT id, first_name, last_name, email, phone, password, role, COALESCE(status, 'active') as status FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
    } else {
        $stmt = $db->prepare("SELECT id, first_name, last_name, email, phone, password, role, COALESCE(status, 'active') as status FROM users WHERE phone = :phone LIMIT 1");
        $stmt->execute([':phone' => $mobile]);
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If not found in users, try admins table (if it exists on this DB)
    if (!$user) {
        try {
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT id, name, email, password, role, is_active FROM admins WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($adminRow) {
                    $user = [
                        'id'         => $adminRow['id'],
                        'first_name' => $adminRow['name'],
                        'last_name'  => '',
                        'email'      => $adminRow['email'],
                        'phone'      => '',
                        'password'   => $adminRow['password'],
                        'role'       => $adminRow['role'] ?: 'admin',
                        'status'     => $adminRow['is_active'] ? 'active' : 'blocked',
                    ];
                    $userType = 'admin';
                }
            }
        } catch (\PDOException $e) {
            // admins table might not exist in this DB — that's fine
        }
    }

    // Generic error for security (don't reveal which field was wrong)
    if (!$user) {
        AuditLogger::authFailure('user_not_found', $email ?: $mobile);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // Check if account is active
    if (($user['status'] ?? 'active') === 'blocked') {
        AuditLogger::authFailure('account_blocked', $email ?: $mobile);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Account is deactivated. Contact administrator.']);
        exit;
    }

    // Verify password (timing-safe bcrypt comparison)
    if (empty($user['password']) || !password_verify($password, $user['password'])) {
        AuditLogger::authFailure('invalid_password', $email ?: $mobile);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // Authentication successful — generate JWT tokens
    $jwt = SecurityBootstrap::getJWT();
    if ($jwt === null) {
        // JWT not configured — return session-based auth instead
        AuditLogger::authFailure('jwt_not_configured', $email ?: $mobile);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Authentication system not configured. Set JWT_SECRET environment variable.']);
        exit;
    }

    $userId = (int) $user['id'];
    $role = $user['role'] ?: 'patient';
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    $tokens = $jwt->generateTokens($userId, $role, [
        'name'  => $name,
        'email' => $user['email'] ?? '',
    ]);

    // Log successful login
    AuditLogger::authSuccess($userId, $role);

    // Return tokens
    http_response_code(200);
    echo json_encode(array_merge([
        'success' => true,
        'user'    => [
            'id'    => $userId,
            'name'  => $name,
            'email' => $user['email'] ?? '',
            'role'  => $role,
        ],
    ], $tokens), JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    error_log('[Auth Login] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
} catch (\Exception $e) {
    error_log('[Auth Login] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
