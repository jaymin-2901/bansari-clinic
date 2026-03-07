-- ============================================================
-- MediConnect – Migration 004: Simplified System
-- ============================================================
--
-- CHANGES:
--   1. Add booking_whatsapp_sent      — WhatsApp sent at booking time
--   2. Add booking_whatsapp_sent_at   — When it was sent
--   3. Add followup_done              — Admin follow-up tracking
--   4. Add followup_done_at           — When follow-up was done
--   5. Add followup_done_by           — Which admin did it
--
-- Run: mysql -u root -p mediconnect < 004_simplified_system.sql
-- ============================================================

-- ── 1. booking_whatsapp_sent (boolean, default 0) ──
SET @dbname = DATABASE();
SET @tablename = 'consultations';
SET @columnname = 'booking_whatsapp_sent';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT "booking_whatsapp_sent already exists"',
    'ALTER TABLE consultations ADD COLUMN booking_whatsapp_sent TINYINT(1) NOT NULL DEFAULT 0'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ── 2. booking_whatsapp_sent_at (datetime, nullable) ──
SET @columnname = 'booking_whatsapp_sent_at';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT "booking_whatsapp_sent_at already exists"',
    'ALTER TABLE consultations ADD COLUMN booking_whatsapp_sent_at DATETIME NULL AFTER booking_whatsapp_sent'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ── 3. followup_done (boolean, default 0) ──
SET @columnname = 'followup_done';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT "followup_done already exists"',
    'ALTER TABLE consultations ADD COLUMN followup_done TINYINT(1) NOT NULL DEFAULT 0'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ── 4. followup_done_at (datetime, nullable) ──
SET @columnname = 'followup_done_at';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT "followup_done_at already exists"',
    'ALTER TABLE consultations ADD COLUMN followup_done_at DATETIME NULL AFTER followup_done'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ── 5. followup_done_by (admin_id, nullable) ──
SET @columnname = 'followup_done_by';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT "followup_done_by already exists"',
    'ALTER TABLE consultations ADD COLUMN followup_done_by INT NULL AFTER followup_done_at'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ── Index for follow-up page query ──
-- Query: appointment_datetime BETWEEN NOW() AND NOW()+24h AND status != cancelled
CREATE INDEX IF NOT EXISTS idx_followup_lookup
ON consultations (appointment_datetime, status, followup_done);

-- ── Verification query ──
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_NAME = 'consultations'
-- AND COLUMN_NAME IN ('booking_whatsapp_sent', 'booking_whatsapp_sent_at',
--                      'followup_done', 'followup_done_at', 'followup_done_by');
