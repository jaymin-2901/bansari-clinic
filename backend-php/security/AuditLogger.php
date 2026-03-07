<?php
/**
 * ============================================================
 * MediConnect – Audit Logger
 * File: backend/security/AuditLogger.php
 * ============================================================
 *
 * Logs security-relevant events for forensic analysis.
 * Captures authentication attempts, access violations,
 * rate limit hits, and suspicious activity.
 */

class AuditLogger
{
    private static ?string $logDir = null;

    /**
     * Log a security event.
     *
     * @param string $event    Event type (e.g., 'login_success', 'auth_failure', 'rate_limit')
     * @param array  $context  Additional context (user_id, ip, endpoint, etc.)
     */
    public static function log(string $event, array $context = []): void
    {
        $logDir = self::getLogDir();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event'     => $event,
            'ip'        => RateLimiter::getClientIP(),
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri'       => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent'=> substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        ];

        $entry = array_merge($entry, $context);

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        $logFile = $logDir . '/security_audit.log';
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        // Rotate log if > 50MB
        if (file_exists($logFile) && filesize($logFile) > 50 * 1024 * 1024) {
            rename($logFile, $logDir . '/security_audit_' . date('Y-m-d_His') . '.log');
        }
    }

    /**
     * Log authentication success.
     */
    public static function authSuccess(int $userId, string $role): void
    {
        self::log('auth_success', [
            'user_id' => $userId,
            'role'    => $role,
        ]);
    }

    /**
     * Log authentication failure.
     */
    public static function authFailure(string $reason, ?string $identifier = null): void
    {
        self::log('auth_failure', [
            'reason'     => $reason,
            'identifier' => $identifier,
        ]);
    }

    /**
     * Log rate limit hit.
     */
    public static function rateLimitHit(string $identifier, string $endpoint): void
    {
        self::log('rate_limit_hit', [
            'identifier' => $identifier,
            'endpoint'   => $endpoint,
        ]);
    }

    /**
     * Log access denied (authorization failure).
     */
    public static function accessDenied(int $userId, string $role, string $requiredRole): void
    {
        self::log('access_denied', [
            'user_id'       => $userId,
            'user_role'     => $role,
            'required_role' => $requiredRole,
        ]);
    }

    /**
     * Log CORS violation.
     */
    public static function corsViolation(string $origin): void
    {
        self::log('cors_violation', [
            'origin' => $origin,
        ]);
    }

    private static function getLogDir(): string
    {
        if (self::$logDir === null) {
            self::$logDir = dirname(__DIR__) . '/logs';
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }
}
