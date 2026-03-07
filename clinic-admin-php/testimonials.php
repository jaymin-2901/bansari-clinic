<?php
/**
 * Bansari Homeopathy – Manage Testimonials
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Testimonials';

$uploadDir = dirname(__DIR__) . '/public/uploads/testimonials/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $db = getClinicDB();
            $id = (int)$_POST['testimonial_id'];

            // Get images to delete
            $stmt = $db->prepare("SELECT before_image, after_image FROM testimonials WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
            if ($t) {
                if ($t['before_image']) deleteImage($uploadDir . $t['before_image']);
                if ($t['after_image']) deleteImage($uploadDir . $t['after_image']);
                $db->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
                setFlash('success', 'Testimonial deleted.');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Error deleting testimonial.');
        }
    }
    header('Location: testimonials.php');
    exit;
}

// Handle toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $db = getClinicDB();
            $id = (int)$_POST['testimonial_id'];
            $db->prepare("UPDATE testimonials SET display_status = IF(display_status='active','inactive','active') WHERE id = ?")->execute([$id]);
            setFlash('success', 'Status updated.');
        } catch (PDOException $e) {
            setFlash('error', 'Error updating status.');
        }
    }
    header('Location: testimonials.php');
    exit;
}

try {
    $db = getClinicDB();
    $testimonials = $db->query("SELECT * FROM testimonials ORDER BY sort_order ASC, created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $testimonials = [];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold">All Testimonials (<?= count($testimonials) ?>)</h6>
        <a href="testimonial_form.php" class="btn btn-sm btn-success">
            <i class="bi bi-plus-lg"></i> Add Testimonial
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th>Images</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($testimonials)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No testimonials yet. <a href="testimonial_form.php">Add one</a></td></tr>
                <?php else: ?>
                <?php foreach ($testimonials as $t): ?>
                <tr>
                    <td><strong>#<?= $t['id'] ?></strong></td>
                    <td>
                        <?= $t['is_anonymous'] ? '<em class="text-muted">Anonymous</em>' : clean($t['patient_name']) ?>
                    </td>
                    <td><?= clean(mb_substr($t['treatment_description'], 0, 50)) ?><?= mb_strlen($t['treatment_description']) > 50 ? '...' : '' ?></td>
                    <td>
                        <?php if ($t['before_image']): ?>
                        <img src="../public/uploads/testimonials/<?= clean($t['before_image']) ?>" class="img-preview" style="width:40px;height:40px;" alt="Before">
                        <?php endif; ?>
                        <?php if ($t['after_image']): ?>
                        <img src="../public/uploads/testimonials/<?= clean($t['after_image']) ?>" class="img-preview" style="width:40px;height:40px;" alt="After">
                        <?php endif; ?>
                        <?php if (!$t['before_image'] && !$t['after_image']): ?>
                        <span class="text-muted small">No images</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <span class="<?= $i < $t['rating'] ? 'text-warning' : 'text-muted' ?>">★</span>
                        <?php endfor; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <button class="badge-status badge-<?= $t['display_status'] ?> border-0 cursor-pointer" style="cursor:pointer">
                                <?= ucfirst($t['display_status']) ?>
                            </button>
                        </form>
                    </td>
                    <td class="d-flex gap-1">
                        <a href="testimonial_form.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-action btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-action btn-outline-danger" data-confirm="Are you sure you want to delete this testimonial?" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
