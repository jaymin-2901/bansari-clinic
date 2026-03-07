<?php
/**
 * ============================================================
 * Bansari Homeopathy – Database Restore Backend
 * File: backup-system/scripts/restore_database.php
 * ============================================================
 * Handles: file upload, validation, safety backup, DB restore.
 * Called via AJAX from clinic-admin-php/restore_backup.php.
 * Returns JSON responses for each step.
 */

// When loaded via proxy (api/restore_backup.php), auth is already done
if (!function_exists('requireAdmin')) {
    require_once __DIR__ . '/../../clinic-admin-php/includes/auth.php';
    requireAdmin();
}

require_once __DIR__ . '/../../backend-php/config/clinic_config.php';

header('Content-Type: application/json');

// ─── Paths ───
$backupRoot    = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$uploadsDir    = $backupRoot . '/uploads';
$safetyDir     = $backupRoot . '/safety_backups';
$dailyDir      = $backupRoot . '/daily_database';
$logFile       = $backupRoot . '/logs/restore_logs.txt';
$mysqlBin      = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$mysqldumpBin  = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

// Max upload size: 100 MB
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024);
}

// ─── Logging ───
function restoreLog(string $msg, string $logFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ─── JSON response helper (unique name to avoid clash with clinic_db.php) ───
function restoreJsonResponse(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ─── File size formatter (unique name to avoid clash) ───
function restoreFormatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
}

// ─── Ensure directories exist ───
foreach ([$uploadsDir, $safetyDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
}

// ─── Get action ───
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ═══════════════════════════════════════════════════════════
// ACTION: Upload backup file
// ═══════════════════════════════════════════════════════════
if ($action === 'upload') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        restoreJsonResponse(false, 'Invalid security token.');
    }

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMap = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds maximum allowed size.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $code = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        restoreJsonResponse(false, $errorMap[$code] ?? 'Upload failed.');
    }

    $file = $_FILES['backup_file'];

    // Size check
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        restoreJsonResponse(false, 'File too large. Maximum size is 100 MB.');
    }

    // Extension check
    $originalName = basename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['sql', 'zip'], true)) {
        restoreJsonResponse(false, 'Invalid file type. Only .sql and .zip files are allowed.');
    }

    // Sanitize filename
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);
    $destPath = $uploadsDir . '/' . $safeName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        restoreJsonResponse(false, 'Failed to save uploaded file.');
    }
    chmod($destPath, 0600);

    // If ZIP, extract the .sql file
    $sqlFile = $destPath;
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($destPath) !== true) {
            unlink($destPath);
            restoreJsonResponse(false, 'Failed to open ZIP archive.');
        }

        // Find exactly one .sql file inside
        $sqlFound = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) === 'sql') {
                if ($sqlFound !== null) {
                    $zip->close();
                    unlink($destPath);
                    restoreJsonResponse(false, 'ZIP contains multiple .sql files. Please upload a ZIP with exactly one .sql file.');
                }
                $sqlFound = $entryName;
            }
        }

        if ($sqlFound === null) {
            $zip->close();
            unlink($destPath);
            restoreJsonResponse(false, 'ZIP archive does not contain any .sql file.');
        }

        // Extract the sql file
        $extractedPath = $uploadsDir . '/' . basename($sqlFound);
        if (!$zip->extractTo($uploadsDir, $sqlFound)) {
            $zip->close();
            unlink($destPath);
            restoreJsonResponse(false, 'Failed to extract .sql file from ZIP.');
        }
        $zip->close();

        // If extracted file has subdirectory path, move it
        $actualExtracted = $uploadsDir . '/' . $sqlFound;
        if ($actualExtracted !== $extractedPath && file_exists($actualExtracted)) {
            rename($actualExtracted, $extractedPath);
        }

        $sqlFile = $extractedPath;
        chmod($sqlFile, 0600);
    }

    // Validate SQL content - check first few bytes for SQL-like content
    $header = file_get_contents($sqlFile, false, null, 0, 1024);
    if ($header === false || strlen($header) < 10) {
        unlink($sqlFile);
        if ($ext === 'zip') unlink($destPath);
        restoreJsonResponse(false, 'File appears to be empty or unreadable.');
    }

    // Check for common SQL markers
    $hasSqlContent = preg_match('/^(--|\/\*|CREATE|INSERT|DROP|SET|USE|ALTER|BEGIN|START)/mi', $header);
    if (!$hasSqlContent) {
        unlink($sqlFile);
        if ($ext === 'zip') unlink($destPath);
        restoreJsonResponse(false, 'File does not appear to contain valid SQL content.');
    }

    // Check for suspicious PHP/script content
    if (preg_match('/<\?(php)?|<script|<%%|eval\s*\(/i', $header)) {
        unlink($sqlFile);
        if ($ext === 'zip') unlink($destPath);
        restoreLog('SECURITY: Blocked upload with suspicious content: ' . $safeName, $logFile);
        restoreJsonResponse(false, 'File rejected: contains potentially malicious content.');
    }

    restoreLog('Upload successful: ' . basename($sqlFile) . ' (' . round(filesize($sqlFile) / 1024, 1) . ' KB)', $logFile);

    restoreJsonResponse(true, 'File uploaded and validated successfully.', [
        'filename' => basename($sqlFile),
        'size' => filesize($sqlFile),
        'size_readable' => restoreFormatBytes(filesize($sqlFile)),
    ]);
}

