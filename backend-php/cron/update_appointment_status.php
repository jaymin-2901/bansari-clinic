<?php
/**
 * ============================================================
 * Automatic Appointment Status Updater
 * File: backend-php/cron/update_appointment_status.php
 * ============================================================
 * 
 * This script automatically updates appointment status based on:
 * 
 * RULE 1: IF appointment_date < current_date
 *         AND patient_type = offline
 *         AND visited = true
 *         THEN status = Completed
 * 
 * RULE 2: IF patient_type = online
 *         AND consultation_finished = true
 *         THEN status = Completed
 * 
 * RULE 3: IF appointment_date < current_date
 *         AND status IN ('pending', 'confirmed')
 *         AND consultation_type = 'offline'
 *         THEN status = Completed (auto-complete for offline after date passes)
 * 
 * RULE 4: IF appointment_date < current_date
 *         AND status IN ('pending', 'confirmed')
 *         AND consultation_type = 'online'
 *         THEN status = Completed (auto-complete for online after date passes)
 * 
 * EXCEPTION: If appointment status = Cancelled, do NOT change.
 * 
 * Run via cron: php /path/to/update_appointment_status.php
 * Or set up a cron job: * * * * * php /path/to/update_appointment_status.php
 */

require_once __DIR__ . '/../config/clinic_db.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

$currentDateTime = date('Y-m-d H:i:s');
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

error_log("Starting automatic appointment status update at $currentDateTime");

try {
    $db = getClinicDB();
    
    // ============================================================
    // RULE 1 & 3: Offline appointments - mark as completed if date passed
    // ============================================================
    // For offline: if appointment date has passed and status is pending/confirmed,
    // mark as completed (assuming patient visited)
    $offlineStmt = $db->prepare("
        UPDATE appointments 
        SET status = 'completed', 
            updated_at = NOW() 
        WHERE status IN ('pending', 'confirmed')
        AND appointment_date < ?
        AND consultation_type = 'offline'
    ");
    $offlineStmt->execute([$currentDate]);
    $offlineUpdated = $offlineStmt->rowCount();
    
    error_log("Updated $offlineUpdated offline appointments to completed (date passed)");
    
    // ============================================================
    // RULE 2 & 4: Online appointments - mark as completed if date passed
    // ============================================================
    // For online: if appointment date has passed and status is pending/confirmed,
    // mark as completed
    $onlineStmt = $db->prepare("
        UPDATE appointments 
        SET status = 'completed', 
            updated_at = NOW() 
        WHERE status IN ('pending', 'confirmed')
        AND appointment_date < ?
        AND consultation_type = 'online'
    ");
    $onlineStmt->execute([$currentDate]);
    $onlineUpdated = $onlineStmt->rowCount();
    
    error_log("Updated $onlineUpdated online appointments to completed (date passed)");
    
    // ============================================================
    // RULE 1: Check for visited column (if exists) - Offline
    // ============================================================
    // Check if 'visited' column exists in the appointments table
    $columnCheck = $db->query("SHOW COLUMNS FROM appointments LIKE 'visited'");
    if ($columnCheck->rowCount() > 0) {
        // If visited column exists, use it for more precise status updates
        $visitedStmt = $db->prepare("
            UPDATE appointments 
            SET status = 'completed', 
                updated_at = NOW() 
            WHERE status IN ('pending', 'confirmed')
            AND appointment_date_time < ?
            AND consultation_type = 'offline'
            AND visited = 1
        ");
        $visitedStmt->execute([$currentDateTime]);
        $visitedUpdated = $visitedStmt->rowCount();
        
        error_log("Updated $visitedUpdated offline appointments based on visited flag");
    }
    
    // ============================================================
    // RULE 2: Check for consultation_finished column (if exists) - Online
    // ============================================================
    // Check if 'consultation_finished' column exists
    $columnCheck2 = $db->query("SHOW COLUMNS FROM appointments LIKE 'consultation_finished'");
    if ($columnCheck2->rowCount() > 0) {
        // If consultation_finished column exists, use it
        $finishedStmt = $db->prepare("
            UPDATE appointments 
            SET status = 'completed', 
                updated_at = NOW() 
            WHERE status IN ('pending', 'confirmed')
            AND consultation_type = 'online'
            AND consultation_finished = 1
        ");
        $finishedStmt->execute();
        $finishedUpdated = $finishedStmt->rowCount();
        
        error_log("Updated $finishedUpdated online appointments based on consultation_finished flag");
    }
    
    // ============================================================
    // SUMMARY
    // ============================================================
    $totalUpdated = $offlineUpdated + $onlineUpdated;
    
    echo "Automatic status update complete. Updated $totalUpdated appointments.\n";
    error_log("Automatic appointment status update completed. Total updated: $totalUpdated");
    
} catch (PDOException $e) {
    error_log("Error in automatic appointment status update: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

