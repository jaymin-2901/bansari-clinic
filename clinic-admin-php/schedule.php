<?php
/**
 * Bansari Homeopathy – Admin: Clinic Schedule Management
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Clinic Schedule';

$dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// ─── Handle POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        try {
            $db = getClinicDB();

            for ($d = 0; $d <= 6; $d++) {
                $isOpen   = isset($_POST["is_open_$d"]) ? 1 : 0;
                $opening  = $_POST["opening_$d"]  ?? '10:00';
                $closing  = $_POST["closing_$d"]  ?? '19:00';
                $newDur   = max(5, (int)($_POST["new_dur_$d"]  ?? 30));
                $oldDur   = max(5, (int)($_POST["old_dur_$d"]  ?? 15));
                $breakS   = $_POST["break_start_$d"] ?? null;
                $breakE   = $_POST["break_end_$d"]   ?? null;

                // Allow empty break
                if (!$breakS) $breakS = null;
                if (!$breakE) $breakE = null;

                $db->prepare("
                    INSERT INTO clinic_schedule (day_of_week, is_open, opening_time, closing_time, new_patient_duration, old_patient_duration, break_start, break_end)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_open = VALUES(is_open),
                        opening_time = VALUES(opening_time),
                        closing_time = VALUES(closing_time),
                        new_patient_duration = VALUES(new_patient_duration),
                        old_patient_duration = VALUES(old_patient_duration),
                        break_start = VALUES(break_start),
                        break_end = VALUES(break_end),
                        updated_at = NOW()
                ")->execute([$d, $isOpen, $opening, $closing, $newDur, $oldDur, $breakS, $breakE]);
            }

            setFlash('success', 'Clinic schedule updated successfully!');
        } catch (PDOException $e) {
            error_log('Schedule update error: ' . $e->getMessage());
            setFlash('error', 'Error updating schedule. Please try again.');
        }
    }
    header('Location: schedule.php');
    exit;
}

// ─── Load current schedule ───
try {
    $db = getClinicDB();
    $rows = $db->query("SELECT * FROM clinic_schedule ORDER BY day_of_week")->fetchAll();
    $schedule = [];
    foreach ($rows as $r) {
        $schedule[$r['day_of_week']] = $r;
    }
} catch (PDOException $e) {
    $schedule = [];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
.schedule-card {
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    background: #fff;
}
.schedule-card.closed {
    background: #f8f9fa;
    opacity: 0.7;
}
.schedule-card .day-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}
.schedule-card .day-name {
    font-weight: 700;
    font-size: 1.05rem;
}
.schedule-card .form-check-input:checked {
    background-color: #2e7d32;
    border-color: #2e7d32;
}
.time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}
.time-grid label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 0.2rem;
}
.duration-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
}
</style>

<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Clinic Weekly Schedule</h6>
        <span class="text-muted small">Configure working hours, slot durations & break times</span>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <?php for ($d = 0; $d <= 6; $d++):
            $s = $schedule[$d] ?? null;
            $isOpen   = $s ? (bool)$s['is_open'] : ($d === 0 ? false : true);
            $opening  = $s['opening_time']  ?? '10:00:00';
            $closing  = $s['closing_time']  ?? '19:00:00';
            $newDur   = $s['new_patient_duration'] ?? 30;
            $oldDur   = $s['old_patient_duration'] ?? 15;
            $breakS   = $s['break_start']   ?? '13:00:00';
            $breakE   = $s['break_end']     ?? '14:00:00';
        ?>
        <div class="schedule-card <?= !$isOpen ? 'closed' : '' ?>" id="card-<?= $d ?>">
            <div class="day-header">
                <span class="day-name">
                    <i class="bi bi-calendar3 me-2 text-success"></i><?= $dayNames[$d] ?>
                </span>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="is_open_<?= $d ?>" id="open_<?= $d ?>"
                           <?= $isOpen ? 'checked' : '' ?>
                           onchange="document.getElementById('fields-<?= $d ?>').style.display = this.checked ? 'block' : 'none'; document.getElementById('card-<?= $d ?>').classList.toggle('closed', !this.checked);">
                    <label class="form-check-label small fw-semibold" for="open_<?= $d ?>">
                        <?= $isOpen ? 'Open' : 'Closed' ?>
                    </label>
                </div>
            </div>

            <div id="fields-<?= $d ?>" style="display:<?= $isOpen ? 'block' : 'none' ?>">
                <div class="time-grid">
                    <div>
                        <label>Opening Time</label>
                        <input type="time" class="form-control form-control-sm" name="opening_<?= $d ?>" value="<?= substr($opening, 0, 5) ?>">
                    </div>
                    <div>
                        <label>Closing Time</label>
                        <input type="time" class="form-control form-control-sm" name="closing_<?= $d ?>" value="<?= substr($closing, 0, 5) ?>">
                    </div>
                    <div>
                        <label>Break Start</label>
                        <input type="time" class="form-control form-control-sm" name="break_start_<?= $d ?>" value="<?= $breakS ? substr($breakS, 0, 5) : '' ?>">
                    </div>
                    <div>
                        <label>Break End</label>
                        <input type="time" class="form-control form-control-sm" name="break_end_<?= $d ?>" value="<?= $breakE ? substr($breakE, 0, 5) : '' ?>">
                    </div>
                    <div>
                        <label><span class="duration-badge bg-primary-subtle text-primary">🆕 New Patient</span> Slot (min)</label>
                        <input type="number" class="form-control form-control-sm" name="new_dur_<?= $d ?>" value="<?= $newDur ?>" min="5" max="120" step="5">
                    </div>
                    <div>
                        <label><span class="duration-badge bg-success-subtle text-success">🔄 Old Patient</span> Slot (min)</label>
                        <input type="number" class="form-control form-control-sm" name="old_dur_<?= $d ?>" value="<?= $oldDur ?>" min="5" max="120" step="5">
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>

        <div class="d-flex justify-content-end mt-3 gap-2">
            <a href="schedule.php" class="btn btn-outline-secondary">Reset</a>
            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save Schedule</button>
        </div>
    </form>
</div>

<!-- Quick info panel -->
<div class="table-container mt-4">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>How It Works</h6>
    </div>
    <div class="p-3">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h6 class="fw-bold text-success"><i class="bi bi-calendar-check me-1"></i> Open/Close Days</h6>
                    <p class="small text-muted mb-0">Toggle the switch to mark a day as open or closed. Closed days won't show available slots to patients.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h6 class="fw-bold text-primary"><i class="bi bi-clock me-1"></i> Slot Duration</h6>
                    <p class="small text-muted mb-0">New patients get longer slots (default 30 min), old/follow-up patients get shorter slots (default 15 min). Slots auto-generate based on these.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h6 class="fw-bold text-warning"><i class="bi bi-cup-hot me-1"></i> Break Time</h6>
                    <p class="small text-muted mb-0">Set lunch/break times. No appointment slots will be generated during break hours. Leave empty for no break.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
