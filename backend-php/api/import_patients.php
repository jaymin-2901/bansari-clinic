<?php
/**
 * ============================================================
 * MediConnect – Patient Import API
 * File: backend/api/import_patients.php
 * ============================================================
 * Handles:
 *  - CSV/XLSX file upload & validation
 *  - Row-by-row processing with duplicate detection
 *  - Sample template download
 *  - Error CSV generation
 *  - Import history listing
 * ============================================================
 */

// ── Strict Error Handling ──
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120); // allow up to 2 min for large imports

require_once __DIR__ . '/../security/bootstrap.php';
require_once __DIR__ . '/../../clinic-admin-php/includes/auth.php';

// ── Security: CORS + Rate Limiting + Admin Auth ──
SecurityBootstrap::adminEndpoint('import');

// ── Legacy Auth & CSRF (backward compatibility) ──
requireAdminAPI();
requireCSRF();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'upload':
            handleUpload();
            break;
        case 'sample_csv':
            downloadSampleCSV();
            break;
        case 'error_csv':
            downloadErrorCSV();
            break;
        case 'history':
            getImportHistory();
            break;
        case 'undo':
            undoLastImport();
            break;
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Throwable $e) {
    writeLog('import_errors.log', 'FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(false, 'Server error occurred. Please try again.');
}

// ══════════════════════════════════════════════════
// UPLOAD & PROCESS
// ══════════════════════════════════════════════════
function handleUpload(): void
{
    // ── Validate request ──
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'POST method required');
    }

    // ── File validation ──
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        $code = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        jsonResponse(false, $errorMsgs[$code] ?? 'File upload failed');
    }

    $file = $_FILES['import_file'];
    $duplicateAction = trim($_POST['duplicate_action'] ?? 'skip');
    $adminId = (int)($_POST['admin_id'] ?? 0);

    // ── Size check (5MB max) ──
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        jsonResponse(false, 'File too large. Maximum allowed: 5MB');
    }
    if ($file['size'] === 0) {
        jsonResponse(false, 'Uploaded file is empty');
    }

    // ── Extension check ──
    $originalName = basename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true)) {
        jsonResponse(false, 'Invalid file type. Only .csv and .xlsx are allowed.');
    }

    // ── MIME type validation ──
    $allowedMimes = [
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream'
        ],
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes[$ext], true)) {
        jsonResponse(false, 'File MIME type mismatch. Got: ' . $mimeType . '. Expected: ' . implode(', ', $allowedMimes[$ext]));
    }

    // ── Move to temp directory ──
    $tempDir = __DIR__ . '/../../uploads/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $tempFile = $tempDir . '/' . uniqid('import_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
        jsonResponse(false, 'Failed to save uploaded file');
    }

    try {
        // ── Parse file ──
        $rows = ($ext === 'csv') ? parseCSV($tempFile) : parseXLSX($tempFile);

        if (empty($rows)) {
            jsonResponse(false, 'File contains no data rows');
        }

        // ── Validate columns ──
        $requiredCols = ['full_name', 'email', 'mobile'];
        $optionalCols = ['gender', 'date_of_birth', 'address', 'medical_history', 'registration_date'];
        $allExpected = array_merge($requiredCols, $optionalCols);

        $headers = array_keys($rows[0]);
        $missing = array_diff($requiredCols, $headers);
        if (!empty($missing)) {
            jsonResponse(false, 'Missing required columns: ' . implode(', ', $missing) . '. Found: ' . implode(', ', $headers));
        }

        // ── Process rows ──
        $result = processRows($rows, $duplicateAction, $adminId);

        // ── Log import ──
        logImport($adminId, $originalName, $ext, $result);

        // ── Respond ──
        jsonResponse(true, 'Import completed', [
            'total'    => $result['total'],
            'inserted' => $result['inserted'],
            'updated'  => $result['updated'],
            'skipped'  => $result['skipped'],
            'failed'   => $result['failed'],
            'errors'   => $result['errors'],
        ]);
    } finally {
        // ── Always delete temp file ──
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
}

