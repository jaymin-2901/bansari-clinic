-- ============================================================
-- MediConnect – Import System Migration
-- Adds: gender & date_of_birth to users, import_logs table
-- Run: mysql -u root < backend/sql/007_import_system.sql
-- ============================================================

USE mediconnect;

-- ── Add gender and date_of_birth to users table (if not exists) ──
SET @col_gender = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender');
SET @sql_gender = IF(@col_gender = 0, 
    'ALTER TABLE users ADD COLUMN gender VARCHAR(10) NULL DEFAULT NULL AFTER phone', 
    'SELECT 1');
PREPARE stmt FROM @sql_gender;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_dob = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'date_of_birth');
SET @sql_dob = IF(@col_dob = 0, 
    'ALTER TABLE users ADD COLUMN date_of_birth DATE NULL DEFAULT NULL AFTER gender', 
    'SELECT 1');
PREPARE stmt FROM @sql_dob;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── Import Logs Table ──
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL DEFAULT 0,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(10) NOT NULL DEFAULT 'csv',
    total_rows INT NOT NULL DEFAULT 0,
    inserted INT NOT NULL DEFAULT 0,
    updated INT NOT NULL DEFAULT 0,
    skipped INT NOT NULL DEFAULT 0,
    failed INT NOT NULL DEFAULT 0,
    duplicate_action VARCHAR(20) NOT NULL DEFAULT 'skip',
    error_details JSON NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
