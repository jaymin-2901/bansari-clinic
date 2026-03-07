<?php
/**
 * Bansari Homeopathy – Manage Appointments
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Appointments';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        try {
            $db = getClinicDB();
            $id = (int)$_POST['appointment_id'];

            if ($_POST['action'] === 'update_status') {
                $newStatus = $_POST['status'];
                $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
                if (in_array($newStatus, $allowed)) {
                    $db->prepare("UPDATE appointments SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                    setFlash('success', 'Appointment status updated.');
                }
            } elseif ($_POST['action'] === 'delete') {
                $db->prepare("DELETE FROM appointments WHERE id = ?")->execute([$id]);
                setFlash('success', 'Appointment deleted.');
            }
        } catch (PDOException $e) {
            error_log('Appointment action error: ' . $e->getMessage());
            setFlash('error', 'An error occurred. Please try again.');
        }
    }
    header('Location: appointments.php?' . http_build_query($_GET));
    exit;
}

try {
    $db = getClinicDB();

    // Filters
    $search = trim($_GET['search'] ?? '');
    $filterType = $_GET['type'] ?? '';
    $filterStatus = $_GET['status'] ?? '';
    $filterDate = $_GET['date'] ?? '';
    $filterDateTo = $_GET['date_to'] ?? '';
    $filterFollowup = $_GET['followup'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    // Build query
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(p.full_name LIKE ? OR p.mobile LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterType && in_array($filterType, ['offline', 'online'])) {
        $where[] = "a.consultation_type = ?";
        $params[] = $filterType;
    }
    if ($filterStatus && in_array($filterStatus, ['pending', 'confirmed', 'completed', 'cancelled'])) {
        $where[] = "a.status = ?";
        $params[] = $filterStatus;
    }
    if ($filterDate && $filterDateTo) {
        $where[] = "a.appointment_date BETWEEN ? AND ?";
        $params[] = $filterDate;
        $params[] = $filterDateTo;
    } elseif ($filterDate) {
        $where[] = "a.appointment_date = ?";
        $params[] = $filterDate;
    }
    if ($filterFollowup === 'pending') {
        $where[] = "a.status = 'completed' AND a.followup_done = 0";
    } elseif ($filterFollowup === 'done') {
        $where[] = "a.followup_done = 1";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM appointments a JOIN patients p ON a.patient_id = p.id $whereSQL");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();

    $pagination = getPagination($totalRecords, $perPage, $page);

    // Fetch
    $stmt = $db->prepare("
        SELECT a.id, a.consultation_type, a.form_type, a.appointment_date, a.appointment_time, a.status, a.created_at,
               p.full_name, p.mobile, p.email, a.patient_id,
               (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id = a.patient_id AND a2.status != 'cancelled' AND a2.id < a.id) as prev_appointments
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        $whereSQL
        ORDER BY a.appointment_date DESC, a.appointment_time ASC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    // Booked slots count for selected date
    $slotsToday = 0;
    if ($filterDate) {
        $sc = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status != 'cancelled'");
        $sc->execute([$filterDate]);
        $slotsToday = (int)$sc->fetchColumn();
    }

} catch (PDOException $e) {
    $appointments = [];
    $pagination = getPagination(0);
    $slotsToday = 0;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold">All Appointments (<?= $pagination['total'] ?>)</h6>
        <div class="d-flex gap-2 align-items-center">
            <!-- Export Button -->
            <a href="api/export_appointments.php?date_from=<?= urlencode($filterDate ?: date('Y-m-d', strtotime('-30 days'))) ?>&date_to=<?= urlencode($filterDateTo ?: date('Y-m-d')) ?><?= $filterStatus ? '&status=' . urlencode($filterStatus) : '' ?><?= $filterType ? '&type=' . urlencode($filterType) : '' ?>" 
               class="btn btn-sm btn-outline-success" title="Export to Excel">
                <i class="bi bi-file-earmark-excel me-1"></i>Export
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="px-3 pt-3">
        <form class="d-flex gap-2 flex-wrap align-items-end" method="GET">
            <div>
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" style="width:180px" 
                       placeholder="Name or mobile..." value="<?= clean($search) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="date" class="form-control form-control-sm" style="width:140px" 
                       value="<?= clean($filterDate) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" style="width:140px" 
                       value="<?= clean($filterDateTo) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Type</label>
                <select name="type" class="form-select form-select-sm" style="width:110px">
                    <option value="">All Types</option>
                    <option value="offline" <?= $filterType === 'offline' ? 'selected' : '' ?>>Offline</option>
                    <option value="online" <?= $filterType === 'online' ? 'selected' : '' ?>>Online</option>
                </select>
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" style="width:120px">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Follow-up</label>
                <select name="followup" class="form-select form-select-sm" style="width:120px">
                    <option value="">All</option>
                    <option value="pending" <?= $filterFollowup === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="done" <?= $filterFollowup === 'done' ? 'selected' : '' ?>>Done</option>
                </select>
            </div>
            <div>
                <button class="btn btn-sm btn-success">Filter</button>
                <?php if ($search || $filterType || $filterStatus || $filterDate || $filterDateTo || $filterFollowup): ?>
                <a href="appointments.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($filterDate && $slotsToday > 0): ?>
    <div class="alert alert-info mx-3 mt-2 mb-0 py-2 small">
        <i class="bi bi-calendar-check me-1"></i>
        <strong><?= $slotsToday ?></strong> slot(s) booked on <?= formatDate($filterDate) ?>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Mobile</th>
                    <th>Type</th>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No appointments found</td></tr>
                <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                <tr data-appointment-id="<?= $apt['id'] ?>">
                    <td><strong>#<?= $apt['id'] ?></strong></td>
                    <td><?= clean($apt['full_name']) ?></td>
                    <td><?= clean($apt['mobile']) ?></td>
                    <td><span class="badge-status badge-<?= $apt['consultation_type'] ?>"><?= ucfirst($apt['consultation_type']) ?></span></td>
                    <td><span class="badge-status badge-<?= ((int)$apt['prev_appointments'] > 0) ? 'secondary' : 'info' ?>"><?= ((int)$apt['prev_appointments'] > 0) ? 'Old' : 'New' ?></span></td>
                    <td><?= formatDate($apt['appointment_date']) ?></td>
                    <td><?= $apt['appointment_time'] ? date('g:i A', strtotime($apt['appointment_time'])) : '—' ?></td>
                    <td>
                        <span class="badge-status badge-<?= $apt['status'] ?> clickable-status" 
                              data-id="<?= $apt['id'] ?>" 
                              data-current="<?= $apt['status'] ?>"
                              title="Click to change status">
                            <?= ucfirst($apt['status']) ?>
                        </span>
                    </td>
                    <td class="d-flex gap-1">
                        <a href="appointment_view.php?id=<?= $apt['id'] ?>" class="btn btn-sm btn-action btn-outline-primary" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($apt['status'] !== 'completed'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="completed">
                            <button class="btn btn-sm btn-action btn-outline-success" title="Mark Completed">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-action btn-outline-danger" data-confirm="Are you sure you want to delete this appointment?" title="Delete">
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

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-wrapper">
        <small class="text-muted">
            Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $perPage, $pagination['total']) ?> of <?= $pagination['total'] ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $filterType ?>&status=<?= $filterStatus ?>&date=<?= $filterDate ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Status Cycle Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square text-primary me-2"></i>Change Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Select new status for appointment #<span id="statusAppointmentId"></span>:</p>
                <div class="d-flex flex-column gap-2" id="statusOptions">
                    <!-- Status options will be populated by JS -->
                </div>
                <div id="statusUpdateResult" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="statusToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="statusToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrfToken ?>';
const statusFlow = ['pending', 'confirmed', 'completed', 'cancelled'];

document.addEventListener('DOMContentLoaded', function() {
    // Handle clickable status badges
    document.querySelectorAll('.clickable-status').forEach(function(badge) {
        badge.style.cursor = 'pointer';
        badge.addEventListener('click', function() {
            const appointmentId = this.dataset.id;
            const currentStatus = this.dataset.current;
            openStatusModal(appointmentId, currentStatus);
        });
    });
});

function openStatusModal(appointmentId, currentStatus) {
    document.getElementById('statusAppointmentId').textContent = appointmentId;
    
    const optionsContainer = document.getElementById('statusOptions');
    optionsContainer.innerHTML = '';
    
    const statusLabels = {
        'pending': { label: 'Pending', class: 'badge-pending', icon: 'bi-hourglass' },
        'confirmed': { label: 'Confirmed', class: 'badge-confirmed', icon: 'bi-check-circle' },
        'completed': { label: 'Completed', class: 'badge-completed', icon: 'bi-check-circle-fill' },
        'cancelled': { label: 'Cancelled', class: 'badge-cancelled', icon: 'bi-x-circle-fill' }
    };
    
    statusFlow.forEach(function(status) {
        const info = statusLabels[status];
        const isCurrent = status === currentStatus;
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn ' + (isCurrent ? 'btn-secondary' : 'btn-outline-primary') + ' d-flex align-items-center justify-content-between';
        btn.innerHTML = '<span class="badge-status ' + info.class + '"><i class="bi ' + info.icon + ' me-2"></i>' + info.label + '</span>' + (isCurrent ? '<i class="bi bi-check"></i>' : '');
        btn.disabled = isCurrent;
        
        if (!isCurrent) {
            btn.addEventListener('click', function() {
                updateStatus(appointmentId, status);
            });
        }
        
        optionsContainer.appendChild(btn);
    });
    
    document.getElementById('statusUpdateResult').style.display = 'none';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

function updateStatus(appointmentId, newStatus) {
    const resultDiv = document.getElementById('statusUpdateResult');
    resultDiv.innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm me-2"></span>Updating...</div>';
    resultDiv.style.display = 'block';
    
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('appointment_id', appointmentId);
    formData.append('status', newStatus);
    formData.append('csrf_token', csrfToken);
    
    fetch('appointments.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || data.redirect) {
            // Close modal and refresh page
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            showToast('Status updated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error updating status') + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // If it's not AJAX, reload page
        window.location.reload();
    });
}

function showToast(message, type) {
    const toastEl = document.getElementById('statusToast');
    const toastBody = document.getElementById('statusToastBody');
    toastBody.textContent = message;
    toastEl.className = 'toast align-items-center border-0 text-white ' + 
        (type === 'success' ? 'bg-success' : 'bg-danger');
    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3000 });
    toast.show();
}
</script>
