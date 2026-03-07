-- ============================================================
-- MediConnect – Patient Form Submissions Table
-- File: patient-form/sql/form_submissions.sql
-- ============================================================
-- Run this SQL in your MySQL database (mediconnect)
-- ============================================================

CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `unique_id` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(15) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `gender` ENUM('male','female','other') DEFAULT NULL,
    `dob` DATE DEFAULT NULL,
    `symptoms` TEXT DEFAULT NULL,
    `pdf_file` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
    `admin_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_unique_id` (`unique_id`),
    INDEX `idx_mobile` (`mobile`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table for patient form admin (if not using existing users table)
CREATE TABLE IF NOT EXISTS `form_admin_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: Admin@123)
-- Change this password immediately after first login!
INSERT INTO `form_admin_users` (`username`, `password`, `full_name`) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Bansari Patel')
ON DUPLICATE KEY UPDATE `id` = `id`;
