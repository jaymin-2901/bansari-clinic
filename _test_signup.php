<?php
require 'backend-php/config/clinic_db.php';
$data = [
    'full_name' => 'Test User',
    'mobile' => '9999999999',
    'password' => 'password123',
    'email' => 'test@example.com'
];

$url = 'http://localhost:8000/api/clinic/signup.php';
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
