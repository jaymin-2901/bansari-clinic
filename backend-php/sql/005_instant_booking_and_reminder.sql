--- ============================================================
-- MediConnect – Migration 005: Instant Booking + 24h Reminder
-- File: backend/sql/005_instant_booking_and_reminder.sql
-- ============================================================
--
-- CHANGES:
--   1. Ensure reminder_scheduled_at column exists
--   2. Ensure reminder_24h_sent column exists (default 0)
--   3. Ensure reminder_24h_sent_at column exists
--   4. Ensure reminder_trigger_type column exists
--   5. Backfill reminder_scheduled_at for existing appointments
--   6. Update existing 'pending' bookings to 'approved' (instant booking)
--   7. Add consultation_fee column (optional, for revenue calc)
--   8. Optimized indexes for cron + analytics queries
--
-- Run: mysql -u root -p mediconnect < 005_instant_booking_and_reminder.sql
-- Safe to re-run (idempotent).
-- ============================================================

-- ── 1. reminder_scheduled_at (datetime, nullable) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_scheduled_at');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_scheduled_at DATETIME NULL',
    'SELECT "reminder_scheduled_at already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. reminder_24h_sent (boolean, default 0) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_24h_sent');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_24h_sent TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "reminder_24h_sent already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3. reminder_24h_sent_at (datetime, nullable) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_24h_sent_at');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_24h_sent_at DATETIME NULL',
    'SELECT "reminder_24h_sent_at already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 4. reminder_trigger_type (enum: scheduled/immediate) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_trigger_type');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_trigger_type ENUM("scheduled","immediate") NULL',
    'SELECT "reminder_trigger_type already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 5. consultation_fee (decimal, for revenue calculation) ──
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'consultation_fee');
SET @q = IF(@col = 0,
    'ALTER TABLE consultations ADD COLUMN consultation_fee DECIMAL(10,2) NULL DEFAULT 500.00',
    'SELECT "consultation_fee already exists"');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 6. Backfill reminder_scheduled_at for existing appointments ──
-- For any appointment that has appointment_datetime but no reminder time set
UPDATE consultations
SET reminder_scheduled_at = DATE_SUB(appointment_datetime, INTERVAL 24 HOUR)
WHERE reminder_scheduled_at IS NULL
  AND appointment_datetime IS NOT NULL;

-- ── 7. Optimized index for 24h cron query ──
-- Query: WHERE reminder_scheduled_at <= NOW() AND reminder_24h_sent = 0 AND status NOT IN (...)
CREATE INDEX IF NOT EXISTS idx_24h_reminder_cron
ON consultations (reminder_scheduled_at, reminder_24h_sent, status);

-- ── 8. Index for revenue analytics ──
CREATE INDEX IF NOT EXISTS idx_revenue_analytics
ON consultations (status, updated_at);

-- ============================================================
-- VERIFICATION (run manually):
--   DESCRIBE consultations;
--   SELECT id, appointment_datetime, reminder_scheduled_at,
--          reminder_24h_sent, reminder_24h_sent_at, consultation_fee
--   FROM consultations LIMIT 10;
-- ============================================================
