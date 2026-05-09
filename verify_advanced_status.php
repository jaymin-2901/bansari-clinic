<?php
require_once __DIR__ . '/backend-php/config/clinic_db.php';

try {
    $db = getClinicDB();
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $start = "$tomorrow 14:00:00";
    $end = "$tomorrow 16:00:00";
    
    // 1. Insert specific window test notice
    $db->prepare("INSERT INTO clinic_status (message, start_datetime, end_datetime, status, is_active) VALUES (?, ?, ?, ?, ?)")
       ->execute(['Afternoon maintenance window.', $start, $end, 'closed', 1]);
    $noticeId = $db->lastInsertId();
    echo "Inserted test notice ID: $noticeId for $tomorrow 2PM-4PM\n";
    
    // 2. Test status API for NOW (should be open)
    echo "--- Testing Status API (Current) ---\n";
    $status = getClinicStatus();
    echo "Current status: " . ($status['closed'] ? 'CLOSED' : 'OPEN') . "\n";
    
    // 3. Test Availability API for Tomorrow 3PM (should be closed)
    echo "--- Testing Availability Tomorrow 3PM ---\n";
    $slotStatus = getClinicStatus("$tomorrow 15:00:00");
    echo "3PM status: " . ($slotStatus['closed'] ? 'CLOSED - ' . $slotStatus['message'] : 'OPEN') . "\n";
    
    // 4. Test Availability API for Tomorrow 10AM (should be open)
    echo "--- Testing Availability Tomorrow 10AM ---\n";
    $slotStatusOpen = getClinicStatus("$tomorrow 10:00:00");
    echo "10AM status: " . ($slotStatusOpen['closed'] ? 'CLOSED' : 'OPEN') . "\n";
    
    // 5. Cleanup
    $db->prepare("DELETE FROM clinic_status WHERE id = ?")->execute([$noticeId]);
    echo "Deleted test notice.\n";
    
} catch (Exception $e) {
    echo "Test error: " . $e->getMessage();
}
?>
