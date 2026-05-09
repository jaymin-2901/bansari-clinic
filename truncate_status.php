<?php
require_once __DIR__ . '/backend-php/config/clinic_config.php';
require_once __DIR__ . '/backend-php/config/clinic_db.php';
$db = getClinicDB();
$db->exec("TRUNCATE TABLE clinic_status");
echo "Table truncated.\n";
?>
