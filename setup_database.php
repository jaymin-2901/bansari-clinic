<?php
/**
 * Database Setup Script
 * Run this once to create necessary tables and seed default data
 * 
 * Usage: Open this file in browser after starting PHP server
 * http://localhost:8080/setup_database.php
 */

// Database configuration - UPDATE THESE VALUES
$dbHost = 'localhost';
$dbName = 'bansari_homeopathy';
$dbUser = 'root';
$dbPass = '';

try {
    // Connect to MySQL (without database first to create it if needed)
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database created/verified<br>";
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create website_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `website_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(255) NOT NULL,
            `setting_value` TEXT,
            `setting_type` VARCHAR(50) DEFAULT 'text',
            `setting_group` VARCHAR(50) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_key_group (`setting_key`, `setting_group`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ website_settings table created<br>";
    
    // Create legal_pages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `legal_pages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `content` LONGTEXT NOT NULL,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ legal_pages table created<br>";
    
    // Seed default settings
    $defaultSettings = [
        // Contact settings
        ['contact_address', '212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.', 'text', 'contact'],
        ['contact_phone', '+91 63543 88539', 'text', 'contact'],
        ['contact_whatsapp', '+91 63543 88539', 'text', 'contact'],
        ['contact_email', 'info@bansarihomeopathy.com', 'text', 'contact'],
        ['contact_hours', '9:30 AM - 1:00 PM, 5:00 PM - 8:00 PM', 'text', 'contact'],
        
        // Home page settings
        ['home_hero_title', 'Gentle Healing,', 'text', 'home'],
        ['home_hero_subtitle', 'Lasting Results', 'text', 'home'],
        ['home_hero_description', 'Experience personalized homeopathic treatment with Dr. Bansari Patel. Holistic care for chronic and acute conditions.', 'textarea', 'home'],
        
        // General settings
        ['clinic_name', 'Bansari Homeopathy Clinic', 'text', 'general'],
        ['clinic_tagline', 'Gentle Healing, Lasting Results', 'text', 'general'],
        
        // About page settings
        ['about_doctor_name', 'Dr. Bansari Patel', 'text', 'about'],
        ['about_doctor_title', 'BHMS, MD (Homeopathy)', 'text', 'about'],
        ['about_doctor_bio', 'Dr. Bansari Patel is a dedicated homeopathic practitioner with years of experience.', 'textarea', 'about'],
        ['about_experience', '10+ Years of Experience', 'text', 'about'],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO website_settings (setting_key, setting_value, setting_type, setting_group) VALUES (?, ?, ?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    echo "✅ Default settings seeded (" . count($defaultSettings) . " settings)<br>";
    
    // Seed legal pages
    $privacyContent = '<h1>Privacy Policy</h1>
<p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information.</p>
<h2>Information We Collect</h2>
<p>We collect personal information that you provide to us, including contact information and medical history.</p>
<h2>How We Use Your Information</h2>
<p>Your information is used solely for providing homeopathic consultation and treatment.</p>
<h2>Contact Us</h2>
<p>If you have any questions about this Privacy Policy, please contact us.</p>';

    $termsContent = '<h1>Terms & Conditions</h1>
<p>By using our services, you agree to these terms and conditions.</p>
<h2>Consultation Services</h2>
<p>Our homeopathic consultations are provided for informational purposes only.</p>
<h2>Appointment Policy</h2>
<ul>
<li>Please arrive on time for your appointment</li>
<li>Cancellations should be made at least 24 hours in advance</li>
</ul>
<h2>Contact Us</h2>
<p>If you have any questions, please contact us.</p>';

    $pdo->exec("INSERT IGNORE INTO legal_pages (title, slug, content) VALUES ('Privacy Policy', 'privacy-policy', '$privacyContent')");
    $pdo->exec("INSERT IGNORE INTO legal_pages (title, slug, content) VALUES ('Terms & Conditions', 'terms-conditions', '$termsContent')");
    echo "✅ Legal pages seeded<br>";
    
    echo "<br><strong>✅ SETUP COMPLETE!</strong><br>";
    echo "<br>Now test the API:<br>";
    echo "<a href='http://localhost:8080/api/clinic/settings.php?group=contact'>Test Contact Settings API</a><br>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<br>Please check your database credentials in this file.";
}
?>

<style>
body { font-family: Arial; padding: 20px; line-height: 1.6; }
a { color: #007bff; }
</style>
