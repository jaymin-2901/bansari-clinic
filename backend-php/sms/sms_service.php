<?php
/**
 * ============================================================
 * MediConnect – SMS Service (Fast2SMS Integration)
 * File: backend/sms/sms_service.php
 * ============================================================
 * Provides sendSMS() function for Indian SMS delivery.
 * Supports Fast2SMS gateway with DLT route.
 * Includes rate limiting, logging, and error handling.
 * ============================================================
 */

require_once __DIR__ . '/../config/comm_config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Send SMS via Fast2SMS gateway
 * 
 * @param string      $phone     Indian phone number (10 digits or with +91)
 * @param string      $message   Message text
 * @param string      $type      Message type for logging
 * @param int|null    $patientId Patient ID (optional)
 * @return array      ['success' => bool, 'message_id' => string|null, 'error' => string|null]
 */
function sendSMS(string $phone, string $message, string $type = 'general', ?int $patientId = null): array
{
    $db = getDBConnection();
    
    // Normalize phone number (strip +91, spaces, dashes)
    $phone = formatPhoneForSMS($phone);
    
    if (!$phone) {
        return ['success' => false, 'message_id' => null, 'error' => 'Invalid phone number'];
    }
    
    // Check rate limit
    if (!checkSMSRateLimit($db, $phone, $type)) {
        $error = "SMS rate limit exceeded for $phone";
        logSMS($db, $patientId, $phone, $message, $type, 'rejected', null, $error);
        writeLog('sms.log', "RATE_LIMIT: $error");
        return ['success' => false, 'message_id' => null, 'error' => $error];
    }
    
    // Validate API key
    if (empty(FAST2SMS_API_KEY) || FAST2SMS_API_KEY === 'your_fast2sms_api_key_here') {
        $error = 'Fast2SMS API key not configured';
        logSMS($db, $patientId, $phone, $message, $type, 'failed', null, $error);
        writeLog('sms.log', "CONFIG_ERROR: $error");
        return ['success' => false, 'message_id' => null, 'error' => $error];
    }
    
    // Build request payload
    $payload = buildSMSPayload($phone, $message);
    
    // Send via cURL
    $result = callFast2SMSAPI($payload);
    
    // Determine status
    $success = false;
    $messageId = null;
    $error = null;
    $status = 'failed';
    
    if ($result['http_code'] === 200 && isset($result['body']['return']) && $result['body']['return'] === true) {
        $success = true;
        $status = 'sent';
        $messageId = $result['body']['request_id'] ?? null;
        writeLog('sms.log', "SENT: Phone=$phone, Type=$type, RequestID=$messageId");
    } else {
        $error = $result['body']['message'] ?? ($result['error'] ?? 'Unknown SMS gateway error');
        writeLog('sms.log', "FAILED: Phone=$phone, Type=$type, Error=$error");
    }
    
    // Log to database
    $logId = logSMS(
        $db,
        $patientId,
        $phone,
        $message,
        $type,
        $status,
        json_encode($result['body'] ?? $result),
        $error,
        $messageId
    );
    
    // Track for rate limiting
    if ($success) {
        trackRateLimit($db, 'sms', $phone, $type);
    }
    
    return [
        'success'    => $success,
        'message_id' => $messageId,
        'log_id'     => $logId,
        'error'      => $error,
        'status'     => $status
    ];
}

/**
 * Send OTP SMS with special rate limiting
 * 
 * @param string   $phone Phone number
 * @param string   $otp   OTP code
 * @param int|null $patientId Patient ID
 * @return array
 */
function sendOTP(string $phone, string $otp, ?int $patientId = null): array
{
    $message = "Your OTP for " . CLINIC_NAME . " is: $otp. Valid for 10 minutes. Do not share with anyone.";
    return sendSMS($phone, $message, 'otp', $patientId);
}

