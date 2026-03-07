<?php
/**
 * Bansari Homeopathy – Admin Logout
 */
require_once __DIR__ . '/../backend-php/security/session_config.php';
session_start();
session_unset();
session_destroy();

// Clear session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

header('Location: login.php');
exit;
