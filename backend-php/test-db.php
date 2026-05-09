<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing MySQL Connection...\n";
echo "Host: localhost\n";
echo "Port: 3306\n";
echo "User: root\n";
echo "Database: bansari_clinic\n\n";

try {
    $conn = new mysqli("127.0.0.1", "root", "", "bansari_clinic", 3306);
    
    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error . "\n";
    } else {
        echo "✅ Connected successfully!\n";
        
        // Check tables
        $result = $conn->query("SHOW TABLES");
        echo "Tables in database:\n";
        while ($row = $result->fetch_array()) {
            echo "  - " . $row[0] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

