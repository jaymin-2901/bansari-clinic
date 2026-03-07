<?php
/**
 * ============================================================
 * MediConnect - Database Backup Manager
 * File: backend/cron/backup_database.php
 * ============================================================
 * Automatic daily and weekly backup system
 * Executed via cron: 0 2 * * * /usr/bin/php /path/to/backup_database.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ─── Configuration ───
$backupDir = __DIR__ . '/../../backups';
$logFile = __DIR__ . '/../logs/backup_log.txt';
$maxDailyBackups = 30; // Keep 30 days of daily backups
$maxWeeklyBackups = 12; // Keep 12 weeks of weekly backups

// ─── Create backup directory if not exists ───
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0700, true); // Restricted permissions
}

// ─── Logging function ───
function logBackup(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    if (is_writable($logFile) || is_writable(dirname($logFile))) {
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    echo $logEntry;
}

// ─── Daily Backup Function ───
function performDailyBackup(): bool
{
    global $backupDir;

    $date = date('Y_m_d');
    $backupFile = $backupDir . "/backup_{$date}.sql";

    // Skip if already backed up today
    if (file_exists($backupFile)) {
        logBackup("Daily backup for {$date} already exists, skipping");
        return true;
    }

    try {
        $db = getDatabase();
        
        // Create dump
        $output = [];
        $returnVar = 0;
        
        // Use mysqldump if available (more efficient)
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s --databases %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($backupFile)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($backupFile)) {
            chmod($backupFile, 0600); // Restrict to owner only
            
            $fileSize = filesize($backupFile);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);
            
            logBackup("✓ Daily backup created: {$backupFile} ({$fileSizeMB}MB)");
            
            // Cleanup old backups
            cleanupDailyBackups();
            
            return true;
        } else {
            logBackup("✗ Daily backup failed: {$date}");
            logBackup("Error: " . implode("\n", $output));
            return false;
        }
    } catch (Exception $e) {
        logBackup("✗ Daily backup error: " . $e->getMessage());
        return false;
    }
}

// ─── Weekly Compressed Backup Function ───
function performWeeklyBackup(): bool
{
    global $backupDir;

    // Check if today is Sunday (weekly backup day)
    if (date('N') != 7) { // 7 = Sunday
        return true;
    }

    $week = date('Y_W'); // ISO week number
    $backupFile = $backupDir . "/backup_week_{$week}.sql";
    $compressedFile = $backupDir . "/backup_week_{$week}.zip";

    // Skip if already backed up this week
    if (file_exists($compressedFile)) {
        logBackup("Weekly backup for week {$week} already exists, skipping");
        return true;
    }

    try {
        // Create uncompressed backup
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s --databases %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($backupFile)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($backupFile)) {
            // Compress the backup
            if (compressBackup($backupFile, $compressedFile)) {
                unlink($backupFile); // Delete uncompressed version
                
                $fileSize = filesize($compressedFile);
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                
                logBackup("✓ Weekly backup created: {$compressedFile} ({$fileSizeMB}MB)");
                
                // Cleanup old weekly backups
                cleanupWeeklyBackups();
                
                return true;
            } else {
                logBackup("✗ Failed to compress weekly backup");
                return false;
            }
        } else {
            logBackup("✗ Weekly backup failed: week {$week}");
            return false;
        }
    } catch (Exception $e) {
        logBackup("✗ Weekly backup error: " . $e->getMessage());
        return false;
    }
}

// ─── Compression Helper ───
function compressBackup(string $sourceFile, string $destFile): bool
{
    if (!extension_loaded('zip')) {
        logBackup("PHP zip extension not available, skipping compression");
        return false;
    }

    try {
        $zip = new ZipArchive();
        if ($zip->open($destFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFile($sourceFile, basename($sourceFile));
        
        // Add metadata file
        $metadata = json_encode([
            'created' => date('Y-m-d H:i:s'),
            'database' => DB_NAME,
            'file_size' => filesize($sourceFile)
        ]);
        $zip->addFromString('backup_metadata.json', $metadata);

        $zip->close();
        chmod($destFile, 0600);
        
        return true;
    } catch (Exception $e) {
        logBackup("Compression error: " . $e->getMessage());
        return false;
    }
}

// ─── Cleanup Old Daily Backups ───
function cleanupDailyBackups(): void
{
    global $backupDir, $maxDailyBackups;

    $files = glob($backupDir . '/backup_????_??_??.sql');
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    if (count($files) > $maxDailyBackups) {
        $filesToDelete = array_slice($files, 0, count($files) - $maxDailyBackups);
        foreach ($filesToDelete as $file) {
            if (unlink($file)) {
                logBackup("Cleaned up old backup: " . basename($file));
            }
        }
    }
}

// ─── Cleanup Old Weekly Backups ───
function cleanupWeeklyBackups(): void
{
    global $backupDir, $maxWeeklyBackups;

    $files = glob($backupDir . '/backup_week_????_??.zip');
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    if (count($files) > $maxWeeklyBackups) {
        $filesToDelete = array_slice($files, 0, count($files) - $maxWeeklyBackups);
        foreach ($filesToDelete as $file) {
            if (unlink($file)) {
                logBackup("Cleaned up old weekly backup: " . basename($file));
            }
        }
    }
}

// ─── Verify backup integrity (optional but recommended) ───
function verifyBackup(string $backupFile): bool
{
    // Check file exists and is readable
    if (!is_readable($backupFile)) {
        logBackup("Backup verification failed: file not readable");
        return false;
    }

    // Check file has reasonable size (at least 1KB)
    if (filesize($backupFile) < 1024) {
        logBackup("Backup verification failed: file too small");
        return false;
    }

    // Check for SQL content
    $firstLine = trim(fgets(fopen($backupFile, 'r')));
    if (!str_contains($firstLine, 'SQL') && !str_contains($firstLine, 'MySQL')) {
        logBackup("Backup verification failed: invalid SQL file");
        return false;
    }

    return true;
}

// ─── Main Execution ───
logBackup("========== Backup Process Started ==========");

// Perform daily backup
$dailySuccess = performDailyBackup();

// Perform weekly backup if applicable
$weeklySuccess = performWeeklyBackup();

if ($dailySuccess && $weeklySuccess) {
    logBackup("========== Backup Process Completed Successfully ==========");
    exit(0);
} else {
    logBackup("========== Backup Process Completed With Errors ==========");
    exit(1);
}
