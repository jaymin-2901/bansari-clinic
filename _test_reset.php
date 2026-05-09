<?php
require 'backend-php/config/clinic_db.php';
$token = 'c1e15fbf55db67721497c69e436439320ab20b5e23c450584366c9677447ebe2';
$data = [
    'token' => $token,
    'password' => 'newpassword123'
];

$url = 'http://localhost:8000/api/auth/reset_password.php';
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo $result;

// Verify password in database
echo "\nChecking patient table for updated password:\n";
$db = getClinicDB();
$stmt = $db->query("SELECT id, full_name, password FROM patients WHERE email = 'test@example.com' AND is_registered = 1");
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if ($patient) {
    echo "Patient found: " . $patient['full_name'] . "\n";
    if (password_verify('newpassword123', $patient['password'])) {
        echo "Password VERIFIED successfully!\n";
    } else {
        echo "Password verification FAILED.\n";
    }
} else {
    echo "Patient not found.\n";
}

// Check if token was deleted
echo "Checking if token was deleted:\n";
$stmt = $db->query("SELECT COUNT(*) FROM password_resets WHERE token = '$token'");
echo "Tokens count: " . $stmt->fetchColumn() . "\n";