// ═══════════════════════════════════════════════════════════
// ACTION: Create safety backup of current database
// ═══════════════════════════════════════════════════════════
if ($action === 'safety_backup') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        restoreJsonResponse(false, 'Invalid security token.');
    }

    restoreLog('Safety Backup Started', $logFile);

    $safetyFile = $safetyDir . '/pre_restore_backup_' . date('Y_m_d_H_i_s') . '.sql';

    $passArg = DB_PASS ? '-p' . DB_PASS : '';
    $command = sprintf(
        '%s -h%s -P%s -u%s %s --single-transaction --routines --triggers --events %s > %s 2>&1',
        escapeshellarg($mysqldumpBin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        $passArg,
        escapeshellarg(DB_NAME),
        escapeshellarg($safetyFile)
    );

    exec($command, $output, $returnVar);

    if ($returnVar !== 0 || !file_exists($safetyFile) || filesize($safetyFile) < 100) {
        if (file_exists($safetyFile)) unlink($safetyFile);
        restoreLog('ERROR: Safety backup FAILED', $logFile);
        restoreJsonResponse(false, 'Failed to create safety backup. Restore aborted for your protection.');
    }

    chmod($safetyFile, 0600);

    // Generate checksum
    $checksum = hash_file('sha256', $safetyFile);
    file_put_contents($safetyFile . '.sha256', $checksum);

    restoreLog('Safety Backup Created: ' . basename($safetyFile) . ' (' . restoreFormatBytes(filesize($safetyFile)) . ')', $logFile);

    restoreJsonResponse(true, 'Safety backup created successfully.', [
        'safety_file' => basename($safetyFile),
        'size' => restoreFormatBytes(filesize($safetyFile)),
    ]);
}

