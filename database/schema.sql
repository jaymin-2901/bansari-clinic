-- ============================================================
-- Bansari Homeopathy Clinic – Complete Database Schema
-- Database: bansari_clinic
-- ============================================================

CREATE DATABASE IF NOT EXISTS bansari_clinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bansari_clinic;

-- ─── 1. ADMINS ───
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin') DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Default admin: admin@bansari.com / password: Admin@123
INSERT INTO admins (name, email, password, role) VALUES
('Dr. Bansari Patel', 'admin@bansari.com', '$2y$10$SaK7s9jRLHtyX4fP4aOFceLi2qGe8PjBvS7AywxgDFgf22Obit.O.', 'super_admin');

-- ─── 2. PATIENTS ───
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    age INT NULL,
    gender ENUM('male','female','other') NULL,
    city VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    password VARCHAR(255) NULL,
    is_registered TINYINT(1) DEFAULT 0,
    status ENUM('active','inactive','blocked') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mobile (mobile),
    INDEX idx_name (full_name),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ─── 3. APPOINTMENTS ───
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_type ENUM('offline','online') NOT NULL,
    form_type ENUM('short','full') NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NULL,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    admin_notes TEXT NULL,
    reminder_sent TINYINT(1) DEFAULT 0,
    reminder_sent_at DATETIME NULL,
    confirmation_status ENUM('pending','reminder_sent','confirmed','cancelled','no_response') DEFAULT 'pending',
    reply_source ENUM('whatsapp','email','manual','auto') NULL,
    whatsapp_message_id VARCHAR(255) NULL,
    email_message_id VARCHAR(255) NULL,
    confirmed_at DATETIME NULL,
    is_followup TINYINT(1) DEFAULT 0,
    parent_appointment_id INT NULL,
    followup_created TINYINT(1) DEFAULT 0,
    followup_done TINYINT(1) DEFAULT 0,
    followup_done_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_date (appointment_date),
    INDEX idx_status (status),
    INDEX idx_type (consultation_type),
    INDEX idx_reminder_sent (reminder_sent),
    INDEX idx_confirmation_status (confirmation_status),
    INDEX idx_is_followup (is_followup),
    INDEX idx_followup_done (followup_done)
) ENGINE=InnoDB;

-- ─── 4. COMPLAINTS (Short Form – Offline) ───
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    chief_complaint TEXT NOT NULL,
    complaint_duration VARCHAR(100) NULL,
    major_diseases JSON NULL COMMENT '["diabetes","bp","thyroid","asthma","tb","surgery"]',
    current_medicines TEXT NULL,
    allergy TEXT NULL,
    declaration_accepted TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 5. MAIN COMPLAINTS (Full Form – Online) ───
CREATE TABLE IF NOT EXISTS main_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    complaint_text TEXT NOT NULL,
    duration VARCHAR(100) NULL,
    severity ENUM('mild','moderate','severe') DEFAULT 'moderate',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 6. PAST DISEASES (Full Form) ───
CREATE TABLE IF NOT EXISTS past_diseases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    disease_name VARCHAR(150) NOT NULL,
    details TEXT NULL,
    year_diagnosed VARCHAR(10) NULL,
    treatment_taken TEXT NULL,
    is_current TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 7. FAMILY HISTORY (Full Form) ───
CREATE TABLE IF NOT EXISTS family_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    relation VARCHAR(50) NOT NULL,
    disease VARCHAR(150) NOT NULL,
    details TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 8. PHYSICAL GENERALS (Full Form) ───
CREATE TABLE IF NOT EXISTS physical_generals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    appetite ENUM('good','moderate','poor','variable') DEFAULT 'good',
    thirst ENUM('normal','increased','decreased','absent') DEFAULT 'normal',
    stool ENUM('regular','constipated','loose','alternating') DEFAULT 'regular',
    urine ENUM('normal','frequent','scanty','burning') DEFAULT 'normal',
    sweat ENUM('normal','profuse','absent','offensive') DEFAULT 'normal',
    sleep_quality ENUM('sound','disturbed','insomnia','excessive') DEFAULT 'sound',
    sleep_position VARCHAR(50) NULL,
    thermal ENUM('hot','chilly','ambithermal') DEFAULT 'ambithermal',
    cravings TEXT NULL,
    aversions TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 9. MENTAL PROFILE (Full Form) ───
CREATE TABLE IF NOT EXISTS mental_profile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    temperament VARCHAR(100) NULL,
    fears TEXT NULL,
    dreams TEXT NULL,
    stress_factors TEXT NULL,
    emotional_state TEXT NULL,
    hobbies TEXT NULL,
    social_behavior VARCHAR(100) NULL,
    additional_notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 10. TESTIMONIALS ───
CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(150) NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    treatment_description TEXT NOT NULL,
    testimonial_text TEXT NULL,
    before_image VARCHAR(255) NULL,
    after_image VARCHAR(255) NULL,
    rating TINYINT DEFAULT 5,
    display_status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (display_status),
    INDEX idx_order (sort_order)
) ENGINE=InnoDB;

-- ─── 11. CONTACT MESSAGES ───
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ─── 12. CLINIC SCHEDULE ───
CREATE TABLE IF NOT EXISTS clinic_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday … 6=Saturday',
    is_open TINYINT(1) DEFAULT 1,
    opening_time TIME NULL,
    closing_time TIME NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    new_patient_duration INT DEFAULT 30 COMMENT 'minutes per slot',
    old_patient_duration INT DEFAULT 15 COMMENT 'minutes per slot',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_day (day_of_week)
) ENGINE=InnoDB;

-- Default schedule (Mon–Sat open, Sunday closed)
INSERT INTO clinic_schedule (day_of_week, is_open, opening_time, closing_time, break_start, break_end, new_patient_duration, old_patient_duration) VALUES
(0, 0, NULL, NULL, NULL, NULL, 30, 15),
(1, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
(2, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
(3, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
(4, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
(5, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
(6, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15);

-- ─── 13. WEBSITE SETTINGS (Key-Value CMS) ───
CREATE TABLE IF NOT EXISTS website_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('text','textarea','image','html','json') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
) ENGINE=InnoDB;

-- ─── 14. REMINDER LOGS ───
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

-- ─── 15. CONFIRMATION TOKENS ───
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
    INDEX idx_appointment_ct (appointment_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ─── 16. REMINDER RATE LIMITS ───
CREATE TABLE IF NOT EXISTS reminder_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    appointment_id INT NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'send_reminder',
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_appointment_rrl (appointment_id),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB;

-- ─── 17. LEGAL PAGES ───
CREATE TABLE IF NOT EXISTS legal_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL COMMENT 'HTML content allowed',
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default Legal Pages
INSERT INTO legal_pages (title, slug, content) VALUES
('Privacy Policy', 'privacy-policy', '<h2>Privacy Policy</h2><p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information.</p>'),
('Terms & Conditions', 'terms-conditions', '<h2>Terms &amp; Conditions</h2><p>By using our services, you agree to these terms and conditions.</p>');

-- ─── Default Website Settings ───
INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) VALUES
-- About Page
('about_doctor_name', 'Dr. Bansari Patel', 'text', 'about'),
('about_doctor_title', 'BHMS, MD (Homeopathy)', 'text', 'about'),
('about_doctor_image', '', 'image', 'about'),
('about_doctor_bio', 'Dr. Bansari Patel is a dedicated homeopathic practitioner with years of experience in treating chronic and acute conditions through classical homeopathy. She believes in holistic healing that addresses the root cause of disease rather than just symptoms.', 'textarea', 'about'),
('about_clinic_philosophy', 'At Bansari Homeopathy Clinic, we believe in the power of natural healing. Our approach combines classical homeopathic principles with modern diagnostic understanding to provide personalized treatment plans for each patient.', 'textarea', 'about'),
('about_experience', '10+ Years of Experience', 'text', 'about'),
('about_mission', 'To provide gentle, effective, and lasting homeopathic treatment that improves quality of life for every patient who walks through our doors.', 'textarea', 'about'),
('about_vision', 'To become the most trusted homeopathic healthcare provider, making natural healing accessible to everyone in our community and beyond.', 'textarea', 'about'),
('about_clinic_image', '', 'image', 'about'),

-- Contact Page
('contact_address', '212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.', 'textarea', 'contact'),
('contact_phone', '+91 63543 88539', 'text', 'contact'),
('contact_whatsapp', '+91 63543 88539', 'text', 'contact'),
('contact_email', 'info@bansarihomeopathy.com', 'text', 'contact'),
('contact_map_iframe', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3671.8!2d72.57!3d23.03!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2z!5e0!3m2!1sen!2sin!4v1234567890', 'text', 'contact'),
('contact_hours', 'Mon - Sat: 9:00 AM - 1:00 PM, 5:00 PM - 8:00 PM\nSunday: Closed', 'textarea', 'contact'),

-- Home Page
('home_hero_title', 'Bansari Homeopathy Clinic', 'text', 'home'),
('home_hero_subtitle', 'Gentle Healing, Lasting Results', 'text', 'home'),
('home_hero_description', 'Experience the power of classical homeopathy with Dr. Bansari Patel. Personalized treatment for chronic and acute conditions.', 'textarea', 'home'),
('home_hero_image', '', 'image', 'home'),

-- General
('clinic_name', 'Bansari Homeopathy Clinic', 'text', 'general'),
('clinic_logo', '', 'image', 'general'),
('clinic_tagline', 'Gentle Healing, Lasting Results', 'text', 'general');