// ══════════════════════════════════════════════════
// CSV PARSER
// ══════════════════════════════════════════════════
function parseCSV(string $filePath): array
{
    $rows = [];
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new RuntimeException('Cannot open CSV file');
    }

    // Detect BOM and skip
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return [];
    }

    // Normalize: trim, lowercase, underscorize
    $headers = array_map(function ($h) {
        $h = trim($h);
        $h = strtolower(preg_replace('/\s+/', '_', $h));
        $h = preg_replace('/[^a-z0-9_]/', '', $h);
        return $h;
    }, $headers);

    $rowNum = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $rowNum++;
        // Skip completely empty rows
        $nonEmpty = array_filter($data, function ($v) { return trim($v ?? '') !== ''; });
        if (empty($nonEmpty)) {
            continue;
        }

        $row = [];
        for ($i = 0; $i < count($headers); $i++) {
            $row[$headers[$i]] = isset($data[$i]) ? trim($data[$i]) : '';
        }
        $row['_row_num'] = $rowNum;
        $rows[] = $row;
    }

    fclose($handle);
    return $rows;
}

// ══════════════════════════════════════════════════
// XLSX PARSER (PhpSpreadsheet)
// ══════════════════════════════════════════════════
function parseXLSX(string $filePath): array
{
    // Check if PhpSpreadsheet is available
    $autoloadPaths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
    ];
    $loaded = false;
    foreach ($autoloadPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }

    if (!$loaded || !class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        throw new RuntimeException(
            'PhpSpreadsheet library not installed. Run: composer require phpoffice/phpspreadsheet'
        );
    }

    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, true, true, false);

    if (empty($data) || count($data) < 2) {
        return [];
    }

    // First row = headers
    $rawHeaders = array_shift($data);
    $headers = array_map(function ($h) {
        $h = trim($h ?? '');
        $h = strtolower(preg_replace('/\s+/', '_', $h));
        $h = preg_replace('/[^a-z0-9_]/', '', $h);
        return $h;
    }, $rawHeaders);

    $rows = [];
    $rowNum = 1;
    foreach ($data as $rowData) {
        $rowNum++;
        // Skip empty rows
        $nonEmpty = array_filter($rowData, function ($v) { return trim($v ?? '') !== ''; });
        if (empty($nonEmpty)) {
            continue;
        }

        $row = [];
        for ($i = 0; $i < count($headers); $i++) {
            $row[$headers[$i]] = isset($rowData[$i]) ? trim($rowData[$i] ?? '') : '';
        }
        $row['_row_num'] = $rowNum;
        $rows[] = $row;
    }

    return $rows;
}

