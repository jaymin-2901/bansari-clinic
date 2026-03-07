<?php
/**
 * Bansari Homeopathy – Admin Dashboard (Premium SaaS Style)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Dashboard';

try {
    $db = getClinicDB();

    // Stats
    $totalAppointments = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $pendingAppointments = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $offlineCount = $db->query("SELECT COUNT(*) FROM appointments WHERE consultation_type = 'offline'")->fetchColumn();
    $onlineCount = $db->query("SELECT COUNT(*) FROM appointments WHERE consultation_type = 'online'")->fetchColumn();
    $todayCount = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
    $totalPatients = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $totalTestimonials = $db->query("SELECT COUNT(*) FROM testimonials WHERE display_status = 'active'")->fetchColumn();
    $unreadMessages = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

    // Reminder Stats
    $remindersSentToday = $db->query("SELECT COUNT(*) FROM appointments WHERE reminder_sent = 1 AND DATE(reminder_sent_at) = CURDATE()")->fetchColumn();
    $awaitingConfirmation = $db->query("SELECT COUNT(*) FROM appointments WHERE confirmation_status = 'reminder_sent'")->fetchColumn();
    $confirmedCount = $db->query("SELECT COUNT(*) FROM appointments WHERE confirmation_status = 'confirmed'")->fetchColumn();
    $needsReminder = $db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending','confirmed') AND reminder_sent = 0 AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)")->fetchColumn();

    // Recent appointments
    $recentAppointments = $db->query("
        SELECT a.id, a.consultation_type, a.form_type, a.appointment_date, a.appointment_time, a.status, a.created_at,
               a.reminder_sent, a.confirmation_status, a.reply_source, a.confirmed_at,
               p.full_name, p.mobile
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        ORDER BY a.created_at DESC 
        LIMIT 8
    ")->fetchAll();

    // Weekly stats
    $weekStart = date('Y-m-d', strtotime('-7 days'));
    $weekAppointments = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date >= '$weekStart'")->fetchColumn();
    $weekCompleted = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date >= '$weekStart' AND status = 'completed'")->fetchColumn();
    $weekNewPatients = $db->query("SELECT COUNT(*) FROM patients WHERE created_at >= '$weekStart'")->fetchColumn();

} catch (PDOException $e) {
    $totalAppointments = $pendingAppointments = $offlineCount = $onlineCount = $todayCount = $totalPatients = $totalTestimonials = $unreadMessages = 0;
    $remindersSentToday = $awaitingConfirmation = $confirmedCount = $needsReminder = 0;
    $weekAppointments = $weekCompleted = $weekNewPatients = 0;
    $recentAppointments = [];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1 class="dashboard-title">Dashboard</h1>
        <p class="dashboard-subtitle">Welcome back! Here's what's happening at your clinic.</p>
    </div>
    <div class="header-actions">
        <span class="current-date">
            <i class="bi bi-calendar3"></i>
            <?= date('EEEE, MMMM d, Y') ?>
        </span>
    </div>
</div>

<!-- Stats Cards Row -->
<div class="stats-grid">
    <div class="stat-card premium">
        <div class="stat-icon-wrapper primary">
            <i class="bi bi-calendar-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $totalAppointments ?></span>
            <span class="stat-label">Total Appointments</span>
        </div>
        <div class="stat-trend positive">
            <i class="bi bi-arrow-up"></i> All time
        </div>
    </div>

    <div class="stat-card premium">
        <div class="stat-icon-wrapper warning">
            <i class="bi bi-clock-fill"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $pendingAppointments ?></span>
            <span class="stat-label">Pending</span>
        </div>
        <div class="stat-trend neutral">
            Awaiting action
        </div>
    </div>

    <div class="stat-card premium">
        <div class="stat-icon-wrapper info">
            <i class="bi bi-calendar2-day"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $todayCount ?></span>
            <span class="stat-label">Today's Appointments</span>
        </div>
        <div class="stat-trend <?= $todayCount > 0 ? 'positive' : 'neutral' ?>">
            <?= $todayCount > 0 ? '<i class="bi bi-check-circle"></i> Scheduled' : 'No appointments' ?>
        </div>
    </div>

    <div class="stat-card premium">
        <div class="stat-icon-wrapper success">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $totalPatients ?></span>
            <span class="stat-label">Total Patients</span>
        </div>
        <div class="stat-trend positive">
            <i class="bi bi-arrow-up"></i> +<?= $weekNewPatients ?> this week
        </div>
    </div>
</div>

<!-- Secondary Stats Row -->
<div class="stats-grid secondary">
    <div class="stat-card compact">
        <div class="stat-icon-small offline">
            <i class="bi bi-building"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $offlineCount ?></span>
            <span class="stat-label">Offline Visits</span>
        </div>
    </div>

    <div class="stat-card compact">
        <div class="stat-icon-small online">
            <i class="bi bi-display"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $onlineCount ?></span>
            <span class="stat-label">Online Consults</span>
        </div>
    </div>

    <div class="stat-card compact">
        <div class="stat-icon-small testimonial">
            <i class="bi bi-chat-quote"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $totalTestimonials ?></span>
            <span class="stat-label">Active Testimonials</span>
        </div>
    </div>

    <div class="stat-card compact">
        <div class="stat-icon-small message">
            <i class="bi bi-envelope"></i>
        </div>
        <div class="stat-content">
            <span class="stat-value"><?= $unreadMessages ?></span>
            <span class="stat-label">Unread Messages</span>
        </div>
    </div>
</div>

<!-- Reminder Stats Row -->
<div class="stats-grid reminder">
    <div class="stat-card reminder-card">
        <div class="reminder-header">
            <i class="bi bi-bell"></i>
            <span>Reminder System</span>
        </div>
        <div class="reminder-stats">
            <div class="reminder-item">
                <span class="reminder-value"><?= $needsReminder ?></span>
                <span class="reminder-label">Need Reminder</span>
            </div>
            <div class="reminder-item">
                <span class="reminder-value"><?= $remindersSentToday ?></span>
                <span class="reminder-label">Sent Today</span>
            </div>
            <div class="reminder-item">
                <span class="reminder-value"><?= $awaitingConfirmation ?></span>
                <span class="reminder-label">Awaiting Reply</span>
            </div>
            <div class="reminder-item">
                <span class="reminder-value confirmed"><?= $confirmedCount ?></span>
                <span class="reminder-label">Confirmed</span>
            </div>
        </div>
    </div>

    <div class="stat-card weekly-card">
        <div class="weekly-header">
            <i class="bi bi-graph-up"></i>
            <span>This Week</span>
        </div>
        <div class="weekly-stats">
            <div class="weekly-item">
                <div class="weekly-icon"><i class="bi bi-calendar-event"></i></div>
                <div class="weekly-info">
                    <span class="weekly-value"><?= $weekAppointments ?></span>
                    <span class="weekly-label">Appointments</span>
                </div>
            </div>
            <div class="weekly-item">
                <div class="weekly-icon success"><i class="bi bi-check-circle"></i></div>
                <div class="weekly-info">
                    <span class="weekly-value"><?= $weekCompleted ?></span>
                    <span class="weekly-label">Completed</span>
                </div>
            </div>
            <div class="weekly-item">
                <div class="weekly-icon primary"><i class="bi bi-person-plus"></i></div>
                <div class="weekly-info">
                    <span class="weekly-value"><?= $weekNewPatients ?></span>
                    <span class="weekly-label">New Patients</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Appointments Table -->
<div class="table-container premium">
    <div class="table-header">
        <div class="table-title">
            <i class="bi bi-clock-history"></i>
            <h6>Recent Appointments</h6>
            <span class="badge-count"><?= count($recentAppointments) ?></span>
        </div>
        <a href="appointments.php" class="btn btn-sm btn-outline-primary">
            View All <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Mobile</th>
                    <th>Type</th>
                    <th>Form</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Reminder</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAppointments)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">No appointments yet</p>
                </td></tr>
                <?php else: ?>
                <?php foreach ($recentAppointments as $apt): ?>
                <tr>
                    <td><strong>#<?= $apt['id'] ?></strong></td>
                    <td><?= clean($apt['full_name']) ?></td>
                    <td><code><?= clean($apt['mobile']) ?></code></td>
                    <td><span class="badge-status badge-<?= $apt['consultation_type'] ?>"><?= ucfirst($apt['consultation_type']) ?></span></td>
                    <td><span class="badge-status badge-<?= $apt['form_type'] ?>"><?= ucfirst($apt['form_type']) ?></span></td>
                    <td><?= formatDate($apt['appointment_date']) ?></td>
                    <td><span class="badge-status badge-<?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span></td>
                    <td>
                        <?php
                        $cStatus = $apt['confirmation_status'] ?? 'pending';
                        $reminderBadge = [
                            'pending' => ['class' => 'pending', 'label' => 'Pending'],
                            'reminder_sent' => ['class' => 'reminder_sent', 'label' => 'Sent'],
                            'confirmed' => ['class' => 'confirmed', 'label' => 'Confirmed'],
                            'cancelled' => ['class' => 'cancelled', 'label' => 'Cancelled'],
                            'no_response' => ['class' => 'no_response', 'label' => 'No Reply'],
                        ];
                        $rb = $reminderBadge[$cStatus] ?? $reminderBadge['pending'];
                        ?>
                        <span class="badge-status badge-<?= $rb['class'] ?>"><?= $rb['label'] ?></span>
                    </td>
                    <td>
                        <a href="appointment_view.php?id=<?= $apt['id'] ?>" class="btn btn-sm btn-action btn-outline-primary" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Premium Dashboard Styles */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.dashboard-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.dashboard-subtitle {
    color: var(--text-secondary);
    margin: 0.25rem 0 0;
    font-size: 0.95rem;
}

