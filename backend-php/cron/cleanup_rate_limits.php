<?php
/**
 * ============================================================
 * MediConnect – Rate Limit Cleanup Cron Job
 * File: backend/cron/cleanup_rate_limits.php
 * ============================================================
 *
 * Removes expired rate limit records and revoked JWT refresh tokens.
 * Run via cron every hour:
 *   0 * * * * php /path/to/backend/cron/cleanup_rate_limits.php
 */

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/RateLimiter.php';

try {
    $db = getDBConnection();
    $limiter = new RateLimiter($db);
    $deleted = $limiter->cleanup();

    // Also clean expired refresh tokens
    $stmt = $db->prepare("DELETE FROM jwt_refresh_tokens WHERE expires_at < NOW()");
    $stmt->execute();
    $tokensDeleted = $stmt->rowCount();

    // Clean old audit log entries (keep 90 days)
    $stmt = $db->prepare("DELETE FROM security_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $auditDeleted = $stmt->rowCount();

    $msg = sprintf(
        "[%s] Cleanup: %d rate limits, %d tokens, %d audit logs removed\n",
        date('Y-m-d H:i:s'),
        $deleted,
        $tokensDeleted,
        $auditDeleted
    );

    echo $msg;
    file_put_contents(
        dirname(__DIR__) . '/logs/cleanup.log',
        $msg,
        FILE_APPEND | LOCK_EX
    );
} catch (\Exception $e) {
    error_log('[Cleanup Cron] Error: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
