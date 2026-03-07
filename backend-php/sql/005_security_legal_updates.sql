-- ============================================================
-- MediConnect - Security & Legal Compliance Database Updates
-- File: backend/sql/005_security_legal_updates.sql
-- ============================================================
-- Add tables for:
-- 1. Legal pages (Privacy Policy, Terms & Conditions)
-- 2. Backup management tracking
-- 3. Security audit logs
-- 4. Enhanced password security features

-- ─── 1. LEGAL PAGES TABLE ───
CREATE TABLE IF NOT EXISTS legal_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL COMMENT 'HTML content allowed',
    created_by INT,
    updated_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Insert default legal pages
INSERT INTO legal_pages (title, slug, content) VALUES
('Privacy Policy', 'privacy-policy', '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal health information.</p>'),
('Terms & Conditions', 'terms-conditions', '<h1>Terms & Conditions</h1><p>By using our services, you agree to these terms and conditions.</p>')
ON DUPLICATE KEY UPDATE slug=slug;

-- ─── 2. BACKUP MANAGEMENT TABLE ───
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_name VARCHAR(100) NOT NULL,
    backup_path VARCHAR(255) NOT NULL,
    backup_type ENUM('daily','weekly') DEFAULT 'daily',
    file_size BIGINT UNSIGNED COMMENT 'Size in bytes',
    is_compressed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL COMMENT 'When backup should be deleted',
    checksum VARCHAR(64) NULL COMMENT 'SHA256 hash for verification',
    verified TINYINT(1) DEFAULT 0,
    uploaded_to_cloud TINYINT(1) DEFAULT 0,
    notes VARCHAR(255) NULL,
    INDEX idx_type (backup_type),
    INDEX idx_created (created_at),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 3. EMAIL LOGS TABLE ───
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(150) NOT NULL,
    recipient_name VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    template_used VARCHAR(100),
    status ENUM('sent','failed','bounced','spam') DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    INDEX idx_recipient (recipient_email),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 4. SMS LOGS TABLE ───
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    appointment_id INT,
    message_type ENUM('reminder','confirmation','followup','other') DEFAULT 'other',
    gateway ENUM('primary','fallback') DEFAULT 'primary',
    message_text TEXT NOT NULL,
    status ENUM('sent','failed','delivered','undelivered') DEFAULT 'sent',
    error_code VARCHAR(50) NULL,
    error_message TEXT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME NULL,
    cost_cents INT NULL COMMENT 'Cost in cents',
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 5. NOTIFICATION ALERTS TABLE ───
CREATE TABLE IF NOT EXISTS notification_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('appointment','message','reminder','system') DEFAULT 'system',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    icon VARCHAR(50) NULL,
    badge_count INT DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_read (read_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 6. ADMIN ACTIVITY LOG TABLE ───
CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    changes JSON NULL COMMENT 'Before/after values',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    status ENUM('success','failed') DEFAULT 'success',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 7. PASSWORD RESET TOKENS ───
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_expires (expires_at),
    INDEX idx_used (used_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 8. ADD MISSING COLUMNS TO APPOINTMENTS ───
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS sms_reminder_sent TINYINT(1) DEFAULT 0 COMMENT 'Track SMS fallback';
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS sms_sent_at DATETIME NULL;
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reminder_failure_reason VARCHAR(255) NULL COMMENT 'Why WhatsApp failed';

-- ─── 9. ADD COLUMN FOR EXPORT AUDIT ───
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS exported_at DATETIME NULL COMMENT 'When data was exported to Excel';

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);
CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status);
CREATE INDEX IF NOT EXISTS idx_appointments_created ON appointments(created_at);
CREATE INDEX IF NOT EXISTS idx_patients_mobile ON patients(mobile);
CREATE INDEX IF NOT EXISTS idx_patients_name ON patients(full_name);
