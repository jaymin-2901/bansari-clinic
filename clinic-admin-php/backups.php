<?php
/**
 * Bansari Homeopathy – 3-Layer Backup Management
 * Admin page for daily database, weekly incremental, and monthly snapshot backups
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Backup Management';
$csrfToken = generateCSRFToken();

// ─── Backup directories ───
$backupRoot = realpath(__DIR__ . '/../backup-system');
$legacyDir  = realpath(__DIR__ . '/../backups');

$dirs = [
    'daily'   => $backupRoot ? $backupRoot . '/daily_database' : null,
    'weekly'  => $backupRoot ? $backupRoot . '/weekly_incremental' : null,
    'monthly' => $backupRoot ? $backupRoot . '/monthly_snapshot' : null,
    'legacy'  => $legacyDir,
];

$logFile = $backupRoot ? $backupRoot . '/logs/backup_logs.txt' : null;

// ─── Handle POST actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        header('Location: backups.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Manual backup trigger
    if ($action === 'create_backup') {
        $type = $_POST['backup_type'] ?? 'daily';
        try {
            require_once __DIR__ . '/../backend-php/config/config.php';
            $date = date('Y_m_d_His');

            if ($type === 'daily') {
                $backupFile = $dirs['daily'] . "/db_backup_" . date('Y_m_d') . ".sql";
                if (file_exists($backupFile)) {
                    setFlash('warning', 'Daily backup already exists for today.');
                } else {
                    $passArg = DB_PASS ? '-p' . DB_PASS : '';
                    $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
                    $command = sprintf(
                        '%s -h%s -P%s -u%s %s --single-transaction --routines --triggers --events %s > %s 2>&1',
                        escapeshellarg($mysqldump),
                        escapeshellarg(DB_HOST),
                        escapeshellarg(DB_PORT),
                        escapeshellarg(DB_USER),
                        $passArg,
                        escapeshellarg(DB_NAME),
                        escapeshellarg($backupFile)
                    );
                    exec($command, $output, $returnVar);
                    if ($returnVar === 0 && file_exists($backupFile)) {
                        chmod($backupFile, 0600);
                        $checksum = hash_file('sha256', $backupFile);
                        file_put_contents($backupFile . '.sha256', $checksum);
                        setFlash('success', 'Daily database backup created successfully.');
                    } else {
                        setFlash('error', 'Daily backup failed. Check server configuration.');
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Manual backup error: ' . $e->getMessage());
            setFlash('error', 'An error occurred creating backup.');
        }
        header('Location: backups.php');
        exit;
    }
}

// ─── Scan all backup directories ───
function scanBackupDir(?string $dir, string $type, string $pattern): array {
    $results = [];
    if (!$dir || !is_dir($dir)) return $results;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess') continue;
        if (!preg_match($pattern, $file)) continue;
        $filePath = $dir . '/' . $file;
        $fileSize = is_file($filePath) ? filesize($filePath) : dirSize($filePath);
        $fileTime = filemtime($filePath);
        $results[] = [
            'name' => $file,
            'type' => $type,
            'dir'  => $dir,
            'size' => formatBackupBytes($fileSize),
            'size_raw' => $fileSize,
            'created' => date('d M Y, h:i A', $fileTime),
            'timestamp' => $fileTime,
            'is_dir' => is_dir($filePath),
            'age_days' => floor((time() - $fileTime) / 86400),
        ];
    }
    return $results;
}

function dirSize(string $dir): int {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function formatBackupBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

$dailyBackups   = scanBackupDir($dirs['daily'],   'Daily DB',   '/^db_backup_.*\.sql$/');
$weeklyBackups  = scanBackupDir($dirs['weekly'],  'Weekly',     '/^weekly_backup_/');
$monthlyBackups = scanBackupDir($dirs['monthly'], 'Monthly',    '/^monthly_snapshot_.*\.tar\.gz$/');
$legacyBackups  = scanBackupDir($dirs['legacy'],  'Legacy',     '/^backup_.*\.(sql|zip)$/');

$allBackups = array_merge($dailyBackups, $weeklyBackups, $monthlyBackups, $legacyBackups);
usort($allBackups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

$totalSize = array_sum(array_column($allBackups, 'size_raw'));

// ─── Read recent log entries ───
$recentLogs = [];
if ($logFile && file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recentLogs = array_slice($lines, -30);
    $recentLogs = array_reverse($recentLogs);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Backup Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-database"></i>
                </div>
                <div>
                    <div class="stat-value"><?= count($allBackups) ?></div>
                    <div class="stat-label">Total Backups</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div>
                    <div class="stat-value"><?= count($dailyBackups) ?></div>
                    <div class="stat-label">Daily DB</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="stat-value"><?= count($weeklyBackups) ?></div>
                    <div class="stat-label">Weekly Incremental</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-archive"></i>
                </div>
                <div>
                    <div class="stat-value"><?= count($monthlyBackups) ?></div>
                    <div class="stat-label">Monthly Snapshots</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Status + Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="table-container">
            <div class="table-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h6>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="create_backup">
                            <input type="hidden" name="backup_type" value="daily">
                            <button type="submit" class="btn btn-success w-100" onclick="return confirm('Create a daily database backup now?')">
                                <i class="bi bi-database-add me-1"></i> Daily DB Backup
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-info w-100 text-white" id="btnWeeklyBackup" onclick="triggerBackup('weekly')">
                            <i class="bi bi-arrow-repeat me-1"></i> Weekly Incremental
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-warning w-100" id="btnMonthlyBackup" onclick="triggerBackup('monthly')">
                            <i class="bi bi-archive me-1"></i> Monthly Snapshot
                        </button>
                    </div>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Weekly/Monthly backups require shell script execution on the server. Use the cron setup below for automation.
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="table-container h-100">
            <div class="table-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-hdd me-2"></i>Storage</h6>
            </div>
            <div class="p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-muted">Total Backup Size</span>
                    <span class="fw-bold"><?= formatBackupBytes($totalSize) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-muted">Daily DB</span>
                    <span class="small"><?= formatBackupBytes(array_sum(array_column($dailyBackups, 'size_raw'))) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-muted">Weekly Files</span>
                    <span class="small"><?= formatBackupBytes(array_sum(array_column($weeklyBackups, 'size_raw'))) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="small text-muted">Monthly Snapshots</span>
                    <span class="small"><?= formatBackupBytes(array_sum(array_column($monthlyBackups, 'size_raw'))) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Table -->
<div class="table-container mb-4">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2"></i>All Backups</h6>
        <div class="d-flex gap-2">
            <select id="filterType" class="form-select form-select-sm" style="width:auto;" onchange="filterBackups()">
                <option value="all">All Types</option>
                <option value="Daily DB">Daily DB</option>
                <option value="Weekly">Weekly</option>
                <option value="Monthly">Monthly</option>
                <option value="Legacy">Legacy</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="backupTable">
            <thead>
                <tr>
                    <th>Backup File</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Created</th>
                    <th>Age</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allBackups)): ?>
                <tr class="no-backups-row"><td colspan="6" class="text-center text-muted py-4">
                    <i class="bi bi-database-x fs-3 d-block mb-2"></i>
                    No backups found. Create your first backup or configure cron jobs.
                </td></tr>
                <?php else: ?>
                <?php foreach ($allBackups as $backup): ?>
                <?php
                    $typeClass = match($backup['type']) {
                        'Daily DB' => 'success',
                        'Weekly'   => 'info',
                        'Monthly'  => 'warning',
                        default    => 'secondary',
                    };
                    $typeIcon = match($backup['type']) {
                        'Daily DB' => 'bi-database',
                        'Weekly'   => 'bi-arrow-repeat',
                        'Monthly'  => 'bi-archive',
                        default    => 'bi-file-earmark',
                    };
                ?>
                <tr data-type="<?= clean($backup['type']) ?>">
                    <td>
                        <i class="bi <?= $typeIcon ?> text-<?= $typeClass ?> me-1"></i>
                        <code class="small"><?= clean($backup['name']) ?></code>
                    </td>
                    <td>
                        <span class="badge bg-<?= $typeClass ?> bg-opacity-10 text-<?= $typeClass ?>">
                            <?= clean($backup['type']) ?>
                        </span>
                    </td>
                    <td><?= $backup['size'] ?></td>
                    <td class="small"><?= $backup['created'] ?></td>
                    <td>
                        <span class="badge bg-<?= $backup['age_days'] > 14 ? 'warning' : 'secondary' ?> bg-opacity-10 text-<?= $backup['age_days'] > 14 ? 'warning' : 'secondary' ?>">
                            <?= $backup['age_days'] ?> day<?= $backup['age_days'] !== 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!$backup['is_dir']): ?>
                        <a href="api/manage_backups.php?action=download&file=<?= urlencode($backup['name']) ?>&type=<?= urlencode($backup['type']) ?>" 
                           class="btn btn-sm btn-outline-primary" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($backup['type'] === 'Daily DB' || $backup['type'] === 'Legacy'): ?>
                        <button class="btn btn-sm btn-outline-success restore-backup" 
                                data-file="<?= clean($backup['name']) ?>"
                                data-type="<?= clean($backup['type']) ?>" title="Restore">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($backup['age_days'] >= 7): ?>
                        <button class="btn btn-sm btn-outline-danger delete-backup" 
                                data-file="<?= clean($backup['name']) ?>"
                                data-type="<?= clean($backup['type']) ?>" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Backup Logs -->
<div class="table-container mb-4">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2"></i>Backup Logs</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
    </div>
    <div id="logContainer" class="p-0">
        <?php if (empty($recentLogs)): ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-journal fs-3 d-block mb-2"></i>
            No backup logs yet. Logs will appear after the first backup runs.
        </div>
        <?php else: ?>
        <pre class="mb-0 p-3 small" style="max-height:300px; overflow-y:auto; background:var(--bg-card); color:var(--text-primary); border:none;"><?php
            foreach ($recentLogs as $line) {
                $escaped = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                if (strpos($line, 'ERROR') !== false) {
                    echo '<span class="text-danger">' . $escaped . '</span>' . "\n";
                } elseif (strpos($line, 'Successful') !== false || strpos($line, 'Completed') !== false) {
                    echo '<span class="text-success">' . $escaped . '</span>' . "\n";
                } elseif (strpos($line, '==========') !== false) {
                    echo '<span class="text-info fw-bold">' . $escaped . '</span>' . "\n";
                } else {
                    echo $escaped . "\n";
                }
            }
        ?></pre>
        <?php endif; ?>
    </div>
</div>

<!-- Cron Setup -->
<div class="table-container">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-terminal me-2"></i>Cron Job Setup</h6>
    </div>
    <div class="p-3">
        <p class="text-muted small mb-2">Add these cron jobs to automate the 3-layer backup system:</p>
        <div class="mb-3">
            <label class="small fw-bold text-success"><i class="bi bi-database me-1"></i>Daily Database (2 AM)</label>
            <code class="d-block p-2 rounded small mt-1" style="background:var(--bg-card); word-break:break-all;">
                0 2 * * * bash <?= $backupRoot ? $backupRoot . '/scripts/daily_db_backup.sh' : '/path/to/backup-system/scripts/daily_db_backup.sh' ?>
            </code>
        </div>
        <div class="mb-3">
            <label class="small fw-bold text-info"><i class="bi bi-arrow-repeat me-1"></i>Weekly Incremental (Sunday 3 AM)</label>
            <code class="d-block p-2 rounded small mt-1" style="background:var(--bg-card); word-break:break-all;">
                0 3 * * 0 bash <?= $backupRoot ? $backupRoot . '/scripts/weekly_incremental_backup.sh' : '/path/to/backup-system/scripts/weekly_incremental_backup.sh' ?>
            </code>
        </div>
        <div class="mb-3">
            <label class="small fw-bold text-warning"><i class="bi bi-archive me-1"></i>Monthly Snapshot (1st at 4 AM)</label>
            <code class="d-block p-2 rounded small mt-1" style="background:var(--bg-card); word-break:break-all;">
                0 4 1 * * bash <?= $backupRoot ? $backupRoot . '/scripts/monthly_snapshot.sh' : '/path/to/backup-system/scripts/monthly_snapshot.sh' ?>
            </code>
        </div>
        <div class="alert alert-info small mb-0 py-2">
            <i class="bi bi-info-circle me-1"></i>
            Set environment variables <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code> in your crontab or a .env file for the shell scripts.
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Confirm Database Restore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2">
                    <strong>Warning:</strong> This will overwrite the current database with the backup data. This action cannot be undone.
                </div>
                <p class="mb-1"><strong>Backup file:</strong></p>
                <code id="restoreFileName" class="d-block mb-3"></code>
                <p class="mb-1">Type <strong>RESTORE</strong> to confirm:</p>
                <input type="text" id="restoreConfirmInput" class="form-control" placeholder="Type RESTORE">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRestoreBtn" disabled>
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Database
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrfToken ?>';

// ─── Filter backups by type ───
function filterBackups() {
    const type = document.getElementById('filterType').value;
    document.querySelectorAll('#backupTable tbody tr').forEach(row => {
        if (row.classList.contains('no-backups-row')) return;
        row.style.display = (type === 'all' || row.dataset.type === type) ? '' : 'none';
    });
}

// ─── Trigger weekly/monthly backup (server-side shell script) ───
function triggerBackup(type) {
    const label = type === 'weekly' ? 'weekly incremental' : 'monthly snapshot';
    if (!confirm(`Run ${label} backup now? This requires server shell access.`)) return;

    fetch('api/manage_backups.php?action=run_backup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=${encodeURIComponent(csrfToken)}&backup_type=${encodeURIComponent(type)}`
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message || (data.success ? 'Backup started' : 'Backup failed'));
        if (data.success) location.reload();
    })
    .catch(() => alert('Error triggering backup'));
}

// ─── Delete backup ───
document.querySelectorAll('.delete-backup').forEach(btn => {
    btn.addEventListener('click', async function() {
        const file = this.dataset.file;
        const type = this.dataset.type;
        if (!confirm(`Delete backup "${file}"? This cannot be undone.`)) return;

        try {
            const res = await fetch('api/manage_backups.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&file=${encodeURIComponent(file)}&type=${encodeURIComponent(type)}`
            });
            const data = await res.json();
            if (data.success) {
                this.closest('tr').remove();
            } else {
                alert(data.message || 'Failed to delete backup');
            }
        } catch (e) {
            alert('Error deleting backup');
        }
    });
});

// ─── Restore backup ───
let restoreFile = '';
let restoreType = '';

document.querySelectorAll('.restore-backup').forEach(btn => {
    btn.addEventListener('click', function() {
        restoreFile = this.dataset.file;
        restoreType = this.dataset.type;
        document.getElementById('restoreFileName').textContent = restoreFile;
        document.getElementById('restoreConfirmInput').value = '';
        document.getElementById('confirmRestoreBtn').disabled = true;
        new bootstrap.Modal(document.getElementById('restoreModal')).show();
    });
});

document.getElementById('restoreConfirmInput')?.addEventListener('input', function() {
    document.getElementById('confirmRestoreBtn').disabled = (this.value !== 'RESTORE');
});

document.getElementById('confirmRestoreBtn')?.addEventListener('click', async function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Restoring...';

    try {
        const res = await fetch('api/manage_backups.php?action=restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&file=${encodeURIComponent(restoreFile)}&type=${encodeURIComponent(restoreType)}`
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'Restore successful' : 'Restore failed'));
        if (data.success) location.reload();
    } catch (e) {
        alert('Error during restore');
    } finally {
        bootstrap.Modal.getInstance(document.getElementById('restoreModal'))?.hide();
    }
});

// ─── Refresh logs via AJAX ───
function refreshLogs() {
    fetch('api/manage_backups.php?action=logs')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.logs) {
                const container = document.getElementById('logContainer');
                if (data.logs.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-journal fs-3 d-block mb-2"></i>No backup logs yet.</div>';
                } else {
                    let html = '<pre class="mb-0 p-3 small" style="max-height:300px; overflow-y:auto; background:var(--bg-card); color:var(--text-primary); border:none;">';
                    data.logs.forEach(line => {
                        const escaped = line.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        if (line.includes('ERROR')) {
                            html += `<span class="text-danger">${escaped}</span>\n`;
                        } else if (line.includes('Successful') || line.includes('Completed')) {
                            html += `<span class="text-success">${escaped}</span>\n`;
                        } else if (line.includes('==========')) {
                            html += `<span class="text-info fw-bold">${escaped}</span>\n`;
                        } else {
                            html += escaped + '\n';
                        }
                    });
                    html += '</pre>';
                    container.innerHTML = html;
                }
            }
        })
        .catch(() => alert('Error loading logs'));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