// ═══════════════════════════════════════════════════════════
// ACTION: Restore database from uploaded SQL file
// ═══════════════════════════════════════════════════════════
if ($action === 'restore') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        restoreJsonResponse(false, 'Invalid security token.');
    }

    $filename = $_POST['filename'] ?? '';
    if (!$filename || !preg_match('/^[a-zA-Z0-9_\-\.]+\.sql$/', $filename)) {
        restoreJsonResponse(false, 'Invalid filename.');
    }

    // Check uploads directory first, then daily backups, then safety backups
    $sqlFile = null;
    foreach ([$uploadsDir, $dailyDir, $safetyDir] as $searchDir) {
        $candidate = $searchDir . '/' . $filename;
        if (file_exists($candidate)) {
            // Verify path is within expected directory
            $realCandidate = realpath($candidate);
            $realSearchDir = realpath($searchDir);
            if ($realCandidate && $realSearchDir && strpos($realCandidate, $realSearchDir) === 0) {
                $sqlFile = $candidate;
                break;
            }
        }
    }

    if (!$sqlFile) {
        restoreJsonResponse(false, 'Backup file not found.');
    }

    restoreLog('Restore Started – File: ' . $filename, $logFile);

    $passArg = DB_PASS ? '-p' . DB_PASS : '';
    $command = sprintf(
        '%s -h%s -P%s -u%s %s %s < %s 2>&1',
        escapeshellarg($mysqlBin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        $passArg,
        escapeshellarg(DB_NAME),
        escapeshellarg($sqlFile)
    );

    $startTime = microtime(true);
    exec($command, $output, $returnVar);
    $elapsed = round(microtime(true) - $startTime, 2);

    if ($returnVar === 0) {
        restoreLog('SQL File Imported Successfully – ' . $filename . ' (' . $elapsed . 's)', $logFile);
        restoreLog('Restore Completed', $logFile);

        restoreJsonResponse(true, 'Database restored successfully.', [
            'filename' => $filename,
            'database' => DB_NAME,
            'duration' => $elapsed . 's',
            'status' => 'Success',
        ]);
    } else {
        $errorOutput = implode("\n", $output);
        restoreLog('ERROR: Restore FAILED – ' . $filename . ' – ' . $errorOutput, $logFile);

        restoreJsonResponse(false, 'Database restore failed. Your safety backup is intact.', [
            'filename' => $filename,
            'database' => DB_NAME,
            'duration' => $elapsed . 's',
            'status' => 'Failed',
        ]);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: List available backup files for restore
// ═══════════════════════════════════════════════════════════
if ($action === 'list') {
    $backups = [];

    // Scan daily backups
    if (is_dir($dailyDir)) {
        foreach (scandir($dailyDir) as $f) {
            if (preg_match('/^db_backup_.*\.sql$/', $f)) {
                $path = $dailyDir . '/' . $f;
                $backups[] = [
                    'name' => $f,
                    'type' => 'Daily DB',
                    'size' => filesize($path),
                    'size_readable' => restoreFormatBytes(filesize($path)),
                    'created' => date('d M Y, h:i A', filemtime($path)),
                    'timestamp' => filemtime($path),
                    'source' => 'daily',
                ];
            }
        }
    }

    // Scan uploaded files
    if (is_dir($uploadsDir)) {
        foreach (scandir($uploadsDir) as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
            if (preg_match('/\.sql$/i', $f)) {
                $path = $uploadsDir . '/' . $f;
                $backups[] = [
                    'name' => $f,
                    'type' => 'Uploaded',
                    'size' => filesize($path),
                    'size_readable' => restoreFormatBytes(filesize($path)),
                    'created' => date('d M Y, h:i A', filemtime($path)),
                    'timestamp' => filemtime($path),
                    'source' => 'upload',
                ];
            }
        }
    }

    // Scan safety backups
    if (is_dir($safetyDir)) {
        foreach (scandir($safetyDir) as $f) {
            if (preg_match('/^pre_restore_backup_.*\.sql$/', $f)) {
                $path = $safetyDir . '/' . $f;
                $backups[] = [
                    'name' => $f,
                    'type' => 'Safety',
                    'size' => filesize($path),
                    'size_readable' => restoreFormatBytes(filesize($path)),
                    'created' => date('d M Y, h:i A', filemtime($path)),
                    'timestamp' => filemtime($path),
                    'source' => 'safety',
                ];
            }
        }
    }

    usort($backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

    restoreJsonResponse(true, 'Backups listed.', ['backups' => $backups]);
}

// ═══════════════════════════════════════════════════════════
// ACTION: Get restore logs
// ═══════════════════════════════════════════════════════════
if ($action === 'logs') {
    $logs = [];
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = array_reverse(array_slice($lines, -50));
    }
    restoreJsonResponse(true, 'Logs loaded.', ['logs' => $logs]);
}

// ═══════════════════════════════════════════════════════════
// ACTION: Delete an uploaded/safety backup file
// ═══════════════════════════════════════════════════════════
if ($action === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        restoreJsonResponse(false, 'Invalid security token.');
    }

    $filename = $_POST['filename'] ?? '';
    $source   = $_POST['source'] ?? '';

    if (!$filename || !preg_match('/^[a-zA-Z0-9_\-\.]+\.sql$/', $filename)) {
        restoreJsonResponse(false, 'Invalid filename.');
    }

    $allowedDirs = [
        'upload' => $uploadsDir,
        'safety' => $safetyDir,
    ];

    $dir = $allowedDirs[$source] ?? null;
    if (!$dir) {
        restoreJsonResponse(false, 'Cannot delete from this source.');
    }

    $filePath = $dir . '/' . $filename;
    $realPath = realpath($filePath);
    $realDir  = realpath($dir);

    if (!$realPath || !$realDir || strpos($realPath, $realDir) !== 0 || !file_exists($filePath)) {
        restoreJsonResponse(false, 'File not found.');
    }

    unlink($filePath);
    if (file_exists($filePath . '.sha256')) unlink($filePath . '.sha256');

    restoreLog('Deleted: ' . $filename . ' from ' . $source, $logFile);
    restoreJsonResponse(true, 'File deleted.');
}

// ─── Fallback ───
restoreJsonResponse(false, 'Invalid action.');