/**
 * Send appointment confirmation SMS
 * 
 * @param string   $phone        Phone number
 * @param string   $patientName  Patient's name
 * @param string   $dateTime     Appointment date/time
 * @param string   $doctorName   Doctor's name
 * @param int|null $patientId    Patient ID
 * @return array
 */
function sendAppointmentConfirmationSMS(
    string $phone,
    string $patientName,
    string $dateTime,
    string $doctorName,
    ?int $patientId = null
): array {
    $formattedDate = date('d M Y, h:i A', strtotime($dateTime));
    $message = "Dear $patientName, your appointment at " . CLINIC_NAME .
               " with $doctorName on $formattedDate is confirmed." .
               " Contact: " . CLINIC_PHONE;
    return sendSMS($phone, $message, 'appointment_confirmation', $patientId);
}

/**
 * Send appointment reminder SMS (24h before)
 * 
 * @param string   $phone       Phone number
 * @param string   $patientName Patient's name
 * @param string   $dateTime    Appointment date/time
 * @param string   $doctorName  Doctor's name
 * @param int|null $patientId   Patient ID
 * @return array
 */
function sendAppointmentReminderSMS(
    string $phone,
    string $patientName,
    string $dateTime,
    string $doctorName,
    ?int $patientId = null
): array {
    $formattedDate = date('d M Y', strtotime($dateTime));
    $formattedTime = date('h:i A', strtotime($dateTime));
    $message = "Reminder: Dear $patientName, you have an appointment tomorrow ($formattedDate) at $formattedTime" .
               " at " . CLINIC_NAME . " with $doctorName." .
               " Reply CANCEL to cancel. Contact: " . CLINIC_PHONE;
    return sendSMS($phone, $message, 'appointment_reminder', $patientId);
}

/**
 * Send cancellation SMS
 * 
 * @param string   $phone       Phone number
 * @param string   $patientName Patient's name
 * @param string   $dateTime    Original appointment date/time
 * @param int|null $patientId   Patient ID
 * @return array
 */
function sendCancellationSMS(
    string $phone,
    string $patientName,
    string $dateTime,
    ?int $patientId = null
): array {
    $formattedDate = date('d M Y, h:i A', strtotime($dateTime));
    $message = "Dear $patientName, your appointment on $formattedDate at " . CLINIC_NAME .
               " has been cancelled. Please call " . CLINIC_PHONE . " to reschedule.";
    return sendSMS($phone, $message, 'cancellation', $patientId);
}


// ═══════════════════════════════════════════════════════════
// INTERNAL HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Format phone number for SMS (10-digit Indian format)
 */
function formatPhoneForSMS(string $phone): ?string
{
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading country code
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        $phone = substr($phone, 2);
    }
    
    // Validate 10 digits
    if (strlen($phone) !== 10) {
        return null;
    }
    
    // Must start with 6-9 (valid Indian mobile)
    if (!preg_match('/^[6-9]/', $phone)) {
        return null;
    }
    
    return $phone;
}

/**
 * Build Fast2SMS API payload
 */
function buildSMSPayload(string $phone, string $message): array
{
    $route = FAST2SMS_ROUTE;
    
    if ($route === 'dlt') {
        // DLT route (for registered templates in India)
        return [
            'route'      => 'dlt',
            'sender_id'  => FAST2SMS_SENDER_ID,
            'message'    => $message,
            'language'   => 'english',
            'flash'      => 0,
            'numbers'    => $phone,
        ];
    }
    
    // Quick transactional route (for testing / non-DLT)
    return [
        'route'      => 'q',
        'message'    => $message,
        'language'   => 'english',
        'flash'      => 0,
        'numbers'    => $phone,
    ];
}

/**
 * Call Fast2SMS API via cURL
 */
