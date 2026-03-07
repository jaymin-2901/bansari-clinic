<?php
/**
 * ============================================================
 * MediConnect - SMS Fallback System
 * File: backend/sms/sms_fallback.php
 * ============================================================
 * Automatically sends SMS when WhatsApp notification fails
 * Prevents duplicate notifications
 */

class SMSFallback
{
    private PDO $db;
    private string $smsGatewayUrl;
    private string $smsApiKey;
    private string $smsFromNumber;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        
        // Configure SMS gateway (update with your provider)
        $this->smsGatewayUrl = getenv('SMS_GATEWAY_URL') ?: 'https://api.sms-provider.com/send';
        $this->smsApiKey = getenv('SMS_API_KEY') ?: '';
        $this->smsFromNumber = getenv('SMS_FROM_NUMBER') ?: 'MEDICN';
    }

    /**
     * Handle WhatsApp failure and send SMS fallback
     * Called when WhatsApp message fails
     * 
     * @param int $appointmentId
     * @param int $patientId
     * @param string $patientPhone
     * @param string $whatsappError
     * @return array
     */
    public function handleWhatsAppFailure(
        int $appointmentId,
        int $patientId,
        string $patientPhone,
        string $whatsappError
    ): array {
        try {
            $db = $this->db;

            // ─── Step 1: Log the WhatsApp failure ───
            $stmt = $db->prepare("
                UPDATE appointments 
                SET reminder_failure_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$whatsappError, $appointmentId]);

            // ─── Step 2: Check if SMS already sent (prevent duplicates) ───
            $stmt = $db->prepare("
                SELECT id FROM sms_logs 
                WHERE appointment_id = ? 
                AND message_type = 'reminder' 
                AND DATE(sent_at) = CURDATE()
            ");
            $stmt->execute([$appointmentId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'SMS already sent for this appointment today',
                    'duplicate' => true
                ];
            }

            // ─── Step 3: Get patient details ───
            $stmt = $db->prepare("
                SELECT p.full_name, a.appointment_date, a.appointment_time
                FROM patients p
                JOIN appointments a ON a.patient_id = p.id
                WHERE p.id = ? AND a.id = ?
                LIMIT 1
            ");
            $stmt->execute([$patientId, $appointmentId]);
            $patient = $stmt->fetch();

            if (!$patient) {
                return ['success' => false, 'message' => 'Patient not found'];
            }

            // ─── Step 4: Prepare SMS message ───
            $appointmentDate = $patient['appointment_date'];
            $appointmentTime = $patient['appointment_time'] ?? 'Not specified';
            $patientName = explode(' ', $patient['full_name'])[0]; // First name only

            $smsMessage = "Hi {$patientName}, Your appointment is on {$appointmentDate} at {$appointmentTime}. Please confirm or call us to reschedule. Regards, MediConnect";

            // Keep within 160 characters for single SMS
            if (strlen($smsMessage) > 160) {
                $smsMessage = "Hi {$patientName}, Your appointment is on {$appointmentDate} at {$appointmentTime}. Please confirm. MediConnect";
            }

            // ─── Step 5: Send SMS ───
            $smsResult = $this->sendSMS($patientPhone, $smsMessage);

            if (!$smsResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to send SMS',
                    'sms_error' => $smsResult['error']
                ];
            }

            // ─── Step 6: Log SMS sent ───
            $stmt = $db->prepare("
                INSERT INTO sms_logs (
                    patient_id,
                    recipient_phone,
                    appointment_id,
                    message_type,
                    gateway,
                    message_text,
                    status,
                    cost_cents
                ) VALUES (?, ?, ?, 'reminder', 'fallback', ?, ?, ?)
            ");

            $stmt->execute([
                $patientId,
                $patientPhone,
                $appointmentId,
                $smsMessage,
                $smsResult['status'] ?? 'sent',
                $smsResult['cost'] ?? 0
            ]);

            // ─── Step 7: Update appointment SMS flag ───
            $stmt = $db->prepare("
                UPDATE appointments 
                SET sms_reminder_sent = 1,
                    sms_sent_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$appointmentId]);

            return [
                'success' => true,
                'message' => 'SMS fallback sent successfully',
                'sms_id' => $this->db->lastInsertId(),
                'delivery_tracking_id' => $smsResult['tracking_id'] ?? null
            ];

        } catch (Exception $e) {
            error_log("SMS Fallback Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via gateway API
     * 
     * @param string $phone Recipient phone number
     * @param string $message SMS message text
     * @return array
     */
    private function sendSMS(string $phone, string $message): array
    {
        try {
            // Example: Using cURL to send to SMS gateway
            // Replace with your actual SMS provider's API
            
            $payload = [
                'apikey' => $this->smsApiKey,
                'from' => $this->smsFromNumber,
                'to' => $phone,
                'message' => $message,
                'type' => 'text'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->smsGatewayUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => "SMS gateway returned status {$httpCode}",
                    'response' => $response
                ];
            }

            $responseData = json_decode($response, true);

            // Check provider's response format
            if (!isset($responseData['success']) || !$responseData['success']) {
                return [
                    'success' => false,
                    'error' => $responseData['message'] ?? 'SMS sending failed',
                    'errorcode' => $responseData['code'] ?? null
                ];
            }

            return [
                'success' => true,
                'status' => 'sent',
                'tracking_id' => $responseData['message_id'] ?? null,
                'cost' => $responseData['cost'] ?? 0
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Retry failed SMS messages
     * Run periodically: 0 */4 * * * /usr/bin/php retry_failed_sms.php
     * 
     * @return array
     */
    public function retryFailedSMS(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sl.*, a.appointment_id, p.full_name
                FROM sms_logs sl
                JOIN patients p ON sl.patient_id = p.id
                WHERE sl.status = 'failed'
                AND sl.sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND (SELECT COUNT(*) FROM sms_logs WHERE appointment_id = ? AND status IN ('sent', 'delivered')) < 3
                LIMIT 10
            ");

            $stmt->execute();
            $failedMessages = $stmt->fetchAll();

            $retried = 0;
            $successful = 0;

            foreach ($failedMessages as $log) {
                $result = $this->sendSMS($log['recipient_phone'], $log['message_text']);

                if ($result['success']) {
                    // Update original log
                    $updateStmt = $this->db->prepare("
                        UPDATE sms_logs 
                        SET status = 'sent',
                            error_message = NULL,
                            sent_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$log['id']]);
                    $successful++;
                }

                $retried++;
            }

            return [
                'success' => true,
                'retried' => $retried,
                'successful' => $successful
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get SMS delivery status from gateway
     * Some providers offer webhook/polling for delivery confirmation
     * 
     * @param string $trackingId
     * @return array
     */
    public function checkDeliveryStatus(string $trackingId): array
    {
        try {
            // Implementation depends on your SMS provider
            // This is a placeholder structure
            
            $payload = [
                'apikey' => $this->smsApiKey,
                'id' => $trackingId
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->smsGatewayUrl . '/status');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                // Update SMS log with delivery status
                $stmt = $this->db->prepare("
                    UPDATE sms_logs 
                    SET status = ?,
                        delivered_at = IF(? = 'delivered', NOW(), delivered_at)
                    WHERE tracking_id = ?
                ");
                $stmt->execute([$data['status'] ?? 'unknown', $data['status'], $trackingId]);

                return ['success' => true, 'status' => $data['status'] ?? 'unknown'];
            }

            return ['success' => false, 'error' => 'Failed to check status'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
