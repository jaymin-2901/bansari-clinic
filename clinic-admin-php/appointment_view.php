<?php
/**
 * Bansari Homeopathy – View Appointment Detail
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Appointment Details';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: appointments.php');
    exit;
}

try {
    $db = getClinicDB();

    // Get appointment + patient
    $stmt = $db->prepare("
        SELECT a.*, p.full_name, p.mobile, p.age, p.gender, p.city, p.email
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $apt = $stmt->fetch();

    if (!$apt) {
        setFlash('error', 'Appointment not found.');
        header('Location: appointments.php');
        exit;
    }

    // Get form data based on type
    $complaints = [];
    $mainComplaints = [];
    $pastDiseases = [];
    $familyHistory = [];
    $physicalGenerals = null;
    $mentalProfile = null;

    if ($apt['form_type'] === 'short') {
        $stmt = $db->prepare("SELECT * FROM complaints WHERE appointment_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $complaints = $stmt->fetch() ?: [];
    } else {
        $stmt = $db->prepare("SELECT * FROM main_complaints WHERE appointment_id = ? ORDER BY sort_order");
        $stmt->execute([$id]);
        $mainComplaints = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM past_diseases WHERE appointment_id = ?");
        $stmt->execute([$id]);
        $pastDiseases = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM family_history WHERE appointment_id = ?");
        $stmt->execute([$id]);
        $familyHistory = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM physical_generals WHERE appointment_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $physicalGenerals = $stmt->fetch();

        $stmt = $db->prepare("SELECT * FROM mental_profile WHERE appointment_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $mentalProfile = $stmt->fetch();
    }

} catch (PDOException $e) {
    setFlash('error', 'Error loading appointment.');
    header('Location: appointments.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="mb-3">
    <a href="appointments.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Appointments
    </a>
</div>

<div class="row g-4">
    <!-- Patient Info -->
    <div class="col-lg-4">
        <div class="form-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-circle text-primary"></i> Patient Information</h6>
            <div class="detail-row"><div class="detail-label">Name</div><div class="detail-value"><?= clean($apt['full_name']) ?></div></div>
            <div class="detail-row"><div class="detail-label">Mobile</div><div class="detail-value"><?= clean($apt['mobile']) ?></div></div>
            <div class="detail-row"><div class="detail-label">Age</div><div class="detail-value"><?= $apt['age'] ?? '-' ?></div></div>
            <div class="detail-row"><div class="detail-label">Gender</div><div class="detail-value"><?= ucfirst($apt['gender'] ?? '-') ?></div></div>
            <div class="detail-row"><div class="detail-label">City</div><div class="detail-value"><?= clean($apt['city'] ?? '-') ?></div></div>
        </div>

        <div class="form-card mt-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-calendar-check text-success"></i> Appointment Details</h6>
            <div class="detail-row"><div class="detail-label">ID</div><div class="detail-value">#<?= $apt['id'] ?></div></div>
            <div class="detail-row"><div class="detail-label">Date</div><div class="detail-value"><?= formatDate($apt['appointment_date']) ?></div></div>
            <div class="detail-row"><div class="detail-label">Time</div><div class="detail-value"><?= $apt['appointment_time'] ?? '-' ?></div></div>
            <div class="detail-row"><div class="detail-label">Type</div><div class="detail-value"><span class="badge-status badge-<?= $apt['consultation_type'] ?>"><?= ucfirst($apt['consultation_type']) ?></span></div></div>
            <div class="detail-row"><div class="detail-label">Form</div><div class="detail-value"><span class="badge-status badge-<?= $apt['form_type'] ?>"><?= ucfirst($apt['form_type']) ?></span></div></div>
            <div class="detail-row"><div class="detail-label">Status</div><div class="detail-value"><span class="badge-status badge-<?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span></div></div>
            <div class="detail-row"><div class="detail-label">Booked On</div><div class="detail-value"><?= formatDate($apt['created_at'], 'd M Y, h:i A') ?></div></div>

            <!-- Status Update -->
            <form method="POST" action="appointments.php" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                <input type="hidden" name="action" value="update_status">
                <div class="d-flex gap-2">
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $apt['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Medical Data -->
    <div class="col-lg-8">
        <?php if ($apt['form_type'] === 'short' && $complaints): ?>
        <!-- Short Form Data -->
        <div class="form-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-pulse text-danger"></i> Medical Details (Short Form)</h6>
            <div class="detail-row"><div class="detail-label">Chief Complaint</div><div class="detail-value"><?= clean($complaints['chief_complaint'] ?? '-') ?></div></div>
            <div class="detail-row"><div class="detail-label">Duration</div><div class="detail-value"><?= clean($complaints['complaint_duration'] ?? '-') ?></div></div>
            <div class="detail-row">
                <div class="detail-label">Major Diseases</div>
                <div class="detail-value">
                    <?php
                    $diseases = json_decode($complaints['major_diseases'] ?? '[]', true);
                    echo $diseases ? implode(', ', array_map('clean', $diseases)) : 'None';
                    ?>
                </div>
            </div>
            <div class="detail-row"><div class="detail-label">Current Medicines</div><div class="detail-value"><?= clean($complaints['current_medicines'] ?? 'None') ?></div></div>
            <div class="detail-row"><div class="detail-label">Allergy</div><div class="detail-value"><?= clean($complaints['allergy'] ?? 'None') ?></div></div>
        </div>

        <?php elseif ($apt['form_type'] === 'full'): ?>
        <!-- Full Form Data -->
        
        <?php if ($mainComplaints): ?>
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-list-check text-danger"></i> Main Complaints</h6>
            <?php foreach ($mainComplaints as $i => $c): ?>
            <div class="bg-light rounded p-3 mb-2">
                <strong>Complaint #<?= $i + 1 ?>:</strong> <?= clean($c['complaint_text']) ?>
                <div class="small text-muted mt-1">
                    Duration: <?= clean($c['duration'] ?? '-') ?> | 
                    Severity: <span class="badge bg-<?= $c['severity'] === 'severe' ? 'danger' : ($c['severity'] === 'moderate' ? 'warning' : 'success') ?>"><?= ucfirst($c['severity']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($pastDiseases): ?>
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-file-medical text-info"></i> Past Medical History</h6>
            <?php foreach ($pastDiseases as $d): ?>
            <div class="bg-light rounded p-3 mb-2">
                <strong><?= clean($d['disease_name']) ?></strong>
                <?= $d['is_current'] ? ' <span class="badge bg-warning">Ongoing</span>' : '' ?>
                <div class="small text-muted mt-1">
                    Year: <?= clean($d['year_diagnosed'] ?? '-') ?> | 
                    Treatment: <?= clean($d['treatment_taken'] ?? '-') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($familyHistory): ?>
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-people text-success"></i> Family History</h6>
            <?php foreach ($familyHistory as $f): ?>
            <div class="detail-row">
                <div class="detail-label"><?= clean($f['relation']) ?></div>
                <div class="detail-value"><?= clean($f['disease']) ?> <?= $f['details'] ? '– ' . clean($f['details']) : '' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($physicalGenerals): ?>
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse text-danger"></i> Physical Generals</h6>
            <div class="row">
                <?php
                $pgFields = [
                    'appetite' => 'Appetite', 'thirst' => 'Thirst', 'stool' => 'Stool',
                    'urine' => 'Urine', 'sweat' => 'Sweat', 'sleep_quality' => 'Sleep',
                    'sleep_position' => 'Sleep Position', 'thermal' => 'Thermal',
                    'cravings' => 'Cravings', 'aversions' => 'Aversions'
                ];
                foreach ($pgFields as $key => $label):
                ?>
                <div class="col-6 col-md-4 mb-2">
                    <small class="text-muted d-block"><?= $label ?></small>
                    <span class="fw-medium"><?= clean(ucfirst($physicalGenerals[$key] ?? '-')) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($mentalProfile): ?>
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-emoji-smile text-primary"></i> Mental & Emotional Profile</h6>
            <?php
            $mpFields = [
                'temperament' => 'Temperament', 'fears' => 'Fears', 'dreams' => 'Dreams',
                'stress_factors' => 'Stress Factors', 'emotional_state' => 'Emotional State',
                'hobbies' => 'Hobbies', 'social_behavior' => 'Social Behavior',
                'additional_notes' => 'Additional Notes'
            ];
            foreach ($mpFields as $key => $label):
                if (!empty($mentalProfile[$key])):
            ?>
            <div class="detail-row">
                <div class="detail-label"><?= $label ?></div>
                <div class="detail-value"><?= clean($mentalProfile[$key]) ?></div>
            </div>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
