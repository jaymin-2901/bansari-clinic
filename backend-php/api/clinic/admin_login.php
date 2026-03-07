<?php
/**
 * Bansari Homeopathy – Admin Login API
 * POST: { email, password }
 *
 * Returns:
 *   success → { success: true, message, admin: { id, name, email, role } }
 *   failure → { success: false, message }
 */
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getJsonInput();

$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email)) {
    jsonResponse(['success' => false, 'message' => 'Please enter your email address.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if (empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Please enter your password.'], 400);
}

try {
    $db = getClinicDB();
    $stmt = $db->prepare("SELECT id, name, email, password, role FROM admins WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin) {
        jsonResponse(['success' => false, 'message' => 'No admin account found with this email.'], 401);
    }

    if (!password_verify($password, $admin['password'])) {
        error_log(date('[Y-m-d H:i:s]') . " ADMIN LOGIN FAIL | email={$email}");
        jsonResponse(['success' => false, 'message' => 'Incorrect password. Please try again.'], 401);
    }

    // Update last login
    $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'admin' => [
            'id'    => (int)$admin['id'],
            'name'  => $admin['name'],
            'email' => $admin['email'],
            'role'  => $admin['role'],
        ]
    ]);

} catch (PDOException $e) {
    error_log('Admin login DB error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'A server error occurred. Please try again later.'], 500);
}