.current-date {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.stats-grid.secondary {
    grid-template-columns: repeat(4, 1fr);
}

.stats-grid.reminder {
    grid-template-columns: 2fr 1fr;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .stats-grid.reminder {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid, .stats-grid.secondary {
        grid-template-columns: 1fr;
    }
}

/* Premium Stat Card */
.stat-card.premium {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.25rem;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card.premium::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card.premium:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 74, 153, 0.12);
}

.stat-card.premium:hover::before {
    opacity: 1;
}

.stat-icon-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.stat-icon-wrapper.primary {
    background: rgba(0, 74, 153, 0.1);
    color: var(--primary);
}

.stat-icon-wrapper.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stat-icon-wrapper.info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.stat-icon-wrapper.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.stat-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.stat-trend {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-trend.positive {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.stat-trend.neutral {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

/* Compact Stat Card */
.stat-card.compact {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1rem;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
}

.stat-card.compact:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 74, 153, 0.08);
}

.stat-icon-small {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.stat-icon-small.offline {
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
}

.stat-icon-small.online {
    background: rgba(236, 72, 153, 0.1);
    color: #ec4899;
}

.stat-icon-small.testimonial {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stat-icon-small.message {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

/* Reminder & Weekly Cards */
.reminder-card, .weekly-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.25rem;
    border: 1px solid var(--border-color);
}

.reminder-header, .weekly-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.reminder-header i {
    color: var(--primary);
}

.weekly-header i {
    color: var(--accent);
}

.reminder-stats, .weekly-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.reminder-item, .weekly-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.reminder-value, .weekly-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.reminder-value.confirmed {
    color: #10b981;
}

.reminder-label, .weekly-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.weekly-item {
    flex-direction: row;
    align-items: center;
    gap: 0.75rem;
}

.weekly-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(0, 74, 153, 0.1);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
}

.weekly-icon.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.weekly-icon.primary {
    background: rgba(77, 182, 172, 0.1);
    color: var(--accent);
}

/* Premium Table Container */
.table-container.premium {
    background: var(--card-bg);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.04);
    border: 1px solid var(--border-color);
}

.table-container.premium .table-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.table-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-title i {
    font-size: 1.25rem;
    color: var(--primary);
}

.table-title h6 {
    margin: 0;
    font-weight: 600;
    color: var(--text-primary);
}

.badge-count {
    background: rgba(0, 74, 153, 0.1);
    color: var(--primary);
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.table-container.premium th {
    background: var(--table-header-bg);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
    padding: 0.875rem 1rem;
}

.table-container.premium td {
    padding: 0.875rem 1rem;
    font-size: 0.9rem;
    vertical-align: middle;
}

.table-container.premium tr {
    transition: background-color 0.2s ease;
}

.table-container.premium tbody tr:hover {
    background: var(--hover-bg);
}

/* Dark mode adjustments */
[data-theme="dark"] .stat-card.premium:hover {
    box-shadow: 0 12px 24px rgba(77, 182, 172, 0.15);
}

[data-theme="dark"] .stat-icon-wrapper.primary {
    background: rgba(77, 182, 172, 0.15);
    color: var(--accent);
}

[data-theme="dark"] .stat-card.premium::before {
    background: linear-gradient(90deg, var(--accent), #10b981);
}
</style>

<?php require_once __DIR__ . '/includes/footer.php';
