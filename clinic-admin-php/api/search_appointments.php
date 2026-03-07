<?php
/**
 * ============================================================
 * MediConnect - Admin Appointment Search & Filters
 * File: clinic-admin/api/search_appointments.php
 * ============================================================
 * Advanced search with filters for date, status, patient name, etc.
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

requireAdmin();

header('Content-Type: application/json');

try {
    $db = getClinicDB();

    // ─── Get filter params ───
    $filters = [
        'search' => trim($_GET['search'] ?? ''),
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'status' => $_GET['status'] ?? null,
        'consultation_type' => $_GET['type'] ?? null,
        'confirmation_status' => $_GET['confirmation'] ?? null,
        'page' => (int)($_GET['page'] ?? 1),
        'limit' => min((int)($_GET['limit'] ?? 20), 100) // Max 100 per page
    ];

    // ─── Build dynamic query ───
    $query = "
        SELECT 
            a.id,
            a.patient_id,
            p.full_name,
            p.mobile,
            p.age,
            p.gender,
            a.appointment_date,
            a.appointment_time,
            a.consultation_type,
            a.form_type,
            a.status,
            a.confirmation_status,
            a.reminder_sent,
            a.sms_reminder_sent,
            a.created_at,
            a.admin_notes
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE 1=1
    ";

    $params = [];

    // ─── Search in patient name or mobile ───
    if (!empty($filters['search'])) {
        $query .= " AND (p.full_name LIKE ? OR p.mobile LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // ─── Date range filter ───
    if (!empty($filters['date_from'])) {
        $query .= " AND a.appointment_date >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $query .= " AND a.appointment_date <= ?";
        $params[] = $filters['date_to'];
    }

    // ─── Status filter ───
    if (!empty($filters['status'])) {
        $query .= " AND a.status = ?";
        $params[] = $filters['status'];
    }

    // ─── Consultation type filter ───
    if (!empty($filters['consultation_type'])) {
        $query .= " AND a.consultation_type = ?";
        $params[] = $filters['consultation_type'];
    }

    // ─── Confirmation status filter ───
    if (!empty($filters['confirmation_status'])) {
        $query .= " AND a.confirmation_status = ?";
        $params[] = $filters['confirmation_status'];
    }

    // ─── Get count for pagination ───
    $countStmt = $db->prepare(str_replace('SELECT a.id, a.patient_id, p.full_name,', 'SELECT COUNT(*) as total', substr($query, 0, strpos($query, 'ORDER')))); 
    // Actually, let's do this properly:
    $countQuery = preg_replace('/SELECT .+ FROM appointments/', 'SELECT COUNT(*) as total FROM appointments', $query);
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch()['total'];

    // ─── Order and pagination ───
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";
    $query .= " LIMIT ? OFFSET ?";
    
    $offset = ($filters['page'] - 1) * $filters['limit'];
    $params[] = $filters['limit'];
    $params[] = $offset;

    // ─── Execute main query ───
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    // ─── Format response ───
    $response = [
        'success' => true,
        'data' => $appointments,
        'pagination' => [
            'total' => $totalCount,
            'page' => $filters['page'],
            'limit' => $filters['limit'],
            'pages' => ceil($totalCount / $filters['limit'])
        ],
        'filters_applied' => array_filter([
            'search' => $filters['search'] ?: null,
            'date_range' => ($filters['date_from'] || $filters['date_to']) ? "{$filters['date_from']} to {$filters['date_to']}" : null,
            'status' => $filters['status'],
            'type' => $filters['consultation_type'],
            'confirmation' => $filters['confirmation_status']
        ])
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Search query error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'data' => []
    ]);
}
