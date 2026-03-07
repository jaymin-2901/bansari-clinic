<?php
/**
 * ============================================================
 * MediConnect - Notification Badges & Alerts
 * File: clinic-admin/api/notifications.php
 * ============================================================
 * Dynamic notification badges for admin dashboard
 * Real-time badge counts for pending items
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get-counts';

try {
    $db = getClinicDB();

    switch ($action) {
        // ─── GET all badge counts ───
        case 'get-counts':
            $counts = getBadgeCounts($db);
            echo json_encode([
                'success' => true,
                'counts' => $counts
            ]);
            break;

        // ─── GET detailed alerts ───
        case 'get-alerts':
            $type = $_GET['type'] ?? 'all';
            $alerts = getDetailedAlerts($db, $type);
            echo json_encode([
                'success' => true,
                'alerts' => $alerts
            ]);
            break;

        // ─── MARK notification as read ───
        case 'mark-read':
            $notificationId = (int)($_POST['id'] ?? 0);
            if ($notificationId > 0) {
                $stmt = $db->prepare("UPDATE notification_alerts SET read_at = NOW() WHERE id = ?");
                $stmt->execute([$notificationId]);
                echo json_encode(['success' => true, 'message' => 'Marked as read']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            break;

        // ─── MARK all as read ───
        case 'mark-all-read':
            $db->query("UPDATE notification_alerts SET read_at = NOW() WHERE read_at IS NULL");
            echo json_encode(['success' => true, 'message' => 'All marked as read']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading notifications'
    ]);
}

/**
 * Get real-time badge counts
 */
function getBadgeCounts(PDO $db): array
{
    try {
        // ─── Pending Appointments (Today & Tomorrow) ───
        $pending = $db->query("
            SELECT COUNT(*) FROM appointments 
            WHERE status = 'pending' 
            AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ")->fetchColumn();

        // ─── Unread Contact Messages ───
        $messages = $db->query("
            SELECT COUNT(*) FROM contact_messages 
            WHERE is_read = 0
        ")->fetchColumn();

        // ─── Reminders to Send (24h window) ───
        $pendingReminders = $db->query("
            SELECT COUNT(*) FROM appointments 
            WHERE reminder_sent = 0 
            AND appointment_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 23 HOUR) 
                                    AND DATE_ADD(CURDATE(), INTERVAL 25 HOUR)
        ")->fetchColumn();

        // ─── Failed WhatsApp Notifications ───
        $whatsappFailures = $db->query("
            SELECT COUNT(*) FROM appointments 
            WHERE reminder_failure_reason IS NOT NULL
            AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ")->fetchColumn();

        // ─── Appointments Awaiting Confirmation ───
        $awaitingConfirm = $db->query("
            SELECT COUNT(*) FROM appointments 
            WHERE confirmation_status IN ('reminder_sent', 'pending')
            AND appointment_date >= CURDATE()
        ")->fetchColumn();

        // ─── Completed Appointments Without Follow-up ───
        $noFollowup = $db->query("
            SELECT COUNT(*) FROM appointments 
            WHERE status = 'completed'
            AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND id NOT IN (SELECT DISTINCT appointment_id FROM followup_notes)
        ")->fetchColumn();

        // ─── New Patient Registrations (Today) ───
        $newPatients = $db->query("
            SELECT COUNT(*) FROM patients 
            WHERE is_registered = 1
            AND created_at >= CURDATE()
        ")->fetchColumn();

        // ─── Unprocess Appointments ───
        $unprocessed = $db->query("
            SELECT COUNT(*) FROM appointments 
            WHERE status = 'pending'
            AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetchColumn();

        return [
            'pending_appointments' => (int)$pending,
            'unread_messages' => (int)$messages,
            'pending_reminders' => (int)$pendingReminders,
            'whatsapp_failures' => (int)$whatsappFailures,
            'awaiting_confirmation' => (int)$awaitingConfirm,
            'no_followup' => (int)$noFollowup,
            'new_patients' => (int)$newPatients,
            'unprocessed' => (int)$unprocessed,
            'timestamp' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        error_log("Badge count error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get detailed alert items
 */
function getDetailedAlerts(PDO $db, string $type = 'all'): array
{
    try {
        $alerts = [];

        // ─── PENDING APPOINTMENTS ───
        if ($type === 'all' || $type === 'appointments') {
            $stmt = $db->query("
                SELECT 
                    CONCAT('APT-', a.id) as alert_id,
                    'appointment' as type,
                    CONCAT(p.full_name, ' - ', a.appointment_date, ' ', COALESCE(a.appointment_time, '')) as title,
                    CONCAT('Appointment pending: ', p.full_name, ' on ', DATE_FORMAT(a.appointment_date, '%d %b %Y')) as description,
                    'calendar' as icon,
                    a.created_at,
                    a.updated_at
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.status = 'pending'
                AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                ORDER BY a.appointment_date ASC
                LIMIT 10
            ");
            $alerts = array_merge($alerts, $stmt->fetchAll());
        }

        // ─── UNREAD MESSAGES ───
        if ($type === 'all' || $type === 'messages') {
            $stmt = $db->query("
                SELECT 
                    CONCAT('MSG-', id) as alert_id,
                    'message' as type,
                    name as title,
                    SUBSTRING(message, 1, 80) as description,
                    'mail' as icon,
                    created_at,
                    updated_at
                FROM contact_messages
                WHERE is_read = 0
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $alerts = array_merge($alerts, $stmt->fetchAll());
        }

        // ─── FAILED REMINDERS ───
        if ($type === 'all' || $type === 'failures') {
            $stmt = $db->query("
                SELECT 
                    CONCAT('FAIL-', a.id) as alert_id,
                    'failure' as type,
                    CONCAT(p.full_name, ' - WhatsApp failed') as title,
                    reminder_failure_reason as description,
                    'alert-circle' as icon,
                    a.updated_at as created_at,
                    a.updated_at
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.reminder_failure_reason IS NOT NULL
                AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ORDER BY a.updated_at DESC
                LIMIT 5
            ");
            $alerts = array_merge($alerts, $stmt->fetchAll());
        }

        // ─── AWAITING CONFIRMATION ───
        if ($type === 'all' || $type === 'confirmation') {
            $stmt = $db->query("
                SELECT 
                    CONCAT('CONF-', a.id) as alert_id,
                    'confirmation' as type,
                    CONCAT(p.full_name, ' - Awaiting confirmation') as title,
                    CONCAT('Appointment on ', a.appointment_date, ' needs confirmation') as description,
                    'check-circle' as icon,
                    a.created_at,
                    a.updated_at
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.confirmation_status IN ('reminder_sent', 'pending')
                AND a.appointment_date >= CURDATE()
                ORDER BY a.appointment_date ASC
                LIMIT 5
            ");
            $alerts = array_merge($alerts, $stmt->fetchAll());
        }

        return $alerts;

    } catch (Exception $e) {
        error_log("Alert retrieval error: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper: Log activity for audit trail
 */
function logAdminActivity(PDO $db, int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?array $changes = null): void
{
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $stmt = $db->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, changes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $adminId,
            $action,
            $entityType,
            $entityId,
            $changes ? json_encode($changes) : null,
            $ipAddress,
            $userAgent
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
