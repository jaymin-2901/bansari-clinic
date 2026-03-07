<?php
/**
 * ============================================================
 * MediConnect – Communication Queue Processor
 * File: backend/includes/queue_processor.php
 * ============================================================
 * Unified queue system that processes pending messages across
 * all channels (SMS, Email). Supports retry logic
 * with exponential backoff, batch processing, and fallback.
 * ============================================================
 */

require_once __DIR__ . '/../config/comm_config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../sms/sms_service.php';
require_once __DIR__ . '/../email/email_service.php';

/**
 * Add a message to the communication queue
 * 
 * @param array $params Queue item parameters
 * @return int|null Queue item ID
 */
function queueMessage(array $params): ?int
{
    $db = getDBConnection();
    
    $defaults = [
        'patient_id'      => null,
        'consultation_id' => null,
        'channel'         => 'sms',
        'priority'        => 5,
        'recipient'       => '',
        'subject'         => null,
        'message'         => '',
        'html_body'       => null,
        'message_type'    => 'general',
        'max_attempts'    => QUEUE_MAX_RETRIES,
        'scheduled_at'    => null,  // null = send immediately
    ];
    
    $p = array_merge($defaults, $params);
    
    if (empty($p['recipient']) || empty($p['message'])) {
        writeLog('queue.log', "QUEUE_ERROR: Missing recipient or message");
        return null;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO communication_queue
                (patient_id, consultation_id, channel, priority, recipient, subject, 
                 message, html_body, message_type, status, max_attempts, scheduled_at, created_at)
            VALUES
                (:patient_id, :consultation_id, :channel, :priority, :recipient, :subject,
                 :message, :html_body, :message_type, 'pending', :max_attempts, :scheduled_at, NOW())
        ");
        
        $stmt->execute([
            ':patient_id'      => $p['patient_id'],
            ':consultation_id' => $p['consultation_id'],
            ':channel'         => $p['channel'],
            ':priority'        => $p['priority'],
            ':recipient'       => $p['recipient'],
            ':subject'         => $p['subject'],
            ':message'         => $p['message'],
            ':html_body'       => $p['html_body'],
            ':message_type'    => $p['message_type'],
            ':max_attempts'    => $p['max_attempts'],
            ':scheduled_at'    => $p['scheduled_at'],
        ]);
        
        $queueId = (int) $db->lastInsertId();
        writeLog('queue.log', "QUEUED: ID=$queueId, Channel={$p['channel']}, To={$p['recipient']}, Type={$p['message_type']}");
        
        return $queueId;
        
    } catch (PDOException $e) {
        writeLog('queue.log', "QUEUE_DB_ERROR: " . $e->getMessage());
        return null;
    }
}

/**
 * Queue an appointment reminder across all channels
 * Sends via SMS and Email.
 * 
 * @param array $appointment Appointment data (from consultations + users join)
 * @return array Results per channel
 */
function queueAppointmentReminder(array $appointment): array
{
    $patientId      = $appointment['patient_id'];
    $consultationId = $appointment['id'] ?? $appointment['consultation_id'];
    $phone          = $appointment['phone'] ?? '';
    $email          = $appointment['email'] ?? '';
    $patientName    = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
    $dateTime       = $appointment['appointment_datetime'];
    $doctorName     = CLINIC_DOCTOR_NAME;
    
    $results = [];
    
    // 1. SMS
    if (!empty($phone)) {
        $formattedDate = date('d M Y', strtotime($dateTime));
        $formattedTime = date('h:i A', strtotime($dateTime));
        $smsMessage = "Reminder: Dear {$patientName}, you have an appointment tomorrow ({$formattedDate}) at {$formattedTime} at " .
            CLINIC_NAME . " with {$doctorName}. Contact: " . CLINIC_PHONE;
        
        $results['sms'] = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'sms',
            'priority'        => 3,
            'recipient'       => $phone,
            'message'         => $smsMessage,
            'message_type'    => 'appointment_reminder',
        ]);
    }
    
    // 3. Email
    if (!empty($email)) {
        $htmlBody = buildAppointmentEmailTemplate($patientName, $dateTime, $doctorName, '', 'reminder');
        
        $results['email'] = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'email',
            'priority'        => 5,
            'recipient'       => $email,
            'subject'         => "Appointment Reminder - Tomorrow at " . CLINIC_NAME,
            'message'         => "Appointment reminder for {$patientName} on " . date('d M Y, h:i A', strtotime($dateTime)),
            'html_body'       => $htmlBody,
            'message_type'    => 'appointment_reminder',
        ]);
    }
    
    return $results;
}

