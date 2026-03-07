<?php
/**
 * API: Website Settings (Public)
 * GET /backend/api/clinic/settings.php?group=about|contact|home|general
 */

require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$group = sanitize($_GET['group'] ?? 'general');
$allowedGroups = ['about', 'contact', 'home', 'general'];

if (!in_array($group, $allowedGroups)) {
    jsonResponse(['error' => 'Invalid settings group'], 400);
}

try {
    $db = getClinicDB();

    if ($group === 'general') {
        // Return all groups
        $stmt = $db->prepare("SELECT setting_key, setting_value, setting_type, setting_group FROM website_settings");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT setting_key, setting_value, setting_type, setting_group FROM website_settings WHERE setting_group = ?");
        $stmt->execute([$group]);
    }

    $rows = $stmt->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $value = $row['setting_value'];
        if ($row['setting_type'] === 'image' && $value) {
            // Don't modify paths that are already full URLs
            if (!str_starts_with($value, 'http')) {
                // Return full path starting with /uploads/ that getImageUrl can use
                $value = '/uploads/' . $row['setting_group'] . '/' . $value;
            }
        }
        $settings[$row['setting_key']] = $value;
    }

    jsonResponse(['success' => true, 'data' => $settings]);

} catch (PDOException $e) {
    error_log("Settings fetch error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch settings'], 500);
}
