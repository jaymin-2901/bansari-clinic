<?php
/**
 * ============================================================
 * MediConnect – Email Service (PHPMailer + SMTP)
 * File: backend/email/email_service.php
 * ============================================================
 * Provides sendEmail() function using PHPMailer with SMTP.
 * Supports Gmail and SendGrid SMTP providers.
 * Includes HTML templates, rate limiting, and logging.
 * ============================================================
 */

// PHPMailer autoload – installed via composer
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once __DIR__ . '/../config/comm_config.php';
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email via SMTP
 * 
 * @param string      $to        Recipient email address
 * @param string      $subject   Email subject
 * @param string      $htmlBody  Full HTML body
 * @param string      $type      Message type for logging
 * @param int|null    $patientId Patient ID (optional)
 * @param string|null $replyTo   Reply-to address (optional)
 * @return array      ['success' => bool, 'message_id' => string|null, 'error' => string|null]
 */
function sendEmail(
    string $to,
    string $subject,
    string $htmlBody,
    string $type = 'general',
    ?int $patientId = null,
    ?string $replyTo = null
): array {
    $db = getDBConnection();
    
    // Validate email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address: $to";
        logEmail($db, $patientId, $to, $subject, $type, 'failed', null, $error);
        return ['success' => false, 'message_id' => null, 'error' => $error];
    }
    
    // Check rate limit
    if (!checkEmailRateLimit($db, $to)) {
        $error = "Email rate limit exceeded for $to";
        logEmail($db, $patientId, $to, $subject, $type, 'rejected', null, $error);
        writeLog('email.log', "RATE_LIMIT: $error");
        return ['success' => false, 'message_id' => null, 'error' => $error];
    }
    
    // Validate SMTP config
    if (empty(SMTP_USERNAME) || SMTP_USERNAME === 'your_email@gmail.com') {
        $error = 'SMTP credentials not configured';
        logEmail($db, $patientId, $to, $subject, $type, 'failed', null, $error);
        writeLog('email.log', "CONFIG_ERROR: $error");
        return ['success' => false, 'message_id' => null, 'error' => $error];
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // ─── SMTP Settings ───
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_ENCRYPTION === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        
        // Debug mode off for production
        $mail->SMTPDebug  = (APP_ENV === 'development') ? SMTP::DEBUG_OFF : SMTP::DEBUG_OFF;
        
        // Timeouts
        $mail->Timeout       = 30;
        $mail->SMTPKeepAlive = false;
        
        // ─── From / Reply-To ───
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        $effectiveReplyTo = $replyTo ?? SMTP_REPLY_TO;
        if (!empty($effectiveReplyTo)) {
            $mail->addReplyTo($effectiveReplyTo, SMTP_FROM_NAME);
        }
        
        // ─── Recipient ───
        $mail->addAddress($to);
        
        // ─── Content ───
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        
        // Plain text alternative (strip HTML tags)
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        
        // ─── Send ───
        $mail->send();
        
        $smtpMessageId = $mail->getLastMessageID();
        
        // Log success
        $logId = logEmail($db, $patientId, $to, $subject, $type, 'sent', json_encode(['message_id' => $smtpMessageId]), null, $smtpMessageId);
        trackRateLimitForEmail($db, $to, $type);
        
        writeLog('email.log', "SENT: To=$to, Subject=$subject, MessageID=$smtpMessageId");
        
        return [
            'success'    => true,
            'message_id' => $smtpMessageId,
            'log_id'     => $logId,
            'error'      => null,
            'status'     => 'sent'
        ];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        $logId = logEmail($db, $patientId, $to, $subject, $type, 'failed', null, $error);
        
        writeLog('email.log', "FAILED: To=$to, Subject=$subject, Error=$error");
        
        return [
            'success'    => false,
            'message_id' => null,
            'log_id'     => $logId,
            'error'      => $error,
            'status'     => 'failed'
        ];
    }
}

/**
 * Send appointment confirmation email
 */
function sendAppointmentConfirmationEmail(
    string $email,
    string $patientName,
    string $dateTime,
    string $doctorName,
    string $bookingRef = '',
    ?int $patientId = null
): array {
    $subject = "Appointment Confirmed - " . CLINIC_NAME;
    $htmlBody = buildAppointmentEmailTemplate(
        $patientName,
        $dateTime,
        $doctorName,
        $bookingRef,
        'confirmation'
    );
    return sendEmail($email, $subject, $htmlBody, 'appointment_confirmation', $patientId);
}

/**
 * Send appointment reminder email (24h before)
 */
function sendAppointmentReminderEmail(
    string $email,
    string $patientName,
    string $dateTime,
    string $doctorName,
    ?int $patientId = null
): array {
    $subject = "Appointment Reminder - Tomorrow at " . CLINIC_NAME;
    $htmlBody = buildAppointmentEmailTemplate(
        $patientName,
        $dateTime,
        $doctorName,
        '',
        'reminder'
    );
    return sendEmail($email, $subject, $htmlBody, 'appointment_reminder', $patientId);
}

