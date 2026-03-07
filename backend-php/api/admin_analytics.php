<?php
/**
 * ============================================================
 * MediConnect – Admin Analytics API (Stats)
 * File: backend/api/admin_analytics.php
 * ============================================================
 * 
 * Endpoints:
 *   ?action=overview    → GET Full analytics overview
 * 
 * Prepared statements throughout. No SQL injection.
 * Admin session validated.
 */

require_once __DIR__ . '/../security/bootstrap.php';
require_once __DIR__ . '/../../clinic-admin-php/includes/auth.php';

// ── Security: CORS + Rate Limiting + Admin Auth ──
SecurityBootstrap::adminEndpoint('analytics');

// ── Legacy Auth (backward compatibility) ──
requireAdminAPI();

$action = $_GET['action'] ?? '';

try {
    $db = getDBConnection();

    switch ($action) {
        case 'overview':
            // Total bookings
            $totalBookings = (int) $db->query("SELECT COUNT(*) FROM consultations")->fetchColumn();

            // Completed consultations
            $completed = (int) $db->query("SELECT COUNT(*) FROM consultations WHERE status = 'completed'")->fetchColumn();

            // New patients (registered this month)
            $newPatients = (int) $db->query("
                SELECT COUNT(*) FROM users 
                WHERE role = 'patient' 
                  AND YEAR(created_at) = YEAR(CURDATE()) 
                  AND MONTH(created_at) = MONTH(CURDATE())
            ")->fetchColumn();

            // Total patients
            $totalPatients = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();

            // Status breakdown
            $statusStmt = $db->query("
                SELECT status, COUNT(*) AS cnt 
                FROM consultations 
                GROUP BY status
            ");
            $statusBreakdown = [];
            while ($row = $statusStmt->fetch()) {
                $statusBreakdown[$row['status']] = (int) $row['cnt'];
            }

            // Monthly booking trend (last 12 months)
            $trendStmt = $db->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
                FROM consultations
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $monthlyTrend = $trendStmt->fetchAll();

            jsonResponse(200, [
                'success' => true,
                'overview' => [
                    'totalBookings'    => $totalBookings,
                    'completedConsultations' => $completed,
                    'newPatients'      => $newPatients,
                    'totalPatients'    => $totalPatients,
                    'statusBreakdown'  => $statusBreakdown,
                    'monthlyTrend'     => $monthlyTrend,
                ],
            ]);
            break;

        default:
            jsonResponse(400, ['success' => false, 'error' => 'Unknown action. Available: overview']);
            break;
    }

} catch (PDOException $e) {
    writeLog('api_errors.log', 'Analytics API Error: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    writeLog('api_errors.log', 'Analytics API Error: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'error' => 'Server error']);
}

// ── Helper: JSON response ──
function jsonResponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit(0);
}
