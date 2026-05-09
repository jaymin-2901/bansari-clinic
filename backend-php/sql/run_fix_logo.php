<?php
// Fix logo DB setting
require_once '../config/clinic_db.php';

$db = getClinicDB();

$stmt = $db->prepare("UPDATE website_settings SET setting_value = 'logo_1773326104_22b54618.png' WHERE setting_key = 'clinic_logo'");
$updated = $stmt->execute();

echo "✅ Logo fixed: $updated rows\n";

$result = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key = 'clinic_logo'")->fetch();
print_r($result);

echo "\nNow logo path: /uploads/logo_1773326104_22b54618.png\n";
echo "Test: http://localhost:8080/uploads/logo_1773326104_22b54618.png\n";

