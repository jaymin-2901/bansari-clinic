-- ============================================================
-- MediConnect – Security: Rate Limiting & Token Blacklist Tables
-- File: backend/sql/020_security_rate_limits.sql
-- ============================================================
-- 
-- Run this migration to create the tables required for:
--   1. API rate limiting (sliding window, per-IP or per-user)
--   2. JWT refresh token tracking (optional, for token revocation)
--
-- Compatible with both mediconnect and bansari_clinic databases.
--
-- Usage:
--   mysql -u root -p mediconnect < backend/sql/020_security_rate_limits.sql
--   mysql -u root -p bansari_clinic < backend/sql/020_security_rate_limits.sql
-- ============================================================

-- ─── 1. API Rate Limits ───
-- Stores sliding-window counters for rate limiting.
-- The `identifier` is either "ip:<address>" or "user:<id>" with optional ":endpoint" suffix.
-- Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic, lock-free counter increments.
CREATE TABLE IF NOT EXISTS api_rate_limits (
    identifier    VARCHAR(255) NOT NULL PRIMARY KEY COMMENT 'ip:x.x.x.x:endpoint or user:id:endpoint',
    request_count INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Number of requests in current window',
    window_start  INT UNSIGNED NOT NULL COMMENT 'Unix timestamp of window start',
    window_reset  INT UNSIGNED NOT NULL COMMENT 'Unix timestamp when window resets',
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_window_reset (window_reset) COMMENT 'For efficient cleanup of expired records'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. JWT Refresh Tokens ───
-- Tracks issued refresh tokens for revocation support.
-- When a user logs out or changes password, their refresh tokens are revoked.
CREATE TABLE IF NOT EXISTS jwt_refresh_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL COMMENT 'User who owns this token',
    token_jti    VARCHAR(64) NOT NULL UNIQUE COMMENT 'JWT ID (jti) claim for lookup',
    user_role    VARCHAR(30) NOT NULL DEFAULT 'patient' COMMENT 'Role at time of issuance',
    expires_at   DATETIME NOT NULL COMMENT 'Token expiration time',
    revoked      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = token has been revoked',
    revoked_at   DATETIME NULL COMMENT 'When the token was revoked',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address   VARCHAR(45) NULL COMMENT 'IP at time of login',
    user_agent   VARCHAR(255) NULL COMMENT 'Browser/client at time of login',

    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_revoked (revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Security Audit Log (optional, for DB-backed audit trail) ───
-- Supplements file-based logging with queryable database records.
CREATE TABLE IF NOT EXISTS security_audit_log (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type  VARCHAR(50) NOT NULL COMMENT 'auth_success, auth_failure, rate_limit, cors_violation, etc.',
    user_id     INT NULL COMMENT 'User involved (if known)',
    ip_address  VARCHAR(45) NOT NULL,
    endpoint    VARCHAR(255) NULL COMMENT 'API endpoint accessed',
    details     JSON NULL COMMENT 'Additional event context',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Cleanup Event (auto-purge expired rate limit records) ───
-- Runs every hour to prevent table bloat.
-- If your MySQL user doesn't have EVENT privileges, run cleanup via cron instead.
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_expired_rate_limits
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM api_rate_limits WHERE window_reset < UNIX_TIMESTAMP();
    DELETE FROM jwt_refresh_tokens WHERE expires_at < NOW() AND revoked = 1;
END //
DELIMITER ;

-- Enable event scheduler (requires SUPER or EVENT privilege)
-- SET GLOBAL event_scheduler = ON;
