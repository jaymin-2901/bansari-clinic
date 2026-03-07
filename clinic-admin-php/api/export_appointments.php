<?php
/**
 * ============================================================
 * MediConnect - Appointment Export to Excel
 * File: clinic-admin/api/export_appointments.php
 * ============================================================
 * Export filtered appointment data to Excel format
 * Requires: composer require phpoffice/phpspreadsheet
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

requireAdmin();

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ─── Validate filters ───
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? null;
$consultationType = $_GET['type'] ?? null;

try {
    $db = getClinicDB();

    // Build query with safe prepared statements
    $query = "
        SELECT 
            a.id,
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
            a.created_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.appointment_date BETWEEN ? AND ?
    ";
    
    $params = [$dateFrom, $dateTo];

    if ($status) {
        $query .= " AND a.status = ?";
        $params[] = $status;
    }

    if ($consultationType) {
        $query .= " AND a.consultation_type = ?";
        $params[] = $consultationType;
    }

    $query .= " ORDER BY a.appointment_date DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    if (empty($appointments)) {
        echo json_encode(['success' => false, 'message' => 'No appointments found']);
        exit;
    }

    // ─── Create Excel Spreadsheet ───
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Appointments');

    // ─── Set Column Widths ───
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(8);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(12);
    $sheet->getColumnDimension('J')->setWidth(15);
    $sheet->getColumnDimension('K')->setWidth(12);
    $sheet->getColumnDimension('L')->setWidth(12);
    $sheet->getColumnDimension('M')->setWidth(15);

    // ─── Header Row ───
    $headers = [
        'ID',
        'Patient Name',
        'Mobile',
        'Age',
        'Gender',
        'Appointment Date',
        'Time',
        'Type',
        'Form',
        'Status',
        'Confirmation',
        'Reminder Sent',
        'Booked On'
    ];

    $sheet->fromArray($headers, NULL, 'A1');

    // Style header row
    $headerStyle = $sheet->getStyle('A1:M1');
    $headerStyle->getFont()->setBold(true);
    $headerStyle->getFont()->setColor(new Color(Color::COLOR_WHITE));
    $headerStyle->getFill()->setFillType(Fill::FILL_SOLID);
    $headerStyle->getFill()->getStartColor()->setARGB('FF1F4E78');
    $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    // ─── Data Rows ───
    $row = 2;
    $totalCost = 0;
    
    foreach ($appointments as $appointment) {
        $sheet->setCellValue('A' . $row, $appointment['id']);
        $sheet->setCellValue('B' . $row, $appointment['full_name']);
        $sheet->setCellValue('C' . $row, $appointment['mobile']);
        $sheet->setCellValue('D' . $row, $appointment['age'] ?? '-');
        $sheet->setCellValue('E' . $row, ucfirst($appointment['gender'] ?? '-'));
        $sheet->setCellValue('F' . $row, $appointment['appointment_date']);
        $sheet->setCellValue('G' . $row, $appointment['appointment_time'] ?? '-');
        $sheet->setCellValue('H' . $row, ucfirst($appointment['consultation_type']));
        $sheet->setCellValue('I' . $row, ucfirst($appointment['form_type']));
        $sheet->setCellValue('J' . $row, ucfirst($appointment['status']));
        $sheet->setCellValue('K' . $row, ucfirst($appointment['confirmation_status'] ?? '-'));
        $sheet->setCellValue('L' . $row, $appointment['reminder_sent'] ? 'Yes' : 'No');
        $sheet->setCellValue('M' . $row, substr($appointment['created_at'], 0, 10));

        // Alternate row colors for readability
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFF2F2F2');
        }

        // Center align columns
        $sheet->getStyle('A' . $row . ':M' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
    }

    // ─── Add Summary Sheet ───
    $summarySheet = $spreadsheet->createSheet();
    $summarySheet->setTitle('Summary');

    $summaryData = [
        ['Appointment Export Summary'],
        [],
        ['Export Date', date('Y-m-d H:i:s')],
        ['Date Range', $dateFrom . ' to ' . $dateTo],
        ['Total Appointments', count($appointments)],
        [],
        ['Status Breakdown'],
    ];

    // Count by status
    $statusCounts = [];
    foreach ($appointments as $apt) {
        $status = $apt['status'];
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    foreach ($statusCounts as $st => $count) {
        $summaryData[] = [ucfirst($st), $count];
    }

    $summaryData[] = [];
    $summaryData[] = ['Consultation Type Breakdown'];

    // Count by type
    $typeCounts = [];
    foreach ($appointments as $apt) {
        $type = $apt['consultation_type'];
        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
    }

    foreach ($typeCounts as $type => $count) {
        $summaryData[] = [ucfirst($type), $count];
    }

    $summarySheet->fromArray($summaryData, NULL, 'A1');

    // Style summary sheet
    $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $summarySheet->getColumnDimension('A')->setWidth(25);
    $summarySheet->getColumnDimension('B')->setWidth(15);

    // ─── Set filename and send ───
    $filename = 'appointments-' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');

    // Log export
    $stmt = $db->prepare("
        UPDATE appointments 
        SET exported_at = NOW() 
        WHERE appointment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$dateFrom, $dateTo]);

    exit;

} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to export data']);
}
