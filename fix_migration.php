<?php
/**
 * Fix migration: Create missing tables and fix admin password
 * Run once: php fix_migration.php
 */
require_once __DIR__ . '/backend-php/config/clinic_config.php';
require_once __DIR__ . '/backend-php/config/clinic_db.php';

$db = getClinicDB();

echo "=== Fixing bansari_clinic database ===\n\n";

// 1. Create clinic_schedule table
echo "1. Creating clinic_schedule table...\n";
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS clinic_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday ... 6=Saturday',
            is_open TINYINT(1) DEFAULT 1,
            opening_time TIME NULL,
            closing_time TIME NULL,
            break_start TIME NULL,
            break_end TIME NULL,
            new_patient_duration INT DEFAULT 30 COMMENT 'minutes per slot',
            old_patient_duration INT DEFAULT 15 COMMENT 'minutes per slot',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_day (day_of_week)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");

    // Insert default schedule (Mon-Sat open, Sunday closed)
    $check = $db->query("SELECT COUNT(*) FROM clinic_schedule")->fetchColumn();
    if ($check == 0) {
        $db->exec("
            INSERT INTO clinic_schedule (day_of_week, is_open, opening_time, closing_time, break_start, break_end, new_patient_duration, old_patient_duration) VALUES
            (0, 0, NULL, NULL, NULL, NULL, 30, 15),
            (1, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
            (2, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
            (3, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
            (4, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
            (5, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15),
            (6, 1, '09:30:00', '20:00:00', '13:00:00', '17:00:00', 30, 15)
        ");
        echo "   -> Created with default schedule (Mon-Sat open, Sunday closed)\n";
    } else {
        echo "   -> Table already had data\n";
    }
} catch (PDOException $e) {
    echo "   -> ERROR: " . $e->getMessage() . "\n";
}

// 2. Create legal_pages table
echo "2. Creating legal_pages table...\n";
try {
    $db->exec("
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
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");

    // Insert default legal pages
    $check = $db->query("SELECT COUNT(*) FROM legal_pages")->fetchColumn();
    if ($check == 0) {
        $db->exec("
            INSERT INTO legal_pages (title, slug, content) VALUES
            ('Privacy Policy', 'privacy-policy', '<h2>Privacy Policy</h2><p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information.</p>'),
            ('Terms & Conditions', 'terms-conditions', '<h2>Terms & Conditions</h2><p>By using our services, you agree to these terms and conditions.</p>')
        ");
        echo "   -> Created with default privacy policy and terms pages\n";
    } else {
        echo "   -> Table already had data\n";
    }
} catch (PDOException $e) {
    echo "   -> ERROR: " . $e->getMessage() . "\n";
}

// 3. Fix admin password hash
echo "3. Fixing admin password hash...\n";
try {
    $admin = $db->query("SELECT id, email, password FROM admins WHERE email = 'admin@bansari.com' LIMIT 1")->fetch();
    if ($admin) {
        if (!password_verify('Admin@123', $admin['password'])) {
            $correctHash = password_hash('Admin@123', PASSWORD_DEFAULT);
            $db->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$correctHash, $admin['id']]);
            echo "   -> Password hash updated for admin@bansari.com\n";
        } else {
            echo "   -> Password already correct\n";
        }
    } else {
        echo "   -> Admin account not found!\n";
    }
} catch (PDOException $e) {
    echo "   -> ERROR: " . $e->getMessage() . "\n";
}

// 4. Add plain_password column to patients table (Task 7)
echo "4. Adding plain_password column to patients table...\n";
try {
    $db->exec("ALTER TABLE patients ADD COLUMN plain_password VARCHAR(255) NULL");
    $db->exec("CREATE INDEX idx_plain_password ON patients(plain_password)");
    echo "   -> Column added successfully\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "   -> Column already exists\n";
    } else {
        echo "   -> ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migration complete ===\n";

// Verify
echo "\n=== Verification ===\n";
$tables = ['clinic_schedule', 'legal_pages'];
foreach ($tables as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "$t: $count rows - OK\n";
    } catch (Exception $e) {
        echo "$t: STILL BROKEN - " . $e->getMessage() . "\n";
    }
}

$admin = $db->query("SELECT password FROM admins WHERE email = 'admin@bansari.com'")->fetch();
echo "Login verify 'Admin@123': " . (password_verify('Admin@123', $admin['password']) ? 'YES' : 'NO') . "\n";

