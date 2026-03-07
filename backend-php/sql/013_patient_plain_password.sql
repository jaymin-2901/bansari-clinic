-- ============================================================
-- Add plain_password column to patients table
-- Task 7: Patient Password Column
-- ============================================================
-- This allows storing the original plain text password for display in admin
-- Note: This is NOT recommended for production security but requested by user

ALTER TABLE patients ADD COLUMN plain_password VARCHAR(255) NULL AFTER password;

-- Index for faster lookups
CREATE INDEX idx_plain_password ON patients(plain_password);

