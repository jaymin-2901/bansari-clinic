<?php
/**
 * ============================================================
 * MediConnect – SMS Delivery Status Checker Cron
 * File: backend/cron/check_delivery_status.php
 * ============================================================
 * Checks delivery status for sent SMS messages.
 * Updates status in sms_logs table.
 * 
 * Run every 10 minutes:
 *   */10 * * * * php /path/to/backend/cron/check_delivery_status.php >> /path/to/logs/delivery_cron.log 2>&1
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

echo "[" . date('Y-m-d H:i:s') . "] Delivery Status Check Started\n";

require_once __DIR__ . '/../sms/sms_service.php';

$db = getDBConnection();

// Fetch SMS logs with "sent" status from the last 24 hours that have a gateway message ID
$stmt = $db->prepare("
    SELECT id, gateway_message_id, phone
    FROM sms_logs
    WHERE status = 'sent'
      AND gateway_message_id IS NOT NULL
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute();
$logs = $stmt->fetchAll();

$updated = 0;
foreach ($logs as $log) {
    $result = getSMSDeliveryStatus($log['gateway_message_id']);
    
    if ($result['status'] !== 'unknown') {
        $newStatus = strtolower($result['status']);
        if (in_array($newStatus, ['delivered', 'failed', 'rejected'])) {
            updateSMSStatus($log['id'], $newStatus);
            $updated++;
            echo "  Updated SMS #{$log['id']} ({$log['phone']}): $newStatus\n";
        }
    }
    
    // Rate limit: don't hammer the API
    usleep(200000); // 200ms delay
}

echo "Updated $updated out of " . count($logs) . " SMS logs.\n";
echo "[" . date('Y-m-d H:i:s') . "] Delivery Status Check Done\n\n";