/**
 * Send appointment cancellation email
 */
function sendCancellationEmail(
    string $email,
    string $patientName,
    string $dateTime,
    string $reason = '',
    ?int $patientId = null
): array {
    $subject = "Appointment Cancelled - " . CLINIC_NAME;
    $htmlBody = buildCancellationEmailTemplate($patientName, $dateTime, $reason);
    return sendEmail($email, $subject, $htmlBody, 'cancellation', $patientId);
}


// ═══════════════════════════════════════════════════════════
// HTML EMAIL TEMPLATES
// ═══════════════════════════════════════════════════════════

/**
 * Build professional HTML email template for appointments
 */
function buildAppointmentEmailTemplate(
    string $patientName,
    string $dateTime,
    string $doctorName,
    string $bookingRef,
    string $templateType
): string {
    $formattedDate = date('l, d F Y', strtotime($dateTime));
    $formattedTime = date('h:i A', strtotime($dateTime));
    
    $isReminder = ($templateType === 'reminder');
    $headerText = $isReminder ? 'Appointment Reminder' : 'Appointment Confirmed';
    $headerColor = $isReminder ? '#f59e0b' : '#10b981';
    $headerIcon  = $isReminder ? '⏰' : '✅';
    
    $introText = $isReminder
        ? "This is a friendly reminder that you have an appointment scheduled for <strong>tomorrow</strong>."
        : "Your appointment has been successfully confirmed. Here are the details:";
    
    $bookingRefHtml = !empty($bookingRef)
        ? "<tr><td style='padding:8px 15px;color:#6b7280;'>Booking Ref:</td><td style='padding:8px 15px;font-weight:600;'>$bookingRef</td></tr>"
        : '';
    
    $clinicName    = htmlspecialchars(CLINIC_NAME);
    $clinicPhone   = htmlspecialchars(CLINIC_PHONE);
    $clinicAddress = htmlspecialchars(CLINIC_ADDRESS);
    $clinicWebsite = htmlspecialchars(CLINIC_WEBSITE);
    $year          = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$headerText}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6;">
        <tr><td style="padding:30px 15px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="margin:0 auto;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.07);">
                
                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg, {$headerColor}, #065f46);padding:30px;text-align:center;">
                        <div style="font-size:40px;margin-bottom:10px;">{$headerIcon}</div>
                        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">{$headerText}</h1>
                        <p style="margin:5px 0 0;color:rgba(255,255,255,0.9);font-size:14px;">{$clinicName}</p>
                    </td>
                </tr>
                
                <!-- Body -->
                <tr>
                    <td style="padding:30px;">
                        <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 20px;">
                            Dear <strong>{$patientName}</strong>,
                        </p>
                        <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 25px;">
                            {$introText}
                        </p>
                        
                        <!-- Appointment Details Card -->
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:25px;">
                            <tr>
                                <td style="padding:20px;">
                                    <h3 style="margin:0 0 15px;color:#1f2937;font-size:16px;border-bottom:2px solid {$headerColor};padding-bottom:8px;">
                                        📋 Appointment Details
                                    </h3>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td style="padding:8px 15px;color:#6b7280;width:35%;">📅 Date:</td>
                                            <td style="padding:8px 15px;font-weight:600;color:#1f2937;">{$formattedDate}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 15px;color:#6b7280;">🕐 Time:</td>
                                            <td style="padding:8px 15px;font-weight:600;color:#1f2937;">{$formattedTime}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 15px;color:#6b7280;">👨‍⚕️ Doctor:</td>
                                            <td style="padding:8px 15px;font-weight:600;color:#1f2937;">{$doctorName}</td>
                                        </tr>
                                        {$bookingRefHtml}
                                        <tr>
                                            <td style="padding:8px 15px;color:#6b7280;">📍 Location:</td>
                                            <td style="padding:8px 15px;font-weight:600;color:#1f2937;">{$clinicAddress}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- Instructions -->
                        <div style="background-color:#eff6ff;border-left:4px solid #3b82f6;border-radius:0 8px 8px 0;padding:15px 20px;margin-bottom:25px;">
                            <h4 style="margin:0 0 8px;color:#1e40af;font-size:14px;">📝 Please Remember:</h4>
                            <ul style="margin:0;padding-left:18px;color:#374151;font-size:14px;line-height:1.8;">
                                <li>Please arrive 10 minutes before your scheduled time</li>
                                <li>Bring any previous medical reports or prescriptions</li>
                                <li>If you need to cancel, please inform us at least 4 hours in advance</li>
                            </ul>
                        </div>
                        
                        <!-- Contact -->
                        <p style="color:#6b7280;font-size:14px;line-height:1.6;margin:0;">
                            For any queries, contact us at <a href="tel:{$clinicPhone}" style="color:#059669;text-decoration:none;font-weight:600;">{$clinicPhone}</a>
                        </p>
                    </td>
                </tr>
                
                <!-- Footer -->
                <tr>
                    <td style="background-color:#f9fafb;padding:20px 30px;border-top:1px solid #e5e7eb;">
                        <p style="margin:0 0 5px;color:#9ca3af;font-size:12px;text-align:center;">
                            This is an automated message from {$clinicName}
                        </p>
                        <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                            {$clinicAddress} | {$clinicPhone}
                        </p>
                        <p style="margin:8px 0 0;color:#d1d5db;font-size:11px;text-align:center;">
                            © {$year} {$clinicName}. All rights reserved.
                        </p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Build cancellation email template
 */
