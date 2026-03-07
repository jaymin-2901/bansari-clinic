<?php
require_once __DIR__ . '/../config/clinic_db.php';
$db = getClinicDB();
$r = $db->query('SELECT id, name, email, role, is_active FROM admins');
$admins = $r->fetchAll();
echo json_encode($admins, JSON_PRETTY_PRINT);
