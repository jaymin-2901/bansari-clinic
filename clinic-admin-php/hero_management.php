<?php
/**
 * Hero Image Management
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../backend-php/config/clinic_config.php';
require_once __DIR__ . '/../backend-php/config/clinic_db.php';
require_once __DIR__ . '/includes/functions.php';

// Auth check
if (!getAdminID()) {
    header('Location: login.php');
    exit;
}

$db = getClinicDB();
$pageTitle = 'Hero Images';

// Fetch all hero images
$stmt = $db->query("SELECT * FROM hero_images ORDER BY sort_order ASC, created_at DESC");
$heroes = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
$flash = getFlash();

require_once __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="container-fluid pb-5">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h5 class="mb-1 text-dark fw-bold">Hero Image Gallery</h5>
            <p class="text-muted small mb-0">Manage home page slides (Desktop & Mobile images separately)</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addHeroModal">
                <i class="bi bi-plus-lg me-1"></i> Add Slide
            </button>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div class="row g-4">
        <?php if (empty($heroes)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm py-5 text-center">
                    <div class="card-body">
                        <i class="bi bi-images text-muted" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-muted">No hero images found. Add your first slide above.</h6>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form action="actions/hero_actions.php?action=update_order" method="POST" class="row g-4 w-100 m-0">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <?php foreach ($heroes as $hero): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm overflow-hidden hero-card">
                            <div class="position-absolute top-0 end-0 p-2 z-2">
                                <span class="badge <?= $hero['is_active'] ? 'bg-success' : 'bg-secondary' ?> shadow-sm">
                                    <?= $hero['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            
                            <div class="hero-preview-container bg-light">
                                <div class="desktop-preview">
                                    <small class="preview-label">Desktop</small>
                                    <img src="../public/uploads/hero/<?= clean($hero['desktop_image']) ?>" alt="Desktop">
                                </div>
                                <?php if ($hero['mobile_image']): ?>
                                    <div class="mobile-preview shadow-sm">
                                        <small class="preview-label">Mobile</small>
                                        <img src="../public/uploads/hero/<?= clean($hero['mobile_image']) ?>" alt="Mobile">
                                    </div>
                                <?php else: ?>
                                    <div class="mobile-preview shadow-sm bg-white d-flex align-items-center justify-center">
                                        <small class="text-muted" style="font-size: 0.6rem;">No Mobile Img</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <label class="small text-muted fw-bold">Order:</label>
                                    <input type="number" name="order[<?= $hero['id'] ?>]" value="<?= $hero['sort_order'] ?>" class="form-control form-control-sm w-25">
                                    
                                    <div class="ms-auto d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="toggleStatus(<?= $hero['id'] ?>, <?= $hero['is_active'] ? 0 : 1 ?>)"
                                                title="<?= $hero['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteHero(<?= $hero['id'] ?>)"
                                                title="Delete Slide">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-success shadow-sm">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Add Hero Modal -->
<div class="modal fade" id="addHeroModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form action="actions/hero_actions.php?action=add" method="POST" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New Hero Slide</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Desktop Image (Recommended: 1920x800)</label>
                        <input type="file" name="desktop_image" class="form-control" accept="image/*" required>
                        <div class="form-text">Required. This will be shown on large screens.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Mobile Image (Recommended: 800x1200)</label>
                        <input type="file" name="mobile_image" class="form-control" accept="image/*">
                        <div class="form-text">Optional. If not provided, desktop image will be scaled.</div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" value="0" class="form-control">
                        <div class="form-text">Lower numbers appear first.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Slide</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Actions Forms (Hidden) -->
<form id="deleteForm" action="actions/hero_actions.php?action=delete" method="POST">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="id" id="deleteId">
</form>

<form id="statusForm" action="actions/hero_actions.php?action=toggle_status" method="POST">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="id" id="statusId">
    <input type="hidden" name="status" id="statusVal">
</form>

<style>
.hero-preview-container {
    position: relative;
    height: 200px;
    padding: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #eee;
}
.desktop-preview {
    width: 80%;
    height: 100%;
    background: #000;
    position: relative;
    border-radius: 4px;
    overflow: hidden;
}
.desktop-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.mobile-preview {
    position: absolute;
    bottom: 10px;
    right: 20px;
    width: 60px;
    height: 90px;
    background: #000;
    border-radius: 4px;
    overflow: hidden;
    border: 2px solid white;
}
.mobile-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.preview-label {
    position: absolute;
    top: 2px;
    left: 4px;
    font-size: 0.5rem;
    color: white;
    background: rgba(0,0,0,0.5);
    padding: 0 2px;
    z-index: 1;
}
.hero-card {
    transition: transform 0.2s;
}
.hero-card:hover {
    transform: translateY(-5px);
}
</style>

<script>
function deleteHero(id) {
    if (confirm('Are you sure you want to delete this hero slide? This will also remove the image files.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function toggleStatus(id, status) {
    document.getElementById('statusId').value = id;
    document.getElementById('statusVal').value = status;
    document.getElementById('statusForm').submit();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
