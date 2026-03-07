<?php
/**
 * ============================================================
 * MediConnect – Admin API: Reminder & Follow-Up Status
 * File: backend/api/admin_reminders.php
 * ============================================================
 * 
 * Simplified endpoints:
 *   ?action=list       → GET all consultations
 *   ?action=stats      → GET follow-up summary statistics (next 24h)
 */

require_once __DIR__ . '/../security/bootstrap.php';
require_once __DIR__ . '/../../clinic-admin-php/includes/auth.php';

// ── Security: CORS + Rate Limiting + Admin Auth ──
SecurityBootstrap::adminEndpoint('admin');

// ── Legacy Auth & CSRF (backward compatibility) ──
requireAdminAPI();
requireCSRF();

$action = $_GET['action'] ?? '';

try {
    $db = getDBConnection();

    switch ($action) {

        // ──────────────────────────────────────────────
        // Patients list (dedicated endpoint)
        // ──────────────────────────────────────────────
        case 'patients':
            $page     = max(1, (int) ($_GET['page'] ?? 1));
            $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
            $offset   = ($page - 1) * $pageSize;

            $search = trim($_GET['search'] ?? '');
            $where = "WHERE u.role = 'patient'";
            $params = [];
            if ($search !== '') {
                $where .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE :s OR u.email LIKE :s2 OR u.phone LIKE :s3)";
                $params[':s'] = '%' . $search . '%';
                $params[':s2'] = '%' . $search . '%';
                $params[':s3'] = '%' . $search . '%';
            }

            $countStmt = $db->prepare("SELECT COUNT(*) FROM users u {$where}");
            $countStmt->execute($params);
            $totalCount = (int) $countStmt->fetchColumn();

            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at, COALESCE(bc.bookings,0) AS bookings
                FROM users u
                LEFT JOIN (
                    SELECT patient_id, COUNT(*) AS bookings FROM consultations GROUP BY patient_id
                ) bc ON bc.patient_id = u.id
                {$where}
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll();
            jsonResponse(200, [
                'success' => true,
                'data' => $rows,
                'total' => $totalCount,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => ceil($totalCount / $pageSize),
            ]);
            break;

        // ──────────────────────────────────────────────
        // 1. List consultations
        // ──────────────────────────────────────────────
        case 'list':
            $page     = max(1, (int) ($_GET['page'] ?? 1));
            $pageSize = min(50, max(1, (int) ($_GET['page_size'] ?? 20)));
            $offset   = ($page - 1) * $pageSize;

            $statusFilter   = $_GET['status'] ?? '';
            $searchFilter   = trim($_GET['search'] ?? '');
            $dateFilter     = $_GET['date'] ?? '';
            $followupFilter = $_GET['followup'] ?? '';

            $where  = [];
            $params = [];

            // Status filter
            if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected', 'completed', 'cancelled'])) {
                $where[]  = 'c.status = :status';
                $params[':status'] = $statusFilter;
            }

            // Search filter (patient name or phone)
            if ($searchFilter !== '') {
                $where[] = '(CONCAT(u.first_name, \' \', u.last_name) LIKE :search OR u.phone LIKE :search2)';
                $params[':search']  = '%' . $searchFilter . '%';
                $params[':search2'] = '%' . $searchFilter . '%';
            }

            // Date filter (exact date match on appointment_datetime)
            if ($dateFilter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
                $where[] = 'DATE(c.appointment_datetime) = :filterdate';
                $params[':filterdate'] = $dateFilter;
            }

            // Follow-up filter
            if ($followupFilter === 'done') {
                $where[] = 'c.followup_done = 1';
            } elseif ($followupFilter === 'pending') {
                $where[] = 'c.followup_done = 0';
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $db->prepare("SELECT COUNT(*) FROM consultations c INNER JOIN users u ON c.patient_id = u.id {$whereClause}");
            $countStmt->execute($params);
            $totalCount = (int) $countStmt->fetchColumn();

            $sql = "
                SELECT 
                    c.id,
                    c.patient_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS patient_name,
                    u.email AS patient_email,
                    u.phone,
                    u.status AS account_status,
                    c.consultation_type,
                    c.appointment_datetime,
                    c.preferred_date,
                    c.preferred_time,
                    c.status,
                    c.confirmation_status,
                    c.followup_done,
                    c.followup_done_at,
                    c.followup_done_by,
                    c.created_at
                FROM consultations c
                INNER JOIN users u ON c.patient_id = u.id
                {$whereClause}
                ORDER BY c.appointment_datetime DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll();

            jsonResponse(200, [
                'success'     => true,
                'data'        => $rows,
                'total'       => $totalCount,
                'page'        => $page,
                'page_size'   => $pageSize,
                'total_pages' => ceil($totalCount / $pageSize),
            ]);
            break;

        // ──────────────────────────────────────────────
        // 2. Follow-up summary statistics (next 24h)
        // ──────────────────────────────────────────────
        case 'stats':
            $stats = $db->query("
                SELECT 
                    COUNT(*) AS total_next_24h,
                    SUM(CASE WHEN followup_done = 1 THEN 1 ELSE 0 END) AS followup_done,
                    SUM(CASE WHEN followup_done = 0 THEN 1 ELSE 0 END) AS pending_followups
                FROM consultations
                WHERE appointment_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                  AND status NOT IN ('cancelled', 'rejected')
            ")->fetch();

            jsonResponse(200, [
                'success' => true,
                'stats'   => [
                    'totalNext24h'      => (int) $stats['total_next_24h'],
                    'followupDoneCount' => (int) $stats['followup_done'],
                    'pendingFollowups'  => (int) $stats['pending_followups'],
                ],
            ]);
            break;

        // ──────────────────────────────────────────────
        // Toggle patient status (activate/block)
        // ──────────────────────────────────────────────
        case 'toggle_patient_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $patientId = (int) ($input['id'] ?? 0);
            $newStatus = trim($input['status'] ?? ''); // expected 'active' or 'blocked'

            if ($patientId <= 0 || ($newStatus !== 'active' && $newStatus !== 'blocked')) {
                jsonResponse(400, ['success' => false, 'error' => 'Invalid parameters']);
                break;
            }

            $check = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'patient'");
            $check->execute([$patientId]);
            if (!$check->fetch()) {
                jsonResponse(404, ['success' => false, 'error' => 'Patient not found']);
                break;
            }

            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $patientId]);

            writeLog('admin_actions.log', "TOGGLE patient #{$patientId} => {$newStatus}");
            jsonResponse(200, ['success' => true, 'message' => 'Patient status updated']);
            break;

        // ──────────────────────────────────────────────
        // 6. View single booking by ID
        // ──────────────────────────────────────────────
        case 'view_booking':
            $bookingId = (int) ($_GET['id'] ?? 0);
            if ($bookingId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Booking id required']);
                break;
            }
            $stmt = $db->prepare("
                SELECT 
                    c.id, c.patient_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS patient_name,
                    u.email AS patient_email,
                    u.phone,
                    c.consultation_type, c.urgency_level, c.symptoms,
                    c.preferred_date, c.preferred_time,
                    c.appointment_datetime, c.booking_ref,
                    c.status, c.confirmation_status,
                    c.followup_done, c.followup_done_at,
                    c.admin_notes, c.notes,
                    c.created_at, c.updated_at
                FROM consultations c
                INNER JOIN users u ON c.patient_id = u.id
                WHERE c.id = :id
            ");
            $stmt->execute([':id' => $bookingId]);
            $booking = $stmt->fetch();
            if (!$booking) {
                jsonResponse(404, ['success' => false, 'error' => 'Booking not found']);
                break;
            }
            jsonResponse(200, ['success' => true, 'booking' => $booking]);
            break;

        // ──────────────────────────────────────────────
        // 7. Update booking (status, notes, datetime)
        // ──────────────────────────────────────────────
        case 'update_booking':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $bookingId = (int) ($input['id'] ?? 0);
            if ($bookingId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Booking id required']);
                break;
            }

            // Verify booking exists
            $check = $db->prepare("SELECT id FROM consultations WHERE id = ?");
            $check->execute([$bookingId]);
            if (!$check->fetch()) {
                jsonResponse(404, ['success' => false, 'error' => 'Booking not found']);
                break;
            }

            $sets = [];
            $params = [];

            if (isset($input['status']) && in_array($input['status'], ['pending', 'approved', 'rejected', 'completed', 'cancelled'])) {
                $sets[] = 'status = ?';
                $params[] = $input['status'];
            }
            if (isset($input['appointment_datetime']) && $input['appointment_datetime'] !== '') {
                $sets[] = 'appointment_datetime = ?';
                $params[] = $input['appointment_datetime'];
            }
            if (isset($input['preferred_date'])) {
                $sets[] = 'preferred_date = ?';
                $params[] = $input['preferred_date'];
            }
            if (isset($input['preferred_time'])) {
                $sets[] = 'preferred_time = ?';
                $params[] = $input['preferred_time'];
            }
            if (isset($input['admin_notes'])) {
                $sets[] = 'admin_notes = ?';
                $params[] = $input['admin_notes'];
            }
            if (isset($input['consultation_type']) && in_array($input['consultation_type'], ['online', 'offline'])) {
                $sets[] = 'consultation_type = ?';
                $params[] = $input['consultation_type'];
            }

            if (empty($sets)) {
                jsonResponse(400, ['success' => false, 'error' => 'No fields to update']);
                break;
            }

            $params[] = $bookingId;
            $stmt = $db->prepare("UPDATE consultations SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);

            writeLog('admin_actions.log', "UPDATE booking #{$bookingId}: " . json_encode($input));
            jsonResponse(200, ['success' => true, 'message' => 'Booking updated successfully']);
            break;

        // ──────────────────────────────────────────────
        // 8. Delete single booking
        // ──────────────────────────────────────────────
        case 'delete_booking':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $bookingId = (int) ($input['id'] ?? 0);
            if ($bookingId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Booking id required']);
                break;
            }

            $check = $db->prepare("SELECT id FROM consultations WHERE id = ?");
            $check->execute([$bookingId]);
            if (!$check->fetch()) {
                jsonResponse(404, ['success' => false, 'error' => 'Booking not found']);
                break;
            }

            $stmt = $db->prepare("DELETE FROM consultations WHERE id = ?");
            $stmt->execute([$bookingId]);

            writeLog('admin_actions.log', "DELETE booking #{$bookingId}");
            jsonResponse(200, ['success' => true, 'message' => 'Booking deleted successfully']);
            break;

        // ──────────────────────────────────────────────
        // 9. Update booking status (quick status change)
        // ──────────────────────────────────────────────
        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $bookingId = (int) ($input['id'] ?? 0);
            $newStatus = $input['status'] ?? '';

            if ($bookingId <= 0 || !in_array($newStatus, ['pending', 'approved', 'rejected', 'completed', 'cancelled'])) {
                jsonResponse(400, ['success' => false, 'error' => 'Valid id and status required']);
                break;
            }

            $stmt = $db->prepare("UPDATE consultations SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $bookingId]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(404, ['success' => false, 'error' => 'Booking not found or no change']);
                break;
            }

            writeLog('admin_actions.log', "STATUS CHANGE booking #{$bookingId} → {$newStatus}");
            jsonResponse(200, ['success' => true, 'message' => 'Status updated to ' . ucfirst($newStatus)]);
            break;

        // ──────────────────────────────────────────────
        // 10. View single patient
        // ──────────────────────────────────────────────
        case 'view_patient':
            $patientId = (int) ($_GET['id'] ?? 0);
            if ($patientId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Patient id required']);
                break;
            }
            $stmt = $db->prepare("
                SELECT u.id, u.first_name, u.last_name,
                       CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                       u.email, u.phone, u.created_at,
                       pa.address, pa.city, pa.state, pa.pincode,
                       mh.blood_type, mh.allergies, mh.current_medications, mh.chronic_conditions
                FROM users u
                LEFT JOIN patient_address pa ON pa.user_id = u.id
                LEFT JOIN medical_history mh ON mh.user_id = u.id
                WHERE u.id = :id AND u.role = 'patient'
            ");
            $stmt->execute([':id' => $patientId]);
            $patient = $stmt->fetch();
            if (!$patient) {
                jsonResponse(404, ['success' => false, 'error' => 'Patient not found']);
                break;
            }

            // Get booking count
            $bStmt = $db->prepare("SELECT COUNT(*) FROM consultations WHERE patient_id = ?");
            $bStmt->execute([$patientId]);
            $patient['booking_count'] = (int) $bStmt->fetchColumn();

            jsonResponse(200, ['success' => true, 'patient' => $patient]);
            break;

        // ──────────────────────────────────────────────
        // 11. Update patient details
        // ──────────────────────────────────────────────
        case 'update_patient':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $patientId = (int) ($input['id'] ?? 0);
            if ($patientId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Patient id required']);
                break;
            }

            $check = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'patient'");
            $check->execute([$patientId]);
            if (!$check->fetch()) {
                jsonResponse(404, ['success' => false, 'error' => 'Patient not found']);
                break;
            }

            $sets = [];
            $params = [];
            if (isset($input['first_name']) && trim($input['first_name']) !== '') {
                $sets[] = 'first_name = ?';
                $params[] = trim($input['first_name']);
            }
            if (isset($input['last_name'])) {
                $sets[] = 'last_name = ?';
                $params[] = trim($input['last_name']);
            }
            if (isset($input['email']) && filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $sets[] = 'email = ?';
                $params[] = $input['email'];
            }
            if (isset($input['phone']) && trim($input['phone']) !== '') {
                $sets[] = 'phone = ?';
                $params[] = trim($input['phone']);
            }

            if (!empty($sets)) {
                $params[] = $patientId;
                $stmt = $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?");
                $stmt->execute($params);
            }

            writeLog('admin_actions.log', "UPDATE patient #{$patientId}: " . json_encode($input));
            jsonResponse(200, ['success' => true, 'message' => 'Patient updated successfully']);
            break;

        // ──────────────────────────────────────────────
        // 12. Delete patient
        // ──────────────────────────────────────────────
        case 'delete_patient':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $patientId = (int) ($input['id'] ?? 0);
            if ($patientId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Patient id required']);
                break;
            }

            $check = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'patient'");
            $check->execute([$patientId]);
            if (!$check->fetch()) {
                jsonResponse(404, ['success' => false, 'error' => 'Patient not found']);
                break;
            }

            // CASCADE will handle consultations, address, medical_history
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
            $stmt->execute([$patientId]);

            writeLog('admin_actions.log', "DELETE patient #{$patientId}");
            jsonResponse(200, ['success' => true, 'message' => 'Patient deleted successfully']);
            break;

        // ──────────────────────────────────────────────
        // LIST PATIENTS (dedicated endpoint)
        // ──────────────────────────────────────────────
        case 'list_patients':
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $sort   = trim($_GET['sort'] ?? 'newest');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = max(1, min(100, (int)($_GET['limit'] ?? 15)));
            $offset = ($page - 1) * $limit;

            $where = ["u.role = 'patient'"];
            $params = [];

            if ($search !== '') {
                $where[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            if ($status !== '' && in_array($status, ['active', 'blocked', 'inactive'])) {
                $where[] = "u.status = :status";
                $params[':status'] = $status;
            }

            $whereSQL = implode(' AND ', $where);

            $orderMap = [
                'newest' => 'u.id DESC',
                'oldest' => 'u.id ASC',
                'name_asc' => 'u.first_name ASC, u.last_name ASC',
                'name_desc' => 'u.first_name DESC, u.last_name DESC',
            ];
            $orderSQL = $orderMap[$sort] ?? 'u.id DESC';

            // Count
            $countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSQL}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch
            $stmt = $db->prepare("
                SELECT u.id, u.first_name, u.last_name, 
                       CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) AS full_name,
                       u.email, u.phone, u.status, u.gender, u.date_of_birth, u.created_at,
                       pa.city, pa.state,
                       mh.blood_type, mh.allergies, mh.current_medications, mh.chronic_conditions,
                       (SELECT COUNT(*) FROM consultations c WHERE c.patient_id = u.id) AS booking_count
                FROM users u
                LEFT JOIN patient_address pa ON pa.user_id = u.id
                LEFT JOIN medical_history mh ON mh.user_id = u.id
                WHERE {$whereSQL}
                ORDER BY {$orderSQL}
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $patients = $stmt->fetchAll();

            jsonResponse(200, [
                'success'  => true,
                'patients' => $patients,
                'total'    => $total,
                'page'     => $page,
                'pages'    => ceil($total / $limit),
            ]);
            break;

        // ──────────────────────────────────────────────
        // TOGGLE PATIENT STATUS (active/blocked)
        // ──────────────────────────────────────────────
        case 'toggle_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['success' => false, 'error' => 'POST required']);
                break;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $patientId = (int)($input['id'] ?? 0);

            if ($patientId <= 0) {
                jsonResponse(400, ['success' => false, 'error' => 'Patient ID required']);
                break;
            }

            $check = $db->prepare("SELECT id, status FROM users WHERE id = ? AND role = 'patient'");
            $check->execute([$patientId]);
            $patient = $check->fetch();

            if (!$patient) {
                jsonResponse(404, ['success' => false, 'error' => 'Patient not found']);
                break;
            }

            $newStatus = ($patient['status'] === 'active') ? 'blocked' : 'active';
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'patient'");
            $stmt->execute([$newStatus, $patientId]);

            writeLog('admin_actions.log', "TOGGLE STATUS: patient #{$patientId} -> {$newStatus}");
            jsonResponse(200, [
                'success'    => true,
                'message'    => 'Patient status changed to ' . $newStatus,
                'new_status' => $newStatus,
            ]);
            break;

        default:
            jsonResponse(400, ['success' => false, 'error' => 'Unknown action. Available: list, stats, view_booking, update_booking, delete_booking, update_status, view_patient, update_patient, delete_patient, list_patients, toggle_status']);
            break;
    }

} catch (PDOException $e) {
    writeLog('api_errors.log', 'Admin Reminders API Error: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    writeLog('api_errors.log', 'Admin Reminders API Error: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'error' => 'Server error']);
}

// ── Helper: JSON response ──
function jsonResponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit(0);
}

