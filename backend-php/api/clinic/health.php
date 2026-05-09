<?php
/**
 * Health Check Endpoint
 * Used to test connectivity between frontend and backend
 */

require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

// Return success response
jsonResponse([
    'success' => true,
    'message' => 'Backend is reachable',
    'timestamp' => time(),
    'backend_url' => 'https://bansari-homeopathic-clinic.infinityfreeapp.com',
    'frontend_url' => defined('FRONTEND_URL') ? FRONTEND_URL : 'unknown',
]);

