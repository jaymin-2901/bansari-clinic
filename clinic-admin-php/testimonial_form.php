<?php
/**
 * Bansari Homeopathy – Add/Edit Testimonial
 * With Manual Image Cropping Support
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

// Cropper.js CSS and JS for image cropping
$extraHead = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<style>
.crop-container { max-height: 400px; background: #333; }
.cropper-view-box, .cropper-face { border-radius: 0; }
.img-container { height: 400px; background: #1a1a1a; }
.img-container img { max-width: 100%; }
.preview-container { height: 150px; overflow: hidden; background: #f5f5f5; text-align: center; }
.preview-container img { max-width: 100%; max-height: 100%; }
.crop-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; }
.crop-modal.active { display: flex; align-items: center; justify-content: center; }
.crop-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 700px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.crop-modal-header { padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; }
.crop-modal-header h3 { margin: 0; font-size: 18px; color: #333; }
.crop-modal-body { padding: 20px; flex: 1; overflow: auto; }
.crop-modal-footer { padding: 15px 20px; border-top: 1px solid #ddd; display: flex; gap: 10px; justify-content: flex-end; background: #f8f9fa; }
.btn-crop { padding: 8px 20px; border-radius: 4px; font-size: 14px; cursor: pointer; border: none; }
.btn-crop-primary { background: #28a745; color: white; }
.btn-crop-primary:hover { background: #218838; }
.btn-crop-secondary { background: #6c757d; color: white; }
.btn-crop-secondary:hover { background: #5a6268; }
.btn-crop:disabled { opacity: 0.5; cursor: not-allowed; }
.current-image-preview { max-width: 120px; max-height: 120px; border-radius: 4px; border: 1px solid #ddd; }
.zoom-controls { margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; }
.zoom-controls label { display: block; margin-bottom: 5px; font-size: 14px; color: #555; }
.zoom-controls input[type="range"] { width: 100%; }
.image-actions { margin-top: 10px; }
.image-actions .btn { font-size: 12px; padding: 4px 10px; }
.pending-badge { display: inline-block; background: #ffc107; color: #333; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 8px; }
.cropped-preview { max-width: 100px; max-height: 100px; border-radius: 4px; border: 2px solid #28a745; margin-top: 10px; }
</style>
';

$extraScripts = '
<script>
let cropper = null;
let currentCropField = "";

document.addEventListener("DOMContentLoaded", function() {
    var beforeInput = document.querySelector("input[name=\"before_image\"]");
    var afterInput = document.querySelector("input[name=\"after_image\"]");
    
    if (beforeInput) {
        beforeInput.addEventListener("change", function() {
            if (this.files && this.files[0]) {
                openCropModal(this.files[0], "before");
            }
        });
    }
    
    if (afterInput) {
        afterInput.addEventListener("change", function() {
            if (this.files && this.files[0]) {
                openCropModal(this.files[0], "after");
            }
        });
    }
    
    var cancelBtn = document.getElementById("cropCancelBtn");
    if (cancelBtn) {
        cancelBtn.addEventListener("click", closeCropModal);
    }
    
    var saveBtn = document.getElementById("cropSaveBtn");
    if (saveBtn) {
        saveBtn.addEventListener("click", saveCrop);
    }
});

function openCropModal(file, fieldName) {
    currentCropField = fieldName;
    
    var reader = new FileReader();
    reader.onload = function(e) {
        var imageContainer = document.getElementById("cropImageContainer");
        if (!imageContainer) return;
        
        imageContainer.innerHTML = "<img src=\"" + e.target.result + "\" id=\"cropImage\" style=\"max-width: 100%;\">";
        
        var modal = document.getElementById("cropModal");
        if (!modal) return;
        modal.classList.add("active");
        
        var image = document.getElementById("cropImage");
        if (!image) return;
        
        // Destroy existing cropper if any
        if (cropper) {
            cropper.destroy();
        }
        
        cropper = new Cropper(image, {
            aspectRatio: 4 / 5,
            viewMode: 1,
            dragMode: "move",
            autoCropArea: 0.9,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false
        });
        
        // Setup zoom slider
        var zoomSlider = document.getElementById("zoomSlider");
        if (zoomSlider) {
            zoomSlider.value = 1;
            zoomSlider.addEventListener("input", function() {
                if (cropper) {
                    cropper.zoomTo(this.value);
                }
            });
        }
    };
    reader.readAsDataURL(file);
}

function closeCropModal() {
    var modal = document.getElementById("cropModal");
    if (modal) {
        modal.classList.remove("active");
    }
    
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    
    // Clear the file input
    var inputName = currentCropField === "before" ? "before_image" : "after_image";
    var input = document.querySelector("input[name=\"" + inputName + "\"]");
    if (input) {
        input.value = "";
    }
}

function saveCrop() {
    if (!cropper) return;
    
    // Get cropped canvas
    var canvas = cropper.getCroppedCanvas({
        maxWidth: 1200,
        maxHeight: 1500,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: "high"
    });
    
    if (!canvas) {
        alert("Failed to crop image");
        return;
    }
    
    // Convert to base64
    var croppedDataUrl = canvas.toDataURL("image/jpeg", 0.92);
    
    // Store in hidden field
    var hiddenFieldName = currentCropField + "_cropped";
    var hiddenField = document.querySelector("input[name=\"" + hiddenFieldName + "\"]");
    if (!hiddenField) {
        hiddenField = document.createElement("input");
        hiddenField.type = "hidden";
        hiddenField.name = hiddenFieldName;
        hiddenField.id = hiddenFieldName;
        var form = document.querySelector("form");
        if (form) {
            form.appendChild(hiddenField);
        }
    }
    hiddenField.value = croppedDataUrl;
    
    // Show preview
    var previewContainer = document.getElementById(currentCropField + "Preview");
    if (previewContainer) {
        var imgHtml = "<img src=\"" + croppedDataUrl + "\" class=\"cropped-preview\">";
        var badgeHtml = "<span class=\"pending-badge\">Cropped - Ready to save</span>";
        previewContainer.innerHTML = imgHtml + badgeHtml;
    }
    
    closeCropModal();
}
</script>
';

// Crop Modal HTML
$cropModal = '
<div id="cropModal" class="crop-modal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3 id="cropModalTitle">Crop Image</h3>
            <button type="button" class="btn-close" onclick="closeCropModal()"></button>
        </div>
        <div class="crop-modal-body">
            <div class="img-container" id="cropImageContainer">
                <img id="cropImage" style="max-width: 100%;">
            </div>
            <div class="zoom-controls">
                <label>Zoom: <span id="zoomValue">100%</span></label>
                <input type="range" id="zoomSlider" min="0.1" max="3" step="0.01" value="1">
            </div>
        </div>
        <div class="crop-modal-footer">
            <button type="button" id="cropCancelBtn" class="btn-crop btn-crop-secondary">Cancel</button>
            <button type="button" id="cropSaveBtn" class="btn-crop btn-crop-primary">Save Crop</button>
        </div>
    </div>
</div>
';


$uploadDir = dirname(__DIR__) . '/public/uploads/testimonials/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Testimonial' : 'Add Testimonial';

// Load existing data for edit
$data = [
    'patient_name' => '',
    'is_anonymous' => 0,
    'treatment_description' => '',
    'testimonial_text' => '',
    'before_image' => '',
    'after_image' => '',
    'rating' => 5,
    'display_status' => 'active',
    'sort_order' => 0,
];

if ($isEdit) {
    try {
        $db = getClinicDB();
        $stmt = $db->prepare("SELECT * FROM testimonials WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if ($existing) {
            $data = array_merge($data, $existing);
        } else {
            setFlash('error', 'Testimonial not found.');
            header('Location: testimonials.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('error', 'Error loading testimonial.');
        header('Location: testimonials.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        header('Location: testimonial_form.php' . ($isEdit ? "?id=$id" : ''));
        exit;
    }

    $data['patient_name'] = clean($_POST['patient_name'] ?? '');
    $data['is_anonymous'] = isset($_POST['is_anonymous']) ? 1 : 0;
    $data['treatment_description'] = clean($_POST['treatment_description'] ?? '');
    $data['testimonial_text'] = clean($_POST['testimonial_text'] ?? '');
    $data['rating'] = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $data['display_status'] = $_POST['display_status'] ?? 'active';
    $data['sort_order'] = (int)($_POST['sort_order'] ?? 0);

    // Handle pending cropped images from JavaScript
    $beforeCropped = $_POST['before_cropped'] ?? '';
    $afterCropped = $_POST['after_cropped'] ?? '';

    // Validate
    if (empty($data['treatment_description'])) {
        setFlash('error', 'Treatment description is required.');
    } else {
        try {
            $db = getClinicDB();

            // Handle before image - either from cropped base64 or direct upload
            if (!empty($beforeCropped)) {
                // Save base64 cropped image
                if ($isEdit && $data['before_image']) {
                    deleteImage($uploadDir . $data['before_image']);
                }
                $data['before_image'] = saveBase64Image($beforeCropped, $uploadDir, 'before');
            } elseif (isset($_FILES['before_image']) && $_FILES['before_image']['error'] === UPLOAD_ERR_OK) {
                // Delete old image if editing
                if ($isEdit && $data['before_image']) {
                    deleteImage($uploadDir . $data['before_image']);
                }
                $data['before_image'] = uploadImage($_FILES['before_image'], $uploadDir, 'before');
            }

            // Handle after image - either from cropped base64 or direct upload
            if (!empty($afterCropped)) {
                // Save base64 cropped image
                if ($isEdit && $data['after_image']) {
                    deleteImage($uploadDir . $data['after_image']);
                }
                $data['after_image'] = saveBase64Image($afterCropped, $uploadDir, 'after');
            } elseif (isset($_FILES['after_image']) && $_FILES['after_image']['error'] === UPLOAD_ERR_OK) {
                if ($isEdit && $data['after_image']) {
                    deleteImage($uploadDir . $data['after_image']);
                }
                $data['after_image'] = uploadImage($_FILES['after_image'], $uploadDir, 'after');
            }

            if ($isEdit) {
                $stmt = $db->prepare("
                    UPDATE testimonials 
                    SET patient_name=?, is_anonymous=?, treatment_description=?, testimonial_text=?,
                        before_image=?, after_image=?, rating=?, display_status=?, sort_order=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $data['patient_name'], $data['is_anonymous'],
                    $data['treatment_description'], $data['testimonial_text'],
                    $data['before_image'], $data['after_image'],
                    $data['rating'], $data['display_status'], $data['sort_order'],
                    $id
                ]);
                setFlash('success', 'Testimonial updated successfully.');
            } else {
                $stmt = $db->prepare("
                    INSERT INTO testimonials (patient_name, is_anonymous, treatment_description, testimonial_text, 
                        before_image, after_image, rating, display_status, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['patient_name'], $data['is_anonymous'],
                    $data['treatment_description'], $data['testimonial_text'],
                    $data['before_image'] ?? '', $data['after_image'] ?? '',
                    $data['rating'], $data['display_status'], $data['sort_order']
                ]);
                setFlash('success', 'Testimonial added successfully.');
            }

            header('Location: testimonials.php');
            exit;

        } catch (PDOException $e) {
            error_log('Testimonial form error: ' . $e->getMessage());
            setFlash('error', 'An error occurred. Please try again.');
        }
    }
}

/**
 * Save base64 image data to file
 */
