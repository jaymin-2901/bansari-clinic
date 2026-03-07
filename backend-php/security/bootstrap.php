<?php
/**
 * ============================================================
 * MediConnect – Security Bootstrap
 * File: backend/security/bootstrap.php
 * ============================================================
 *
 * Single entry point for all security middleware.
 * Include this file at the top of any API endpoint to activate:
 *   - CORS handling (strict origin whitelist)
 *   - Security headers (XSS, clickjacking, MIME sniffing protection)
 *   - Rate limiting (IP-based or user-based)
 *   - Authentication (JWT + session fallback)
 *   - RBAC (role-based access control)
 *   - Audit logging
 *
 * Usage Examples:
 *   ──────────────────────────────────────────────────────
 *   // PUBLIC endpoint (rate limiting + CORS only):
 *   require_once __DIR__ . '/../security/bootstrap.php';
 *   SecurityBootstrap::publicEndpoint('book');
 *   ──────────────────────────────────────────────────────
 *   // AUTHENTICATED endpoint (any valid user):
 *   require_once __DIR__ . '/../security/bootstrap.php';
 *   $user = SecurityBootstrap::authenticatedEndpoint('appointments');
 *   // $user = ['sub' => 1, 'role' => 'patient', ...]
 *   ──────────────────────────────────────────────────────
 *   // ADMIN-ONLY endpoint:
 *   require_once __DIR__ . '/../security/bootstrap.php';
 *   $admin = SecurityBootstrap::adminEndpoint('admin_analytics');
 *   ──────────────────────────────────────────────────────
 *   // STAFF-OR-ADMIN endpoint:
 *   require_once __DIR__ . '/../security/bootstrap.php';
 *   $user = SecurityBootstrap::staffEndpoint('followup');
 *   ──────────────────────────────────────────────────────
 *   // ROLE-SPECIFIC endpoint:
 *   require_once __DIR__ . '/../security/bootstrap.php';
 *   $user = SecurityBootstrap::roleEndpoint(['admin', 'staff'], 'import');
 *   ──────────────────────────────────────────────────────
 */

// Load dependencies
require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/database.php';
// Load clinic config for FRONTEND_URL (CORS)
require_once __DIR__ . '/../config/clinic_config.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/JWTHandler.php';
require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/CORSHandler.php';
require_once __DIR__ . '/SecurityHeaders.php';
require_once __DIR__ . '/AuditLogger.php';

class SecurityBootstrap
{
    private static ?CORSHandler $cors = null;
    private static ?RateLimiter $rateLimiter = null;
    private static ?JWTHandler $jwt = null;
    private static ?AuthMiddleware $auth = null;
    private static bool $initialized = false;

    /**
     * Initialize all security components.
     * Called automatically on first use.
     */
    private static function init(): void
    {
        if (self::$initialized) return;

        // 1. CORS — must be first (handles OPTIONS preflight)
        self::$cors = new CORSHandler();
        self::$cors->handle();

        // 2. Security headers
        SecurityHeaders::apply();

        // 3. Rate limiter (needs DB)
        try {
            $db = getDBConnection();
            self::$rateLimiter = new RateLimiter($db);
        } catch (\Exception $e) {
            error_log('[Security] Rate limiter DB init failed: ' . $e->getMessage());
            // Rate limiting is degraded but other protections still work
        }

        // 4. JWT handler
        try {
            self::$jwt = new JWTHandler();
            self::$auth = new AuthMiddleware(self::$jwt, getDBConnection());
        } catch (\RuntimeException $e) {
            // JWT_SECRET not configured — JWT auth unavailable
            // Session-based auth still works via AuthMiddleware fallback
            error_log('[Security] JWT not configured: ' . $e->getMessage());
        }

        self::$initialized = true;
    }

    /**
     * Public endpoint: CORS + Security Headers + Rate Limiting only.
     * No authentication required.
     *
     * @param string $endpointName Endpoint identifier for rate limit tiers
     */
    public static function publicEndpoint(string $endpointName = 'default'): void
    {
        self::init();

        // Apply rate limiting by IP
        if (self::$rateLimiter !== null) {
            self::$rateLimiter->check(null, $endpointName);
        }
    }

