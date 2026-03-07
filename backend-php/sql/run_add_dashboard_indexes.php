<?php
// Run this script once to add recommended indexes for dashboard speed
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    $sql = file_get_contents(__DIR__ . '/add_dashboard_indexes.sql');
    $db->exec($sql);
    echo "Indexes added successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