function buildCancellationEmailTemplate(
    string $patientName,
    string $dateTime,
    string $reason = ''
): string {
    $formattedDate = date('l, d F Y', strtotime($dateTime));
    $formattedTime = date('h:i A', strtotime($dateTime));
    
    $reasonHtml = !empty($reason)
        ? "<p style='color:#374151;font-size:14px;line-height:1.6;margin:15px 0;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>"
        : '';
    
    $clinicName    = htmlspecialchars(CLINIC_NAME);
    $clinicPhone   = htmlspecialchars(CLINIC_PHONE);
    $clinicAddress = htmlspecialchars(CLINIC_ADDRESS);
    $year          = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Cancelled</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6;">
        <tr><td style="padding:30px 15px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="margin:0 auto;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.07);">
                
                <tr>
                    <td style="background:linear-gradient(135deg, #ef4444, #991b1b);padding:30px;text-align:center;">
                        <div style="font-size:40px;margin-bottom:10px;">❌</div>
                        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">Appointment Cancelled</h1>
                        <p style="margin:5px 0 0;color:rgba(255,255,255,0.9);font-size:14px;">{$clinicName}</p>
                    </td>
                </tr>
                
                <tr>
                    <td style="padding:30px;">
                        <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 20px;">
                            Dear <strong>{$patientName}</strong>,
                        </p>
                        <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 20px;">
                            Your appointment scheduled for <strong>{$formattedDate}</strong> at <strong>{$formattedTime}</strong> has been cancelled.
                        </p>
                        {$reasonHtml}
                        <div style="background-color:#fef3c7;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;padding:15px 20px;margin:20px 0;">
                            <p style="margin:0;color:#92400e;font-size:14px;">
                                To reschedule your appointment, please call us at 
                                <a href="tel:{$clinicPhone}" style="color:#d97706;font-weight:600;text-decoration:none;">{$clinicPhone}</a>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <td style="background-color:#f9fafb;padding:20px 30px;border-top:1px solid #e5e7eb;">
                        <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                            {$clinicAddress} | {$clinicPhone}
                        </p>
                        <p style="margin:8px 0 0;color:#d1d5db;font-size:11px;text-align:center;">
                            © {$year} {$clinicName}. All rights reserved.
                        </p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}


// ═══════════════════════════════════════════════════════════
// INTERNAL HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════

/**
 * Check email rate limit
 */
function checkEmailRateLimit(PDO $db, string $email): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt 
        FROM rate_limit_tracker 
        WHERE channel = 'email' 
          AND recipient = :email 
          AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    
    return ($row['cnt'] ?? 0) < EMAIL_RATE_LIMIT;
}

/**
 * Track email for rate limiting
 */
function trackRateLimitForEmail(PDO $db, string $email, string $type): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO rate_limit_tracker (channel, recipient, message_type, sent_at) 
            VALUES ('email', :recipient, :type, NOW())
        ");
        $stmt->execute([':recipient' => $email, ':type' => $type]);
    } catch (PDOException $e) {
        writeLog('email.log', "RATE_TRACK_ERROR: " . $e->getMessage());
    }
}

/**
 * Log email to database
 */
function logEmail(
    PDO $db,
    ?int $patientId,
    string $email,
    string $subject,
    string $type,
    string $status,
    ?string $response,
    ?string $errorMessage,
    ?string $smtpMessageId = null
): ?int {
    try {
        $bodyPreview = ''; // Will be set from context if needed
        $stmt = $db->prepare("
            INSERT INTO email_logs 
                (patient_id, email, subject, message_type, smtp_provider, smtp_message_id, status, response, error_message, created_at, sent_at)
            VALUES 
                (:patient_id, :email, :subject, :type, :provider, :msg_id, :status, :response, :error, NOW(), 
                 CASE WHEN :status2 = 'sent' THEN NOW() ELSE NULL END)
        ");
        $stmt->execute([
            ':patient_id' => $patientId,
            ':email'      => $email,
            ':subject'    => $subject,
            ':type'       => $type,
            ':provider'   => SMTP_PROVIDER,
            ':msg_id'     => $smtpMessageId,
            ':status'     => $status,
            ':status2'    => $status,
            ':response'   => $response,
            ':error'      => $errorMessage,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        writeLog('email.log', "DB_LOG_ERROR: " . $e->getMessage());
        return null;
    }
}
