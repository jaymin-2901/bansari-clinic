<?php
/**
 * Bansari Homeopathy – Database Seeder
 * Seeds default settings and legal pages on first run
 * 
 * Usage: Call this endpoint once during setup or include in migration
 * GET /backend/api/clinic/seed_settings.php
 */

require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $db = getClinicDB();
    
    $results = [
        'settings_seeded' => 0,
        'legal_pages_seeded' => 0,
        'errors' => []
    ];

    // Seed website_settings table
    $defaultSettings = [
        // General settings
        ['clinic_name', 'Bansari Homeopathy Clinic', 'text', 'general'],
        ['clinic_tagline', 'Gentle Healing, Lasting Results', 'text', 'general'],
        
        // Contact settings
        ['contact_address', '212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.', 'text', 'contact'],
        ['contact_phone', '+91 63543 88539', 'text', 'contact'],
        ['contact_whatsapp', '+91 63543 88539', 'text', 'contact'],
        ['contact_email', 'info@bansarihomeopathy.com', 'text', 'contact'],
        ['contact_hours', '9:30 AM - 1:00 PM, 5:00 PM - 8:00 PM', 'text', 'contact'],
        
        // Home page settings
        ['home_hero_title', 'Gentle Healing,', 'text', 'home'],
        ['home_hero_subtitle', 'Lasting Results', 'text', 'home'],
        ['home_hero_description', 'Experience personalized homeopathic treatment with Dr. Bansari Patel. Holistic care for chronic and acute conditions, treating mind, body and spirit.', 'textarea', 'home'],
        
        // About page settings
        ['about_doctor_name', 'Dr. Bansari Patel', 'text', 'about'],
        ['about_doctor_title', 'BHMS, MD (Homeopathy)', 'text', 'about'],
        ['about_doctor_bio', 'Dr. Bansari Patel is a dedicated homeopathic practitioner with years of experience in treating chronic and acute conditions through classical homeopathy.', 'textarea', 'about'],
        ['about_experience', '10+ Years of Experience', 'text', 'about'],
        ['about_clinic_philosophy', 'At Bansari Homeopathy Clinic, we believe in the power of natural healing. Our approach combines classical homeopathic principles with modern diagnostic understanding.', 'textarea', 'about'],
        ['about_mission', 'To provide gentle, effective, and lasting homeopathic treatment that improves quality of life for every patient.', 'textarea', 'about'],
        ['about_vision', 'To become the most trusted homeopathic healthcare provider, making natural healing accessible to everyone.', 'textarea', 'about'],
    ];

    foreach ($defaultSettings as $setting) {
        list($key, $value, $type, $group) = $setting;
        
        // Check if setting exists
        $stmt = $db->prepare("SELECT id FROM website_settings WHERE setting_key = ? AND setting_group = ?");
        $stmt->execute([$key, $group]);
        
        if (!$stmt->fetch()) {
            // Insert default setting
            $insert = $db->prepare("INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) VALUES (?, ?, ?, ?)");
            $insert->execute([$key, $value, $type, $group]);
            $results['settings_seeded']++;
        }
    }

    // Seed legal_pages table
    $privacyContent = '<h1>Privacy Policy</h1>
<p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information.</p>

<h2>Information We Collect</h2>
<p>We collect personal information that you provide to us, including:</p>
<ul>
<li>Contact information (name, phone number, email address)</li>
<li>Medical history and health information</li>
<li>Appointment preferences</li>
</ul>

<h2>How We Use Your Information</h2>
<p>Your information is used solely for:</p>
<ul>
<li>Providing homeopathic consultation and treatment</li>
<li>Scheduling and managing appointments</li>
<li>Communicating with you about your treatment</li>
<li>Follow-up care and reminders</li>
</ul>

<h2>Data Protection</h2>
<p>We implement appropriate security measures to protect your personal information. Your data is stored securely and is only accessible to authorized healthcare providers.</p>

<h2>Your Rights</h2>
<p>You have the right to:</p>
<ul>
<li>Access your personal information</li>
<li>Request correction of inaccurate data</li>
<li>Request deletion of your data</li>
<li>Opt-out of communications</li>
</ul>

<h2>Contact Us</h2>
<p>If you have any questions about this Privacy Policy, please contact us.</p>';

    $termsContent = '<h1>Terms & Conditions</h1>
<p>By using our services, you agree to these terms and conditions.</p>

<h2>Consultation Services</h2>
<p>Our homeopathic consultations are provided for informational purposes only. We recommend maintaining a relationship with your primary healthcare provider.</p>

<h2>Appointment Policy</h2>
<ul>
<li>Please arrive on time for your appointment</li>
<li>Cancellations should be made at least 24 hours in advance</li>
<li>Online consultations require a stable internet connection</li>
</ul>

<h2>Treatment Recommendations</h2>
<p>Homeopathic remedies are prescribed based on the principle of like cures like. Results may vary depending on individual conditions. Continue any conventional treatments as advised by your primary physician.</p>

<h2>Payment Terms</h2>
<p>Payment is due at the time of consultation. We accept various payment methods for your convenience.</p>

<h2>Limitation of Liability</h2>
<p>While we strive to provide the best possible care, homeopathic treatment results may vary. We are not liable for any outcomes resulting from treatment.</p>

<h2>Changes to Terms</h2>
<p>We reserve the right to modify these terms at any time. Continued use of our services constitutes acceptance of these terms.</p>';

    $defaultLegalPages = [
        ['Privacy Policy', 'privacy-policy', $privacyContent],
        ['Terms & Conditions', 'terms-conditions', $termsContent]
    ];

    foreach ($defaultLegalPages as $page) {
        list($title, $slug, $content) = $page;
        
        // Check if page exists
        $stmt = $db->prepare("SELECT id FROM legal_pages WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if (!$stmt->fetch()) {
            $insert = $db->prepare("INSERT INTO legal_pages (title, slug, content) VALUES (?, ?, ?)");
            $insert->execute([$title, $slug, $content]);
            $results['legal_pages_seeded']++;
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Database seeded successfully',
        'results' => $results
    ]);

} catch (PDOException $e) {
    error_log("Seed error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to seed database: ' . $e->getMessage()], 500);
}

