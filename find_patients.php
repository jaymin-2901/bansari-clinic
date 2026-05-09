<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'bansari_clinic', 3307);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$res = $conn->query('SELECT patient_id, COUNT(*) as c FROM appointments GROUP BY patient_id ORDER BY c DESC LIMIT 5');
while($row = $res->fetch_assoc()) {
    echo "Patient ID: " . $row['patient_id'] . " | Appointments: " . $row['c'] . "\n";
}
echo "--- LATEST PATIENTS ---\n";
$res = $conn->query('SELECT id, full_name, mobile, created_at FROM patients ORDER BY created_at DESC LIMIT 5');
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['full_name'] . " | Mobile: " . $row['mobile'] . " | Since: " . $row['created_at'] . "\n";
}
