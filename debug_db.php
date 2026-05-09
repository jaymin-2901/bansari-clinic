<?php
require_once __DIR__ . '/backend-php/config/clinic_config.php';
require_once __DIR__ . '/backend-php/config/clinic_db.php';

$db = getClinicDB();
$notices = $db->query("SELECT * FROM clinic_status")->fetchAll(PDO::FETCH_ASSOC);
echo "--- ALL NOTICES ---\n";
print_r($notices);

$checkTime = date('Y-m-d H:i:s');
echo "Checking for NOW ($checkTime)...\n";
$res = getClinicStatus($checkTime);
print_r($res);

$tomorrow = date('Y-m-d', strtotime('+1 day'));
echo "Checking for Tomorrow 10AM ($tomorrow 10:00:00)...\n";
$res2 = getClinicStatus($tomorrow . ' 10:00:00');
print_r($res2);
?>
