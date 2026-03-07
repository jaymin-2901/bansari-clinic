<?php
/**
 * API: Contact Form Submission
 * POST /backend/api/clinic/contact.php
 */

require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = getJsonInput();

$error = validateRequired($data, ['name', 'message']);
if ($error) {
    jsonResponse(['error' => $error], 400);
}

try {
    $db = getClinicDB();
    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        sanitize($data['name']),
        sanitize($data['email'] ?? ''),
        sanitize($data['phone'] ?? ''),
        sanitize($data['subject'] ?? ''),
        sanitize($data['message'])
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Thank you for your message! We will get back to you soon.'
    ], 201);

} catch (PDOException $e) {
    error_log("Contact form error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to submit message. Please try again.'], 500);
}
