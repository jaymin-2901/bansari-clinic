<?php $currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'], '.php'); ?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">B</div>
            <div>
                <h5 class="mb-0 text-white fw-bold">Bansari Clinic</h5>
                <small class="text-white-50">Admin Panel</small>
            </div>
        </div>
        <button class="btn btn-link text-white d-lg-none" id="closeSidebar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="appointments.php" class="nav-link <?= $currentPage === 'appointments' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="followup.php" class="nav-link <?= $currentPage === 'followup' ? 'active' : '' ?>">
                    <i class="bi bi-telephone-outbound-fill"></i>
                    <span>Follow-Up</span>
                    <?php
                    try {
                        $dbNav = getClinicDB();
                        $pendingFU = $dbNav->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending','confirmed') AND followup_done = 0")->fetchColumn();
                        if ($pendingFU > 0) echo '<span class="badge bg-warning text-dark ms-auto">' . $pendingFU . '</span>';
                    } catch (Exception $e) {}
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="patients.php" class="nav-link <?= $currentPage === 'patients' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Patients</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="testimonials.php" class="nav-link <?= $currentPage === 'testimonials' ? 'active' : '' ?>">
                    <i class="bi bi-star-fill"></i>
                    <span>Testimonials</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="messages.php" class="nav-link <?= $currentPage === 'messages' ? 'active' : '' ?>">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Messages</span>
                    <?php
                    try {
                        $db = getClinicDB();
                        $unread = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
                        if ($unread > 0) echo '<span class="badge bg-danger ms-auto">' . $unread . '</span>';
                    } catch (Exception $e) {}
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="backups.php" class="nav-link <?= $currentPage === 'backups' ? 'active' : '' ?>">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>Backups</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="restore_backup.php" class="nav-link <?= $currentPage === 'restore_backup' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Restore Backup</span>
                </a>
            </li>
            <li class="sidebar-divider"></li>
            <li class="sidebar-section-title">Website</li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?= in_array($currentPage, ['settings', 'about', 'contact_settings']) ? 'active' : '' ?>">
                    <i class="bi bi-gear-fill"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="legal_pages.php" class="nav-link <?= $currentPage === 'legal_pages' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Legal Pages</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center">
            <div class="admin-avatar">
                <?= substr(getAdminName(), 0, 1) ?>
            </div>
            <div class="ms-2 text-white">
                <small class="d-block fw-semibold"><?= clean(getAdminName()) ?></small>
                <small class="text-white-50">Administrator</small>
            </div>
        </div>
        <a href="logout.php" class="btn btn-sm btn-outline-light mt-2 w-100">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>

<!-- Main Content Wrapper -->
<div class="main-content">
    <!-- Top Bar -->
    <header class="topbar">
        <button class="btn btn-link text-dark d-lg-none" id="openSidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <h4 class="mb-0 fw-bold"><?= $pageTitle ?? 'Dashboard' ?></h4>
        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Notification Bell -->
            <div class="position-relative" id="notificationBell" style="cursor:pointer" title="Notifications">
                <i class="bi bi-bell fs-5 text-muted"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifBadge" style="font-size:0.65rem">0</span>
            </div>
            <button class="theme-toggle-btn" id="themeToggle" title="Toggle dark mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>
            <span class="text-muted small"><?= date('d M Y') ?></span>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mx-3 mt-3" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <div class="content-area">
