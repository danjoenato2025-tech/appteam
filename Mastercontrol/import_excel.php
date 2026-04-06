<?php
/**
 * import_excel.php  –  Excel Import Handler for Request Record
 *
 * POST  __action=preview_import   → parse file, return rows as JSON (no DB write)
 * POST  __action=confirm_import   → write parsed rows to DB, skip duplicates
 *
 * Requires PhpSpreadsheet:
 *   composer require phpoffice/phpspreadsheet
 *
 * Place this file alongside requestrecord.php  (e.g. /Mastercontrol/import_excel.php)
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

include('../dbconnection/config.php');
header('Content-Type: application/json');

// ── Helper: normalise a cell value to a Y-m-d string or null ──────────
function toDate($val): ?string {
    if ($val === null || $val === '') return null;
    if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
    if (is_numeric($val)) {
        // Excel serial date
        try {
            $d = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val);
            return $d->format('Y-m-d');
        } catch (\Throwable $e) {}
    }
    // Try any string date
    $ts = strtotime((string)$val);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

function trimVal($v): string {
    return trim((string)($v ?? ''));
}

// ── Load PhpSpreadsheet ───────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    // Try common locations
    $tries = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        '/var/www/html/vendor/autoload.php',
    ];
    foreach ($tries as $t) {
        if (file_exists($t)) { $autoload = $t; break; }
    }
}
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'message' => 'PhpSpreadsheet not found. Run: composer require phpoffice/phpspreadsheet']);
    exit;
}
require $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

// Detect action from either FormData POST or raw JSON body
$action = $_POST['__action'] ?? '';
$_jsonBody = null;
if ($action === '') {
    // confirm_import sends Content-Type: application/json — $_POST will be empty
    $raw = file_get_contents('php://input');
    if ($raw) {
        $_jsonBody = json_decode($raw, true);
        $action = $_jsonBody['__action'] ?? '';
    }
}

/* ══════════════════════════════════════════════════════════
   ACTION: preview_import
   Parses uploaded file, returns rows for user confirmation.
══════════════════════════════════════════════════════════ */
if ($action === 'preview_import') {
    if (empty($_FILES['excel_file']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
        exit;
    }

    $file     = $_FILES['excel_file']['tmp_name'];
    $origName = $_FILES['excel_file']['name'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xlsm', 'xls', 'csv'])) {
        echo json_encode(['success' => false, 'message' => 'Unsupported file type. Use .xlsx, .xlsm, or .xls']);
        exit;
    }

    try {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);

        // For xlsm we need to use Xlsx reader
        if ($ext === 'xlsm') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($file);

        // Detect which sheet(s) to import – let user pick or auto-detect year sheets
        $sheetNames = $spreadsheet->getSheetNames();
        $dataSheets = array_filter($sheetNames, fn($n) => preg_match('/^\d{4}$/', trim($n)));

        // If user specified a sheet via POST, use that; else use all year sheets
        $targetSheet = $_POST['sheet'] ?? null;
        if ($targetSheet && in_array($targetSheet, $sheetNames)) {
            $sheetsToRead = [$targetSheet];
        } elseif (!empty($dataSheets)) {
            $sheetsToRead = array_values($dataSheets);
        } else {
            // Fallback: first sheet
            $sheetsToRead = [$sheetNames[0]];
        }

        $rows = [];
        $errors = [];
        $rowNum = 0;

        foreach ($sheetsToRead as $sheetName) {
            $ws = $spreadsheet->getSheetByName($sheetName);
            if (!$ws) continue;

            $firstRow = true;
            foreach ($ws->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCellIterator('A', 'J') as $cell) {
                    $cells[] = $cell->getCalculatedValue();
                }

                // Skip header row
                $first = strtolower(trim((string)($cells[0] ?? '')));
                if ($firstRow || in_array($first, ['id','no','no+a1:h1','#'])) {
                    $firstRow = false;
                    continue;
                }

                // Skip empty rows
                $allEmpty = true;
                foreach ($cells as $c) { if ($c !== null && $c !== '') { $allEmpty = false; break; } }
                if ($allEmpty) continue;

                $rowNum++;

                // Map columns:
                // 0=ID, 1=Date Requested, 2=Date Received, 3=Request Number,
                // 4=Ext Number, 5=Section, 6=Information, 7=Reason, 8=Performed By, 9=Imp Date

                $dateReq  = toDate($cells[1] ?? null);
                $dateRec  = toDate($cells[2] ?? null);
                $reqNum   = trimVal($cells[3] ?? '');
                $extNum   = trimVal($cells[4] ?? '');
                $section  = trimVal($cells[5] ?? '');
                $info     = trimVal($cells[6] ?? '');
                $reason   = trimVal($cells[7] ?? '');
                $performed= trimVal($cells[8] ?? '');
                $impDate  = toDate($cells[9] ?? null);

                if (!$dateReq || !$reqNum) {
                    $errors[] = "Row {$rowNum} (sheet {$sheetName}): skipped – missing Date Requested or Request Number.";
                    continue;
                }

                $rows[] = [
                    'date_requested'  => $dateReq,
                    'date_received'   => $dateRec,
                    'request_number'  => $reqNum,
                    'ext_number'      => $extNum ?: null,
                    'request_section' => $section,
                    'information'     => $info    ?: null,
                    'reason'          => $reason  ?: null,
                    'performed'       => $performed ?: null,
                    'imp_date'        => $impDate,
                    '_sheet'          => $sheetName,
                ];
            }
        }

        // Check for existing request numbers in DB
        if (!empty($rows)) {
            $reqNums = array_column($rows, 'request_number');
            $placeholders = implode(',', array_fill(0, count($reqNums), '?'));
            $existStmt = $pdo->prepare("SELECT request_number FROM request_record WHERE request_number IN ({$placeholders})");
            $existStmt->execute($reqNums);
            $existing = $existStmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($rows as &$r) {
                $r['_exists'] = in_array($r['request_number'], $existing);
            }
            unset($r);
        }

        echo json_encode([
            'success'      => true,
            'rows'         => $rows,
            'total'        => count($rows),
            'sheets'       => $sheetNames,
            'data_sheets'  => array_values($dataSheets),
            'errors'       => $errors,
            'new_count'    => count(array_filter($rows, fn($r) => !$r['_exists'])),
            'dup_count'    => count(array_filter($rows, fn($r) => $r['_exists'])),
        ]);

    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Parse error: ' . $e->getMessage()]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════
   ACTION: confirm_import
   Receives JSON rows array, inserts new ones, skips duplicates.
══════════════════════════════════════════════════════════ */
if ($action === 'confirm_import') {
    // $_jsonBody was already parsed above when detecting the action
    $body = $_jsonBody ?? [];
    $rows = $body['rows'] ?? [];

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'No rows to import.']);
        exit;
    }

    $skip_dupes = (bool)($body['skip_dupes'] ?? true);

    $inserted = 0;
    $skipped  = 0;
    $failed   = 0;

    $stmt = $pdo->prepare('
        INSERT IGNORE INTO request_record
            (date_requested, date_received, request_number, ext_number,
             request_section, information, reason, performed, imp_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($rows as $r) {
        if ($skip_dupes && !empty($r['_exists'])) {
            $skipped++;
            continue;
        }
        try {
            $stmt->execute([
                $r['date_requested']  ?: null,
                $r['date_received']   ?: null,
                $r['request_number'],
                $r['ext_number']      ?: null,
                $r['request_section'] ?: null,
                $r['information']     ?: null,
                $r['reason']          ?: null,
                $r['performed']       ?: null,
                $r['imp_date']        ?: null,
            ]);
            if ($stmt->rowCount() > 0) $inserted++;
            else $skipped++;
        } catch (\Throwable $e) {
            $failed++;
        }
    }

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'failed'   => $failed,
        'message'  => "Import complete: {$inserted} added, {$skipped} skipped (duplicates), {$failed} failed.",
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
exit;