// ══════════════════════════════════════════════════
// ROW PROCESSING
// ══════════════════════════════════════════════════
function processRows(array $rows, string $duplicateAction, int $adminId): array
{
    $db = getDBConnection();

    $result = [
        'total'    => count($rows),
        'inserted' => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'failed'   => 0,
        'errors'   => [],
    ];

    // Prepared statements
    $stmtCheckEmail = $db->prepare(
        "SELECT id, email, phone FROM users WHERE email = ? AND role = 'patient' LIMIT 1"
    );
    $stmtCheckPhone = $db->prepare(
        "SELECT id, email, phone FROM users WHERE phone = ? AND role = 'patient' LIMIT 1"
    );
    $stmtInsert = $db->prepare(
        "INSERT INTO users (role, first_name, last_name, email, phone, password, gender, date_of_birth, status, created_at)
         VALUES ('patient', ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
    );
    $stmtUpdate = $db->prepare(
        "UPDATE users SET first_name = ?, last_name = ?, phone = ?, gender = ?, date_of_birth = ?
         WHERE id = ? AND role = 'patient'"
    );
    // For address
    $stmtCheckAddress = $db->prepare(
        "SELECT id FROM patient_address WHERE user_id = ? LIMIT 1"
    );
    $stmtInsertAddress = $db->prepare(
        "INSERT INTO patient_address (user_id, address) VALUES (?, ?)"
    );
    $stmtUpdateAddress = $db->prepare(
        "UPDATE patient_address SET address = ? WHERE user_id = ?"
    );
    // For medical history
    $stmtCheckMedical = $db->prepare(
        "SELECT id FROM medical_history WHERE user_id = ? LIMIT 1"
    );
    $stmtInsertMedical = $db->prepare(
        "INSERT INTO medical_history (user_id, allergies) VALUES (?, ?)"
    );
    $stmtUpdateMedical = $db->prepare(
        "UPDATE medical_history SET allergies = ? WHERE user_id = ?"
    );

    foreach ($rows as $row) {
        $rowNum = $row['_row_num'] ?? '?';
        $fullName = sanitizeInput($row['full_name'] ?? '');
        $email    = sanitizeInput($row['email'] ?? '');
        $mobile   = sanitizeInput($row['mobile'] ?? '');
        $gender   = sanitizeInput($row['gender'] ?? '');
        $dob      = sanitizeInput($row['date_of_birth'] ?? '');
        $address  = sanitizeInput($row['address'] ?? '');
        $medHistory = sanitizeInput($row['medical_history'] ?? '');
        $regDate  = sanitizeInput($row['registration_date'] ?? '');

        // ── Validate required fields ──
        $errors = [];
        if ($fullName === '') {
            $errors[] = 'full_name is required';
        }
        if ($email === '') {
            $errors[] = 'email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if ($mobile === '') {
            $errors[] = 'mobile is required';
        } else {
            // Clean mobile: strip spaces, dashes, country code prefixes
            $cleanMobile = preg_replace('/[\s\-\(\)\+]/', '', $mobile);
            // Remove leading 91 or 0
            if (strlen($cleanMobile) > 10 && substr($cleanMobile, 0, 2) === '91') {
                $cleanMobile = substr($cleanMobile, 2);
            }
            if (strlen($cleanMobile) > 10 && $cleanMobile[0] === '0') {
                $cleanMobile = substr($cleanMobile, 1);
            }
            if (!preg_match('/^\d{10}$/', $cleanMobile)) {
                $errors[] = 'Mobile must be 10 digits (got: ' . $mobile . ')';
            } else {
                $mobile = '+91' . $cleanMobile;
            }
        }

        // Validate date_of_birth format
        if ($dob !== '') {
            $dobParsed = date_create($dob);
            if ($dobParsed === false) {
                $errors[] = 'Invalid date_of_birth format';
                $dob = null;
            } else {
                $dob = $dobParsed->format('Y-m-d');
            }
        } else {
            $dob = null;
        }

        // Validate gender
        if ($gender !== '') {
            $gender = ucfirst(strtolower($gender));
            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                $errors[] = 'Gender must be Male, Female, or Other';
            }
        } else {
            $gender = null;
        }

        // Validate registration_date
        if ($regDate !== '') {
            $rdParsed = date_create($regDate);
            if ($rdParsed === false) {
                $regDate = date('Y-m-d H:i:s');
            } else {
                $regDate = $rdParsed->format('Y-m-d H:i:s');
            }
        } else {
            $regDate = date('Y-m-d H:i:s');
        }

        if (!empty($errors)) {
            $result['failed']++;
            $result['errors'][] = [
                'row'    => $rowNum,
                'data'   => compactRow($row),
                'reason' => implode('; ', $errors),
            ];
            continue;
        }

        // ── Split full_name into first + last ──
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        // ── Duplicate detection ──
        $existingId = null;
        $dupField = null;

        $stmtCheckEmail->execute([$email]);
        $existing = $stmtCheckEmail->fetch();
        if ($existing) {
            $existingId = (int)$existing['id'];
            $dupField = 'email';
        }

        if (!$existingId) {
            $stmtCheckPhone->execute([$mobile]);
            $existing = $stmtCheckPhone->fetch();
            if ($existing) {
                $existingId = (int)$existing['id'];
                $dupField = 'mobile';
            }
        }

        if ($existingId) {
            if ($duplicateAction === 'update') {
                // ── Update existing record ──
                try {
                    $stmtUpdate->execute([$firstName, $lastName, $mobile, $gender, $dob, $existingId]);

                    // Update address
                    if ($address !== '') {
                        $stmtCheckAddress->execute([$existingId]);
                        if ($stmtCheckAddress->fetch()) {
                            $stmtUpdateAddress->execute([$address, $existingId]);
                        } else {
                            $stmtInsertAddress->execute([$existingId, $address]);
                        }
                    }

                    // Update medical history
                    if ($medHistory !== '') {
                        $stmtCheckMedical->execute([$existingId]);
                        if ($stmtCheckMedical->fetch()) {
                            $stmtUpdateMedical->execute([$medHistory, $existingId]);
                        } else {
                            $stmtInsertMedical->execute([$existingId, $medHistory]);
                        }
                    }

                    $result['updated']++;
                } catch (PDOException $e) {
                    $result['failed']++;
                    $result['errors'][] = [
                        'row'    => $rowNum,
                        'data'   => compactRow($row),
                        'reason' => 'DB update error: ' . $e->getMessage(),
                    ];
                }
            } else {
                // ── Skip duplicate ──
                $result['skipped']++;
                $result['errors'][] = [
                    'row'    => $rowNum,
                    'data'   => compactRow($row),
                    'reason' => 'Duplicate ' . $dupField . ' (existing ID: ' . $existingId . ')',
                ];
            }
            continue;
        }

        // ── Insert new record ──
        try {
            // Generate a random password hash (patient can reset later)
            $randomPwd = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);

            $stmtInsert->execute([
                $firstName,
                $lastName,
                $email,
                $mobile,
                $randomPwd,
                $gender,
                $dob,
                $regDate,
            ]);

            $newId = (int)$db->lastInsertId();

            // Insert address if provided
            if ($address !== '') {
                $stmtInsertAddress->execute([$newId, $address]);
            }

            // Insert medical history if provided
            if ($medHistory !== '') {
                $stmtInsertMedical->execute([$newId, $medHistory]);
            }

            $result['inserted']++;
        } catch (PDOException $e) {
            $result['failed']++;
            $result['errors'][] = [
                'row'    => $rowNum,
                'data'   => compactRow($row),
                'reason' => 'DB insert error: ' . $e->getMessage(),
            ];
        }
    }

    return $result;
}

