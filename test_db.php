<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3307;dbname=mediconnect', 'root', '');
    echo "Connected perfectly.\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
