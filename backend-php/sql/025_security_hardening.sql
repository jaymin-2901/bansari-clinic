-- ============================================================
-- Bansari Homeopathy – Security Hardening & Legal Templates
-- File: backend-php/sql/025_security_hardening.sql
-- ============================================================
-- Run: mysql -u root -p bansari_clinic < backend-php/sql/025_security_hardening.sql

-- ─── 1. SMS Fallback Columns (if not already present) ───
ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS sms_reminder_sent TINYINT(1) DEFAULT 0 AFTER reminder_sent_at,
    ADD COLUMN IF NOT EXISTS sms_sent_at DATETIME NULL AFTER sms_reminder_sent,
    ADD COLUMN IF NOT EXISTS reminder_failure_reason TEXT NULL AFTER sms_sent_at;

-- ─── 2. Password Change History (prevent reuse) ───
CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 3. Login Attempts Tracking ───
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    success TINYINT(1) DEFAULT 0,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 4. Followup Notes Table (for notification badge queries) ───
CREATE TABLE IF NOT EXISTS followup_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    admin_id INT NULL,
    note TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_appointment (appointment_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── 5. Full Privacy Policy Template ───
UPDATE legal_pages SET content = '
<h1>Privacy Policy</h1>
<p><strong>Last Updated:</strong> March 2026</p>
<p>Bansari Homeopathy Clinic (&quot;we&quot;, &quot;us&quot;, or &quot;our&quot;) is committed to protecting the privacy and security of your personal and health information. This Privacy Policy explains how we collect, use, store, and protect your data in compliance with Indian data protection laws.</p>

<h2>1. Information We Collect</h2>
<p>We may collect the following categories of personal information:</p>
<ul>
<li><strong>Personal Identification:</strong> Full name, age, gender, mobile number, email address, city/address</li>
<li><strong>Health Information:</strong> Chief complaints, medical history, family history, physical generals, mental profile, past diseases, current medications, allergies</li>
<li><strong>Appointment Data:</strong> Booking dates, consultation type, appointment status, follow-up records</li>
<li><strong>Communication Data:</strong> WhatsApp messages, SMS notifications, email correspondence related to appointments</li>
<li><strong>Technical Data:</strong> IP address, browser type (collected automatically for security purposes)</li>
</ul>

<h2>2. How We Use Your Information</h2>
<p>Your information is used exclusively for:</p>
<ul>
<li>Scheduling and managing your appointments</li>
<li>Providing homeopathic consultation and treatment</li>
<li>Sending appointment reminders via WhatsApp, SMS, or email</li>
<li>Sending appointment confirmation requests</li>
<li>Maintaining your medical history for continuity of care</li>
<li>Generating anonymised analytics to improve clinic operations</li>
<li>Complying with legal and regulatory requirements</li>
</ul>

<h2>3. WhatsApp &amp; Communication</h2>
<p>We use WhatsApp and SMS to send:</p>
<ul>
<li>Appointment booking confirmations</li>
<li>24-hour appointment reminders</li>
<li>Appointment cancellation notifications</li>
<li>Follow-up reminders</li>
</ul>
<p>If WhatsApp delivery fails, we may automatically send an SMS as a fallback to ensure you receive important appointment information. You may opt out of non-essential communications by contacting us.</p>

<h2>4. Data Storage &amp; Security</h2>
<p>We implement industry-standard security measures to protect your data:</p>
<ul>
<li><strong>Encryption:</strong> All passwords are hashed using bcrypt with high-cost factors</li>
<li><strong>Secure Sessions:</strong> Admin sessions use HttpOnly, Secure, and SameSite cookie attributes</li>
<li><strong>SQL Injection Prevention:</strong> All database queries use parameterised prepared statements</li>
<li><strong>Access Control:</strong> Role-based access ensures only authorised personnel can view patient data</li>
<li><strong>Audit Logging:</strong> All administrative actions are logged for accountability</li>
<li><strong>Regular Backups:</strong> Daily and weekly encrypted database backups</li>
<li><strong>Rate Limiting:</strong> Protection against brute-force attacks on all endpoints</li>
</ul>

<h2>5. Data Sharing</h2>
<p>We do <strong>not</strong> sell, rent, or share your personal or health information with third parties, except:</p>
<ul>
<li>When required by law or court order</li>
<li>With your explicit written consent</li>
<li>With SMS/WhatsApp service providers solely for delivering appointment notifications (they do not retain your data)</li>
</ul>

<h2>6. Data Retention</h2>
<p>We retain your personal and health data for as long as necessary to provide medical services and comply with legal retention requirements. You may request deletion of your data by contacting us, subject to legal obligations.</p>

<h2>7. Your Rights</h2>
<p>You have the right to:</p>
<ul>
<li>Access your personal data held by us</li>
<li>Request correction of inaccurate information</li>
<li>Request deletion of your data (subject to legal requirements)</li>
<li>Withdraw consent for non-essential communications</li>
<li>Lodge a complaint with the relevant data protection authority</li>
</ul>

<h2>8. Cookies</h2>
<p>Our admin panel uses session cookies for authentication. These cookies are:</p>
<ul>
<li>Essential for system functionality</li>
<li>HttpOnly (not accessible via JavaScript)</li>
<li>Session-based (deleted when browser closes)</li>
</ul>

<h2>9. Changes to This Policy</h2>
<p>We may update this privacy policy from time to time. Changes will be posted on this page with an updated revision date.</p>

<h2>10. Contact Us</h2>
<p>For privacy-related queries or to exercise your rights, contact us at:</p>
<p><strong>Bansari Homeopathy Clinic</strong><br>
Email: info@bansarihomeopathy.com<br>
Phone: +91 63543 88539</p>
' WHERE slug = 'privacy-policy';

-- ─── 6. Full Terms & Conditions Template ───
UPDATE legal_pages SET content = '
<h1>Terms &amp; Conditions</h1>
<p><strong>Last Updated:</strong> March 2026</p>
<p>These Terms and Conditions govern your use of the services provided by Bansari Homeopathy Clinic (&quot;the Clinic&quot;). By booking an appointment or using our services, you agree to these terms.</p>

<h2>1. Appointment Booking Policy</h2>
<ul>
<li>Appointments can be booked online through our website or by contacting the clinic directly.</li>
<li>All appointments are subject to availability of slots.</li>
<li>A confirmed appointment does not guarantee a specific consultation duration; the doctor will provide adequate time based on medical necessity.</li>
<li>First-time patients are allocated 30-minute slots; returning patients are allocated 15-minute slots.</li>
<li>Online consultations require a stable internet connection; the clinic is not responsible for connectivity issues on the patient''s end.</li>
</ul>

<h2>2. Cancellation Policy</h2>
<ul>
<li>Appointments may be cancelled or rescheduled at least <strong>4 hours before</strong> the scheduled time.</li>
<li>Cancellations can be made via WhatsApp, phone call, or through the appointment confirmation link.</li>
<li>Late cancellations (less than 4 hours notice) may be noted in your patient record.</li>
<li>Repeated late cancellations may affect future booking priority.</li>
</ul>

<h2>3. No-Show Policy</h2>
<ul>
<li>If you do not attend your appointment without prior cancellation, it will be recorded as a &quot;no-show&quot;.</li>
<li>After <strong>3 consecutive no-shows</strong>, the clinic reserves the right to require advance confirmation before accepting future bookings.</li>
<li>We send appointment reminders via WhatsApp/SMS 24 hours before your appointment to help you remember.</li>
</ul>

<h2>4. Consultation &amp; Treatment Disclaimer</h2>
<ul>
<li>Homeopathic treatment is provided based on the principles of classical homeopathy.</li>
<li>Treatment outcomes vary from patient to patient and are not guaranteed.</li>
<li>The information provided during consultation is for medical purposes only and should not be shared or used for self-medication.</li>
<li>Patients must disclose all relevant medical history, current medications, and allergies for safe treatment.</li>
<li>The clinic is not liable for outcomes resulting from incomplete or inaccurate information provided by the patient.</li>
</ul>

<h2>5. Liability Disclaimer</h2>
<ul>
<li>The clinic provides services with reasonable care and professional standards.</li>
<li>We are not liable for any indirect, incidental, or consequential damages arising from the use of our services.</li>
<li>Our website and appointment system are provided &quot;as is&quot;. While we strive for 100% uptime, we do not guarantee uninterrupted service.</li>
<li>The clinic is not responsible for delays caused by technical issues with WhatsApp, SMS, or email service providers.</li>
</ul>

<h2>6. Patient Responsibilities</h2>
<ul>
<li>Arrive at least 5 minutes before your scheduled appointment time.</li>
<li>Provide accurate personal and medical information.</li>
<li>Follow the prescribed treatment plan and report any adverse reactions promptly.</li>
<li>Respect clinic staff and other patients.</li>
<li>Make payments for consultation as per the clinic''s fee schedule.</li>
</ul>

<h2>7. Patient Data &amp; Privacy</h2>
<p>Your personal and health data is handled in accordance with our <a href="/privacy-policy">Privacy Policy</a>. By using our services, you consent to the collection and use of your data as described therein.</p>

<h2>8. Communication Consent</h2>
<p>By booking an appointment, you consent to receive:</p>
<ul>
<li>Appointment confirmation messages</li>
<li>Reminder notifications (24 hours before appointment)</li>
<li>Follow-up communication related to your treatment</li>
</ul>
<p>You may opt out of non-essential communications by contacting us.</p>

<h2>9. Intellectual Property</h2>
<p>All content on our website, including text, images, logos, and design, is the property of Bansari Homeopathy Clinic and is protected by intellectual property laws. Unauthorised reproduction is prohibited.</p>

<h2>10. Modifications</h2>
<p>The clinic reserves the right to modify these terms at any time. Continued use of our services after changes constitutes acceptance of the updated terms.</p>

<h2>11. Governing Law</h2>
<p>These terms are governed by and construed in accordance with the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Ahmedabad, Gujarat.</p>

<h2>12. Contact</h2>
<p>For questions about these terms, contact us at:</p>
<p><strong>Bansari Homeopathy Clinic</strong><br>
Email: info@bansarihomeopathy.com<br>
Phone: +91 63543 88539</p>
' WHERE slug = 'terms-conditions';

-- ─── 7. Add indexes for filter performance ───
ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_followup_date (followup_done, appointment_date);
ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_patient_status (patient_id, status);
ALTER TABLE patients ADD INDEX IF NOT EXISTS idx_mobile_search (mobile);
