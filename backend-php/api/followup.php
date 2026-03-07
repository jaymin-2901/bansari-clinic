<?php
/**
 * ============================================================
 * MediConnect – Follow-Up API (Simplified System)
 * File: backend/api/followup.php
 * ============================================================
 * 
 * Endpoints for admin follow-up tracking:
 *   ?action=list       → GET appointments within next 24 hours
 *   ?action=stats      → GET follow-up summary statistics
 *   ?action=mark_done  → POST mark follow-up as done
 * 
 * Usage:
 *   GET  /api/followup.php?action=list
 *   GET  /api/followup.php?action=stats
 *   POST /api/followup.php?action=mark_done  { consultation_id, admin_id }
 */

require_once __DIR__ . '/../security/bootstrap.php';
require_once __DIR__ . '/../../clinic-admin-php/includes/auth.php';

// ── Security: CORS + Rate Limiting + Admin Auth ──
SecurityBootstrap::staffEndpoint('admin');

// ── Legacy Auth & CSRF (backward compatibility) ──
requireAdminAPI();
requireCSRF();

$action = $_GET['action'] ?? '';

try {
    $db = getDBConnection();

    switch ($action) {

        // ──────────────────────────────────────────────
        // 1. List appointments within next 24 hours
        // ──────────────────────────────────────────────
        case 'list':
            $stmt = $db->prepare("
                SELECT 
                    c.id,
                    c.patient_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS patient_name,
                    u.phone AS mobile,
                    c.booking_ref,
                    c.consultation_type,
                    c.appointment_datetime,
                    c.preferred_date,
                    c.preferred_time,
                    c.status,
                    c.booking_whatsapp_sent,
                    c.booking_whatsapp_sent_at,
                    c.followup_done,
                    c.followup_done_at,
                    c.followup_done_by
                FROM consultations c
                INNER JOIN users u ON c.patient_id = u.id
                WHERE c.appointment_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                  AND c.status NOT IN ('cancelled', 'rejected')
                ORDER BY c.appointment_datetime ASC
            ");
            $stmt->execute();
            $appointments = $stmt->fetchAll();

            jsonResponse(200, [
                'success' => true,
                'data'    => $appointments,
                'count'   => count($appointments),
            ]);
            break;

        // ──────────────────────────────────────────────
        // 2. Follow-up summary statistics (next 24h)
        // ──────────────────────────────────────────────
        case 'stats':
            $stats = $db->query("
                SELECT 
                    COUNT(*) AS total_next_24h,
                    SUM(CASE WHEN booking_whatsapp_sent = 1 THEN 1 ELSE 0 END) AS whatsapp_sent_count,
                    SUM(CASE WHEN followup_done = 1 THEN 1 ELSE 0 END) AS followup_done_count,
                    SUM(CASE WHEN followup_done = 0 THEN 1 ELSE 0 END) AS pending_followups
                FROM consultations
                WHERE appointment_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                  AND status NOT IN ('cancelled', 'rejected')
            ")->fetch();

            jsonResponse(200, [
                'success' => true,
                'stats'   => [
                    'totalNext24h'      => (int) $stats['total_next_24h'],
                    'whatsappSentCount' => (int) $stats['whatsapp_sent_count'],
                    'followupDoneCount' => (int) $stats['followup_done_count'],
                    'pendingFollowups'  => (int) $stats['pending_followups'],
                ],
            ]);
            break;

        // ──────────────────────────────────────────────
        // 3. Mark follow-up as done
        // ──────────────────────────────────────────────
        case 'mark_done':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $consultationId = (int) ($input['consultation_id'] ?? 0);
            $adminId        = (int) ($input['admin_id'] ?? 0);

            if ($consultationId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'consultation_id required']);
                break;
            }

            // Check if already done
            $checkStmt = $db->prepare("SELECT followup_done FROM consultations WHERE id = :id");
            $checkStmt->execute([':id' => $consultationId]);
            $row = $checkStmt->fetch();

            if (!$row) {
                jsonResponse(404, ['success' => false, 'error' => 'Consultation not found']);
                break;
            }

            if ((int) $row['followup_done'] === 1) {
                jsonResponse(409, ['success' => false, 'error' => 'Follow-up already marked as done']);
                break;
            }

            // Mark done
            $updateStmt = $db->prepare("
                UPDATE consultations 
                SET followup_done = 1,
                    followup_done_at = NOW(),
                    followup_done_by = :admin_id
                WHERE id = :id AND followup_done = 0
            ");
            $updateStmt->execute([
                ':id'       => $consultationId,
                ':admin_id' => $adminId > 0 ? $adminId : null,
            ]);

            writeLog('followup.log', "FOLLOW-UP DONE: Consultation #{$consultationId} by Admin #{$adminId}");

            jsonResponse(200, [
                'success' => true,
                'message' => 'Follow-up marked as done',
            ]);
            break;

        default:
            jsonResponse(400, ['success' => false, 'error' => 'Unknown action. Available: list, stats, mark_done']);
            break;
    }

} catch (PDOException $e) {
    writeLog('api_errors.log', 'Follow-Up API Error: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    writeLog('api_errors.log', 'Follow-Up API Error: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'error' => 'Server error']);
}

// ── Helper: Send JSON response ──
function jsonResponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit(0);
}