function callFast2SMSAPI(array $payload): array
{
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL            => FAST2SMS_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'authorization: ' . FAST2SMS_API_KEY,
            'Content-Type: application/json',
            'Cache-Control: no-cache',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        writeLog('sms.log', "CURL_ERROR: $curlError");
        return [
            'http_code' => 0,
            'body'      => null,
            'error'     => "cURL error: $curlError"
        ];
    }
    
    $body = json_decode($response, true);
    
    return [
        'http_code' => $httpCode,
        'body'      => $body,
        'error'     => null
    ];
}

/**
 * Check SMS rate limit for a phone number
 */
function checkSMSRateLimit(PDO $db, string $phone, string $type): bool
{
    $limit = ($type === 'otp') ? SMS_OTP_RATE_LIMIT : SMS_RATE_LIMIT_PER_HOUR;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt 
        FROM rate_limit_tracker 
        WHERE channel = 'sms' 
          AND recipient = :phone 
          AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([':phone' => $phone]);
    $row = $stmt->fetch();
    
    return ($row['cnt'] ?? 0) < $limit;
}

/**
 * Track a sent message for rate limiting
 */
function trackRateLimit(PDO $db, string $channel, string $recipient, string $type): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO rate_limit_tracker (channel, recipient, message_type, sent_at) 
            VALUES (:channel, :recipient, :type, NOW())
        ");
        $stmt->execute([
            ':channel'   => $channel,
            ':recipient' => $recipient,
            ':type'      => $type,
        ]);
    } catch (PDOException $e) {
        writeLog('sms.log', "RATE_TRACK_ERROR: " . $e->getMessage());
    }
}

/**
 * Log SMS to database
 */
function logSMS(
    PDO $db,
    ?int $patientId,
    string $phone,
    string $message,
    string $type,
    string $status,
    ?string $response,
    ?string $errorMessage,
    ?string $gatewayMessageId = null
): ?int {
    try {
        $stmt = $db->prepare("
            INSERT INTO sms_logs 
                (patient_id, phone, message, message_type, sms_gateway, gateway_message_id, status, response, error_message, created_at)
            VALUES 
                (:patient_id, :phone, :message, :type, :gateway, :msg_id, :status, :response, :error, NOW())
        ");
        $stmt->execute([
            ':patient_id' => $patientId,
            ':phone'      => $phone,
            ':message'    => $message,
            ':type'       => $type,
            ':gateway'    => SMS_GATEWAY,
            ':msg_id'     => $gatewayMessageId,
            ':status'     => $status,
            ':response'   => $response,
            ':error'      => $errorMessage,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        writeLog('sms.log', "DB_LOG_ERROR: " . $e->getMessage());
        return null;
    }
}

/**
 * Get SMS delivery status from Fast2SMS
 * 
 * @param string $requestId The request_id from Fast2SMS
 * @return array
 */
function getSMSDeliveryStatus(string $requestId): array
{
    $url = "https://www.fast2sms.com/dev/dlr?request_id=" . urlencode($requestId);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'authorization: ' . FAST2SMS_API_KEY,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $body = json_decode($response, true);
    
    return [
        'http_code' => $httpCode,
        'body'      => $body,
        'status'    => $body['data'][0]['status'] ?? 'unknown',
    ];
}

/**
 * Update SMS delivery status in database
 */
function updateSMSStatus(int $logId, string $status): bool
{
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            UPDATE sms_logs 
            SET status = :status, 
                delivered_at = CASE WHEN :status2 = 'delivered' THEN NOW() ELSE delivered_at END
            WHERE id = :id
        ");
        $stmt->execute([
            ':status'  => $status,
            ':status2' => $status,
            ':id'      => $logId,
        ]);
        return true;
    } catch (PDOException $e) {
        writeLog('sms.log', "STATUS_UPDATE_ERROR: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean old rate limit records (run periodically)
 */
function cleanupRateLimitRecords(PDO $db): int
{
    $stmt = $db->prepare("DELETE FROM rate_limit_tracker WHERE sent_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $stmt->execute();
    return $stmt->rowCount();
}