    /**
     * Authenticated endpoint: All protections + valid token required.
     *
     * @param string $endpointName Endpoint identifier for rate limit tiers
     * @return array Decoded user payload ['sub', 'role', ...]
     */
    public static function authenticatedEndpoint(string $endpointName = 'default'): array
    {
        self::init();

        // Rate limit before auth check (prevent auth brute-force)
        if (self::$rateLimiter !== null) {
            self::$rateLimiter->check(null, $endpointName);
        }

        // Require authentication
        if (self::$auth === null) {
            // JWT not configured; fall back to session check
            self::fallbackSessionAuth();
            return $_SESSION['_security_user'] ?? [];
        }

        $user = self::$auth->authenticate(true);

        // Switch to user-based rate limiting now that we know who they are
        if (self::$rateLimiter !== null && isset($user['sub'])) {
            // No need to re-check, but record for user-based tracking
        }

        return $user;
    }

    /**
     * Admin-only endpoint.
     *
     * @param string $endpointName Endpoint identifier
     * @return array Admin user payload
     */
    public static function adminEndpoint(string $endpointName = 'admin'): array
    {
        self::init();

        if (self::$rateLimiter !== null) {
            self::$rateLimiter->check(null, $endpointName);
        }

        if (self::$auth === null) {
            self::fallbackSessionAuth(['admin', 'super_admin']);
            return $_SESSION['_security_user'] ?? [];
        }

        return self::$auth->requireAdmin();
    }

    /**
     * Staff-or-admin endpoint.
     *
     * @param string $endpointName Endpoint identifier
     * @return array User payload
     */
    public static function staffEndpoint(string $endpointName = 'admin'): array
    {
        self::init();

        if (self::$rateLimiter !== null) {
            self::$rateLimiter->check(null, $endpointName);
        }

        if (self::$auth === null) {
            self::fallbackSessionAuth(['admin', 'super_admin', 'staff']);
            return $_SESSION['_security_user'] ?? [];
        }

        return self::$auth->requireStaff();
    }

    /**
     * Role-specific endpoint.
     *
     * @param array  $roles        Allowed roles
     * @param string $endpointName Endpoint identifier
     * @return array User payload
     */
    public static function roleEndpoint(array $roles, string $endpointName = 'default'): array
    {
        self::init();

        if (self::$rateLimiter !== null) {
            self::$rateLimiter->check(null, $endpointName);
        }

        if (self::$auth === null) {
            self::fallbackSessionAuth($roles);
            return $_SESSION['_security_user'] ?? [];
        }

        return self::$auth->requireRole($roles);
    }

    /**
     * Apply CORS and security headers only (no rate limiting or auth).
     * Useful for static/health endpoints.
     */
    public static function headersOnly(): void
    {
        if (self::$cors === null) {
            self::$cors = new CORSHandler();
            self::$cors->handle();
        }
        SecurityHeaders::apply();
    }

    /**
     * Get JWT handler instance (for login endpoints that need to generate tokens).
     */
    public static function getJWT(): ?JWTHandler
    {
        if (self::$jwt === null) {
            try {
                self::$jwt = new JWTHandler();
            } catch (\RuntimeException $e) {
                return null;
            }
        }
        return self::$jwt;
    }

    /**
     * Get auth middleware instance.
     */
    public static function getAuth(): ?AuthMiddleware
    {
        return self::$auth;
    }

    /**
     * Fallback session-based auth when JWT is not configured.
     * Maintains backward compatibility with existing admin/clinic sessions.
     */
    private static function fallbackSessionAuth(?array $requiredRoles = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = null;

        // Check admin session
        if (!empty($_SESSION['admin']['id'])) {
            $user = [
                'sub'  => (int) $_SESSION['admin']['id'],
                'role' => $_SESSION['admin']['role'] ?? 'admin',
                'name' => $_SESSION['admin']['name'] ?? '',
                'type' => 'session',
            ];
        }
        // Check clinic admin session
        elseif (!empty($_SESSION['clinic_admin']['id'])) {
            $user = [
                'sub'  => (int) $_SESSION['clinic_admin']['id'],
                'role' => 'admin',
                'name' => $_SESSION['clinic_admin']['name'] ?? '',
                'type' => 'session',
            ];
        }
        // Check patient session
        elseif (!empty($_SESSION['patient']['id'])) {
            $user = [
                'sub'  => (int) $_SESSION['patient']['id'],
                'role' => 'patient',
                'name' => $_SESSION['patient']['name'] ?? '',
                'type' => 'session',
            ];
        }

        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Authentication required.',
                'code'    => 'UNAUTHORIZED',
            ]);
            exit;
        }

        if ($requiredRoles !== null && !in_array($user['role'], $requiredRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Access denied. Insufficient privileges.',
                'code'    => 'FORBIDDEN',
            ]);
            exit;
        }

        $_SESSION['_security_user'] = $user;
    }
}
