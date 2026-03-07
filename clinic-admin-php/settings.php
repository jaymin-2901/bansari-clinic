<?php
/**
 * Bansari Homeopathy – Website Settings (All Sections)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Settings';

$generalUploadDir = dirname(__DIR__) . '/public/uploads/general/';
$aboutUploadDir   = dirname(__DIR__) . '/public/uploads/about/';
$homeUploadDir    = dirname(__DIR__) . '/public/uploads/home/';
$clinicImagesDir  = dirname(__DIR__) . '/public/uploads/clinic-images/';
if (!is_dir($generalUploadDir)) mkdir($generalUploadDir, 0755, true);
if (!is_dir($aboutUploadDir))   mkdir($aboutUploadDir, 0755, true);
if (!is_dir($homeUploadDir))    mkdir($homeUploadDir, 0755, true);
if (!is_dir($clinicImagesDir)) mkdir($clinicImagesDir, 0755, true);

$activeTab = $_GET['tab'] ?? 'general';
if (!in_array($activeTab, ['general', 'home', 'about', 'contact', 'clinic'])) $activeTab = 'general';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $section = $_POST['section'] ?? '';
        try {
            $db = getClinicDB();

            if ($section === 'general') {
                $fields = ['clinic_name', 'clinic_tagline'];
                $stmt = $db->prepare("
                    INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                    VALUES (?, ?, 'text', 'general')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                foreach ($fields as $field) {
                    if (isset($_POST[$field])) {
                        $stmt->execute([$field, trim($_POST[$field])]);
                    }
                }
                // Logo upload
                if (isset($_FILES['clinic_logo']) && $_FILES['clinic_logo']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadImage($_FILES['clinic_logo'], $generalUploadDir, 'logo');
                    if ($filename) {
                        $db->prepare("
                            INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                            VALUES ('clinic_logo', ?, 'image', 'general')
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ")->execute([$filename]);
                    }
                }
                setFlash('success', 'General settings saved.');
                $activeTab = 'general';

            } elseif ($section === 'home') {
                $fields = ['home_hero_title', 'home_hero_subtitle', 'home_hero_description'];
                $stmt = $db->prepare("
                    INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                    VALUES (?, ?, 'text', 'home')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                foreach ($fields as $field) {
                    if (isset($_POST[$field])) {
                        $stmt->execute([$field, trim($_POST[$field])]);
                    }
                }
                // Hero image upload (Desktop)
                if (isset($_FILES['home_hero_image']) && $_FILES['home_hero_image']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadImage($_FILES['home_hero_image'], $homeUploadDir, 'hero');
                    if ($filename) {
                        $db->prepare("
                            INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                            VALUES ('home_hero_image', ?, 'image', 'home')
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ")->execute([$filename]);
                    }
                }
                // Hero image upload (Mobile)
                if (isset($_FILES['home_hero_image_mobile']) && $_FILES['home_hero_image_mobile']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadImage($_FILES['home_hero_image_mobile'], $homeUploadDir, 'hero-mobile');
                    if ($filename) {
                        $db->prepare("
                            INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                            VALUES ('home_hero_image_mobile', ?, 'image', 'home')
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ")->execute([$filename]);
                    }
                }
                setFlash('success', 'Home page settings saved.');
                $activeTab = 'home';

            } elseif ($section === 'about') {
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
                        $stmt->execute([$field, trim($_POST[$field])]);
                    }
                }
                // Doctor image upload
                if (isset($_FILES['about_doctor_image']) && $_FILES['about_doctor_image']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadImage($_FILES['about_doctor_image'], $aboutUploadDir, 'doctor');
                    if ($filename) {
                        $db->prepare("
                            INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                            VALUES ('about_doctor_image', ?, 'image', 'about')
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ")->execute([$filename]);
                    }
                }
                // Clinic image upload
                if (isset($_FILES['about_clinic_image']) && $_FILES['about_clinic_image']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadImage($_FILES['about_clinic_image'], $aboutUploadDir, 'clinic');
                    if ($filename) {
                        $db->prepare("
                            INSERT INTO website_settings (setting_key, setting_value, setting_type, setting_group) 
                            VALUES ('about_clinic_image', ?, 'image', 'about')
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ")->execute([$filename]);
                    }
                }
                setFlash('success', 'About page settings saved.');
                $activeTab = 'about';

            } elseif ($section === 'contact') {
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
                        $stmt->execute([$field, trim($_POST[$field])]);
                    }
                }
                setFlash('success', 'Contact settings saved.');
                $activeTab = 'contact';
            }
        } catch (PDOException $e) {
            error_log('Settings save error: ' . $e->getMessage());
            setFlash('error', 'Error saving settings. Please try again.');
        }
    }
    header('Location: settings.php?tab=' . urlencode($activeTab));
    exit;
}

// Load all settings
try {
    $db = getClinicDB();
    $rows = $db->query("SELECT setting_key, setting_value FROM website_settings")->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Settings Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" href="?tab=general">
            <i class="bi bi-gear me-1"></i> General
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'home' ? 'active' : '' ?>" href="?tab=home">
            <i class="bi bi-house me-1"></i> Home Page
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'about' ? 'active' : '' ?>" href="?tab=about">
            <i class="bi bi-person-badge me-1"></i> About Page
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'contact' ? 'active' : '' ?>" href="?tab=contact">
            <i class="bi bi-telephone me-1"></i> Contact
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'clinic' ? 'active' : '' ?>" href="?tab=clinic">
            <i class="bi bi-images me-1"></i> Clinic Images
        </a>
    </li>
</ul>

<!-- ═══ GENERAL TAB ═══ -->
<?php if ($activeTab === 'general'): ?>
<div class="form-card" style="max-width: 800px;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="section" value="general">

        <h5 class="fw-bold mb-4"><i class="bi bi-gear text-primary"></i> General Settings</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Clinic Name</label>
                <input type="text" name="clinic_name" class="form-control"
                       value="<?= clean($settings['clinic_name'] ?? 'Bansari Homeopathy Clinic') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Tagline</label>
                <input type="text" name="clinic_tagline" class="form-control"
                       value="<?= clean($settings['clinic_tagline'] ?? 'Gentle Healing, Lasting Results') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Clinic Logo</label>
                <?php if (!empty($settings['clinic_logo'])): ?>
                <div class="mb-2">
                    <img src="/uploads/general/<?= clean($settings['clinic_logo']) ?>" class="img-preview" alt="Logo">
                </div>
                <?php endif; ?>
                <input type="file" name="clinic_logo" class="form-control" accept="image/*">
                <small class="text-muted">Max 5MB. Accepted: JPG, PNG, WebP</small>
            </div>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg me-1"></i> Save General Settings
        </button>
    </form>
</div>
<?php endif; ?>

<!-- ═══ HOME PAGE TAB ═══ -->
<?php if ($activeTab === 'home'): ?>
<div class="form-card" style="max-width: 800px;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="section" value="home">

        <h5 class="fw-bold mb-4"><i class="bi bi-house text-success"></i> Home Page Settings</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Hero Title</label>
                <input type="text" name="home_hero_title" class="form-control"
                       value="<?= clean($settings['home_hero_title'] ?? '') ?>"
                       placeholder="e.g., Bansari Homeopathy">
            </div>
            <div class="col-md-6">
                <label class="form-label">Hero Subtitle</label>
                <input type="text" name="home_hero_subtitle" class="form-control"
                       value="<?= clean($settings['home_hero_subtitle'] ?? '') ?>"
                       placeholder="e.g., Gentle Healing, Lasting Results">
            </div>
            <div class="col-12">
                <label class="form-label">Hero Description</label>
                <textarea name="home_hero_description" class="form-control" rows="3"
                          placeholder="Short description shown on the hero banner"><?= clean($settings['home_hero_description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Hero Background Image (Desktop)</label>
                <?php if (!empty($settings['home_hero_image'])): ?>
                <div class="mb-2">
                    <img src="/uploads/home/<?= clean($settings['home_hero_image']) ?>" class="img-preview" alt="Hero Desktop">
                </div>
                <?php endif; ?>
                <input type="file" name="home_hero_image" class="form-control" accept="image/*">
                <small class="text-muted">Max 5MB. Accepted: JPG, PNG, WebP. Shown on screens wider than 768px.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Hero Background Image (Mobile)</label>
                <?php if (!empty($settings['home_hero_image_mobile'])): ?>
                <div class="mb-2">
                    <img src="/uploads/home/<?= clean($settings['home_hero_image_mobile']) ?>" class="img-preview" alt="Hero Mobile">
                </div>
                <?php endif; ?>
                <input type="file" name="home_hero_image_mobile" class="form-control" accept="image/*">
                <small class="text-muted">Max 5MB. Accepted: JPG, PNG, WebP. Shown on screens 768px and narrower. If not uploaded, desktop image will be used.</small>
            </div>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg me-1"></i> Save Home Settings
        </button>
    </form>
</div>
<?php endif; ?>

<!-- ═══ ABOUT PAGE TAB ═══ -->
<?php if ($activeTab === 'about'): ?>
<div class="form-card" style="max-width: 900px;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="section" value="about">

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
                    <img src="/uploads/about/<?= clean($settings['about_doctor_image']) ?>" class="img-preview" alt="Doctor">
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
                    <img src="/uploads/about/<?= clean($settings['about_clinic_image']) ?>" class="img-preview" alt="Clinic">
                </div>
                <?php endif; ?>
                <input type="file" name="about_clinic_image" class="form-control" accept="image/*">
            </div>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg me-1"></i> Save About Settings
        </button>
    </form>
</div>
<?php endif; ?>

<!-- ═══ CONTACT TAB ═══ -->
<?php if ($activeTab === 'contact'): ?>
<div class="form-card" style="max-width: 800px;">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="section" value="contact">

        <h5 class="fw-bold mb-4"><i class="bi bi-telephone text-info"></i> Contact Information</h5>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label">Clinic Address</label>
                <textarea name="contact_address" class="form-control" rows="3"
                          placeholder="Full clinic address"><?= clean($settings['contact_address'] ?? '') ?></textarea>
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
                <textarea name="contact_hours" class="form-control" rows="2"
                          placeholder="Mon-Sat: 9:00 AM - 1:00 PM, 5:00 PM - 8:00 PM"><?= clean($settings['contact_hours'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Google Maps Embed URL</label>
                <input type="text" name="contact_map_iframe" class="form-control" 
                       value="<?= clean($settings['contact_map_iframe'] ?? '') ?>"
                       placeholder="https://www.google.com/maps/embed?pb=...">
                <small class="text-muted">Paste the Google Maps embed URL (from iframe src)</small>
            </div>
            <div class="col-12">
                <label class="form-label">Google Maps Link (Directions)</label>
                <input type="text" name="contact_map_url" class="form-control" 
                       value="<?= clean($settings['contact_map_url'] ?? '') ?>"
                       placeholder="https://goo.gl/maps/...">
                <small class="text-muted">Shareable Google Maps link for "Get Directions" button</small>
            </div>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg me-1"></i> Save Contact Settings
        </button>
    </form>
</div>
<?php endif; ?>

<!-- ═══ CLINIC IMAGES TAB ═══ -->
<?php if ($activeTab === 'clinic'): ?>
<?php
// Get clinic images from database
try {
    $db = getClinicDB();
    $clinicImages = $db->query("SELECT id, image_path, created_at FROM clinic_images ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $clinicImages = [];
}
$imageBaseUrl = '/uploads/clinic-images';
?>
<div class="form-card" style="max-width: 900px;">
    <h5 class="fw-bold mb-4"><i class="bi bi-images text-success"></i> Clinic Images Gallery</h5>
    <p class="text-muted mb-4">Upload multiple images of your clinic to display on the About Us page. These images will appear in a gallery on the frontend.</p>
    
    <!-- Upload Form -->
    <form id="clinicImageUploadForm" enctype="multipart/form-data" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label">Upload New Image</label>
                <input type="file" name="clinic_image" class="form-control" accept="image/*" required id="clinicImageInput">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100" id="uploadBtn">
                    <i class="bi bi-upload me-1"></i> Upload Image
                </button>
            </div>
        </div>
        <small class="text-muted">Max 5MB. Accepted: JPG, PNG, WebP, GIF</small>
    </form>

    <!-- Upload Progress -->
    <div id="uploadProgress" class="alert alert-info d-none" role="alert">
        <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            <span>Uploading image...</span>
        </div>
    </div>

    <!-- Upload Result -->
    <div id="uploadResult"></div>

    <hr class="my-4">

    <!-- Existing Images Gallery -->
    <h6 class="fw-bold mb-3">Uploaded Images (<span id="imageCount"><?= count($clinicImages) ?></span>)</h6>
    
    <?php if (empty($clinicImages)): ?>
    <div class="text-center py-5 bg-light rounded">
        <i class="bi bi-images text-muted" style="font-size: 3rem;"></i>
        <p class="text-muted mt-2">No clinic images uploaded yet.</p>
    </div>
    <?php else: ?>
    <div class="row g-3" id="clinicImagesGallery">
        <?php foreach ($clinicImages as $img): ?>
        <div class="col-md-4 col-sm-6" id="image-card-<?= $img['id'] ?>">
            <div class="card h-100">
                <img src="<?= $imageBaseUrl ?>/<?= clean($img['image_path']) ?>" 
                     class="card-img-top" 
                     alt="Clinic Image" 
                     style="height: 200px; object-fit: cover;"
                     onerror="this.src='/assets/img/placeholder.jpg'">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><?= date('d M Y', strtotime($img['created_at'])) ?></small>
                        <button type="button" class="btn btn-sm btn-danger delete-image" 
                                data-id="<?= $img['id'] ?>"
                                title="Delete Image">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Upload handling
document.getElementById('clinicImageUploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const uploadBtn = document.getElementById('uploadBtn');
    const progressDiv = document.getElementById('uploadProgress');
    const resultDiv = document.getElementById('uploadResult');
    const fileInput = document.getElementById('clinicImageInput');
    
    // Check if file is selected
    if (!fileInput.files[0]) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select an image to upload.</div>';
        return;
    }
    
    // Show progress
    uploadBtn.disabled = true;
    progressDiv.classList.remove('d-none');
    resultDiv.innerHTML = '';
    
    try {
        const response = await fetch('/api/clinic/clinic_images.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success">Image uploaded successfully! Reloading...</div>';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Upload failed') + '</div>';
        }
    } catch (err) {
        resultDiv.innerHTML = '<div class="alert alert-danger">Error uploading image: ' + err.message + '</div>';
    } finally {
        uploadBtn.disabled = false;
        progressDiv.classList.add('d-none');
    }
});

// Delete handling
document.querySelectorAll('.delete-image').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Are you sure you want to delete this image?')) return;
        
        const imageId = this.dataset.id;
        const card = document.getElementById('image-card-' + imageId);
        
        try {
            const response = await fetch('/api/clinic/clinic_images.php?id=' + imageId, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                card.remove();
                document.getElementById('imageCount').textContent = document.querySelectorAll('[id^="image-card-"]').length;
                
                // Show empty state if no images left
                if (document.querySelectorAll('[id^="image-card-"]').length === 0) {
                    document.getElementById('clinicImagesGallery').innerHTML = `
                        <div class="col-12">
                            <div class="text-center py-5 bg-light rounded">
                                <i class="bi bi-images text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No clinic images uploaded yet.</p>
                            </div>
                        </div>
                    `;
                }
            } else {
                alert(data.error || 'Delete failed');
            }
        } catch (err) {
            alert('Error deleting image: ' + err.message);
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