/**
 * Queue appointment confirmation across all channels
 */
function queueAppointmentConfirmation(array $appointment): array
{
    $patientId      = $appointment['patient_id'] ?? ($appointment['id'] ?? null);
    $consultationId = $appointment['consultation_id'] ?? ($appointment['id'] ?? null);
    $phone          = $appointment['phone'] ?? '';
    $email          = $appointment['email'] ?? '';
    $patientName    = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
    $dateTime       = $appointment['appointment_datetime'];
    $bookingRef     = $appointment['booking_ref'] ?? '';
    $doctorName     = CLINIC_DOCTOR_NAME;
    
    $results = [];
    
    // SMS
    if (!empty($phone)) {
        $formattedDate = date('d M Y, h:i A', strtotime($dateTime));
        $smsMessage = "Dear {$patientName}, your appointment at " . CLINIC_NAME .
            " with {$doctorName} on {$formattedDate} is confirmed. Contact: " . CLINIC_PHONE;
        
        $results['sms'] = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'sms',
            'priority'        => 3,
            'recipient'       => $phone,
            'message'         => $smsMessage,
            'message_type'    => 'appointment_confirmation',
        ]);
    }
    
    // Email
    if (!empty($phone)) {
        $formattedDate = date('d M Y, h:i A', strtotime($dateTime));
        $smsMessage = "Dear {$patientName}, your appointment at " . CLINIC_NAME .
            " with {$doctorName} on {$formattedDate} is confirmed. Contact: " . CLINIC_PHONE;
        
        $results['sms'] = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'sms',
            'priority'        => 3,
            'recipient'       => $phone,
            'message'         => $smsMessage,
            'message_type'    => 'appointment_confirmation',
        ]);
    }
    
    // Email
    if (!empty($email)) {
        $htmlBody = buildAppointmentEmailTemplate($patientName, $dateTime, $doctorName, $bookingRef, 'confirmation');
        
        $results['email'] = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'email',
            'priority'        => 5,
            'recipient'       => $email,
            'subject'         => "Appointment Confirmed - " . CLINIC_NAME,
            'message'         => "Appointment confirmed for {$patientName} on " . date('d M Y, h:i A', strtotime($dateTime)),
            'html_body'       => $htmlBody,
            'message_type'    => 'appointment_confirmation',
        ]);
    }
    
    return $results;
}

/**
 * Process the communication queue (run via cron)
 * Picks up pending items, sends them, handles retries.
 * 
 * @param int $batchSize Max items to process per run
 * @return array Summary of processing results
 */
function processQueue(int $batchSize = 0): array
{
    if ($batchSize <= 0) {
        $batchSize = QUEUE_BATCH_SIZE;
    }
    
    $db = getDBConnection();
    $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'retried' => 0, 'skipped' => 0];
    
    // Release stale "processing" items (stuck for > QUEUE_PROCESSING_TIMEOUT minutes)
    releaseStaleItems($db);
    
    // Fetch pending items
    $stmt = $db->prepare("
        SELECT * FROM communication_queue
        WHERE status IN ('pending', 'failed')
          AND attempts < max_attempts
          AND (scheduled_at IS NULL OR scheduled_at <= NOW())
          AND (next_retry_at IS NULL OR next_retry_at <= NOW())
        ORDER BY priority ASC, created_at ASC
        LIMIT :batch_size
    ");
    $stmt->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        writeLog('queue.log', "PROCESS: No pending items in queue");
        return $summary;
    }
    
    writeLog('queue.log', "PROCESS: Found " . count($items) . " items to process");
    
    foreach ($items as $item) {
        $summary['processed']++;
        
        // Mark as processing
        $db->prepare("UPDATE communication_queue SET status = 'processing', last_attempt_at = NOW(), attempts = attempts + 1 WHERE id = :id")
           ->execute([':id' => $item['id']]);
        
        // Send based on channel
        $result = sendQueueItem($item);
        
        if ($result['success']) {
            // Mark as sent
            $db->prepare("UPDATE communication_queue SET status = 'sent', sent_at = NOW(), log_id = :log_id WHERE id = :id")
               ->execute([':log_id' => $result['log_id'] ?? null, ':id' => $item['id']]);
            $summary['sent']++;
            writeLog('queue.log', "SENT: QueueID={$item['id']}, Channel={$item['channel']}, To={$item['recipient']}");
            
        } else {
            $currentAttempts = $item['attempts'] + 1;
            
            if ($currentAttempts >= $item['max_attempts']) {
                // Max retries reached — mark as permanently failed
                $db->prepare("UPDATE communication_queue SET status = 'failed', error_message = :error WHERE id = :id")
                   ->execute([':error' => $result['error'], ':id' => $item['id']]);
                $summary['failed']++;
                writeLog('queue.log', "FAILED_PERMANENT: QueueID={$item['id']}, Error={$result['error']}");
                
                // Try fallback channel
                handleFallback($item, $result['error']);
                
            } else {
                // Schedule retry with exponential backoff
                $delayMins = QUEUE_RETRY_DELAY_MINS * pow(2, $currentAttempts - 1);
                $db->prepare("UPDATE communication_queue SET status = 'pending', error_message = :error, next_retry_at = DATE_ADD(NOW(), INTERVAL :delay MINUTE) WHERE id = :id")
                   ->execute([':error' => $result['error'], ':delay' => $delayMins, ':id' => $item['id']]);
                $summary['retried']++;
                writeLog('queue.log', "RETRY_SCHEDULED: QueueID={$item['id']}, Attempt=$currentAttempts, NextRetry={$delayMins}min");
            }
        }
    }
    
    writeLog('queue.log', "PROCESS_COMPLETE: " . json_encode($summary));
    return $summary;
}

