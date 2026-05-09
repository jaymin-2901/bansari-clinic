<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'bansari_clinic', 3307);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$res = $conn->query('SHOW TABLES');
while($row = $res->fetch_array()) echo $row[0] . "\n";
