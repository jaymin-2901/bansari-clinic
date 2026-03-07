<?php
// List admin users and their credentials
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    $stmt = $db->query("SELECT id, email, password, role FROM users WHERE role = 'admin'");
    foreach ($stmt as $row) {
        echo 'ID: ' . $row['id'] . ', Email: ' . $row['email'] . ', Password: ' . $row['password'] . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
