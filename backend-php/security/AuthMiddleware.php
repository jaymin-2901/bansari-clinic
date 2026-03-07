<?php
/**
 * ============================================================
 * MediConnect – Authentication & RBAC Middleware
 * File: backend/security/AuthMiddleware.php
 * ============================================================
 *
 * Provides authentication and role-based access control guards
 * that integrate with both existing session-based auth and new JWT auth.
 *
 * Usage:
 *   require_once __DIR__ . '/../security/bootstrap.php';
 *
 *   // Public endpoint (no auth required, rate limiting only)
 *   SecurityBootstrap::publicEndpoint('book');
 *
 *   // Authenticated endpoint (any valid token)
 *   $user = SecurityBootstrap::authenticatedEndpoint('admin');
 *
 *   // Role-restricted endpoint
 *   $user = SecurityBootstrap::roleEndpoint(['admin', 'staff'], 'admin');
 *
 * Attack Scenarios Prevented:
 *   1. Unauthenticated access: Patient data API without token → 401
 *   2. Privilege escalation: Patient tries admin endpoint → 403
 *   3. Session fixation: JWT is stateless, no session to fixate
 *   4. Token tampering: Signature validation catches modifications
 *   5. Stale tokens: Expired tokens are rejected
 */

class AuthMiddleware
{
    private JWTHandler $jwt;
    private ?PDO $db;

    public function __construct(JWTHandler $jwt, ?PDO $db = null)
    {
        $this->jwt = $jwt;
        $this->db = $db;
    }

    /**
     * Authenticate a request via JWT Bearer token.
     * Returns the decoded token payload if valid.
     *
     * @param bool $required If true, sends 401 and exits on failure
     * @return array|null Decoded token payload, or null if not authenticated
     */
    public function authenticate(bool $required = true): ?array
    {
        $token = JWTHandler::extractBearerToken();

        if ($token === null) {
            // Fallback: check for existing session-based auth
            $sessionUser = $this->checkSessionAuth();
            if ($sessionUser !== null) {
                return $sessionUser;
            }

            if ($required) {
                $this->sendUnauthorized('Authentication required. Provide a Bearer token.');
            }
            return null;
        }

        try {
            $payload = $this->jwt->validateAccessToken($token);

            // Optional: Check if user still exists and is active
            if ($this->db !== null) {
                $this->verifyUserActive($payload);
            }

            return $payload;
        } catch (\RuntimeException $e) {
            if ($required) {
                $this->sendUnauthorized('Invalid or expired token: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Require a specific role (or set of roles).
     *
     * @param array  $allowedRoles Allowed roles (e.g., ['admin', 'staff'])
     * @param bool   $required     If true, sends 401/403 and exits on failure
     * @return array|null Decoded token payload
     */
    public function requireRole(array $allowedRoles, bool $required = true): ?array
    {
        $payload = $this->authenticate($required);
        if ($payload === null) {
            return null;
        }

        $userRole = $payload['role'] ?? '';
        if (!in_array($userRole, $allowedRoles, true)) {
            if ($required) {
                $this->sendForbidden(
                    'Access denied. Required role: ' . implode(' or ', $allowedRoles) . '.'
                );
            }
            return null;
        }

        return $payload;
    }

    /**
     * Require admin role.
     * @return array Decoded token payload
     */
    public function requireAdmin(): array
    {
        return $this->requireRole(['admin', 'super_admin']);
    }

    /**
     * Require staff or admin role.
     * @return array Decoded token payload
     */
    public function requireStaff(): array
    {
        return $this->requireRole(['admin', 'super_admin', 'staff']);
    }

    /**
     * Require patient role (or self-access).
     * @return array Decoded token payload
     */
    public function requirePatient(): array
    {
        return $this->requireRole(['patient', 'admin', 'super_admin', 'staff']);
    }

    /**
     * Ensure a user can only access their own resources.
     * Admins and staff can access any resource.
     *
     * @param int $resourceOwnerId The user ID who owns the resource
     * @return array Decoded token payload
     */
    public function requireSelfOrStaff(int $resourceOwnerId): array
    {
        $payload = $this->authenticate(true);

        $userRole = $payload['role'] ?? '';
        $userId = (int) ($payload['sub'] ?? 0);

        // Admins and staff can access any resource
        if (in_array($userRole, ['admin', 'super_admin', 'staff'], true)) {
            return $payload;
        }

        // Patients can only access their own resources
        if ($userId !== $resourceOwnerId) {
            $this->sendForbidden('You can only access your own resources.');
        }

        return $payload;
    }

    /**
     * Check for existing PHP session-based authentication.
     * Provides backward compatibility with existing admin panel.
     */
    private function checkSessionAuth(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Don't auto-start session for API requests
            return null;
        }

        // Check admin session
        if (!empty($_SESSION['admin']['id'])) {
            return [
                'sub'  => (int) $_SESSION['admin']['id'],
                'role' => $_SESSION['admin']['role'] ?? 'admin',
                'name' => $_SESSION['admin']['name'] ?? '',
                'type' => 'session',
            ];
        }

        // Check clinic admin session
        if (!empty($_SESSION['clinic_admin']['id'])) {
            return [
                'sub'  => (int) $_SESSION['clinic_admin']['id'],
                'role' => 'admin',
                'name' => $_SESSION['clinic_admin']['name'] ?? '',
                'type' => 'session',
            ];
        }

        // Check patient session
        if (!empty($_SESSION['patient']['id'])) {
            return [
                'sub'  => (int) $_SESSION['patient']['id'],
                'role' => 'patient',
                'name' => $_SESSION['patient']['name'] ?? '',
                'type' => 'session',
            ];
        }

        return null;
    }

    /**
     * Verify the user referenced in the token is still active.
     */
    private function verifyUserActive(array $payload): void
    {
        $userId = (int) ($payload['sub'] ?? 0);
        $role = $payload['role'] ?? '';

        if ($userId <= 0) {
            return;
        }

        // Check appropriate table based on role
        try {
            if (in_array($role, ['admin', 'super_admin'], true)) {
                $stmt = $this->db->prepare("
                    SELECT is_active FROM admins WHERE id = :id LIMIT 1
                ");
            } else {
                // For users table (mediconnect DB) — check status
                $stmt = $this->db->prepare("
                    SELECT COALESCE(status, 'active') as is_active FROM users WHERE id = :id LIMIT 1
                ");
            }

            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('User account not found');
            }

            $active = $row['is_active'] ?? 'active';
            if ($active === '0' || $active === 'blocked' || $active === 0 || $active === false) {
                throw new \RuntimeException('User account is deactivated');
            }
        } catch (\PDOException $e) {
            // Log but don't block — DB might be the wrong one for this user type
            error_log('[AuthMiddleware] User verification DB error: ' . $e->getMessage());
        }
    }

    /**
     * Send 401 Unauthorized response and exit.
     */
    private function sendUnauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: Bearer realm="mediconnect"');
        echo json_encode([
            'success' => false,
            'error'   => $message,
            'code'    => 'UNAUTHORIZED',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send 403 Forbidden response and exit.
     */
    private function sendForbidden(string $message): void
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => $message,
            'code'    => 'FORBIDDEN',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