/**
 * Send a single queue item based on its channel
 */
function sendQueueItem(array $item): array
{
    switch ($item['channel']) {
        case 'sms':
            return sendSMS(
                $item['recipient'],
                $item['message'],
                $item['message_type'],
                $item['patient_id']
            );
        
        case 'email':
            return sendEmail(
                $item['recipient'],
                $item['subject'] ?? 'Notification from ' . CLINIC_NAME,
                $item['html_body'] ?? "<p>{$item['message']}</p>",
                $item['message_type'],
                $item['patient_id']
            );
        
        default:
            return ['success' => false, 'message_id' => null, 'error' => "Unknown channel: {$item['channel']}"];
    }
}

/**
 * Handle fallback: if SMS fails, try email
 */
function handleFallback(array $failedItem, string $error): void
{
    // Fallback from SMS to email if available
    if ($failedItem['channel'] === 'sms') {
        writeLog('queue.log', "FALLBACK: SMS failed for {$failedItem['recipient']}, consider email notification");
    }
}

/**
 * Release stale items that have been "processing" too long
 */
function releaseStaleItems(PDO $db): void
{
    $timeout = QUEUE_PROCESSING_TIMEOUT;
    $stmt = $db->prepare("
        UPDATE communication_queue 
        SET status = 'pending', 
            error_message = CONCAT(IFNULL(error_message, ''), ' | Processing timeout')
        WHERE status = 'processing' 
          AND last_attempt_at < DATE_SUB(NOW(), INTERVAL :timeout MINUTE)
    ");
    $stmt->execute([':timeout' => $timeout]);
    
    $released = $stmt->rowCount();
    if ($released > 0) {
        writeLog('queue.log', "RELEASED: $released stale items from processing state");
    }
}

/**
 * Get queue statistics for admin dashboard
 */
function getQueueStats(): array
{
    $db = getDBConnection();
    
    $stmt = $db->query("
        SELECT 
            channel,
            status,
            COUNT(*) as count
        FROM communication_queue
        GROUP BY channel, status
    ");
    $rows = $stmt->fetchAll();
    
    $stats = [
        'total'    => 0,
        'pending'  => 0,
        'sent'     => 0,
        'failed'   => 0,
        'by_channel' => [],
    ];
    
    foreach ($rows as $row) {
        $stats['total'] += $row['count'];
        if ($row['status'] === 'pending' || $row['status'] === 'processing') {
            $stats['pending'] += $row['count'];
        } elseif ($row['status'] === 'sent') {
            $stats['sent'] += $row['count'];
        } elseif ($row['status'] === 'failed') {
            $stats['failed'] += $row['count'];
        }
        
        if (!isset($stats['by_channel'][$row['channel']])) {
            $stats['by_channel'][$row['channel']] = ['total' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0];
        }
        $stats['by_channel'][$row['channel']]['total'] += $row['count'];
        $stats['by_channel'][$row['channel']][$row['status']] = ($stats['by_channel'][$row['channel']][$row['status']] ?? 0) + $row['count'];
    }
    
    return $stats;
}

/**
 * Clean up old completed queue items (older than 30 days)
 */
function cleanupOldQueueItems(int $daysOld = 30): int
{
    $db = getDBConnection();
    $stmt = $db->prepare("
        DELETE FROM communication_queue 
        WHERE status IN ('sent', 'cancelled') 
          AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->execute([':days' => $daysOld]);
    return $stmt->rowCount();
}
