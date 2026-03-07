-- ============================================================
-- MediConnect – Admin Dashboard Migration
-- File: backend/sql/005_admin_dashboard_columns.sql
-- ============================================================
-- 
-- Adds columns needed by the admin dashboard & bulk actions:
--   - confirmation_status
--   - followup_done / followup_done_at / followup_done_by
--   - appointment_datetime
--   - booking_ref
--   - notes
--   - updated_at
--
-- Run: mysql -u root mediconnect < backend/sql/005_admin_dashboard_columns.sql
-- ============================================================

-- ── Add columns if they don't exist ──
-- Note: MySQL doesn't support IF NOT EXISTS for ALTER TABLE ADD COLUMN,
-- so these will error harmlessly if the column already exists.

-- Confirmation status text
ALTER TABLE consultations ADD COLUMN confirmation_status VARCHAR(50) DEFAULT 'Pending' AFTER status;

-- Follow-up tracking
ALTER TABLE consultations ADD COLUMN followup_done TINYINT(1) DEFAULT 0 AFTER confirmation_status;
ALTER TABLE consultations ADD COLUMN followup_done_at DATETIME NULL DEFAULT NULL AFTER followup_done;
ALTER TABLE consultations ADD COLUMN followup_done_by INT NULL DEFAULT NULL AFTER followup_done_at;

-- Appointment datetime (precise)
ALTER TABLE consultations ADD COLUMN appointment_datetime DATETIME NULL DEFAULT NULL AFTER preferred_time;

-- Booking reference
ALTER TABLE consultations ADD COLUMN booking_ref VARCHAR(50) NULL DEFAULT NULL AFTER appointment_datetime;

-- Notes
ALTER TABLE consultations ADD COLUMN notes TEXT NULL DEFAULT NULL AFTER admin_notes;

-- Updated timestamp
ALTER TABLE consultations ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- ── Indexes for Dashboard Performance ──
CREATE INDEX IF NOT EXISTS idx_consult_status ON consultations(status);
CREATE INDEX IF NOT EXISTS idx_consult_appt_dt ON consultations(appointment_datetime);
CREATE INDEX IF NOT EXISTS idx_consult_followup ON consultations(followup_done);

-- ── Sample Dummy Data (run only once on fresh DB) ──
-- Uncomment below to insert test data:

/*
-- Test admin user
INSERT IGNORE INTO users (id, role, first_name, last_name, email, phone, password)
VALUES (1, 'admin', 'Dr. Bansari', 'Patel', 'admin@mediconnect.in', '+919227504540', '$2y$10$dummyhashhere');

-- Test patients
INSERT IGNORE INTO users (id, role, first_name, last_name, email, phone, password) VALUES
(10, 'patient', 'Rahul',   'Sharma',  'rahul@test.com',   '+919876543210', '$2y$10$dummyhash'),
(11, 'patient', 'Priya',   'Mehta',   'priya@test.com',   '+919876543211', '$2y$10$dummyhash'),
(12, 'patient', 'Amit',    'Patel',   'amit@test.com',    '+919876543212', '$2y$10$dummyhash'),
(13, 'patient', 'Neha',    'Desai',   'neha@test.com',    '+919876543213', '$2y$10$dummyhash'),
(14, 'patient', 'Vikram',  'Singh',   'vikram@test.com',  '+919876543214', '$2y$10$dummyhash'),
(15, 'patient', 'Anjali',  'Joshi',   'anjali@test.com',  '+919876543215', '$2y$10$dummyhash');

-- Test appointments
INSERT INTO consultations (patient_id, consultation_type, urgency_level, symptoms, preferred_date, preferred_time, appointment_datetime, status, confirmation_status, followup_done) VALUES
(10, 'offline', 'normal',    'Recurring headaches',         '2026-02-27', '10:00:00', '2026-02-27 10:00:00', 'approved', 'Confirmed', 0),
(11, 'offline', 'urgent',    'Skin rash and itching',       '2026-02-27', '11:00:00', '2026-02-27 11:00:00', 'pending',  'Pending',   0),
(12, 'online',  'normal',    'Follow-up for asthma',        '2026-02-27', '14:00:00', '2026-02-27 14:00:00', 'approved', 'Confirmed', 1),
(13, 'offline', 'normal',    'Joint pain and stiffness',    '2026-02-28', '09:30:00', '2026-02-28 09:30:00', 'pending',  'Pending',   0),
(14, 'offline', 'emergency', 'High fever since 2 days',     '2026-02-26', '17:00:00', '2026-02-26 17:00:00', 'completed','Confirmed', 1),
(15, 'online',  'normal',    'Anxiety and sleep issues',    '2026-02-28', '16:00:00', '2026-02-28 16:00:00', 'pending',  'Pending',   0);
*/
