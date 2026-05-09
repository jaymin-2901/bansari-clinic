<?php
/**
 * Bansari Homeopathy – Patients Management
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Patients';

// ─── AJAX Reset Password Handler ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    requireAdmin();

    $action = $_POST['action'] ?? '';
    $csrfOk = verifyCSRFToken($_POST['csrf_token'] ?? '');

    if (!$csrfOk) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }

    if ($action === 'reset_password') {
        $patientId   = (int)($_POST['patient_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if (!$patientId) {
            echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
            exit;
        }
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit;
        }
        if (strlen($newPassword) > 100) {
            echo json_encode(['success' => false, 'message' => 'Password is too long.']);
            exit;
        }

        try {
            $db   = getClinicDB();
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE patients SET password = ?, is_registered = 1 WHERE id = ?");
            $stmt->execute([$hash, $patientId]);

            if ($stmt->rowCount()) {
                echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Patient not found.']);
            }
        } catch (PDOException $e) {
            error_log('Reset password error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['patient_id'] ?? 0);

    if ($action === 'delete' && $id) {
        try {
            $db = getClinicDB();
            $db->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
            setFlash('success', 'Patient deleted.');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete patient. They may have appointments.');
        }
    }
    header('Location: patients.php?' . http_build_query(array_intersect_key($_GET, array_flip(['search', 'sort', 'dir', 'gender', 'registered', 'page']))));
    exit;
}

// Filters & sorting
$search     = trim($_GET['search'] ?? '');
$sort       = in_array($_GET['sort'] ?? '', ['full_name', 'mobile', 'city', 'age', 'gender', 'created_at', 'appointments']) ? $_GET['sort'] : 'created_at';
$dir        = strtolower($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
$filterGender = in_array($_GET['gender'] ?? '', ['male', 'female', 'other']) ? $_GET['gender'] : '';
$filterReg  = in_array($_GET['registered'] ?? '', ['1', '0']) ? $_GET['registered'] : '';

try {
    $db = getClinicDB();
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    // Build WHERE clause
    $where   = [];
    $params  = [];

    if ($search !== '') {
        $where[]  = "(p.full_name LIKE ? OR p.mobile LIKE ? OR p.email LIKE ? OR p.city LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterGender !== '') {
        $where[]  = "p.gender = ?";
        $params[] = $filterGender;
    }
    if ($filterReg !== '') {
        $where[]  = "p.is_registered = ?";
        $params[] = (int)$filterReg;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM patients p $whereSql");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $pagination = getPagination($total, $perPage, $page);

    // Sort mapping
    $orderCol = $sort;
    $joinAppointments = '';
    $selectExtra = '';
    if ($sort === 'appointments') {
        $joinAppointments = 'LEFT JOIN (SELECT patient_id, COUNT(*) AS appt_count FROM appointments GROUP BY patient_id) a ON a.patient_id = p.id';
        $orderCol = 'appt_count';
        $selectExtra = ', COALESCE(a.appt_count, 0) AS appt_count';
    } else {
        // Still get appointment count for display
        $joinAppointments = 'LEFT JOIN (SELECT patient_id, COUNT(*) AS appt_count FROM appointments GROUP BY patient_id) a ON a.patient_id = p.id';
        $selectExtra = ', COALESCE(a.appt_count, 0) AS appt_count';
        $orderCol = "p.$sort";
    }

    // Select specific columns — never load raw password hash into the page
    $sql = "SELECT p.id, p.full_name, p.mobile, p.email, p.age, p.gender, p.city,
                   p.is_registered, p.created_at, p.updated_at,
                   IF(p.password IS NOT NULL AND p.password != '', 1, 0) AS has_password
                   $selectExtra 
            FROM patients p 
            $joinAppointments 
            $whereSql 
            ORDER BY $orderCol $dir 
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();

} catch (PDOException $e) {
    $patients   = [];
    $pagination = getPagination(0);
}

// Helper to build sort links
function sortLink(string $col, string $label): string {
    global $sort, $dir, $search, $filterGender, $filterReg;
    $newDir = ($sort === $col && $dir === 'ASC') ? 'desc' : 'asc';
    $arrow  = '';
    if ($sort === $col) {
        $arrow = $dir === 'ASC' ? ' ↑' : ' ↓';
    }
    $qs = http_build_query(array_filter([
        'search'     => $search,
        'sort'       => $col,
        'dir'        => $newDir,
        'gender'     => $filterGender,
        'registered' => $filterReg,
    ], fn($v) => $v !== ''));
    return '<a href="patients.php?' . $qs . '" class="text-decoration-none text-dark fw-semibold">' . $label . $arrow . '</a>';
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

    <div class="content-header p-4 mb-4 bg-white rounded-4 shadow-sm border border-opacity-10">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold">Patient Management</h4>
                <p class="text-muted small mb-0">Manage clinic patients, their records, and account settings.</p>
            </div>
            <a href="appointments.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Add Patient
            </a>
        </div>

        <form method="GET" class="p-3 rounded-3" style="background: var(--bg-light);">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-muted">Search Patients</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Name, mobile or city..." value="<?= clean($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All Genders</option>
                        <option value="male" <?= $filterGender === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $filterGender === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $filterGender === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted">Account Type</label>
                    <select name="registered" class="form-select">
                        <option value="">All Types</option>
                        <option value="1" <?= $filterReg === '1' ? 'selected' : '' ?>>Registered</option>
                        <option value="0" <?= $filterReg === '0' ? 'selected' : '' ?>>Guest</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <div class="d-flex gap-2 justify-content-md-end">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                        <?php if ($search || $filterGender || $filterReg): ?>
                        <a href="patients.php" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-x-lg"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Patients Table Card -->
    <div class="table-container bg-white rounded-4 shadow-sm border border-opacity-10 overflow-hidden">
        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-50">
            <h6 class="mb-0 fw-bold border-start border-primary border-4 ps-2">
                Total Patients <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?= $pagination['total'] ?></span>
            </h6>
        </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th><?= sortLink('full_name', 'Patient Details') ?></th>
                    <th>Contact Info</th>
                    <th><?= sortLink('age', 'Age/Gender') ?></th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Medical Info</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                <tr><td colspan="12" class="text-center text-muted py-4">No patients found</td></tr>
                <?php else: ?>
                <?php 
                $offset = $pagination['offset'];
                foreach ($patients as $i => $p): 
                ?>
                <tr class="clickable-row">
                    <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: hsl(<?= (crc32($p['full_name']) % 360) ?>, 70%, 45%);">
                                <?= strtoupper(substr($p['full_name'], 0, 1)) ?>
                            </div>
                            <div class="patient-meta">
                                <h6 class="patient-name"><?= clean($p['full_name']) ?></h6>
                                <span class="patient-sub text-muted mt-1">Joined <?= formatDate($p['created_at'], 'M Y') ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <a href="tel:<?= clean($p['mobile']) ?>" class="text-decoration-none text-dark fw-medium small">
                                <i class="bi bi-telephone-fill text-muted me-1"></i> <?= clean($p['mobile']) ?>
                            </a>
                            <?php if ($p['email']): ?>
                            <a href="mailto:<?= clean($p['email']) ?>" class="text-decoration-none text-muted smaller">
                                <i class="bi bi-envelope-fill me-1"></i> <?= clean($p['email']) ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <span class="fw-medium"><?= $p['age'] ?: '-' ?> yrs</span>
                            <?php if ($p['gender']): ?>
                            <span class="badge rounded-pill bg-<?= $p['gender'] === 'male' ? 'primary' : ($p['gender'] === 'female' ? 'danger' : 'secondary') ?> bg-opacity-10 text-<?= $p['gender'] === 'male' ? 'primary' : ($p['gender'] === 'female' ? 'danger' : 'secondary') ?> smaller border border-<?= $p['gender'] === 'male' ? 'primary' : ($p['gender'] === 'female' ? 'danger' : 'secondary') ?> border-opacity-25" style="width: fit-content;">
                                <?= ucfirst($p['gender']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i> <?= $p['city'] ? clean($p['city']) : 'Not specified' ?></span>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-2">
                            <?php if ($p['is_registered']): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="width: fit-content;">
                                <i class="bi bi-patch-check-fill me-1"></i> Registered
                            </span>
                            <button type="button" class="btn btn-xs btn-outline-warning reset-pwd-btn py-0 px-2 fw-medium" 
                                    style="font-size: 0.7rem; width: fit-content;"
                                    data-patient-id="<?= $p['id'] ?>" 
                                    data-patient-name="<?= clean($p['full_name']) ?>">
                                <i class="bi bi-key-fill me-1"></i>Reset Pwd
                            </button>
                            <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="width: fit-content;">
                                Guest
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <a href="appointments.php?search=<?= urlencode($p['mobile']) ?>" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none border border-primary border-opacity-25" style="width: fit-content;">
                                <?= $p['appt_count'] ?> Appts <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                            <span class="smaller text-muted">Latest: <?= formatDate($p['created_at'], 'd M') ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="appointments.php?search=<?= urlencode($p['mobile']) ?>" class="btn btn-sm btn-outline-primary" title="View Appointments">
                                <i class="bi bi-calendar-event"></i>
                            </a>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-sm btn-outline-danger" data-confirm="Delete patient '<?= clean($p['full_name']) ?>'? All their appointments will also be deleted." title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-wrapper">
        <small class="text-muted">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?> (<?= $pagination['total'] ?> total)</small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">‹</a>
                </li>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($pagination['total_pages'], $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $pagination['total_pages']): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">›</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">
                    <i class="bi bi-key-fill text-warning me-2"></i>Reset Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle"></i>
                    Setting a new password for <strong id="resetPatientName"></strong>
                </div>
                <input type="hidden" id="resetPatientId">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">New Password</label>
                    <div class="input-group">
                        <input type="password" id="newPasswordInput" class="form-control" 
                               placeholder="Min 6 characters" minlength="6" maxlength="100">
                        <button type="button" class="btn btn-outline-secondary" id="toggleNewPwd" tabindex="-1">
                            <i class="bi bi-eye" id="newPwdIcon"></i>
                        </button>
                    </div>
                    <div class="form-text" id="pwdStrength"></div>
                    <div class="text-danger small mt-1" id="resetPwdError" style="display:none;"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold small">Confirm Password</label>
                    <input type="password" id="confirmPasswordInput" class="form-control" 
                           placeholder="Re-enter password" maxlength="100">
                    <div class="text-danger small mt-1" id="confirmPwdError" style="display:none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-warning fw-semibold" id="confirmResetBtn">
                    <i class="bi bi-check-lg"></i> Reset Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast for notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="resetToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="resetToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
    const csrfToken = '<?= $csrfToken ?>';

    // Note: Password toggle is now handled in footer.php globally
    
    // ─── Reset Password Modal ───
    const modal          = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    const resetPatientId = document.getElementById('resetPatientId');
    const resetName      = document.getElementById('resetPatientName');
    const newPwdInput    = document.getElementById('newPasswordInput');
    const confirmPwdIn   = document.getElementById('confirmPasswordInput');
    const pwdStrength    = document.getElementById('pwdStrength');
    const resetPwdErr    = document.getElementById('resetPwdError');
    const confirmPwdErr  = document.getElementById('confirmPwdError');
    const confirmBtn     = document.getElementById('confirmResetBtn');
    const toggleNewPwd   = document.getElementById('toggleNewPwd');
    const newPwdIcon     = document.getElementById('newPwdIcon');

    // Open modal on key icon click
    document.querySelectorAll('.reset-pwd-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            resetPatientId.value = this.dataset.patientId;
            resetName.textContent = this.dataset.patientName;
            newPwdInput.value = '';
            confirmPwdIn.value = '';
            pwdStrength.innerHTML = '';
            resetPwdErr.style.display = 'none';
            confirmPwdErr.style.display = 'none';
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bi bi-check-lg"></i> Reset Password';
            modal.show();
        });
    });

    // Toggle new password visibility in modal
    toggleNewPwd.addEventListener('click', () => {
        const isPassword = newPwdInput.type === 'password';
        newPwdInput.type = isPassword ? 'text' : 'password';
        newPwdIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // Password strength indicator
    newPwdInput.addEventListener('input', () => {
        const val = newPwdInput.value;
        resetPwdErr.style.display = 'none';
        if (!val) { pwdStrength.innerHTML = ''; return; }

        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { label: 'Very Weak', color: '#ef4444', bg: 'rgba(239,68,68,0.1)' },
            { label: 'Weak',      color: '#f97316', bg: 'rgba(249,115,22,0.1)' },
            { label: 'Fair',      color: '#eab308', bg: 'rgba(234,179,8,0.1)' },
            { label: 'Good',      color: '#22c55e', bg: 'rgba(34,197,94,0.1)' },
            { label: 'Strong',    color: '#10b981', bg: 'rgba(16,185,129,0.1)' }
        ];
        const level = levels[Math.min(score, 4)];
        pwdStrength.innerHTML = '<span class="badge" style="background:' + level.bg + ';color:' + level.color + ';font-size:0.7rem;margin-top:0.35rem;">' + level.label + '</span>';
    });

    confirmPwdIn.addEventListener('input', () => {
        confirmPwdErr.style.display = 'none';
    });

    // Submit reset
    confirmBtn.addEventListener('click', async function() {
        resetPwdErr.style.display = 'none';
        confirmPwdErr.style.display = 'none';

        const newPwd     = newPwdInput.value;
        const confirmPwd = confirmPwdIn.value;

        if (newPwd.length < 6) {
            resetPwdErr.textContent = 'Password must be at least 6 characters.';
            resetPwdErr.style.display = 'block';
            newPwdInput.focus();
            return;
        }
        if (newPwd !== confirmPwd) {
            confirmPwdErr.textContent = 'Passwords do not match.';
            confirmPwdErr.style.display = 'block';
            confirmPwdIn.focus();
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Resetting...';

        try {
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('patient_id', resetPatientId.value);
            formData.append('new_password', newPwd);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('patients.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                modal.hide();
                showToast(data.message, 'success');
            } else {
                resetPwdErr.textContent = data.message;
                resetPwdErr.style.display = 'block';
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-check-lg"></i> Reset Password';
            }
        } catch (err) {
            console.error('Reset password error:', err);
            resetPwdErr.textContent = 'Network error. Please try again.';
            resetPwdErr.style.display = 'block';
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bi bi-check-lg"></i> Reset Password';
        }
    });

    // ─── Toast Helper ───
    function showToast(message, type) {
        const toastEl   = document.getElementById('resetToast');
        const toastBody = document.getElementById('resetToastBody');
        toastBody.textContent = message;
        toastEl.className = 'toast align-items-center border-0 text-white ' + 
            (type === 'success' ? 'bg-success' : 'bg-danger');
        const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4000 });
        toast.show();
    }
})();
</script>