function saveBase64Image(string $base64Data, string $uploadDir, string $prefix): ?string {
    // Remove data URL prefix if present
    if (strpos($base64Data, 'data:') === 0) {
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
    }
    
    $imageData = base64_decode($base64Data);
    if ($imageData === false) return null;
    
    $extension = 'jpg';
    // Detect image type from first bytes
    if (strpos($base64Data, 'data:image/png') !== false || 
        strpos($base64Data, 'iVBOR') !== false) {
        $extension = 'png';
    } elseif (strpos($base64Data, 'data:image/webp') !== false ||
              strpos($base64Data, 'UklGR') !== false) {
        $extension = 'webp';
    }
    
    $filename = $prefix . '_cropped_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filepath = rtrim($uploadDir, '/') . '/' . $filename;
    
    if (file_put_contents($filepath, $imageData)) {
        return $filename;
    }
    return null;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="mb-3">
    <a href="testimonials.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Testimonials
    </a>
</div>

<div class="form-card" style="max-width: 800px;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <div class="row g-3">
            <!-- Patient Name -->
            <div class="col-md-8">
                <label class="form-label">Patient Name</label>
                <input type="text" name="patient_name" class="form-control" 
                       value="<?= clean($data['patient_name']) ?>" placeholder="Patient name (optional if anonymous)">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                    <input type="checkbox" name="is_anonymous" class="form-check-input" id="isAnonymous"
                           <?= $data['is_anonymous'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isAnonymous">Display as Anonymous</label>
                </div>
            </div>

            <!-- Treatment Description -->
            <div class="col-12">
                <label class="form-label">Treatment Description *</label>
                <input type="text" name="treatment_description" class="form-control" 
                       value="<?= clean($data['treatment_description']) ?>" required
                       placeholder="e.g., Chronic Skin Eczema – 5 years">
            </div>

            <!-- Testimonial Text -->
            <div class="col-12">
                <label class="form-label">Testimonial Text</label>
                <textarea name="testimonial_text" class="form-control" rows="4" 
                          placeholder="Patient's testimonial in their own words..."><?= clean($data['testimonial_text']) ?></textarea>
            </div>

            <!-- Before Image -->
            <div class="col-md-6">
                <label class="form-label" id="beforeImageLabel">Before Image</label>
                <?php if ($data['before_image']): ?>
                <div class="mb-2" id="beforePreview">
                    <img src="../public/uploads/testimonials/<?= clean($data['before_image']) ?>" class="img-preview" alt="Before">
                    <small class="d-block text-muted mt-1">Current image (upload new to replace)</small>
                </div>
                <?php else: ?>
                <div class="mb-2" id="beforePreview"></div>
                <?php endif; ?>
                <input type="file" name="before_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                <small class="text-muted">Max 5MB. JPG, PNG, WebP</small>
            </div>

            <!-- After Image -->
            <div class="col-md-6">
                <label class="form-label" id="afterImageLabel">After Image</label>
                <?php if ($data['after_image']): ?>
                <div class="mb-2" id="afterPreview">
                    <img src="../public/uploads/testimonials/<?= clean($data['after_image']) ?>" class="img-preview" alt="After">
                    <small class="d-block text-muted mt-1">Current image (upload new to replace)</small>
                </div>
                <?php else: ?>
                <div class="mb-2" id="afterPreview"></div>
                <?php endif; ?>
                <input type="file" name="after_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                <small class="text-muted">Max 5MB. JPG, PNG, WebP</small>
            </div>

            <!-- Rating -->
            <div class="col-md-4">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= $data['rating'] == $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-4">
                <label class="form-label">Display Status</label>
                <select name="display_status" class="form-select">
                    <option value="active" <?= $data['display_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $data['display_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <!-- Sort Order -->
            <div class="col-md-4">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" 
                       value="<?= $data['sort_order'] ?>" min="0">
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Update Testimonial' : 'Add Testimonial' ?>
            </button>
            <a href="testimonials.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<!-- Crop Modal -->
<div id="cropModal" class="crop-modal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3 id="cropModalTitle">Crop Image</h3>
            <button type="button" class="btn-close" onclick="closeCropModal()"></button>
        </div>
        <div class="crop-modal-body">
            <div class="img-container" id="cropImageContainer">
                <img id="cropImage" style="max-width: 100%;">
            </div>
            <div class="zoom-controls">
                <label>Zoom: <span id="zoomValue">100%</span></label>
                <input type="range" id="zoomSlider" min="0.1" max="3" step="0.01" value="1">
            </div>
        </div>
        <div class="crop-modal-footer">
            <button type="button" id="cropCancelBtn" class="btn-crop btn-crop-secondary">Cancel</button>
            <button type="button" id="cropSaveBtn" class="btn-crop btn-crop-primary">Save Crop</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
