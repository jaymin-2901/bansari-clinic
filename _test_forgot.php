<?php
require 'backend-php/config/clinic_db.php';
$data = [
    'email' => 'test@example.com',
    'captcha_token' => 'test_captcha_token' // verifyCaptcha returns true if RECAPTCHA_SECRET is test secret
];

$url = 'http://localhost:8000/api/auth/forgot_password.php';
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

// Check database for token
echo "\nChecking database for tokens:\n";
$db = getClinicDB();
$stmt = $db->query("SELECT * FROM password_resets WHERE email = 'test@example.com'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
