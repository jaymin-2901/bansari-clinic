-- ============================================================
-- Bansari Homeopathy Clinic – Reminder & Confirmation System
-- Migration: 011_reminder_confirmation_system.sql
-- ============================================================

USE bansari_clinic;

-- ─── 1. Add reminder & confirmation fields to appointments ───
ALTER TABLE appointments
    ADD COLUMN reminder_sent TINYINT(1) DEFAULT 0 AFTER admin_notes,
    ADD COLUMN reminder_sent_at DATETIME NULL AFTER reminder_sent,
    ADD COLUMN confirmation_status ENUM('pending','reminder_sent','confirmed','cancelled','no_response') DEFAULT 'pending' AFTER reminder_sent_at,
    ADD COLUMN reply_source ENUM('whatsapp','email','manual','auto') NULL AFTER confirmation_status,
    ADD COLUMN whatsapp_message_id VARCHAR(255) NULL AFTER reply_source,
    ADD COLUMN email_message_id VARCHAR(255) NULL AFTER whatsapp_message_id,
    ADD COLUMN confirmed_at DATETIME NULL AFTER email_message_id,
    ADD COLUMN is_followup TINYINT(1) DEFAULT 0 AFTER confirmed_at,
    ADD COLUMN parent_appointment_id INT NULL AFTER is_followup,
    ADD COLUMN followup_created TINYINT(1) DEFAULT 0 AFTER parent_appointment_id,
    ADD INDEX idx_reminder_sent (reminder_sent),
    ADD INDEX idx_confirmation_status (confirmation_status),
    ADD INDEX idx_is_followup (is_followup),
    ADD INDEX idx_parent_appointment (parent_appointment_id);

-- ─── 2. Reminder Log table ───
CREATE TABLE IF NOT EXISTS reminder_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    channel ENUM('whatsapp','email') NOT NULL,
    status ENUM('queued','sent','delivered','failed','replied') DEFAULT 'queued',
    message_id VARCHAR(255) NULL COMMENT 'External message ID (WhatsApp/Email)',
    recipient VARCHAR(255) NOT NULL COMMENT 'Phone number or email address',
    template_name VARCHAR(100) NULL,
    error_message TEXT NULL,
    patient_reply TEXT NULL,
    reply_received_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_appointment (appointment_id),
    INDEX idx_status (status),
    INDEX idx_channel (channel),
    INDEX idx_message_id (message_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ─── 3. Confirmation Tokens table (for email confirm/cancel links) ───
CREATE TABLE IF NOT EXISTS confirmation_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    action ENUM('confirm','cancel') NOT NULL,
    used TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_appointment (appointment_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ─── 4. Rate limit table for manual reminders ───
CREATE TABLE IF NOT EXISTS reminder_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    appointment_id INT NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'send_reminder',
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_appointment (appointment_id),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB;
