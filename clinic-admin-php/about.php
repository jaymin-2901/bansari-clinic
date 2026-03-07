<?php
/**
 * Bansari Homeopathy – Manage About Page Content
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'About Page';

$uploadDir = dirname(__DIR__) . '/uploads/about/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        try {
            $db = getClinicDB();

            // Text fields to save
            $fields = [
                'about_doctor_name', 'about_doctor_title', 'about_doctor_bio',
                'about_clinic_philosophy', 'about_experience',
                'about_mission', 'about_vision'
            ];

            $stmt = $db->prepare("
                INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                VALUES (?, ?, 'textarea', 'about')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $stmt->execute([$field, clean($_POST[$field])]);
                }
            }

            // Handle doctor image upload
            if (isset($_FILES['about_doctor_image']) && $_FILES['about_doctor_image']['error'] === UPLOAD_ERR_OK) {
                $filename = uploadImage($_FILES['about_doctor_image'], $uploadDir, 'doctor');
                if ($filename) {
                    $db->prepare("
                        INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                        VALUES ('about_doctor_image', ?, 'image', 'about')
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ")->execute([$filename]);
                }
            }

            // Handle clinic image upload
            if (isset($_FILES['about_clinic_image']) && $_FILES['about_clinic_image']['error'] === UPLOAD_ERR_OK) {
                $filename = uploadImage($_FILES['about_clinic_image'], $uploadDir, 'clinic');
                if ($filename) {
                    $db->prepare("
                        INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                        VALUES ('about_clinic_image', ?, 'image', 'about')
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ")->execute([$filename]);
                }
            }

            setFlash('success', 'About page content saved successfully.');
        } catch (PDOException $e) {
            error_log('About page save error: ' . $e->getMessage());
            setFlash('error', 'Error saving content. Please try again.');
        }
    }
    header('Location: about.php');
    exit;
}

// Load current settings
try {
    $db = getClinicDB();
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM website_settings WHERE setting_group = 'about'");
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

<div class="form-card" style="max-width: 900px;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <h5 class="fw-bold mb-4"><i class="bi bi-person-badge text-primary"></i> Doctor Profile</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Doctor Name</label>
                <input type="text" name="about_doctor_name" class="form-control" 
                       value="<?= clean($settings['about_doctor_name'] ?? 'Dr. Bansari Patel') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Title / Qualification</label>
                <input type="text" name="about_doctor_title" class="form-control" 
                       value="<?= clean($settings['about_doctor_title'] ?? 'BHMS, MD (Homeopathy)') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Doctor Bio</label>
                <textarea name="about_doctor_bio" class="form-control" rows="4"><?= clean($settings['about_doctor_bio'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Doctor Photo</label>
                <?php if (!empty($settings['about_doctor_image'])): ?>
                <div class="mb-2">
                    <img src="../uploads/about/<?= clean($settings['about_doctor_image']) ?>" class="img-preview" alt="Doctor">
                </div>
                <?php endif; ?>
                <input type="file" name="about_doctor_image" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label">Experience</label>
                <input type="text" name="about_experience" class="form-control" 
                       value="<?= clean($settings['about_experience'] ?? '10+ Years of Experience') ?>"
                       placeholder="e.g., 10+ Years of Experience">
            </div>
        </div>

        <hr class="my-4">

        <h5 class="fw-bold mb-4"><i class="bi bi-building text-success"></i> Clinic Information</h5>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label">Clinic Philosophy</label>
                <textarea name="about_clinic_philosophy" class="form-control" rows="4"><?= clean($settings['about_clinic_philosophy'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Mission</label>
                <textarea name="about_mission" class="form-control" rows="3"><?= clean($settings['about_mission'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Vision</label>
                <textarea name="about_vision" class="form-control" rows="3"><?= clean($settings['about_vision'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Clinic Image</label>
                <?php if (!empty($settings['about_clinic_image'])): ?>
                <div class="mb-2">
                    <img src="../uploads/about/<?= clean($settings['about_clinic_image']) ?>" class="img-preview" alt="Clinic">
                </div>
                <?php endif; ?>
                <input type="file" name="about_clinic_image" class="form-control" accept="image/*">
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
