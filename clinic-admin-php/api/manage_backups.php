<?php
/**
 * ============================================================
 * Bansari Homeopathy – 3-Layer Backup Management API
 * File: clinic-admin/api/manage_backups.php
 * ============================================================
 * List, download, delete, restore database backups across
 * daily_database, weekly_incremental, monthly_snapshot layers.
 * Only accessible to authenticated admin users.
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$action = $_GET['action'] ?? null;

// ─── Backup directories ───
$backupRoot = realpath(__DIR__ . '/../../backup-system');
$legacyDir  = realpath(__DIR__ . '/../../backups');
$logFile    = $backupRoot ? $backupRoot . '/logs/backup_logs.txt' : null;

$backupDirs = [
    'Daily DB' => $backupRoot ? $backupRoot . '/daily_database' : null,
    'Weekly'   => $backupRoot ? $backupRoot . '/weekly_incremental' : null,
    'Monthly'  => $backupRoot ? $backupRoot . '/monthly_snapshot' : null,
    'Legacy'   => $legacyDir,
];

// File name patterns per type
$patterns = [
    'Daily DB' => '/^db_backup_.*\.sql$/',
    'Weekly'   => '/^weekly_backup_/',
    'Monthly'  => '/^monthly_snapshot_.*\.tar\.gz$/',
    'Legacy'   => '/^backup_.*\.(sql|zip)$/',
];

/**
 * Resolve a backup file by name and type, return [filePath, dir] or null.
 */
function resolveBackupFile(string $filename, string $type, array $backupDirs, array $patterns): ?array {
    // Validate filename against expected pattern
    if (!isset($patterns[$type]) || !preg_match($patterns[$type], $filename)) {
        return null;
    }
    $dir = $backupDirs[$type] ?? null;
    if (!$dir || !is_dir($dir)) return null;
    $filePath = $dir . '/' . $filename;
    if (!file_exists($filePath)) return null;
    // Prevent directory traversal
    $realPath = realpath($filePath);
    $realDir  = realpath($dir);
    if ($realPath === false || $realDir === false || strpos($realPath, $realDir) !== 0) return null;
    return [$filePath, $dir];
}

