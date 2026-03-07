-- ============================================================
-- MediConnect: Smart 24h Reminder System - Database Migration v2
-- File: backend/sql/002_smart_reminder_scheduled_at.sql
-- 
-- Adds reminder_scheduled_at column for smart booking logic.
-- Run this AFTER the initial schema setup.
-- Safe to re-run (idempotent).
-- ============================================================

-- ── Add reminder_scheduled_at ──
-- This is the COMPUTED time when the reminder SHOULD be sent.
-- Formula: appointment_datetime - 24 hours
-- If booking happens AFTER this time, the reminder is sent immediately
-- and this column still records what the ideal time was.
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_scheduled_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_scheduled_at DATETIME NULL AFTER reminder_24h_sent',
    'SELECT "Column reminder_scheduled_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── Add reminder_trigger_type ──
-- Records HOW the reminder was triggered:
--   'scheduled' = sent by cron at the scheduled time (early booking)
--   'immediate' = sent instantly at booking time (late booking, within 24h)
--   NULL        = not yet processed
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultations'
    AND COLUMN_NAME = 'reminder_trigger_type');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE consultations ADD COLUMN reminder_trigger_type ENUM("scheduled","immediate") NULL AFTER reminder_scheduled_at',
    'SELECT "Column reminder_trigger_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── Backfill reminder_scheduled_at for existing appointments ──
-- For any appointment that already has appointment_datetime set,
-- compute the ideal 24h-before reminder time.
UPDATE consultations
SET reminder_scheduled_at = DATE_SUB(appointment_datetime, INTERVAL 24 HOUR)
WHERE reminder_scheduled_at IS NULL
  AND appointment_datetime IS NOT NULL;

-- ── New index for the smart cron query ──
-- The cron now simply looks for: reminder_scheduled_at <= NOW() AND reminder_24h_sent = 0
-- This is far simpler and more robust than the old time-window approach.
CREATE INDEX IF NOT EXISTS idx_smart_reminder_lookup
ON consultations (reminder_scheduled_at, reminder_24h_sent, status);

-- ============================================================
-- VERIFICATION QUERIES (uncomment to run manually)
-- ============================================================
-- SELECT id, appointment_datetime, reminder_scheduled_at, reminder_24h_sent, 
--        reminder_trigger_type, status
-- FROM consultations 
-- ORDER BY appointment_datetime DESC LIMIT 20;
-- ============================================================
