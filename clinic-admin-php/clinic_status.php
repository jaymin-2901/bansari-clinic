<?php
/**
 * Bansari Homeopathy – Clinic Status / Notice Management
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Clinic Status';
$activeMenu = 'clinic_status';

$db = getClinicDB();
$csrfToken = generateCSRFToken();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $start = $_POST['start_datetime'] ?? '';
        $end = $_POST['end_datetime'] ?? '';
        $status = $_POST['status'] ?? 'closed';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if ($message && $start && $end) {
            try {
                if ($action === 'add') {
                    $stmt = $db->prepare("INSERT INTO clinic_status (message, start_datetime, end_datetime, status, is_active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$message, $start, $end, $status, $isActive]);
                    setFlash('success', 'Status notice added successfully.');
                } else {
                    $stmt = $db->prepare("UPDATE clinic_status SET message = ?, start_datetime = ?, end_datetime = ?, status = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$message, $start, $end, $status, $isActive, $id]);
                    setFlash('success', 'Status notice updated.');
                }
            } catch (PDOException $e) {
                setFlash('error', 'Database error: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Please fill in all required fields.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM clinic_status WHERE id = ?")->execute([$id]);
        setFlash('success', 'Notice deleted.');
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $active = (int)$_POST['is_active'];
        $db->prepare("UPDATE clinic_status SET is_active = ? WHERE id = ?")->execute([$active, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    header('Location: clinic_status.php');
    exit;
}

// Fetch notices
$notices = $db->query("SELECT * FROM clinic_status ORDER BY start_datetime DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="content-header p-4 mb-4 bg-white rounded-4 shadow-sm border border-opacity-10">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold">Clinic Status & Notices</h4>
            <p class="text-muted small mb-0">Manage temporary closures or special announcements for the booking system.</p>
        </div>
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
            <i class="bi bi-plus-lg me-1"></i> Add Notice
        </button>
    </div>
</div>

<?php if ($flash = getFlash()): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="table-container bg-white rounded-4 shadow-sm border border-opacity-10 overflow-hidden">
    <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-50">
        <h6 class="mb-0 fw-bold border-start border-primary border-4 ps-2">Active & Scheduled Notices</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Message</th>
                    <th>Start Date/Time</th>
                    <th>End Date/Time</th>
                    <th>Active</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notices)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No status notices found.</td></tr>
                <?php else: foreach ($notices as $n): 
                    $isPast = strtotime($n['end_datetime']) < time();
                    $isCurrent = strtotime($n['start_datetime']) <= time() && strtotime($n['end_datetime']) >= time();
                ?>
                    <tr class="<?= $isPast ? 'opacity-50' : '' ?>">
                        <td>
                            <span class="badge bg-<?= $n['status'] === 'closed' ? 'danger' : 'success' ?> bg-opacity-10 text-<?= $n['status'] === 'closed' ? 'danger' : 'success' ?> border border-<?= $n['status'] === 'closed' ? 'danger' : 'success' ?> border-opacity-25 rounded-pill px-3">
                                <?= strtoupper($n['status']) ?>
                            </span>
                            <?php if ($isCurrent): ?>
                                <span class="badge bg-warning text-dark ms-1 small" style="font-size: 0.6rem;">LIVE</span>
                            <?php endif; ?>
                        </td>
                        <td><div class="text-wrap" style="max-width: 300px;"><?= clean($n['message']) ?></div></td>
                        <td><small><?= date('d M Y, h:i A', strtotime($n['start_datetime'])) ?></small></td>
                        <td><small><?= date('d M Y, h:i A', strtotime($n['end_datetime'])) ?></small></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox" data-id="<?= $n['id'] ?>" <?= $n['is_active'] ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-1 edit-notice" 
                                    data-notice='<?= json_encode($n) ?>'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notice?');">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="id" id="noticeId" value="">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Clinic Status Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Notice Message</label>
                    <textarea name="message" id="noticeMessage" class="form-control" rows="3" required placeholder="e.g. Clinic is closed for Holi festival until Monday."></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Start Date & Time</label>
                        <input type="datetime-local" name="start_datetime" id="noticeStart" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">End Date & Time</label>
                        <input type="datetime-local" name="end_datetime" id="noticeEnd" class="form-control" required>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Clinic Status</label>
                        <select name="status" id="noticeStatus" class="form-select">
                            <option value="closed">Closed</option>
                            <option value="open">Open (Informational only)</option>
                        </select>
                    </div>
                    <div class="col-6 d-flex align-items-end pb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="noticeIsActive" checked>
                            <label class="form-check-label small fw-bold ms-1" for="noticeIsActive">Mark as Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">Save Notice</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    const modalAction = document.getElementById('modalAction');
    const modalTitle = document.getElementById('modalTitle');
    const noticeId = document.getElementById('noticeId');
    const noticeMessage = document.getElementById('noticeMessage');
    const noticeStart = document.getElementById('noticeStart');
    const noticeEnd = document.getElementById('noticeEnd');
    const noticeStatus = document.getElementById('noticeStatus');
    const noticeIsActive = document.getElementById('noticeIsActive');

    // Add Notice
    document.querySelector('[data-bs-target="#statusModal"]').addEventListener('click', () => {
        modalAction.value = 'add';
        modalTitle.textContent = 'Add Clinic Status Notice';
        noticeId.value = '';
        noticeMessage.value = '';
        noticeStart.value = '';
        noticeEnd.value = '';
        noticeStatus.value = 'closed';
        noticeIsActive.checked = true;
    });

    // Edit Notice
    document.querySelectorAll('.edit-notice').forEach(btn => {
        btn.addEventListener('click', function() {
            const n = JSON.parse(this.dataset.notice);
            modalAction.value = 'edit';
            modalTitle.textContent = 'Edit Clinic Status Notice';
            noticeId.value = n.id;
            noticeMessage.value = n.message;
            noticeStart.value = n.start_datetime.replace(' ', 'T');
            noticeEnd.value = n.end_datetime.replace(' ', 'T');
            noticeStatus.value = n.status;
            noticeIsActive.checked = parseInt(n.is_active) === 1;
            modal.show();
        });
    });

    // Toggle Active Switch
    document.querySelectorAll('.toggle-status').forEach(sw => {
        sw.addEventListener('change', async function() {
            const formData = new FormData();
            formData.append('action', 'toggle_active');
            formData.append('id', this.dataset.id);
            formData.append('is_active', this.checked ? 1 : 0);
            formData.append('csrf_token', '<?= $csrfToken ?>');

            try {
                await fetch('clinic_status.php', { method: 'POST', body: formData });
            } catch (e) {
                console.error('Toggle error:', e);
            }
        });
    });
});
</script>
