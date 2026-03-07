<?php
/**
 * Bansari Homeopathy – Slot API (Hardcoded Timings + Auto Patient Type Detection)
 *
 * GET  /slots.php?action=closed_days                       → list of closed day indices
 * GET  /slots.php?action=available_slots&date=YYYY-MM-DD   → available time slots
 *      Optional: &patient_id=123                           → auto-detect new/old patient
 *
 * Clinic hours (fixed):
 *   Morning: 9:30 AM – 1:00 PM
 *   Evening: 5:00 PM – 8:00 PM
 *   Sunday:  Closed
 */
require_once __DIR__ . '/../../config/clinic_config.php';
require_once __DIR__ . '/../../config/clinic_db.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$action = $_GET['action'] ?? '';

try {
    $db = getClinicDB();

    // ──────────────────────────────────────────────
    //  1. Return closed day indices for date picker
    // ──────────────────────────────────────────────
    if ($action === 'closed_days') {
        jsonResponse([
            'success'     => true,
            'closed_days' => [CLINIC_CLOSED_DAY], // [0] = Sunday
        ]);
    }

    // ──────────────────────────────────────────────
    //  2. Generate available slots for a date
    // ──────────────────────────────────────────────
    if ($action === 'available_slots') {
        $date      = $_GET['date'] ?? '';
        $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            jsonResponse(['error' => 'Valid date (YYYY-MM-DD) is required'], 400);
        }

        // Reject past dates
        $today = date('Y-m-d');
        if ($date < $today) {
            jsonResponse(['error' => 'Cannot book appointments for past dates'], 400);
        }

        // Check if clinic is closed (Sunday)
        $dow = (int)date('w', strtotime($date));
        if ($dow === CLINIC_CLOSED_DAY) {
            jsonResponse([
                'success' => true,
                'slots'   => [],
                'message' => 'Clinic is closed on Sunday.',
                'is_open' => false,
            ]);
        }

        // ── Auto-detect patient type ──
        $patientType = 'new'; // default
        if ($patientId > 0) {
            $prevStmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status != 'cancelled'");
            $prevStmt->execute([$patientId]);
            $prevCount = (int)$prevStmt->fetchColumn();
            $patientType = ($prevCount > 0) ? 'old' : 'new';
        }

        $duration = ($patientType === 'old') ? OLD_PATIENT_DURATION : NEW_PATIENT_DURATION;
        $durationSec = $duration * 60;

        // Current time threshold (if booking today, exclude past slots with 30-min buffer)
        $isToday = ($date === $today);
        $nowSec  = $isToday ? (time() + 1800) : 0;

        // Get already-booked times for this date
        $bookedStmt = $db->prepare("
            SELECT appointment_time FROM appointments
            WHERE appointment_date = ? AND status != 'cancelled'
        ");
        $bookedStmt->execute([$date]);
        $bookedTimes = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);
        $bookedSet = [];
        foreach ($bookedTimes as $bt) {
            $bookedSet[] = date('H:i', strtotime($bt));
        }

        // ── Generate slots for both sessions ──
        $sessions = [
            ['open' => CLINIC_MORNING_OPEN, 'close' => CLINIC_MORNING_CLOSE, 'label' => 'Morning'],
            ['open' => CLINIC_EVENING_OPEN, 'close' => CLINIC_EVENING_CLOSE, 'label' => 'Evening'],
        ];

        $slots = [];
        foreach ($sessions as $session) {
            $openSec  = strtotime($session['open']);
            $closeSec = strtotime($session['close']);
            $current  = $openSec;

            while ($current + $durationSec <= $closeSec) {
                $timeStr = date('H:i', $current);
                $isBooked = in_array($timeStr, $bookedSet);
                $isPast   = ($isToday && $current < $nowSec);

                $slots[] = [
                    'time'      => $timeStr,
                    'display'   => date('g:i A', $current),
                    'available' => !$isBooked && !$isPast,
                    'booked'    => $isBooked,
                    'past'      => $isPast,
                    'session'   => $session['label'],
                ];

                $current += $durationSec;
            }
        }

        jsonResponse([
            'success'      => true,
            'date'         => $date,
            'patient_type' => $patientType,
            'duration'     => $duration,
            'is_open'      => true,
            'schedule'     => [
                'morning' => CLINIC_MORNING_OPEN . ' – ' . CLINIC_MORNING_CLOSE,
                'evening' => CLINIC_EVENING_OPEN . ' – ' . CLINIC_EVENING_CLOSE,
            ],
            'slots'        => $slots,
            'total_slots'  => count($slots),
            'available'    => count(array_filter($slots, fn($s) => $s['available'])),
        ]);
    }

    // Unknown action
    jsonResponse(['error' => 'Invalid action. Use: closed_days, available_slots'], 400);

} catch (PDOException $e) {
    error_log("Slots API error: " . $e->getMessage());
    jsonResponse(['error' => 'Server error'], 500);
}
