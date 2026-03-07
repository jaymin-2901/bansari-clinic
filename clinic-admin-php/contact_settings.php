<?php
/**
 * Bansari Homeopathy – Manage Contact Page Settings
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Contact Page';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        try {
            $db = getClinicDB();

            $fields = [
                'contact_address', 'contact_phone', 'contact_whatsapp',
                'contact_email', 'contact_map_iframe', 'contact_map_url', 'contact_hours'
            ];

            $stmt = $db->prepare("
                INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                VALUES (?, ?, 'text', 'contact')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $stmt->execute([$field, clean($_POST[$field])]);
                }
            }

            setFlash('success', 'Contact settings saved successfully.');
        } catch (PDOException $e) {
            setFlash('error', 'Error saving settings.');
        }
    }
    header('Location: contact_settings.php');
    exit;
}

// Load current settings
try {
    $db = getClinicDB();
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM website_settings WHERE setting_group = 'contact'");
    $stmt->execute();
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="form-card" style="max-width: 800px;">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Clinic Address</label>
                <textarea name="contact_address" class="form-control" rows="3"><?= clean($settings['contact_address'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="text" name="contact_phone" class="form-control" 
                       value="<?= clean($settings['contact_phone'] ?? '') ?>" placeholder="+91 63543 88539">
            </div>
            <div class="col-md-6">
                <label class="form-label">WhatsApp Number</label>
                <input type="text" name="contact_whatsapp" class="form-control" 
                       value="<?= clean($settings['contact_whatsapp'] ?? '') ?>" placeholder="+91 63543 88539">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="contact_email" class="form-control" 
                       value="<?= clean($settings['contact_email'] ?? '') ?>" placeholder="info@bansarihomeopathy.com">
            </div>
            <div class="col-md-6">
                <label class="form-label">Working Hours</label>
                <textarea name="contact_hours" class="form-control" rows="2"><?= clean($settings['contact_hours'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Google Maps Embed URL</label>
                <input type="text" name="contact_map_iframe" class="form-control" 
                       value="<?= clean($settings['contact_map_iframe'] ?? '') ?>"
                       placeholder="https://www.google.com/maps/embed?pb=...">
                <small class="text-muted">Paste the Google Maps embed URL (from iframe src)</small>
            </div>
            <div class="col-12">
                <label class="form-label">Google Maps Link (for Directions)</label>
                <input type="text" name="contact_map_url" class="form-control" 
                       value="<?= clean($settings['contact_map_url'] ?? '') ?>"
                       placeholder="https://goo.gl/maps/...">
                <small class="text-muted">Paste the shareable Google Maps link (goo.gl/maps or maps.google.com)</small>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
