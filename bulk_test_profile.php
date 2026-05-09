<?php
$ids = [2, 12, 1, 5];
foreach ($ids as $id) {
    echo "--- Testing Patient ID: $id ---\n";
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['patient_id'] = $id;
    ob_start();
    require 'backend-php/api/patient/profile.php';
    $output = ob_get_clean();
    echo "Output: " . substr($output, 0, 100) . "...\n";
    $data = json_decode($output, true);
    if ($data && isset($data['success'])) {
        echo "SUCCESS: " . ($data['success'] ? "YES" : "NO") . "\n";
        if (!$data['success']) echo "ERROR: " . $data['error'] . "\n";
    } else {
        echo "FAILED TO DECODE JSON or EMPTY OUTPUT\n";
    }
}
