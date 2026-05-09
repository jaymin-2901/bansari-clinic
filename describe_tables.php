<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'bansari_clinic', 3307);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$res = $conn->query('DESCRIBE appointments');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "--- DESCRIBE patients ---\n";
$res = $conn->query('DESCRIBE patients');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
