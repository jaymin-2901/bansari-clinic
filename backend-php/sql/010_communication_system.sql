-- ============================================================
-- MediConnect – Communication Automation System
-- File: backend/sql/010_communication_system.sql
-- ============================================================
-- Run this SQL to create all tables needed for SMS, Email,
-- SMS, email, communication queue, and analytics.
-- ============================================================

USE mediconnect;

-- ─── SMS Logs ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sms_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NULL,
    phone           VARCHAR(20) NOT NULL,
    message         TEXT NOT NULL,
    message_type    ENUM('otp','appointment_confirmation','appointment_reminder','cancellation','followup','general') DEFAULT 'general',
    sms_gateway     VARCHAR(50) DEFAULT 'fast2sms',
    gateway_message_id VARCHAR(100) NULL,
    status          ENUM('queued','sent','delivered','failed','rejected') DEFAULT 'queued',
    response        TEXT NULL,
    error_message   TEXT NULL,
    retry_count     TINYINT UNSIGNED DEFAULT 0,
    cost            DECIMAL(6,4) DEFAULT 0.0000,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at    TIMESTAMP NULL,
    
    INDEX idx_sms_patient (patient_id),
    INDEX idx_sms_status (status),
    INDEX idx_sms_type (message_type),
    INDEX idx_sms_created (created_at),
    INDEX idx_sms_phone (phone),
    
    CONSTRAINT fk_sms_patient FOREIGN KEY (patient_id) 
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Email Logs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS email_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NULL,
    email           VARCHAR(255) NOT NULL,
    subject         VARCHAR(500) NOT NULL,
    body_preview    TEXT NULL COMMENT 'First 500 chars of HTML body for reference',
    message_type    ENUM('appointment_confirmation','appointment_reminder','cancellation','followup','otp','general') DEFAULT 'general',
    smtp_provider   VARCHAR(50) DEFAULT 'gmail',
    smtp_message_id VARCHAR(255) NULL,
    status          ENUM('queued','sent','delivered','failed','bounced') DEFAULT 'queued',
    response        TEXT NULL,
    error_message   TEXT NULL,
    retry_count     TINYINT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at         TIMESTAMP NULL,
    
    INDEX idx_email_patient (patient_id),
    INDEX idx_email_status (status),
    INDEX idx_email_type (message_type),
    INDEX idx_email_created (created_at),
    INDEX idx_email_addr (email),
    
    CONSTRAINT fk_email_patient FOREIGN KEY (patient_id)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Communication Queue (Unified queue for all channels) ───
CREATE TABLE IF NOT EXISTS communication_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NULL,
    consultation_id INT UNSIGNED NULL,
    channel         ENUM('sms','email') NOT NULL,
    priority        TINYINT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
    recipient       VARCHAR(255) NOT NULL COMMENT 'Phone or email',
    subject         VARCHAR(500) NULL COMMENT 'Email subject only',
    message         TEXT NOT NULL,
    html_body       MEDIUMTEXT NULL COMMENT 'HTML body for email',
    message_type    ENUM('otp','appointment_confirmation','appointment_reminder','cancellation','followup','general') DEFAULT 'general',
    status          ENUM('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
    attempts        TINYINT UNSIGNED DEFAULT 0,
    max_attempts    TINYINT UNSIGNED DEFAULT 3,
    last_attempt_at TIMESTAMP NULL,
    next_retry_at   TIMESTAMP NULL,
    error_message   TEXT NULL,
    scheduled_at    TIMESTAMP NULL COMMENT 'NULL = send immediately',
    sent_at         TIMESTAMP NULL,
    log_id          INT UNSIGNED NULL COMMENT 'FK to the channel-specific log table',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_queue_status (status),
    INDEX idx_queue_channel (channel),
    INDEX idx_queue_priority (priority),
    INDEX idx_queue_scheduled (scheduled_at),
    INDEX idx_queue_next_retry (next_retry_at),
    INDEX idx_queue_patient (patient_id),
    INDEX idx_queue_consultation (consultation_id),
    INDEX idx_queue_pending (status, scheduled_at, next_retry_at, priority),
    
    CONSTRAINT fk_queue_patient FOREIGN KEY (patient_id)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_queue_consultation FOREIGN KEY (consultation_id)
        REFERENCES consultations(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Appointment Status Updates (audit trail) ───────────────
CREATE TABLE IF NOT EXISTS appointment_updates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT UNSIGNED NOT NULL,
    patient_id      INT UNSIGNED NULL,
    old_status      VARCHAR(50) NULL,
    new_status      VARCHAR(50) NOT NULL,
    update_source   ENUM('admin','patient','sms_reply','system','cron') DEFAULT 'system',
    source_detail   VARCHAR(255) NULL COMMENT 'e.g., admin user id',
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_appt_update_consult (consultation_id),
    INDEX idx_appt_update_patient (patient_id),
    INDEX idx_appt_update_source (update_source),
    INDEX idx_appt_update_created (created_at),
    
    CONSTRAINT fk_appt_update_consult FOREIGN KEY (consultation_id)
        REFERENCES consultations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_appt_update_patient FOREIGN KEY (patient_id)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Rate Limiting Tracker ──────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limit_tracker (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel         ENUM('sms','email') NOT NULL,
    recipient       VARCHAR(255) NOT NULL,
    message_type    VARCHAR(50) NOT NULL,
    sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_rate_channel_recipient (channel, recipient, sent_at),
    INDEX idx_rate_cleanup (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Add columns to consultations if not present ────────────
-- These columns track whether SMS/Email reminders were sent
ALTER TABLE consultations
    ADD COLUMN IF NOT EXISTS sms_reminder_sent TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS sms_reminder_sent_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS email_reminder_sent TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS email_reminder_sent_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS sms_confirmation_sent TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS email_confirmation_sent TINYINT(1) DEFAULT 0;


-- ─── Communication Analytics View ───────────────────────────
CREATE OR REPLACE VIEW v_communication_stats AS
SELECT 
    'sms' AS channel,
    COUNT(*) AS total,
    SUM(IF(status = 'sent', 1, 0)) AS sent_count,
    SUM(IF(status = 'delivered', 1, 0)) AS delivered_count,
    SUM(IF(status = 'failed', 1, 0)) AS failed_count,
    SUM(IF(status = 'queued', 1, 0)) AS pending_count
FROM sms_logs
UNION ALL
SELECT 
    'email' AS channel,
    COUNT(*) AS total,
    SUM(IF(status = 'sent', 1, 0)) AS sent_count,
    SUM(IF(status = 'delivered', 1, 0)) AS delivered_count,
    SUM(IF(status = 'failed', 1, 0)) AS failed_count,
    SUM(IF(status = 'queued', 1, 0)) AS pending_count
FROM email_logs;
