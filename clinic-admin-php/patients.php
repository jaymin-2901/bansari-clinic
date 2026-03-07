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
            $stmt = $db->prepare("UPDATE patients SET password = ?, plain_password = ?, is_registered = 1 WHERE id = ?");
            $stmt->execute([$hash, $newPassword, $patientId]);

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
    // Include plain_password for display (Task 7)
    $sql = "SELECT p.id, p.full_name, p.mobile, p.email, p.age, p.gender, p.city,
                   p.is_registered, p.created_at, p.updated_at, p.plain_password,
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

<!-- Filters -->
<div class="table-container mb-3">
    <form method="GET" class="row g-2 p-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" 
                   value="<?= clean($search) ?>" placeholder="Name, mobile, email, city...">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Gender</label>
            <select name="gender" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="male" <?= $filterGender === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= $filterGender === 'female' ? 'selected' : '' ?>>Female</option>
                <option value="other" <?= $filterGender === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Registered</label>
            <select name="registered" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="1" <?= $filterReg === '1' ? 'selected' : '' ?>>Yes</option>
                <option value="0" <?= $filterReg === '0' ? 'selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-success w-100"><i class="bi bi-search"></i> Filter</button>
        </div>
        <div class="col-md-2">
            <a href="patients.php" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </form>
</div>

<!-- Patients Table -->
<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill text-primary me-1"></i> Patients (<?= $pagination['total'] ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= sortLink('full_name', 'Name') ?></th>
                    <th><?= sortLink('mobile', 'Mobile') ?></th>
                    <th>Email</th>
                    <th><?= sortLink('age', 'Age') ?></th>
                    <th><?= sortLink('gender', 'Gender') ?></th>
                    <th><?= sortLink('city', 'City') ?></th>
                    <th>Registered</th>
                    <th>Password</th>
                    <th><?= sortLink('appointments', 'Appts') ?></th>
                    <th><?= sortLink('created_at', 'Joined') ?></th>
                    <th>Actions</th>
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
                <tr>
                    <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="admin-avatar" style="width:32px;height:32px;font-size:0.75rem;">
                                <?= strtoupper(substr($p['full_name'], 0, 1)) ?>
                            </div>
                            <span class="fw-semibold"><?= clean($p['full_name']) ?></span>
                        </div>
                    </td>
                    <td><code><?= clean($p['mobile']) ?></code></td>
                    <td><small class="text-muted"><?= $p['email'] ? clean($p['email']) : '-' ?></small></td>
                    <td><?= $p['age'] ?: '-' ?></td>
                    <td>
                        <?php if ($p['gender']): ?>
                        <span class="badge bg-<?= $p['gender'] === 'male' ? 'info' : ($p['gender'] === 'female' ? 'pink' : 'secondary') ?> bg-opacity-10 text-<?= $p['gender'] === 'male' ? 'info' : ($p['gender'] === 'female' ? 'danger' : 'secondary') ?>">
                            <?= ucfirst($p['gender']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $p['city'] ? clean($p['city']) : '-' ?></td>
                    <td>
                        <?php if ($p['is_registered']): ?>
                        <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i> Yes</span>
                        <?php else: ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['is_registered'] && $p['has_password']): ?>
                        <div class="d-flex align-items-center gap-1">
                            <code class="pwd-display" id="pwd-<?= $p['id'] ?>" data-plain="<?= clean($p['plain_password'] ?? '') ?>">••••••••</code>
                            <button type="button" class="btn btn-sm btn-link text-primary p-0 ms-1 toggle-pwd-btn" 
                                    data-patient-id="<?= $p['id'] ?>" 
                                    title="Show/Hide Password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <?php elseif ($p['is_registered']): ?>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i> Not set</span>
                            <button type="button" class="btn btn-sm btn-link text-warning p-0 ms-1 reset-pwd-btn" 
                                    data-patient-id="<?= $p['id'] ?>" 
                                    data-patient-name="<?= clean($p['full_name']) ?>" 
                                    title="Set Password">
                                <i class="bi bi-key"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['appt_count'] > 0): ?>
                        <a href="appointments.php?search=<?= urlencode($p['mobile']) ?>" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none">
                            <?= $p['appt_count'] ?> <i class="bi bi-arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= formatDate($p['created_at'], 'd M Y') ?></small></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-action btn-outline-danger" data-confirm="Delete patient '<?= clean($p['full_name']) ?>'? All their appointments will also be deleted." title="Delete">
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
                // Update the mask display for this patient
                const maskEl = document.getElementById('pwd-' + resetPatientId.value);
                if (maskEl) {
                    maskEl.textContent = '••••••••';
                    maskEl.dataset.plain = newPwd;
                }
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