try {
    $db = getClinicDB();

    // ═══════════════════════════════════════════
    // LIST BACKUPS (all layers)
    // ═══════════════════════════════════════════
    if ($action === 'list') {
        $backups = [];
        foreach ($backupDirs as $type => $dir) {
            if (!$dir || !is_dir($dir)) continue;
            $files = scandir($dir);
            $pattern = $patterns[$type] ?? '/^$/';
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess') continue;
                if (!preg_match($pattern, $file)) continue;
                $filePath = $dir . '/' . $file;
                $fileSize = is_file($filePath) ? filesize($filePath) : 0;
                $fileTime = filemtime($filePath);
                $backups[] = [
                    'name' => $file,
                    'type' => $type,
                    'size' => $fileSize,
                    'size_readable' => formatBytes($fileSize),
                    'created' => date('Y-m-d H:i:s', $fileTime),
                    'is_dir' => is_dir($filePath),
                ];
            }
        }
        usort($backups, fn($a, $b) => strtotime($b['created']) - strtotime($a['created']));
        echo json_encode(['success' => true, 'backups' => $backups, 'total_backups' => count($backups)]);
        exit;
    }

    // ═══════════════════════════════════════════
    // DOWNLOAD BACKUP
    // ═══════════════════════════════════════════
    if ($action === 'download') {
        $filename = $_GET['file'] ?? null;
        $type     = $_GET['type'] ?? 'Legacy';

        if (!$filename) {
            echo json_encode(['success' => false, 'message' => 'No file specified']);
            exit;
        }

        $resolved = resolveBackupFile($filename, $type, $backupDirs, $patterns);
        if (!$resolved) {
            echo json_encode(['success' => false, 'message' => 'Invalid or missing backup file']);
            exit;
        }
        [$filePath, $dir] = $resolved;

        if (!is_readable($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Backup file not readable']);
            exit;
        }

        // Integrity check
        $checksumFile = $filePath . '.sha256';
        if (file_exists($checksumFile)) {
            $expected = trim(file_get_contents($checksumFile));
            $actual   = hash_file('sha256', $filePath);
            if ($expected !== $actual) {
                error_log("Backup integrity check failed: $filename");
                echo json_encode(['success' => false, 'message' => 'Backup file integrity check failed']);
                exit;
            }
        }

        logBackupAccess($db, $filename, 'download', getAdminId());

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        if (ob_get_level()) ob_end_clean();
        readfile($filePath);
        exit;
    }

    // ═══════════════════════════════════════════
    // DELETE BACKUP
    // ═══════════════════════════════════════════
    if ($action === 'delete') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        $filename = $_POST['file'] ?? null;
        $type     = $_POST['type'] ?? 'Legacy';

        if (!$filename) {
            echo json_encode(['success' => false, 'message' => 'No file specified']);
            exit;
        }

        $resolved = resolveBackupFile($filename, $type, $backupDirs, $patterns);
        if (!$resolved) {
            echo json_encode(['success' => false, 'message' => 'Invalid or missing backup file']);
            exit;
        }
        [$filePath, $dir] = $resolved;

        // Safety: prevent deletion of files < 7 days old
        $fileTime = filemtime($filePath);
        if ((time() - $fileTime) < 7 * 24 * 3600) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete recent backups (< 7 days old)']);
            exit;
        }

        $deleted = false;
        if (is_dir($filePath)) {
            // Weekly incremental backups are directories
            $deleted = deleteDirectory($filePath);
        } else {
            $deleted = unlink($filePath);
            // Clean up checksum
            if (file_exists($filePath . '.sha256')) {
                unlink($filePath . '.sha256');
            }
        }

        if ($deleted) {
            logBackupAccess($db, $filename, 'delete', getAdminId());
            echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete backup file']);
        }
        exit;
    }

    // ═══════════════════════════════════════════
    // RESTORE DATABASE (Daily DB / Legacy .sql only)
    // ═══════════════════════════════════════════
    if ($action === 'restore') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        $filename = $_POST['file'] ?? null;
        $type     = $_POST['type'] ?? 'Legacy';

        // Only allow SQL file restores
        if (!$filename || !preg_match('/\.sql$/', $filename)) {
            echo json_encode(['success' => false, 'message' => 'Only .sql backup files can be restored']);
            exit;
        }

        $resolved = resolveBackupFile($filename, $type, $backupDirs, $patterns);
        if (!$resolved) {
            echo json_encode(['success' => false, 'message' => 'Invalid or missing backup file']);
            exit;
        }
        [$filePath, $dir] = $resolved;

        // Integrity check before restore
        $checksumFile = $filePath . '.sha256';
        if (file_exists($checksumFile)) {
            $expected = trim(file_get_contents($checksumFile));
            $actual   = hash_file('sha256', $filePath);
            if ($expected !== $actual) {
                echo json_encode(['success' => false, 'message' => 'Integrity check failed. Backup may be corrupted.']);
                exit;
            }
        }

        // Load DB config
        require_once __DIR__ . '/../../backend-php/config/config.php';
        $passArg = DB_PASS ? '-p' . DB_PASS : '';
        $mysql = 'C:\\xampp\\mysql\\bin\\mysql.exe';
        $command = sprintf(
            '%s -h%s -P%s -u%s %s %s < %s 2>&1',
            escapeshellarg($mysql),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_PORT),
            escapeshellarg(DB_USER),
            $passArg,
            escapeshellarg(DB_NAME),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            logBackupAccess($db, $filename, 'restore', getAdminId());
            // Log to backup log file
            if ($logFile = realpath(__DIR__ . '/../../backup-system') . '/logs/backup_logs.txt') {
                $msg = '[' . date('Y-m-d H:i:s') . '] DATABASE RESTORED from ' . $filename . ' by admin #' . getAdminId() . "\n";
                file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
            }
            echo json_encode(['success' => true, 'message' => 'Database restored successfully from ' . $filename]);
        } else {
            $errorOutput = implode("\n", $output);
            error_log("Database restore failed: $errorOutput");
            echo json_encode(['success' => false, 'message' => 'Restore failed. Check server logs.']);
        }
        exit;
    }

    // ═══════════════════════════════════════════
    // RUN BACKUP (trigger shell scripts)
    // ═══════════════════════════════════════════
    if ($action === 'run_backup') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        $backupType = $_POST['backup_type'] ?? '';
        $scriptDir  = $backupRoot ? $backupRoot . '/scripts' : null;

        if (!$scriptDir || !is_dir($scriptDir)) {
            echo json_encode(['success' => false, 'message' => 'Backup scripts directory not found']);
            exit;
        }

        $scripts = [
            'daily'   => $scriptDir . '/daily_db_backup.sh',
            'weekly'  => $scriptDir . '/weekly_incremental_backup.sh',
            'monthly' => $scriptDir . '/monthly_snapshot.sh',
        ];

        if (!isset($scripts[$backupType])) {
            echo json_encode(['success' => false, 'message' => 'Invalid backup type']);
            exit;
        }

        $script = $scripts[$backupType];
        if (!file_exists($script)) {
            echo json_encode(['success' => false, 'message' => 'Backup script not found: ' . basename($script)]);
            exit;
        }

        // Run in background
        $cmd = 'bash ' . escapeshellarg($script) . ' > /dev/null 2>&1 &';
        exec($cmd);

        logBackupAccess($db, basename($script), 'run_' . $backupType, getAdminId());

        echo json_encode(['success' => true, 'message' => ucfirst($backupType) . ' backup triggered. Check logs for progress.']);
        exit;
    }

    // ═══════════════════════════════════════════
    // READ BACKUP LOGS
    // ═══════════════════════════════════════════
    if ($action === 'logs') {
        $logPath = $backupRoot ? $backupRoot . '/logs/backup_logs.txt' : null;
        if (!$logPath || !file_exists($logPath)) {
            echo json_encode(['success' => true, 'logs' => []]);
            exit;
        }
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent = array_slice($lines, -30);
        $recent = array_reverse($recent);
        echo json_encode(['success' => true, 'logs' => $recent]);
        exit;
    }

    // ═══════════════════════════════════════════
    // GET BACKUP INFO
    // ═══════════════════════════════════════════
    if ($action === 'info') {
        $filename = $_GET['file'] ?? null;
        $type     = $_GET['type'] ?? 'Legacy';

        if (!$filename) {
            echo json_encode(['success' => false, 'message' => 'No file specified']);
            exit;
        }

        $resolved = resolveBackupFile($filename, $type, $backupDirs, $patterns);
        if (!$resolved) {
            echo json_encode(['success' => false, 'message' => 'Invalid or missing backup file']);
            exit;
        }
        [$filePath, $dir] = $resolved;

        $fileSize = is_file($filePath) ? filesize($filePath) : 0;
        $fileTime = filemtime($filePath);

        echo json_encode([
            'success' => true,
            'info' => [
                'filename' => $filename,
                'type' => $type,
                'size' => formatBytes($fileSize),
                'created' => date('Y-m-d H:i:s', $fileTime),
                'is_dir' => is_dir($filePath),
            ]
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    error_log("Backup management error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

// ─── Helper Functions ───

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

function deleteDirectory(string $dir): bool
{
    if (!is_dir($dir)) return false;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    return rmdir($dir);
}

function logBackupAccess(PDO $db, string $filename, string $action, int $adminId): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO backup_access_logs (admin_id, filename, action, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $adminId,
            $filename,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log backup access: " . $e->getMessage());
    }
}
