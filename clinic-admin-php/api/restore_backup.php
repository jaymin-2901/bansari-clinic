<?php
/**
 * Bansari Homeopathy – Restore API Proxy
 * Routes restore operations to backup-system/scripts/restore_database.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Return JSON error instead of redirect for unauthenticated API requests
if (!isAdmin()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

require __DIR__ . '/../../backup-system/scripts/restore_database.php';
