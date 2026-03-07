-- ============================================================
-- MediConnect – Full Database Setup
-- Creates database, tables, and seeds sample data
-- Run: C:\xampp\mysql\bin\mysql.exe -u root < backend/sql/setup_full.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS mediconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mediconnect;

-- ── Users Table ──
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('patient', 'admin') NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Patient Address ──
CREATE TABLE IF NOT EXISTS patient_address (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    pincode VARCHAR(10),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Medical History ──
CREATE TABLE IF NOT EXISTS medical_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    blood_type VARCHAR(5),
    allergies TEXT,
    current_medications TEXT,
    chronic_conditions TEXT,
    previous_surgeries TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Consultations ──
CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_type ENUM('online', 'offline') NOT NULL,
    urgency_level ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
    symptoms TEXT,
    preferred_date DATE,
    preferred_time TIME,
    appointment_datetime DATETIME NULL DEFAULT NULL,
    booking_ref VARCHAR(50) NULL DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    confirmation_status VARCHAR(50) DEFAULT 'Pending',
    followup_done TINYINT(1) DEFAULT 0,
    followup_done_at DATETIME NULL DEFAULT NULL,
    followup_done_by INT NULL DEFAULT NULL,
    admin_notes TEXT,
    notes TEXT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Admin Logs ──
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    consultation_id INT NOT NULL,
    action VARCHAR(100),
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id),
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Indexes ──
CREATE INDEX idx_consult_status ON consultations(status);
CREATE INDEX idx_consult_appt_dt ON consultations(appointment_datetime);
CREATE INDEX idx_consult_followup ON consultations(followup_done);
CREATE INDEX idx_followup_lookup ON consultations(appointment_datetime, status, followup_done);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: password — bcrypt hash)
INSERT INTO users (id, role, first_name, last_name, email, phone, password) VALUES
(1, 'admin', 'Dr. Bansari', 'Patel', 'admin@mediconnect.in', '+919227504540', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Patients
INSERT INTO users (id, role, first_name, last_name, email, phone, password) VALUES
(10, 'patient', 'Rahul',   'Sharma',   'rahul@test.com',   '+919876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(11, 'patient', 'Priya',   'Mehta',    'priya@test.com',   '+919876543211', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(12, 'patient', 'Amit',    'Patel',    'amit@test.com',    '+919876543212', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(13, 'patient', 'Neha',    'Desai',    'neha@test.com',    '+919876543213', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(14, 'patient', 'Vikram',  'Singh',    'vikram@test.com',  '+919876543214', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(15, 'patient', 'Anjali',  'Joshi',    'anjali@test.com',  '+919876543215', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(16, 'patient', 'Sanjay',  'Gupta',    'sanjay@test.com',  '+919876543216', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(17, 'patient', 'Meera',   'Reddy',    'meera@test.com',   '+919876543217', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(18, 'patient', 'Kiran',   'Nair',     'kiran@test.com',   '+919876543218', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(19, 'patient', 'Deepak',  'Verma',    'deepak@test.com',  '+919876543219', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(20, 'patient', 'Ritu',    'Agarwal',  'ritu@test.com',    '+919876543220', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(21, 'patient', 'Arjun',   'Thakur',   'arjun@test.com',   '+919876543221', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Appointments — mix of statuses, some within 24h, some past, some future
INSERT INTO consultations (patient_id, consultation_type, urgency_level, symptoms, preferred_date, preferred_time, appointment_datetime, status, confirmation_status, followup_done, followup_done_at, created_at) VALUES
-- Today's appointments (within 24h — will show ⏰ 24h badge)
(10, 'offline', 'normal',    'Recurring headaches and migraine',     '2026-02-26', '14:00:00', '2026-02-26 14:00:00', 'approved',  'Confirmed', 0, NULL, '2026-02-24 09:00:00'),
(11, 'offline', 'urgent',    'Skin rash and severe itching',         '2026-02-26', '15:30:00', '2026-02-26 15:30:00', 'approved',  'Confirmed', 0, NULL, '2026-02-24 10:00:00'),
(12, 'online',  'normal',    'Follow-up consultation for asthma',    '2026-02-26', '16:00:00', '2026-02-26 16:00:00', 'pending',   'Pending',   0, NULL, '2026-02-25 14:00:00'),

-- Tomorrow's appointments
(13, 'offline', 'normal',    'Joint pain and stiffness in knees',    '2026-02-27', '09:30:00', '2026-02-27 09:30:00', 'approved',  'Confirmed', 0, NULL, '2026-02-25 11:00:00'),
(14, 'offline', 'emergency', 'High fever since 3 days',              '2026-02-27', '10:00:00', '2026-02-27 10:00:00', 'pending',   'Pending',   0, NULL, '2026-02-26 09:00:00'),
(15, 'online',  'normal',    'Anxiety and chronic sleep issues',     '2026-02-27', '11:30:00', '2026-02-27 11:30:00', 'approved',  'Confirmed', 0, NULL, '2026-02-25 16:00:00'),

-- Past appointments (completed/cancelled)
(16, 'offline', 'normal',    'General health checkup',               '2026-02-24', '10:00:00', '2026-02-24 10:00:00', 'completed', 'Confirmed', 1, '2026-02-24 11:00:00', '2026-02-22 10:00:00'),
(17, 'offline', 'urgent',    'Severe stomach pain',                  '2026-02-24', '14:00:00', '2026-02-24 14:00:00', 'completed', 'Confirmed', 1, '2026-02-24 15:30:00', '2026-02-23 08:00:00'),
(18, 'online',  'normal',    'Allergic rhinitis follow-up',          '2026-02-25', '11:00:00', '2026-02-25 11:00:00', 'completed', 'Confirmed', 1, '2026-02-25 12:00:00', '2026-02-23 15:00:00'),
(19, 'offline', 'normal',    'Eczema persistent patches',            '2026-02-23', '09:00:00', '2026-02-23 09:00:00', 'cancelled', 'Cancelled', 0, NULL, '2026-02-21 14:00:00'),

-- Future appointments (after tomorrow)
(20, 'offline', 'normal',    'Child wellness checkup',               '2026-02-28', '10:00:00', '2026-02-28 10:00:00', 'pending',   'Pending',   0, NULL, '2026-02-26 08:00:00'),
(21, 'offline', 'normal',    'Thyroid medication review',            '2026-02-28', '14:30:00', '2026-02-28 14:30:00', 'approved',  'Confirmed', 0, NULL, '2026-02-25 09:00:00'),
(10, 'online',  'normal',    'Weight management consultation',       '2026-03-01', '10:00:00', '2026-03-01 10:00:00', 'pending',   'Pending',   0, NULL, '2026-02-26 11:00:00'),
(11, 'offline', 'urgent',    'Chronic cough not improving',          '2026-03-01', '15:00:00', '2026-03-01 15:00:00', 'pending',   'Pending',   0, NULL, '2026-02-26 12:00:00'),
(12, 'online',  'normal',    'Stress and burnout counseling',        '2026-03-02', '11:00:00', '2026-03-02 11:00:00', 'approved',  'Confirmed', 0, NULL, '2026-02-25 17:00:00');
