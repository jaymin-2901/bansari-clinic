-- ============================================================
-- MediConnect – STEP 1: Smart 24h Reminder — Database Changes
-- File: backend/sql/003_smart_24h_reminder_fields.sql
-- ============================================================
-- 
-- Safe ALTER TABLE queries. Idempotent — can re-run safely.
-- Adds the three fields required for smart 24h scheduling:
--
--   reminder_24h_sent       TINYINT(1) DEFAULT 0   — boolean flag
--   reminder_scheduled_at   DATETIME   NULL         — when to send reminder
--   reminder_24h_sent_at    DATETIME   NULL         — when reminder was sent
--
-- Also ensures reminder_trigger_type exists (tracks 'scheduled' vs 'immediate').
-- ============================================================

-- ── 1. reminder_24h_sent (boolean, default 0) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_24h_sent');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_24h_sent TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "reminder_24h_sent already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. reminder_scheduled_at (datetime, nullable) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_scheduled_at');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_scheduled_at DATETIME NULL AFTER reminder_24h_sent',
    'SELECT "reminder_scheduled_at already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3. reminder_24h_sent_at (datetime, nullable) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_24h_sent_at');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_24h_sent_at DATETIME NULL AFTER reminder_scheduled_at',
    'SELECT "reminder_24h_sent_at already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 4. reminder_trigger_type (enum: scheduled/immediate) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_trigger_type');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_trigger_type ENUM("scheduled","immediate") NULL AFTER reminder_24h_sent_at',
    'SELECT "reminder_trigger_type already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 5. Backfill: Compute reminder_scheduled_at for existing rows ──
-- For any appointment that already has appointment_datetime but no scheduling info,
-- set reminder_scheduled_at = appointment_datetime − 24 hours.
UPDATE consultations
SET reminder_scheduled_at = DATE_SUB(appointment_datetime, INTERVAL 24 HOUR)
WHERE reminder_scheduled_at IS NULL
  AND appointment_datetime IS NOT NULL;

-- ── 6. Index for cron performance ──
-- The cron query: WHERE reminder_scheduled_at <= NOW() AND reminder_24h_sent = 0
CREATE INDEX IF NOT EXISTS idx_reminder_smart_lookup
ON consultations (reminder_scheduled_at, reminder_24h_sent, status);

-- ============================================================
-- VERIFICATION (run manually to check):
--   DESCRIBE consultations;
--   SELECT id, appointment_datetime, reminder_scheduled_at,
--          reminder_24h_sent, reminder_24h_sent_at, reminder_trigger_type
--   FROM consultations LIMIT 10;
-- ============================================================
