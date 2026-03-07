<?php
require_once __DIR__ . '/clinic-admin-php/includes/db.php';

$db = getClinicDB();
$apts = $db->query('SELECT id, patient_id, appointment_date, appointment_time FROM appointments WHERE appointment_time IS NOT NULL AND appointment_time != "" ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

foreach($apts as $a) {
    echo 'ID: ' . $a['id'] . ' | Date: ' . $a['appointment_date'] . ' | Time: ' . $a['appointment_time'] . PHP_EOL;
}