// ══════════════════════════════════════════════════
// SAMPLE CSV DOWNLOAD
// ══════════════════════════════════════════════════
function downloadSampleCSV(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="patient_import_template.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');

    // BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header
    fputcsv($output, [
        'full_name', 'email', 'mobile', 'gender',
        'date_of_birth', 'address', 'medical_history', 'registration_date'
    ]);

    // Sample rows
    $samples = [
        ['Rahul Sharma', 'rahul.sharma@example.com', '9876543210', 'Male', '1990-05-15', '123 MG Road, Ahmedabad', 'Diabetes Type 2', '2026-01-15'],
        ['Priya Patel', 'priya.patel@example.com', '9876543211', 'Female', '1985-08-22', '456 SG Highway, Surat', 'Asthma', '2026-01-20'],
        ['Amit Desai', 'amit.desai@example.com', '9876543212', 'Male', '1978-11-10', '789 Ring Road, Vadodara', 'Hypertension', ''],
    ];

    foreach ($samples as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// ══════════════════════════════════════════════════
// ERROR CSV DOWNLOAD
// ══════════════════════════════════════════════════
function downloadErrorCSV(): void
{
    $errorsJson = $_POST['errors'] ?? $_GET['errors'] ?? '[]';
    $errors = json_decode($errorsJson, true);

    if (!is_array($errors) || empty($errors)) {
        jsonResponse(false, 'No error data provided');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import_errors_' . date('Y-m-d_His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header
    fputcsv($output, ['Row #', 'Full Name', 'Email', 'Mobile', 'Reason']);

    foreach ($errors as $err) {
        $data = $err['data'] ?? [];
        fputcsv($output, [
            $err['row'] ?? '',
            $data['full_name'] ?? '',
            $data['email'] ?? '',
            $data['mobile'] ?? '',
            $err['reason'] ?? 'Unknown error',
        ]);
    }

    fclose($output);
    exit;
}

// ══════════════════════════════════════════════════
// IMPORT HISTORY
// ══════════════════════════════════════════════════
function getImportHistory(): void
{
    $db = getDBConnection();
    $stmt = $db->prepare(
        "SELECT id, admin_id, filename, file_type, total_rows, inserted, updated, skipped, failed, 
                duplicate_action, created_at
         FROM import_logs
         ORDER BY created_at DESC
         LIMIT 20"
    );
    $stmt->execute();
    $logs = $stmt->fetchAll();

    jsonResponse(true, 'Import history', ['logs' => $logs]);
}

// ══════════════════════════════════════════════════
// UNDO LAST IMPORT
// ══════════════════════════════════════════════════
function undoLastImport(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'POST required');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $importId = (int)($input['import_id'] ?? 0);

    if ($importId <= 0) {
        jsonResponse(false, 'Invalid import ID');
    }

    $db = getDBConnection();

    // Get import log
    $stmt = $db->prepare("SELECT * FROM import_logs WHERE id = ? LIMIT 1");
    $stmt->execute([$importId]);
    $log = $stmt->fetch();

    if (!$log) {
        jsonResponse(false, 'Import log not found');
    }

    // Can only undo imports less than 1 hour old
    $createdAt = strtotime($log['created_at']);
    if (time() - $createdAt > 3600) {
        jsonResponse(false, 'Can only undo imports within 1 hour');
    }

    // Delete users created at/after this import's timestamp (approximate)
    $stmt = $db->prepare(
        "DELETE FROM users WHERE role = 'patient' AND created_at >= ? 
         ORDER BY id DESC LIMIT ?"
    );
    $stmt->execute([$log['created_at'], (int)$log['inserted']]);
    $deleted = $stmt->rowCount();

    // Mark import log as undone
    $stmt = $db->prepare("DELETE FROM import_logs WHERE id = ?");
    $stmt->execute([$importId]);

    writeLog('import_actions.log', "UNDO import #{$importId}: deleted {$deleted} records");

    jsonResponse(true, "Undo complete. {$deleted} records removed.");
}

// ══════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════
function sanitizeInput(string $value): string
{
    $value = trim($value);
    $value = strip_tags($value);
    // Remove null bytes
    $value = str_replace("\0", '', $value);
    return $value;
}

function compactRow(array $row): array
{
    unset($row['_row_num']);
    return $row;
}

function logImport(int $adminId, string $filename, string $fileType, array $result): void
{
    try {
        $db = getDBConnection();
        $stmt = $db->prepare(
            "INSERT INTO import_logs (admin_id, filename, file_type, total_rows, inserted, updated, skipped, failed, duplicate_action, error_details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $adminId,
            $filename,
            $fileType,
            $result['total'],
            $result['inserted'],
            $result['updated'],
            $result['skipped'],
            $result['failed'],
            $_POST['duplicate_action'] ?? 'skip',
            json_encode($result['errors']),
        ]);
    } catch (PDOException $e) {
        writeLog('import_errors.log', 'Failed to log import: ' . $e->getMessage());
    }
}

function jsonResponse(bool $success, string $message, array $data = []): void
{
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
