<?php
/**
 * Bansari Homeopathy – Follow-Up Page
 * Shows today's and upcoming appointments requiring follow-up.
 * Includes WhatsApp + Email reminder sending and confirmation tracking.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Follow-Up';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        try {
            $db = getClinicDB();
            $id = (int)$_POST['appointment_id'];

            if ($_POST['action'] === 'mark_followup_done') {
                $db->prepare("UPDATE appointments SET followup_done = 1, followup_done_at = NOW() WHERE id = ?")->execute([$id]);
                setFlash('success', 'Follow-up marked as done.');
            } elseif ($_POST['action'] === 'mark_followup_pending') {
                $db->prepare("UPDATE appointments SET followup_done = 0, followup_done_at = NULL WHERE id = ?")->execute([$id]);
                setFlash('success', 'Follow-up marked as pending.');
            } elseif ($_POST['action'] === 'update_status') {
                $newStatus = $_POST['status'];
                $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
                if (in_array($newStatus, $allowed)) {
                    $db->prepare("UPDATE appointments SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                    setFlash('success', 'Appointment status updated.');
                }
            } elseif ($_POST['action'] === 'update_confirmation') {
                // Task 6: When admin sets Confirmation = Confirmed, auto-set Follow-Up Status = Done
                $newConfirmation = $_POST['confirmation_status'];
                $allowedConfirmation = ['pending', 'reminder_sent', 'confirmed', 'cancelled', 'no_response'];
                if (in_array($newConfirmation, $allowedConfirmation)) {
                    if ($newConfirmation === 'confirmed') {
                        // Auto-set followup_done = 1 when confirmation is set to confirmed
                        $db->prepare("UPDATE appointments SET confirmation_status = ?, confirmed_at = NOW(), followup_done = 1, followup_done_at = NOW() WHERE id = ?")->execute([$newConfirmation, $id]);
                        setFlash('success', 'Appointment confirmed and follow-up marked as done.');
                    } else {
                        $db->prepare("UPDATE appointments SET confirmation_status = ? WHERE id = ?")->execute([$newConfirmation, $id]);
                        setFlash('success', 'Confirmation status updated.');
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Followup action error: ' . $e->getMessage());
            setFlash('error', 'An error occurred. Please try again.');
        }
    }
    header('Location: followup.php?' . http_build_query($_GET));
    exit;
}

try {
    $db = getClinicDB();

    // Filter
    $filterView = $_GET['view'] ?? 'today';
    $filterFollowup = $_GET['followup'] ?? '';

    // Build query
    $where = ["a.status IN ('pending', 'confirmed')"];
    $params = [];

    if ($filterView === 'today') {
        $where[] = "a.appointment_date = CURDATE()";
    } elseif ($filterView === 'tomorrow') {
        $where[] = "a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($filterView === 'week') {
        $where[] = "a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($filterView === 'all') {
        $where[] = "a.appointment_date >= CURDATE()";
    }

    if ($filterFollowup === 'done') {
        $where[] = "a.followup_done = 1";
    } elseif ($filterFollowup === 'pending') {
        $where[] = "a.followup_done = 0";
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // Stats
    $todayTotal = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending','confirmed')")->fetchColumn();
    $todayPendingFollowup = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending','confirmed') AND followup_done = 0")->fetchColumn();
    $todayDoneFollowup = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending','confirmed') AND followup_done = 1")->fetchColumn();
    $remindersSent = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status IN ('pending','confirmed') AND reminder_sent = 1")->fetchColumn();
    $confirmedCount = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND confirmation_status = 'confirmed'")->fetchColumn();

    // Fetch appointments with reminder/confirmation fields
    $stmt = $db->prepare("
        SELECT a.id, a.consultation_type, a.form_type, a.appointment_date, a.appointment_time,
               a.status, a.followup_done, a.followup_done_at,
               a.reminder_sent, a.reminder_sent_at, a.confirmation_status, a.reply_source, a.confirmed_at,
               a.is_followup, a.admin_notes, a.created_at,
               p.full_name, p.mobile, p.email, p.city, a.patient_id,
               (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id = a.patient_id AND a2.status != 'cancelled' AND a2.id < a.id) as prev_appointments
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        $whereSQL
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    $appointments = [];
    $todayTotal = $todayPendingFollowup = $todayDoneFollowup = $remindersSent = $confirmedCount = 0;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Follow-Up Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $todayTotal ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $todayPendingFollowup ?></div>
                    <div class="stat-label">Pending Follow-Up</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $todayDoneFollowup ?></div>
                    <div class="stat-label">Follow-Up Done</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-send-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $remindersSent ?></div>
                    <div class="stat-label">Reminders Sent</div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Filter Tabs -->
<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-telephone-outbound me-1"></i>
            Follow-Up Tracking
            <span class="badge bg-primary ms-2"><?= count($appointments) ?></span>
        </h6>

        <div class="d-flex gap-2 flex-wrap align-items-center">
            <!-- View filter -->
            <div class="btn-group btn-group-sm" role="group">
                <a href="?view=today" class="btn <?= $filterView === 'today' ? 'btn-primary' : 'btn-outline-primary' ?>">Today</a>
                <a href="?view=tomorrow" class="btn <?= $filterView === 'tomorrow' ? 'btn-primary' : 'btn-outline-primary' ?>">Tomorrow</a>
                <a href="?view=week" class="btn <?= $filterView === 'week' ? 'btn-primary' : 'btn-outline-primary' ?>">This Week</a>
                <a href="?view=all" class="btn <?= $filterView === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All Upcoming</a>
            </div>

            <!-- Follow-up status filter -->
            <select class="form-select form-select-sm" style="width: 150px" onchange="location.href='?view=<?= $filterView ?>&followup='+this.value">
                <option value="" <?= $filterFollowup === '' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $filterFollowup === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                <option value="done" <?= $filterFollowup === 'done' ? 'selected' : '' ?>>✅ Done</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Mobile</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Reminder</th>
                    <th>Confirmation</th>
                    <th>Follow-Up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">No appointments found for this filter.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                <?php
                    $isToday = ($apt['appointment_date'] === date('Y-m-d'));
                    $rowClass = $isToday && !$apt['followup_done'] ? 'table-warning' : '';
                    $reminderSent = (int)($apt['reminder_sent'] ?? 0);
                    $confirmStatus = $apt['confirmation_status'] ?? 'pending';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><strong>#<?= $apt['id'] ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div>
                                <span class="fw-semibold"><?= clean($apt['full_name']) ?></span>
                                <?php if ((int)$apt['prev_appointments'] === 0): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info ms-1" style="font-size:0.65rem">NEW</span>
                                <?php endif; ?>
                                <?php if ((int)($apt['is_followup'] ?? 0)): ?>
                                    <span class="badge bg-purple bg-opacity-10 text-purple ms-1" style="font-size:0.65rem">FOLLOW-UP</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="tel:<?= preg_replace('/[^0-9]/', '', $apt['mobile']) ?>" 
                           class="text-decoration-none" title="Call">
                            <?= clean($apt['mobile']) ?>
                            <i class="bi bi-telephone text-primary ms-1"></i>
                        </a>
                    </td>
                    <td><span class="badge-status badge-<?= $apt['consultation_type'] ?>"><?= ucfirst($apt['consultation_type']) ?></span></td>
                    <td>
                        <?= formatDate($apt['appointment_date']) ?>
                        <?php if ($isToday): ?>
                            <span class="badge bg-primary ms-1" style="font-size:0.6rem">TODAY</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $apt['appointment_time'] ? date('g:i A', strtotime($apt['appointment_time'])) : '—' ?></td>
                    
                    <!-- Appointment Status -->
                    <td>
                        <?php
                        $statusBadgeMap = [
                            'pending' => 'badge-pending',
                            'confirmed' => 'badge-confirmed',
                            'completed' => 'badge-completed',
                            'cancelled' => 'badge-cancelled',
                        ];
                        $statusClass = $statusBadgeMap[$apt['status']] ?? 'badge-pending';
                        ?>
                        <span class="badge-status <?= $statusClass ?>"><?= ucfirst($apt['status']) ?></span>
                    </td>

                    <!-- Reminder Status -->
                    <td>
                        <?php if ($reminderSent): ?>
                            <span class="badge-status badge-reminder_sent" title="Sent: <?= $apt['reminder_sent_at'] ? date('d M g:i A', strtotime($apt['reminder_sent_at'])) : '' ?>">
                                <i class="bi bi-check-circle me-1"></i>Sent
                            </span>
                            <?php if ($apt['reply_source']): ?>
                                <br><small class="text-muted">via <?= ucfirst(str_replace('_', ' ', $apt['reply_source'])) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-status badge-not_sent">
                                <i class="bi bi-clock me-1"></i>Not Sent
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Confirmation Status -->
                    <td>
                        <?php
                        $confirmBadgeMap = [
                            'pending' => ['class' => 'badge-pending', 'icon' => 'bi-hourglass', 'label' => 'Pending'],
                            'reminder_sent' => ['class' => 'badge-reminder_sent', 'icon' => 'bi-send', 'label' => 'Awaiting Reply'],
                            'confirmed' => ['class' => 'badge-confirmed', 'icon' => 'bi-check-circle-fill', 'label' => 'Confirmed'],
                            'cancelled' => ['class' => 'badge-cancelled', 'icon' => 'bi-x-circle-fill', 'label' => 'Cancelled'],
                            'no_response' => ['class' => 'badge-no_response', 'icon' => 'bi-question-circle', 'label' => 'No Response'],
                        ];
                        $badge = $confirmBadgeMap[$confirmStatus] ?? $confirmBadgeMap['pending'];
                        ?>
                        <span class="badge-status <?= $badge['class'] ?>">
                            <i class="bi <?= $badge['icon'] ?> me-1"></i><?= $badge['label'] ?>
                        </span>
                        <?php if ($apt['confirmed_at']): ?>
                            <br><small class="text-muted"><?= date('d M g:i A', strtotime($apt['confirmed_at'])) ?></small>
                        <?php endif; ?>
                    </td>

                    <!-- Follow-Up Status -->
                    <td>
                        <?php if ((int)$apt['followup_done']): ?>
                            <span class="badge-status badge-completed" title="Done: <?= $apt['followup_done_at'] ?>">
                                ✅ Done
                            </span>
                        <?php else: ?>
                            <span class="badge-status badge-pending">⏳ Pending</span>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="appointment_view.php?id=<?= $apt['id'] ?>" class="btn btn-sm btn-action btn-outline-primary" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>

                            <!-- Send Reminder Buttons (Separate WhatsApp & Email) -->
                            <?php if (!in_array($apt['status'], ['completed', 'cancelled']) && $confirmStatus !== 'confirmed'): ?>
                            <button class="btn btn-sm btn-action btn-followup-whatsapp" 
                                    data-appointment-id="<?= $apt['id'] ?>" 
                                    data-patient-name="<?= clean($apt['full_name']) ?>"
                                    data-patient-mobile="<?= clean($apt['mobile']) ?>"
                                    data-patient-email="<?= clean($apt['email'] ?? '') ?>"
                                    data-appointment-date="<?= formatDate($apt['appointment_date']) ?>"
                                    data-appointment-time="<?= $apt['appointment_time'] ? date('g:i A', strtotime($apt['appointment_time'])) : 'TBC' ?>"
                                    data-channel="whatsapp"
                                    title="Send WhatsApp Reminder">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                            <button class="btn btn-sm btn-action btn-followup-email" 
                                    data-appointment-id="<?= $apt['id'] ?>" 
                                    data-patient-name="<?= clean($apt['full_name']) ?>"
                                    data-patient-mobile="<?= clean($apt['mobile']) ?>"
                                    data-patient-email="<?= clean($apt['email'] ?? '') ?>"
                                    data-appointment-date="<?= formatDate($apt['appointment_date']) ?>"
                                    data-appointment-time="<?= $apt['appointment_time'] ? date('g:i A', strtotime($apt['appointment_time'])) : 'TBC' ?>"
                                    data-channel="email"
                                    title="Send Email Reminder">
                                <i class="bi bi-envelope"></i>
                            </button>
                            <?php endif; ?>

                            <?php if (!(int)$apt['followup_done']): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                <input type="hidden" name="action" value="mark_followup_done">
                                <button class="btn btn-sm btn-action btn-outline-success" title="Mark Follow-Up Done">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                <input type="hidden" name="action" value="mark_followup_pending">
                                <button class="btn btn-sm btn-action btn-outline-warning" title="Undo Follow-Up">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($apt['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="confirmed">
                                <button class="btn btn-sm btn-action btn-outline-info" title="Confirm Appointment">
                                    <i class="bi bi-calendar-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Followup Reminder Modal -->
<div class="modal fade" id="followupReminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="fupModalTitle"><i class="bi bi-send-fill text-primary me-2"></i>Send Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p class="mb-1">Send <strong id="fupChannelLabel"></strong> reminder to <strong id="fupPatientName"></strong>?</p>
                    <div class="small text-muted">
                        <div><i class="bi bi-calendar me-1"></i><span id="fupDate"></span></div>
                        <div><i class="bi bi-clock me-1"></i><span id="fupTime"></span></div>
                        <div id="fupMobileRow"><i class="bi bi-phone me-1"></i><span id="fupMobile"></span></div>
                        <div id="fupEmailRow"><i class="bi bi-envelope me-1"></i><span id="fupEmail"></span></div>
                    </div>
                </div>
                <!-- WhatsApp Preview (only for whatsapp channel) -->
                <div id="fupWhatsappPreview" class="alert alert-light border small mb-2" style="display:none;">
                    <strong><i class="bi bi-whatsapp text-success me-1"></i>WhatsApp Message Preview:</strong><br>
                    <em id="fupMessagePreview" class="text-muted"></em>
                </div>
                <!-- Email Preview (only for email channel) -->
                <div id="fupEmailPreview" class="alert alert-light border small mb-2" style="display:none;">
                    <strong><i class="bi bi-envelope-fill text-danger me-1"></i>Email Preview:</strong><br>
                    <span class="text-muted">A confirmation email with <strong>Confirm</strong> and <strong>Cancel</strong> buttons will be sent from <code>jaymin29chavda@gmail.com</code>.</span>
                </div>
                <div id="fupReminderResult" class="mt-3" style="display:none;"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm" id="confirmFollowupReminder">
                    <i class="bi bi-send me-1"></i>Send
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1090">
    <div id="followupToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="followupToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
    window.ADMIN_API_KEY = 'bansari-admin-api-key-2026';
    window.NEXT_API_BASE = 'http://localhost:3000';
</script>
<script src="js/followup-reminders.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
