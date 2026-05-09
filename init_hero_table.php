<?php
require_once __DIR__ . '/backend-php/config/clinic_db.php';
try {
    $db = getClinicDB();
    $sql = "CREATE TABLE IF NOT EXISTS hero_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        desktop_image VARCHAR(255) NOT NULL,
        mobile_image VARCHAR(255) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    echo "Table 'hero_images' created successfully or already exists.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
