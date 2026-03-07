<?php
/**
 * ============================================================
 * MediConnect – Unified Reminder Cron Job
 * File: backend/cron/send_reminders.php
 * ============================================================
 * Master cron script that sends appointment reminders via
 * SMS and Email channels.
 * 
 * Run every 5 minutes via cron:
 *   */5 * * * * php /path/to/backend/cron/send_reminders.php >> /path/to/logs/cron.log 2>&1
 * 
 * Logic:
 *   1. Fetch appointments where appointment_date = tomorrow
 *      AND reminder not yet sent
 *   2. Queue SMS + Email reminders
 *   3. Process the queue immediately
 *   4. Update reminder_sent flags
 * ============================================================
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] === Reminder Cron Started ===\n";

// ─── Load Dependencies ──────────────────────────────────────
require_once __DIR__ . '/../config/comm_config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/queue_processor.php';

try {
    $db = getDBConnection();
} catch (Exception $e) {
    echo "FATAL: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ─── STEP 1: Fetch tomorrow's appointments needing reminders ─
echo "Fetching tomorrow's appointments...\n";

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.id AS consultation_id,
        c.patient_id,
        c.appointment_datetime,
        c.status,
        c.confirmation_status,
        c.booking_ref,
        c.sms_reminder_sent,
        c.email_reminder_sent,
        c.reminder_24h_sent,
        u.first_name,
        u.last_name,
        u.email,
        u.phone
    FROM consultations c
    JOIN users u ON c.patient_id = u.id
    WHERE DATE(c.appointment_datetime) = DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))
      AND c.status IN ('pending', 'approved')
      AND (
          c.sms_reminder_sent = 0 
          OR c.email_reminder_sent = 0
      )
    ORDER BY c.appointment_datetime ASC
");
$stmt->execute();
$appointments = $stmt->fetchAll();

$totalAppts = count($appointments);
echo "Found $totalAppts appointments needing reminders.\n";

if ($totalAppts === 0) {
    echo "No reminders to send. Exiting.\n";
    $elapsed = round(microtime(true) - $startTime, 3);
    echo "[" . date('Y-m-d H:i:s') . "] === Cron Complete ($elapsed s) ===\n\n";
    exit(0);
}

// ─── STEP 2: Queue reminders for each appointment ───────────
$queued = ['sms' => 0, 'email' => 0];
$skipped = ['sms' => 0, 'email' => 0];

foreach ($appointments as $appt) {
    $patientName = trim($appt['first_name'] . ' ' . $appt['last_name']);
    $phone = $appt['phone'] ?? '';
    $email = $appt['email'] ?? '';
    $dateTime = $appt['appointment_datetime'];
    $consultationId = $appt['id'];
    $patientId = $appt['patient_id'];
    
    echo "  Processing: $patientName (ID: $consultationId, Date: $dateTime)\n";
    
    // ── SMS ──
    if (!$appt['sms_reminder_sent'] && !empty($phone)) {
        $formattedDate = date('d M Y', strtotime($dateTime));
        $formattedTime = date('h:i A', strtotime($dateTime));
        
        $smsMessage = "Reminder: Dear {$patientName}, you have an appointment tomorrow ({$formattedDate}) at {$formattedTime}" .
            " at " . CLINIC_NAME . " with " . CLINIC_DOCTOR_NAME . ". Contact: " . CLINIC_PHONE;
        
        $queueId = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'sms',
            'priority'        => 3,
            'recipient'       => $phone,
            'message'         => $smsMessage,
            'message_type'    => 'appointment_reminder',
        ]);
        
        if ($queueId) {
            $queued['sms']++;
            $db->prepare("UPDATE consultations SET sms_reminder_sent = 1, sms_reminder_sent_at = NOW() WHERE id = ?")
               ->execute([$consultationId]);
        }
    } else {
        $skipped['sms']++;
    }
    
    // ── Email ──
    if (!$appt['email_reminder_sent'] && !empty($email)) {
        $htmlBody = buildAppointmentEmailTemplate($patientName, $dateTime, CLINIC_DOCTOR_NAME, '', 'reminder');
        
        $queueId = queueMessage([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'channel'         => 'email',
            'priority'        => 5,
            'recipient'       => $email,
            'subject'         => "Appointment Reminder - Tomorrow at " . CLINIC_NAME,
            'message'         => "Appointment reminder for {$patientName}",
            'html_body'       => $htmlBody,
            'message_type'    => 'appointment_reminder',
        ]);
        
        if ($queueId) {
            $queued['email']++;
            $db->prepare("UPDATE consultations SET email_reminder_sent = 1, email_reminder_sent_at = NOW() WHERE id = ?")
               ->execute([$consultationId]);
        }
    } else {
        $skipped['email']++;
    }
}

echo "\nQueued: SMS={$queued['sms']}, Email={$queued['email']}\n";
echo "Skipped: SMS={$skipped['sms']}, Email={$skipped['email']}\n";

// ─── STEP 3: Process the queue immediately ──────────────────
echo "\nProcessing communication queue...\n";
$queueResult = processQueue();

echo "Queue Results: " . json_encode($queueResult) . "\n";

// ─── STEP 4: Cleanup old rate limit records ─────────────────
$cleaned = cleanupRateLimitRecords($db);
if ($cleaned > 0) {
    echo "Cleaned up $cleaned old rate limit records.\n";
}

// ─── Summary ─────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 3);
writeLog('cron_reminders.log', json_encode([
    'appointments_found' => $totalAppts,
    'queued'             => $queued,
    'queue_results'      => $queueResult,
    'elapsed_seconds'    => $elapsed,
]));

echo "[" . date('Y-m-d H:i:s') . "] === Cron Complete ($elapsed s) ===\n\n";
