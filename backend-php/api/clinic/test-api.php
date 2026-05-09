<?php
/**
 * Test API Endpoint - Lists all available endpoints
 * Access: https://bansari-homeopathic-clinic.infinityfreeapp.com/api/clinic/test-api.php
 */

require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

$endpoints = [
    'GET /api/clinic/settings.php?group=general' => 'Works (you confirmed)',
    'POST /api/clinic/login.php' => 'Returns 404 (needs upload)',
    'POST /api/clinic/signup.php' => 'Returns 404 (needs upload)',
    'POST /api/clinic/appointments.php' => 'Returns 404 (needs upload)',
    'GET /api/clinic/slots.php?action=closed_days' => 'Returns 404 (needs upload)',
];

jsonResponse([
    'success' => true,
    'message' => 'API is working',
    'backend_url' => 'https://bansari-homeopathic-clinic.infinityfreeapp.com',
    'available_endpoints' => $endpoints,
    'note' => 'Login returns 404 because login.php is not uploaded to server'
]);
