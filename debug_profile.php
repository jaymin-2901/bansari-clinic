<?php
try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'bansari_clinic', 3307);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $res = $conn->query('SELECT id, full_name, mobile FROM patients ORDER BY created_at DESC LIMIT 1');
    $patient = $res->fetch_assoc();
    if (!$patient) {
        echo "No patients found";
    } else {
        echo "Testing with Patient ID: " . $patient['id'] . " (" . $patient['full_name'] . ")\n";
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['patient_id'] = $patient['id'];
        
        // Suppress output buffering to capture result
        ob_start();
        require 'backend-php/api/patient/profile.php';
        $output = ob_get_clean();
        echo "API Output:\n" . $output;
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
