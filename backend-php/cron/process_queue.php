<?php
/**
 * ============================================================
 * MediConnect – Queue Processing Cron Job
 * File: backend/cron/process_queue.php
 * ============================================================
 * Processes the communication queue independently.
 * Handles retries and failed messages.
 * 
 * Run every 2 minutes via cron:
 *   */2 * * * * php /path/to/backend/cron/process_queue.php >> /path/to/logs/queue_cron.log 2>&1
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Queue Processor Started\n";

require_once __DIR__ . '/../includes/queue_processor.php';

// Process queue
$result = processQueue();

echo "Processed: {$result['processed']}, Sent: {$result['sent']}, Failed: {$result['failed']}, Retried: {$result['retried']}\n";

// Periodic cleanup (once per hour based on minutes)
if ((int) date('i') < 2) {
    $cleaned = cleanupOldQueueItems(30);
    if ($cleaned > 0) {
        echo "Cleaned up $cleaned old queue items.\n";
    }
    
    $db = getDBConnection();
    cleanupRateLimitRecords($db);
}

$elapsed = round(microtime(true) - $startTime, 3);
echo "[" . date('Y-m-d H:i:s') . "] Queue Processor Done ($elapsed s)\n\n";
