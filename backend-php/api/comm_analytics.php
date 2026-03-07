<?php
/**
 * ============================================================
 * MediConnect – Communication Analytics API
 * File: backend/api/comm_analytics.php
 * ============================================================
 * REST API for communication statistics, logs, and queue status.
 * Used by the admin dashboard Communications page.
 * 
 * Endpoints (via ?action=):
 *   overview   – Total sent/failed/delivered/pending per channel
 *   sms_logs   – Paginated SMS log entries
 *   email_logs – Paginated email log entries
 *   wa_logs    – Paginated WhatsApp unofficial logs
 *   queue      – Current queue status and items
 *   trends     – Daily/weekly send trends
 *   wa_status  – WhatsApp unofficial server connection status
 *   send_test  – Send a test message (SMS/Email/WhatsApp)
 * ============================================================
 */

require_once __DIR__ . '/../security/bootstrap.php';
require_once __DIR__ . '/../config/comm_config.php';

// ── Security: CORS + Rate Limiting + Admin Auth ──
SecurityBootstrap::adminEndpoint('analytics');

$db = getDBConnection();
$action = $_GET['action'] ?? 'overview';

try {
    switch ($action) {
        case 'overview':
            echo json_encode(getCommOverview($db));
            break;
            
        case 'sms_logs':
            echo json_encode(getSMSLogs($db));
            break;
            
        case 'email_logs':
            echo json_encode(getEmailLogs($db));
            break;
            
        case 'wa_logs':
            echo json_encode(getWALogs($db));
            break;
            
        case 'queue':
            echo json_encode(getQueueStatus($db));
            break;
            
        case 'trends':
            echo json_encode(getCommTrends($db));
            break;
            
        case 'wa_status':
            require_once __DIR__ . '/../whatsapp/wa_unofficial_client.php';
            echo json_encode(['success' => true, 'data' => getWAUnofficialStatus()]);
            break;
            
        case 'send_test':
            echo json_encode(handleSendTest($db));
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


// ═══════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * Communication overview – aggregate stats per channel
 */
function getCommOverview(PDO $db): array
{
    // SMS stats
    $sms = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(IF(status = 'sent', 1, 0)) as sent,
            SUM(IF(status = 'delivered', 1, 0)) as delivered,
            SUM(IF(status = 'failed', 1, 0)) as failed,
            SUM(IF(status IN ('queued', 'rejected'), 1, 0)) as other
        FROM sms_logs
    ")->fetch();
    
    // Email stats
    $email = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(IF(status = 'sent', 1, 0)) as sent,
            SUM(IF(status = 'delivered', 1, 0)) as delivered,
            SUM(IF(status = 'failed', 1, 0)) as failed,
            SUM(IF(status IN ('queued', 'bounced'), 1, 0)) as other
        FROM email_logs
    ")->fetch();
    
    // WhatsApp Unofficial stats
    $waUnofficial = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(IF(status = 'sent', 1, 0)) as sent,
            SUM(IF(status = 'delivered', 1, 0)) as delivered,
            SUM(IF(status = 'failed', 1, 0)) as failed,
            SUM(IF(status IN ('queued'), 1, 0)) as pending,
            SUM(IF(status = 'read', 1, 0)) as `read`,
            SUM(IF(direction = 'incoming', 1, 0)) as incoming,
            SUM(IF(direction = 'outgoing', 1, 0)) as outgoing
        FROM whatsapp_unofficial_logs
    ")->fetch();
    
    // WhatsApp Official stats (existing table)
    $waOfficial = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(IF(status = 'sent', 1, 0)) as sent,
            SUM(IF(status = 'delivered', 1, 0)) as delivered,
            SUM(IF(status = 'failed', 1, 0)) as failed,
            SUM(IF(status = 'read', 1, 0)) as `read`
        FROM whatsapp_reminder_log
    ")->fetch();
    
    // Queue stats
    $queue = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(IF(status = 'pending', 1, 0)) as pending,
            SUM(IF(status = 'processing', 1, 0)) as processing,
            SUM(IF(status = 'sent', 1, 0)) as sent,
            SUM(IF(status = 'failed', 1, 0)) as failed
        FROM communication_queue
    ")->fetch();
    
    // Today's counts
    $today = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM sms_logs WHERE DATE(created_at) = CURDATE()) as sms_today,
            (SELECT COUNT(*) FROM email_logs WHERE DATE(created_at) = CURDATE()) as email_today,
            (SELECT COUNT(*) FROM whatsapp_unofficial_logs WHERE DATE(created_at) = CURDATE() AND direction = 'outgoing') as wa_today
    ")->fetch();
    
    // Appointment updates via WhatsApp replies
    $autoReplies = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(IF(new_status = 'Confirmed', 1, 0)) as confirmed,
            SUM(IF(new_status = 'Cancelled', 1, 0)) as cancelled
        FROM appointment_updates
        WHERE update_source = 'whatsapp_reply'
    ")->fetch();
    
    return [
        'success' => true,
        'data' => [
            'sms'            => $sms,
            'email'          => $email,
            'whatsapp_unofficial' => $waUnofficial,
            'whatsapp_official'   => $waOfficial,
            'queue'          => $queue,
            'today'          => $today,
            'auto_replies'   => $autoReplies,
            'generated_at'   => date('Y-m-d H:i:s'),
        ],
    ];
}

/**
 * Paginated SMS logs
 */
function getSMSLogs(PDO $db): array
{
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $perPage  = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
    $status   = $_GET['status'] ?? '';
    $type     = $_GET['type'] ?? '';
    $search   = $_GET['search'] ?? '';
    $offset   = ($page - 1) * $perPage;
    
    $where = '1=1';
    $params = [];
    
    if ($status) {
        $where .= ' AND s.status = :status';
        $params[':status'] = $status;
    }
    if ($type) {
        $where .= ' AND s.message_type = :type';
        $params[':type'] = $type;
    }
    if ($search) {
        $where .= ' AND (s.phone LIKE :search OR s.message LIKE :search2 OR u.first_name LIKE :search3)';
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }
    
    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM sms_logs s LEFT JOIN users u ON s.patient_id = u.id WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Fetch page
    $stmt = $db->prepare("
        SELECT s.*, 
               CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS patient_name
        FROM sms_logs s
        LEFT JOIN users u ON s.patient_id = u.id
        WHERE $where
        ORDER BY s.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    return [
        'success'    => true,
        'data'       => $logs,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => (int) $total,
            'pages'    => ceil($total / $perPage),
        ],
    ];
}

/**
 * Paginated Email logs
 */
function getEmailLogs(PDO $db): array
{
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
    $status  = $_GET['status'] ?? '';
    $type    = $_GET['type'] ?? '';
    $search  = $_GET['search'] ?? '';
    $offset  = ($page - 1) * $perPage;
    
    $where = '1=1';
    $params = [];
    
    if ($status) {
        $where .= ' AND e.status = :status';
        $params[':status'] = $status;
    }
    if ($type) {
        $where .= ' AND e.message_type = :type';
        $params[':type'] = $type;
    }
    if ($search) {
        $where .= ' AND (e.email LIKE :search OR e.subject LIKE :search2 OR u.first_name LIKE :search3)';
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM email_logs e LEFT JOIN users u ON e.patient_id = u.id WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    $stmt = $db->prepare("
        SELECT e.*, 
               CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS patient_name
        FROM email_logs e
        LEFT JOIN users u ON e.patient_id = u.id
        WHERE $where
        ORDER BY e.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    return [
        'success'    => true,
        'data'       => $logs,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => (int) $total,
            'pages'    => ceil($total / $perPage),
        ],
    ];
}

/**
 * Paginated WhatsApp Unofficial logs
 */
function getWALogs(PDO $db): array
{
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
    $status  = $_GET['status'] ?? '';
    $direction = $_GET['direction'] ?? '';
    $search  = $_GET['search'] ?? '';
    $offset  = ($page - 1) * $perPage;
    
    $where = '1=1';
    $params = [];
    
    if ($status) {
        $where .= ' AND w.status = :status';
        $params[':status'] = $status;
    }
    if ($direction) {
        $where .= ' AND w.direction = :direction';
        $params[':direction'] = $direction;
    }
    if ($search) {
        $where .= ' AND (w.phone LIKE :search OR w.message LIKE :search2 OR u.first_name LIKE :search3)';
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM whatsapp_unofficial_logs w LEFT JOIN users u ON w.patient_id = u.id WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    $stmt = $db->prepare("
        SELECT w.*, 
               CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS patient_name
        FROM whatsapp_unofficial_logs w
        LEFT JOIN users u ON w.patient_id = u.id
        WHERE $where
        ORDER BY w.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    return [
        'success'    => true,
        'data'       => $logs,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => (int) $total,
            'pages'    => ceil($total / $perPage),
        ],
    ];
}

/**
 * Queue status and recent items
 */
function getQueueStatus(PDO $db): array
{
    // Summary
    $summary = $db->query("
        SELECT 
            channel,
            status,
            COUNT(*) AS cnt
        FROM communication_queue
        GROUP BY channel, status
        ORDER BY channel, status
    ")->fetchAll();
    
    // Recent pending items
    $pending = $db->query("
        SELECT q.*, 
               CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS patient_name
        FROM communication_queue q
        LEFT JOIN users u ON q.patient_id = u.id
        WHERE q.status IN ('pending', 'processing')
        ORDER BY q.priority ASC, q.created_at ASC
        LIMIT 50
    ")->fetchAll();
    
    // Recent failed
    $failed = $db->query("
        SELECT q.*, 
               CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS patient_name
        FROM communication_queue q
        LEFT JOIN users u ON q.patient_id = u.id
        WHERE q.status = 'failed'
        ORDER BY q.updated_at DESC
        LIMIT 20
    ")->fetchAll();
    
    return [
        'success' => true,
        'data'    => [
            'summary' => $summary,
            'pending' => $pending,
            'failed'  => $failed,
        ],
    ];
}

/**
 * Communication trends (daily counts for last 30 days)
 */
function getCommTrends(PDO $db): array
{
    $days = min(90, max(7, (int) ($_GET['days'] ?? 30)));
    
    // SMS daily counts
    $sms = $db->prepare("
        SELECT DATE(created_at) as date,
               COUNT(*) as total,
               SUM(IF(status IN ('sent','delivered'), 1, 0)) as success,
               SUM(IF(status = 'failed', 1, 0)) as failed
        FROM sms_logs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $sms->execute([':days' => $days]);
    
    // Email daily counts
    $email = $db->prepare("
        SELECT DATE(created_at) as date,
               COUNT(*) as total,
               SUM(IF(status IN ('sent','delivered'), 1, 0)) as success,
               SUM(IF(status = 'failed', 1, 0)) as failed
        FROM email_logs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $email->execute([':days' => $days]);
    
    // WhatsApp daily counts
    $wa = $db->prepare("
        SELECT DATE(created_at) as date,
               COUNT(*) as total,
               SUM(IF(status IN ('sent','delivered','read'), 1, 0)) as success,
               SUM(IF(status = 'failed', 1, 0)) as failed
        FROM whatsapp_unofficial_logs
        WHERE direction = 'outgoing'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $wa->execute([':days' => $days]);
    
    return [
        'success' => true,
        'data'    => [
            'sms'      => $sms->fetchAll(),
            'email'    => $email->fetchAll(),
            'whatsapp' => $wa->fetchAll(),
            'days'     => $days,
        ],
    ];
}

/**
 * Handle test message sending from admin panel
 */
function handleSendTest(PDO $db): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'error' => 'POST method required'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $channel = $input['channel'] ?? '';
    $recipient = $input['recipient'] ?? '';
    $message = $input['message'] ?? 'Test message from ' . CLINIC_NAME;
    
    if (empty($channel) || empty($recipient)) {
        return ['success' => false, 'error' => 'Missing channel or recipient'];
    }
    
    switch ($channel) {
        case 'sms':
            require_once __DIR__ . '/../sms/sms_service.php';
            return sendSMS($recipient, $message, 'general');
        
        case 'email':
            require_once __DIR__ . '/../email/email_service.php';
            $subject = $input['subject'] ?? 'Test Email from ' . CLINIC_NAME;
            $html = "<div style='font-family:sans-serif;padding:20px;'><h2>Test Email</h2><p>$message</p><p style='color:#999;'>Sent from " . CLINIC_NAME . " Communication System</p></div>";
            return sendEmail($recipient, $subject, $html, 'general');
        
        case 'whatsapp':
            require_once __DIR__ . '/../whatsapp/wa_unofficial_client.php';
            return sendWhatsAppUnofficial($recipient, $message, 'general');
        
        default:
            return ['success' => false, 'error' => "Unknown channel: $channel"];
    }
}
