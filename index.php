<?php
/**
 * index.php – Unified Report Dashboard
 * Pulls live data from: Master Control, QAD, LASYS, Sato Printer
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include('dbconnection/config.php');

$sessionUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'full_name' => $_SESSION['full_name'],
    'role' => $_SESSION['role'],
    'email' => $_SESSION['email'],
    'color' => $_SESSION['color'],
];
$isAdmin = $sessionUser['role'] === 'admin';
$initials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', $sessionUser['full_name']), 0, 2)
));

$today = date('Y-m-d');
$thisMonth = date('Y-m');
$thisYear = date('Y');

/* ════ MASTER CONTROL ════ */
$mc_cancellation = (int) $pdo->query("SELECT COUNT(*) FROM cancellation_record")->fetchColumn();
$mc_newreg = (int) $pdo->query("SELECT COUNT(*) FROM newreguser_record")->fetchColumn();
$mc_password = (int) $pdo->query("SELECT COUNT(*) FROM passwordreset_record")->fetchColumn();
$mc_request = (int) $pdo->query("SELECT COUNT(*) FROM request_record")->fetchColumn();
$mc_incident = (int) $pdo->query("SELECT COUNT(*) FROM incident_record")->fetchColumn();
$mc_total = $mc_cancellation + $mc_newreg + $mc_password + $mc_request + $mc_incident;

$mc_month = 0;
foreach (['cancellation_record', 'newreguser_record', 'passwordreset_record', 'request_record', 'incident_record'] as $tbl) {
    $mc_month += (int) $pdo->query("SELECT COUNT(*) FROM $tbl WHERE DATE_FORMAT(date_requested,'%Y-%m')='$thisMonth'")->fetchColumn();
}

// 6-month trend
$mc_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $cnt = 0;
    foreach (['cancellation_record', 'newreguser_record', 'passwordreset_record', 'request_record', 'incident_record'] as $tbl) {
        $cnt += (int) $pdo->query("SELECT COUNT(*) FROM $tbl WHERE DATE_FORMAT(date_requested,'%Y-%m')='$m'")->fetchColumn();
    }
    $mc_trend[] = ['month' => date('M', strtotime("-$i months")), 'count' => $cnt];
}

$mc_recent = $pdo->query("
  (SELECT date_requested, CONVERT('Cancellation' USING utf8mb4) AS type, CONVERT(request_number USING utf8mb4) AS request_number, CONVERT(performed USING utf8mb4) AS performed FROM cancellation_record ORDER BY record_id DESC LIMIT 5)
  UNION ALL
  (SELECT date_requested, CONVERT('New Reg User' USING utf8mb4), CONVERT(request_number USING utf8mb4), CONVERT(performed USING utf8mb4) FROM newreguser_record ORDER BY record_id DESC LIMIT 5)
  UNION ALL
  (SELECT date_requested, CONVERT('Password Reset' USING utf8mb4), CONVERT(request_number USING utf8mb4), CONVERT(performed USING utf8mb4) FROM passwordreset_record ORDER BY record_id DESC LIMIT 5)
  UNION ALL
  (SELECT date_requested, CONVERT('Request Record' USING utf8mb4), CONVERT(request_number USING utf8mb4), CONVERT(performed USING utf8mb4) FROM request_record ORDER BY record_id DESC LIMIT 5)
  UNION ALL
  (SELECT date_requested, CONVERT('Incident Report' USING utf8mb4), CONVERT(request_number USING utf8mb4), NULL FROM incident_record ORDER BY record_id DESC LIMIT 5)
  ORDER BY date_requested DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

/* ════ QAD ════ */
$qad_total = (int) $pdo->query("SELECT COUNT(*) FROM qad_request")->fetchColumn();
$qad_month = (int) $pdo->query("SELECT COUNT(*) FROM qad_request WHERE DATE_FORMAT(date_requested,'%Y-%m')='$thisMonth'")->fetchColumn();
$qad_done = (int) $pdo->query("SELECT COUNT(*) FROM qad_request WHERE accomplished_by IS NOT NULL AND accomplished_by <> ''")->fetchColumn();
$qad_pending = $qad_total - $qad_done;

$qad_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $qad_trend[] = [
        'month' => date('M', strtotime("-$i months")),
        'count' => (int) $pdo->query("SELECT COUNT(*) FROM qad_request WHERE DATE_FORMAT(date_requested,'%Y-%m')='$m'")->fetchColumn()
    ];
}
$qad_cats = $pdo->query("SELECT request_category, COUNT(*) AS cnt FROM qad_request GROUP BY request_category ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$qad_recent = $pdo->query("SELECT date_requested, request_number, requestor, request_category, accomplished_by FROM qad_request ORDER BY record_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

/* ════ LASYS ════ */
$la_total = (int) $pdo->query("SELECT COUNT(*) FROM la_request")->fetchColumn();
$la_month = (int) $pdo->query("SELECT COUNT(*) FROM la_request WHERE DATE_FORMAT(date_requested,'%Y-%m')='$thisMonth'")->fetchColumn();
$la_done = (int) $pdo->query("SELECT COUNT(*) FROM la_request WHERE accomplished_by IS NOT NULL AND accomplished_by <> ''")->fetchColumn();
$la_pending = $la_total - $la_done;

$la_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $la_trend[] = [
        'month' => date('M', strtotime("-$i months")),
        'count' => (int) $pdo->query("SELECT COUNT(*) FROM la_request WHERE DATE_FORMAT(date_requested,'%Y-%m')='$m'")->fetchColumn()
    ];
}
$la_cats = $pdo->query("SELECT request_category, COUNT(*) AS cnt FROM la_request GROUP BY request_category ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$la_recent = $pdo->query("SELECT date_requested, request_number, requestor, request_category, accomplished_by FROM la_request ORDER BY record_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

/* ════ SATO PRINTER ════ */
$sato_total = (int) $pdo->query("SELECT COUNT(*) FROM sato_printers")->fetchColumn();
$sato_active = (int) $pdo->query("SELECT COUNT(*) FROM sato_printers WHERE status='active'")->fetchColumn();
$sato_inactive = (int) $pdo->query("SELECT COUNT(*) FROM sato_printers WHERE status='inactive'")->fetchColumn();
$sato_hist_total = (int) $pdo->query("SELECT COUNT(*) FROM sato_printer_history")->fetchColumn();
$sato_hist_month = (int) $pdo->query("SELECT COUNT(*) FROM sato_printer_history WHERE DATE_FORMAT(date,'%Y-%m')='$thisMonth'")->fetchColumn();

$sato_issues = $pdo->query("SELECT issue_problem, COUNT(*) AS cnt FROM sato_printer_history GROUP BY issue_problem ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$sato_recent = $pdo->query("SELECT date, printer_name, issue_problem, action_taken, remarks FROM sato_printer_history ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$sato_printers_stat = $pdo->query("
    SELECT p.printer_name, p.section_name, p.status, COUNT(h.id) AS issue_count
    FROM sato_printers p LEFT JOIN sato_printer_history h
    ON CONVERT(h.printer_name USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(p.printer_name USING utf8mb4) COLLATE utf8mb4_general_ci
    GROUP BY p.id ORDER BY issue_count DESC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

/* ════ TOTALS ════ */
$grand_total = $mc_total + $qad_total + $la_total;
$grand_month = $mc_month + $qad_month + $la_month;
$total_pending = $qad_pending + $la_pending;
$total_done = $qad_done + $la_done;

$combined_trend = [];
for ($i = 0; $i < 6; $i++) {
    $combined_trend[] = [
        'month' => $mc_trend[$i]['month'],
        'mc' => $mc_trend[$i]['count'],
        'qad' => $qad_trend[$i]['count'],
        'la' => $la_trend[$i]['count'],
    ];
}

/* ════ MONTHLY PER-SYSTEM (12 months, current year) ════ */
$chartYear = isset($_GET['chart_year']) ? (int) $_GET['chart_year'] : (int) $thisYear;
$availableYears = [];
foreach (['cancellation_record', 'newreguser_record', 'passwordreset_record', 'request_record', 'incident_record', 'qad_request', 'la_request', 'sato_printer_history'] as $_t) {
    try {
        $col = in_array($_t, ['sato_printer_history']) ? 'date' : 'date_requested';
        $yrs = $pdo->query("SELECT DISTINCT YEAR($col) AS y FROM $_t WHERE $col IS NOT NULL ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($yrs as $y) {
            if ($y)
                $availableYears[(int) $y] = true;
        }
    } catch (Exception $e) {
    }
}
krsort($availableYears);
$availableYears = array_keys($availableYears);
if (empty($availableYears))
    $availableYears = [(int) $thisYear];
if (!in_array($chartYear, $availableYears))
    $chartYear = $availableYears[0];

$months12 = [];
$mc12 = [];
$qad12 = [];
$la12 = [];
$sato12 = [];
for ($m = 1; $m <= 12; $m++) {
    $ym = sprintf('%04d-%02d', $chartYear, $m);
    $months12[] = date('M', mktime(0, 0, 0, $m, 1, $chartYear));
    // Master Control
    $mcc = 0;
    foreach (['cancellation_record', 'newreguser_record', 'passwordreset_record', 'request_record', 'incident_record'] as $tbl) {
        $mcc += (int) $pdo->query("SELECT COUNT(*) FROM $tbl WHERE DATE_FORMAT(date_requested,'%Y-%m')='$ym'")->fetchColumn();
    }
    $mc12[] = $mcc;
    $qad12[] = (int) $pdo->query("SELECT COUNT(*) FROM qad_request WHERE DATE_FORMAT(date_requested,'%Y-%m')='$ym'")->fetchColumn();
    $la12[] = (int) $pdo->query("SELECT COUNT(*) FROM la_request WHERE DATE_FORMAT(date_requested,'%Y-%m')='$ym'")->fetchColumn();
    try {
        $sato12[] = (int) $pdo->query("SELECT COUNT(*) FROM sato_printer_history WHERE DATE_FORMAT(date,'%Y-%m')='$ym'")->fetchColumn();
    } catch (Exception $e) {
        $sato12[] = 0;
    }
}

$monthlyJson = json_encode(['months' => $months12, 'mc' => $mc12, 'qad' => $qad12, 'la' => $la12, 'sato' => $sato12]);
$trendJson = json_encode($combined_trend);
$qadCatsJson = json_encode($qad_cats);
$laCatsJson = json_encode($la_cats);
$availYearsJson = json_encode($availableYears);
$chartYearJson = (int) $chartYear;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – AM Team</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        .module-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .mod-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            border-top: 4px solid var(--acc, #696cff);
            position: relative;
            overflow: hidden;
        }

        .mod-card::after {
            content: '';
            position: absolute;
            right: -14px;
            bottom: -14px;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--acc, #696cff);
            opacity: .07;
        }

        .mod-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
            background: var(--acc, #696cff);
            margin-bottom: 12px;
        }

        .mod-card-label {
            font-size: 12px;
            font-weight: 600;
            color: #a0a8b5;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .mod-card-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3a4a;
            line-height: 1.1;
            margin: 4px 0;
        }

        .mod-card-sub {
            font-size: 12px;
            color: #6e7a8a;
        }

        .badge-up {
            color: #28c76f;
            font-weight: 700;
        }

        .badge-pend {
            color: #ff9f43;
            font-weight: 700;
        }

        .dash-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .dash-3-2 {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .panel {
            background: #fff;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
        }

        .panel-title {
            font-size: 14px;
            font-weight: 700;
            color: #2d3a4a;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-title .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .mini-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .mini-stat {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .mini-stat-val {
            font-size: 20px;
            font-weight: 700;
            color: #2d3a4a;
        }

        .mini-stat-lbl {
            font-size: 11px;
            color: #a0a8b5;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .act-table {
            width: 100%;
            border-collapse: collapse;
        }

        .act-table th {
            font-size: 11px;
            font-weight: 700;
            color: #a0a8b5;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 6px 10px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f1f5;
        }

        .act-table td {
            font-size: 13px;
            color: #4a5568;
            padding: 9px 10px;
            border-bottom: 1px solid #f8f9fa;
        }

        .act-table tr:last-child td {
            border-bottom: none;
        }

        .act-table tr:hover td {
            background: #f8f9ff;
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .type-mc {
            background: #ede7f6;
            color: #7e57c2;
        }

        .type-qad {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-la {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .type-sato {
            background: #fff3e0;
            color: #e65100;
        }

        .pill-done {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .pill-pend {
            background: #fff8ec;
            color: #e65100;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .pill-ok {
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .pill-off {
            background: #f5f5f5;
            color: #757575;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .mc-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .mc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mc-row-left {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #4a5568;
        }

        .mc-row-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .mc-bar-wrap {
            flex: 1;
            height: 5px;
            background: #f0f1f5;
            border-radius: 3px;
            margin: 0 14px;
        }

        .mc-bar {
            height: 5px;
            border-radius: 3px;
        }

        .mc-cnt {
            font-size: 13px;
            font-weight: 700;
            color: #2d3a4a;
            min-width: 30px;
            text-align: right;
        }

        .printer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .printer-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 11px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .printer-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .printer-name {
            font-size: 12.5px;
            font-weight: 600;
            color: #2d3a4a;
        }

        .printer-sec {
            font-size: 11px;
            color: #a0a8b5;
        }

        .printer-cnt {
            margin-left: auto;
            font-size: 11.5px;
            font-weight: 700;
            color: #696cff;
            background: #ede7ff;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .sec-link {
            font-size: 12.5px;
            color: #696cff;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sec-link:hover {
            text-decoration: underline;
        }

        .grand-strip {
            background: linear-gradient(135deg, #696cff 0%, #9b59f5 100%);
            border-radius: 14px;
            padding: 20px 26px;
            display: flex;
            align-items: center;
            gap: 32px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .gs-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .gs-val {
            font-size: 26px;
            font-weight: 700;
            color: #fff;
        }

        .gs-lbl {
            font-size: 11px;
            color: rgba(255, 255, 255, .75);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .gs-divider {
            width: 1px;
            height: 40px;
            background: rgba(255, 255, 255, .25);
        }

        .dash-tabs {
            display: flex;
            gap: 4px;
            background: #f0f1f5;
            padding: 4px;
            border-radius: 10px;
            width: fit-content;
        }

        .dash-tab {
            padding: 7px 16px;
            border-radius: 7px;
            font-size: 12.5px;
            font-weight: 600;
            color: #6e7a8a;
            cursor: pointer;
            border: none;
            background: transparent;
            transition: all .15s;
        }

        .dash-tab.active {
            background: #fff;
            color: #696cff;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .1);
        }

        .btn-badge {
            background: #696cff;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        @media(max-width:900px) {
            .module-grid {
                grid-template-columns: 1fr 1fr;
            }

            .dash-2col,
            .dash-3-2 {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:560px) {
            .module-grid {
                grid-template-columns: 1fr;
            }

            .grand-strip {
                flex-direction: column;
                gap: 14px;
            }
        }

        /* Per-system chart */
        .sys-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid var(--sc, #696cff);
            color: var(--sc, #696cff);
            background: transparent;
            cursor: pointer;
            transition: all .15s;
        }

        .sys-btn.active {
            background: var(--sc, #696cff);
            color: #fff;
        }

        .sys-btn .sys-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .monthly-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .monthly-table th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #a0a8b5;
            padding: 7px 12px 10px;
            border-bottom: 2px solid #f0f1f5;
            text-align: center;
        }

        .monthly-table th:first-child {
            text-align: left;
        }

        .monthly-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f8f9fa;
            text-align: center;
            font-weight: 600;
            color: #4a5568;
        }

        .monthly-table td:first-child {
            text-align: left;
            font-weight: 700;
            color: #2d3a4a;
        }

        .monthly-table tfoot td {
            padding: 9px 12px;
            font-weight: 700;
            background: #f8f9ff;
            border-top: 2px solid #e8eaf0;
            color: #2d3a4a;
        }

        .monthly-table tr:hover td {
            background: #f8f9ff;
        }

        .monthly-table .cur-month td {
            background: #f0f0ff;
        }

        .monthly-table .zero {
            color: #d0d4dc !important;
            font-weight: 400 !important;
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <a class="sidebar-logo" href="#">
            <div class="logo-icon">AM</div><span class="logo-text">TEAM</span>
        </a>
        <nav>
            <ul>
                <li class="nav-section-label">Main</li>
                <li class="nav-item"><a class="nav-link active" href="index.php"><span class="nav-icon"><i
                                class="fa-solid fa-house"></i></span><span class="nav-text">Dashboard</span></a></li>
                <li class="nav-item nav-accordion" id="masterControl">
                    <a class="nav-link" href="#" onclick="toggleAcc('masterControl');return false;"><span
                            class="nav-icon"><i class="fa-solid fa-sliders"></i></span><span class="nav-text">Master
                            Control</span><i class="fa fa-chevron-right nav-chevron"></i></a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="Mastercontrol/cancellation.php">Change and Cancellation</a>
                        </li>
                        <li><a class="nav-sub-link" href="Mastercontrol/newreguser.php">New User Registration</a></li>
                        <li><a class="nav-sub-link" href="Mastercontrol/passwordreset.php">Password Request</a></li>
                        <li><a class="nav-sub-link" href="Mastercontrol/requestrecord.php">Request Record</a></li>
                        <li><a class="nav-sub-link" href="Mastercontrol/incident.php">Incident Report</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="QADControl">
                    <a class="nav-link" href="#" onclick="toggleAcc('QADControl');return false;"><span
                            class="nav-icon"><i class="fa-solid fa-flask"></i></span><span class="nav-text">Queen's
                            Annes Drive</span><i class="fa fa-chevron-right nav-chevron"></i></a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="QAD/qad_request.php">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="Lasys">
                    <a class="nav-link" href="#" onclick="toggleAcc('Lasys');return false;"><span class="nav-icon"><i
                                class="fa-solid fa-tag"></i></span><span class="nav-text">Label Assurance
                            System</span><i class="fa fa-chevron-right nav-chevron"></i></a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="LASYS/la_request.php">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="printerAcc">
                    <a class="nav-link" href="#" onclick="toggleAcc('printerAcc');return false;"><span
                            class="nav-icon"><i class="fa-solid fa-print"></i></span><span class="nav-text">Sato
                            Printer</span><i class="fa fa-chevron-right nav-chevron"></i></a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="SATO/sato_request.php">List of Printer</a></li>
                        <li><a class="nav-sub-link" href="SATO/printerhistory.php">Printer History</a></li>
                    </ul>
                </li>
                <div class="nav-divider"></div>
                <li class="nav-section-label">Apps &amp; Pages</li>
                <li class="nav-item nav-accordion" id="userAcc">
                    <a class="nav-link" href="#" onclick="toggleAcc('userAcc');return false;"><span class="nav-icon"><i
                                class="fa-solid fa-users"></i></span><span class="nav-text">Users</span><i
                            class="fa fa-chevron-right nav-chevron"></i></a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="Account/tbl_userlist.php">List</a></li>
                        <li><a class="nav-sub-link" href="Account/user_account.php">Account Settings</a></li>
                        <li><a class="nav-sub-link" href="Account/pending.php">Pending Approvals</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="page-wrapper">
        <header class="topbar">
            <div class="topbar-search"><i class="fa fa-search"
                    style="color:var(--text-light);font-size:13px;"></i><input type="text"
                    placeholder="Search (CTRL + K)"></div>
            <div class="topbar-spacer"></div>
            <div style="display:flex;align-items:center;gap:6px;">
                <div class="topbar-icon"><i class="fa fa-globe"></i></div>
                <div class="topbar-icon"><i class="fa fa-sun"></i></div>
                <div class="topbar-icon"><i class="fa-solid fa-table-cells"></i></div>
                <div class="topbar-icon"><i class="fa fa-bell"></i></div>
                <div class="avatar-top"
                    style="background:<?= htmlspecialchars($sessionUser['color']) ?>;position:relative;">
                    <?= htmlspecialchars($initials) ?>
                    <div class="user-dropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-name"><?= htmlspecialchars($sessionUser['full_name']) ?></div>
                            <div class="dropdown-email"><?= htmlspecialchars($sessionUser['email']) ?></div>
                            <span class="dropdown-role"><?= $isAdmin ? 'Admin' : 'User' ?></span>
                        </div>
                        <a class="dropdown-item" href="Account/user_account.php"><i class="fa fa-user"></i> My
                            Profile</a>
                        <a class="dropdown-item" href="Account/user_account.php"><i class="fa fa-gear"></i> Settings</a>
                        <a class="dropdown-item danger" href="logout.php"><i class="fa fa-right-from-bracket"></i>
                            Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="breadcrumb">
                <a href="index.php">Home</a>
                <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                <span style="color:var(--text-mid);">Dashboard</span>
            </div>

            <!-- Welcome -->
            <div class="dash-welcome" style="margin-bottom:24px;">
                <div class="dash-welcome-text">
                    <div class="congrats">Welcome back,
                        <?= htmlspecialchars(explode(' ', $sessionUser['full_name'])[0]) ?>! 👋
                    </div>
                    <p>Operations overview for <strong><?= date('l, F j, Y') ?></strong>.<br>
                        <?= $grand_month > 0 ? "<span class='badge-up'>↑ $grand_month new requests this month.</span>" : 'No new requests this month yet.' ?>
                    </p>
                    <a href="#section-details" class="btn-badge">View Details</a>
                </div>
                <svg class="dash-welcome-img" viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg"
                    style="position:absolute;right:32px;bottom:0;height:130px;opacity:.85;">
                    <ellipse cx="100" cy="130" rx="60" ry="8" fill="#e8e7ff" opacity=".5" />
                    <rect x="60" y="80" width="80" height="48" rx="5" fill="#696cff" opacity=".15" />
                    <rect x="65" y="84" width="70" height="36" rx="3" fill="#fff" stroke="#c8c9ff" stroke-width="1.5" />
                    <rect x="68" y="87" width="64" height="26" rx="2" fill="#f0f0ff" />
                    <line x1="73" y1="93" x2="105" y2="93" stroke="#696cff" stroke-width="1.5" stroke-linecap="round" />
                    <line x1="73" y1="97" x2="118" y2="97" stroke="#c8c9ff" stroke-width="1" stroke-linecap="round" />
                    <rect x="78" y="128" width="44" height="4" rx="2" fill="#b0b0d0" />
                    <circle cx="100" cy="55" r="16" fill="#ffb347" />
                    <rect x="88" y="68" width="24" height="20" rx="6" fill="#696cff" />
                    <rect x="84" y="75" width="8" height="14" rx="4" fill="#ffb347" />
                    <rect x="108" y="75" width="8" height="14" rx="4" fill="#ffb347" />
                    <path d="M91 88 Q100 96 109 88" stroke="#fff" stroke-width="1.5" fill="none"
                        stroke-linecap="round" />
                    <rect x="148" y="95" width="6" height="30" rx="3" fill="#6ecf8b" />
                    <ellipse cx="151" cy="88" rx="12" ry="14" fill="#6ecf8b" opacity=".7" />
                    <rect x="143" y="125" width="16" height="8" rx="3" fill="#c8a882" />
                </svg>
            </div>

            <!-- Grand Total Strip -->
            <div class="grand-strip">
                <div class="gs-item">
                    <div class="gs-val"><?= number_format($grand_total) ?></div>
                    <div class="gs-lbl">Total Records</div>
                </div>
                <div class="gs-divider"></div>
                <div class="gs-item">
                    <div class="gs-val"><?= number_format($grand_month) ?></div>
                    <div class="gs-lbl">This Month</div>
                </div>
                <div class="gs-divider"></div>
                <div class="gs-item">
                    <div class="gs-val"><?= number_format($total_done) ?></div>
                    <div class="gs-lbl">Accomplished</div>
                </div>
                <div class="gs-divider"></div>
                <div class="gs-item">
                    <div class="gs-val"><?= number_format($total_pending) ?></div>
                    <div class="gs-lbl">Pending</div>
                </div>
                <div class="gs-divider"></div>
                <div class="gs-item">
                    <div class="gs-val"><?= number_format($sato_total) ?></div>
                    <div class="gs-lbl">Sato Printers</div>
                </div>
                <div class="gs-divider"></div>
                <div class="gs-item">
                    <div class="gs-val"><?= number_format($sato_hist_total) ?></div>
                    <div class="gs-lbl">Printer Incidents</div>
                </div>
            </div>

            <!-- Module Cards -->
            <div class="module-grid">
                <div class="mod-card" style="--acc:#7e57c2;">
                    <div class="mod-card-icon"><i class="fa-solid fa-sliders"></i></div>
                    <div class="mod-card-label">Master Control</div>
                    <div class="mod-card-value"><?= number_format($mc_total) ?></div>
                    <div class="mod-card-sub"><span class="badge-up"><?= $mc_month ?> this month</span> · 5 sub-modules
                    </div>
                </div>
                <div class="mod-card" style="--acc:#1976d2;">
                    <div class="mod-card-icon" style="background:#1976d2;"><i class="fa-solid fa-flask"></i></div>
                    <div class="mod-card-label">QAD Monitoring</div>
                    <div class="mod-card-value"><?= number_format($qad_total) ?></div>
                    <div class="mod-card-sub"><span class="badge-up"><?= $qad_done ?> done</span> · <span
                            class="badge-pend"><?= $qad_pending ?> pending</span></div>
                </div>
                <div class="mod-card" style="--acc:#2e7d32;">
                    <div class="mod-card-icon" style="background:#2e7d32;"><i class="fa-solid fa-tag"></i></div>
                    <div class="mod-card-label">Label Assurance</div>
                    <div class="mod-card-value"><?= number_format($la_total) ?></div>
                    <div class="mod-card-sub"><span class="badge-up"><?= $la_done ?> done</span> · <span
                            class="badge-pend"><?= $la_pending ?> pending</span></div>
                </div>
                <div class="mod-card" style="--acc:#e65100;">
                    <div class="mod-card-icon" style="background:#e65100;"><i class="fa-solid fa-print"></i></div>
                    <div class="mod-card-label">Sato Printers</div>
                    <div class="mod-card-value"><?= number_format($sato_total) ?></div>
                    <div class="mod-card-sub"><span class="badge-up"><?= $sato_active ?> active</span> ·
                        <?= $sato_hist_month ?> incidents this month
                    </div>
                </div>
            </div>

            <!-- Per-System Monthly Charts -->
            <div class="panel" style="margin-bottom:24px;">
                <!-- Header row -->
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:18px;">
                    <div class="panel-title" style="margin-bottom:0;"><span class="dot"
                            style="background:#696cff;"></span> Monthly Requests per System</div>
                    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <!-- Year selector -->
                        <div style="display:flex;align-items:center;gap:6px;">
                            <label style="font-size:12px;font-weight:600;color:#a0a8b5;">Year</label>
                            <select id="chartYearSel" onchange="changeChartYear()"
                                style="padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#2d3a4a;background:#fff;cursor:pointer;"></select>
                        </div>
                        <!-- Chart type toggle -->
                        <div class="dash-tabs" style="width:auto;">
                            <button class="dash-tab active" id="btn-line"
                                onclick="setChartType('line',this)">Line</button>
                            <button class="dash-tab" id="btn-bar" onclick="setChartType('bar',this)">Bar</button>
                            <button class="dash-tab" id="btn-stacked"
                                onclick="setChartType('stacked',this)">Stacked</button>
                        </div>
                    </div>
                </div>
                <!-- System toggle buttons -->
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                    <button class="sys-btn active" id="sbtn-all" onclick="toggleSystem('all',this)"
                        style="--sc:#696cff;">
                        <span class="sys-dot"></span> All Systems
                    </button>
                    <button class="sys-btn active" id="sbtn-mc" onclick="toggleSystem('mc',this)" style="--sc:#7e57c2;">
                        <span class="sys-dot"></span> Master Control
                    </button>
                    <button class="sys-btn active" id="sbtn-qad" onclick="toggleSystem('qad',this)"
                        style="--sc:#1976d2;">
                        <span class="sys-dot"></span> QAD
                    </button>
                    <button class="sys-btn active" id="sbtn-la" onclick="toggleSystem('la',this)" style="--sc:#2e7d32;">
                        <span class="sys-dot"></span> LASYS
                    </button>
                    <button class="sys-btn active" id="sbtn-sato" onclick="toggleSystem('sato',this)"
                        style="--sc:#e65100;">
                        <span class="sys-dot"></span> Sato Printer
                    </button>
                </div>
                <!-- Main chart canvas -->
                <canvas id="monthlyChart" height="120"></canvas>
                <!-- Monthly summary table -->
                <div style="overflow-x:auto;margin-top:20px;">
                    <table class="monthly-table" id="monthlyTable">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th style="color:#7e57c2;">Master Control</th>
                                <th style="color:#1976d2;">QAD</th>
                                <th style="color:#2e7d32;">LASYS</th>
                                <th style="color:#e65100;">Sato</th>
                                <th style="color:#696cff;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="monthlyTableBody"></tbody>
                        <tfoot id="monthlyTableFoot"></tfoot>
                    </table>
                </div>
            </div>

            <!-- 6-Month Trend + MC Breakdown -->
            <div class="dash-3-2">
                <div class="panel">
                    <div class="panel-title">
                        <span class="dot" style="background:#696cff;"></span> 6-Month Request Trend
                        <span style="margin-left:auto;display:flex;gap:12px;align-items:center;">
                            <span
                                style="display:flex;align-items:center;gap:4px;font-size:11px;color:#7e57c2;font-weight:600;"><span
                                    style="width:10px;height:4px;background:#7e57c2;border-radius:2px;display:inline-block;"></span>MC</span>
                            <span
                                style="display:flex;align-items:center;gap:4px;font-size:11px;color:#1976d2;font-weight:600;"><span
                                    style="width:10px;height:4px;background:#1976d2;border-radius:2px;display:inline-block;"></span>QAD</span>
                            <span
                                style="display:flex;align-items:center;gap:4px;font-size:11px;color:#2e7d32;font-weight:600;"><span
                                    style="width:10px;height:4px;background:#2e7d32;border-radius:2px;display:inline-block;"></span>LASYS</span>
                        </span>
                    </div>
                    <canvas id="trendChart" height="180"></canvas>
                </div>
                <div class="panel">
                    <div class="panel-title"><span class="dot" style="background:#7e57c2;"></span> Master Control
                        Breakdown</div>
                    <div class="mc-list">
                        <?php
                        $mc_items = [
                            ['Cancellation', $mc_cancellation, '#7e57c2', 'fa-ban'],
                            ['New Reg User', $mc_newreg, '#1976d2', 'fa-user-plus'],
                            ['Password Reset', $mc_password, '#ff9f43', 'fa-key'],
                            ['Request Record', $mc_request, '#28c76f', 'fa-file-alt'],
                            ['Incident Report', $mc_incident, '#ff3e1d', 'fa-triangle-exclamation'],
                        ];
                        $mc_max = max(array_column($mc_items, 1)) ?: 1;
                        foreach ($mc_items as [$label, $cnt, $color, $icon]): ?>
                            <div class="mc-row">
                                <div class="mc-row-left">
                                    <div class="mc-row-icon" style="background:<?= $color ?>18;color:<?= $color ?>;"><i
                                            class="fa-solid <?= $icon ?>"></i></div>
                                    <span><?= $label ?></span>
                                </div>
                                <div class="mc-bar-wrap">
                                    <div class="mc-bar"
                                        style="width:<?= round($cnt / $mc_max * 100) ?>%;background:<?= $color ?>;">
                                    </div>
                                </div>
                                <div class="mc-cnt"><?= number_format($cnt) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- QAD + LASYS Category Charts -->
            <div class="dash-2col" id="section-details">
                <div class="panel">
                    <div class="panel-title"><span class="dot" style="background:#1976d2;"></span> QAD – Categories <a
                            href="QAD/qad_request.php" class="sec-link" style="margin-left:auto;">View All <i
                                class="fa fa-arrow-right"></i></a></div>
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#1976d2;"><?= $qad_total ?></div>
                            <div class="mini-stat-lbl">Total</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#28c76f;"><?= $qad_done ?></div>
                            <div class="mini-stat-lbl">Done</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#ff9f43;"><?= $qad_pending ?></div>
                            <div class="mini-stat-lbl">Pending</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val"><?= $qad_month ?></div>
                            <div class="mini-stat-lbl">This Month</div>
                        </div>
                    </div>
                    <?php if (!empty($qad_cats)): ?><canvas id="qadCatChart" height="160"></canvas>
                    <?php else: ?>
                        <div style="text-align:center;color:#a0a8b5;padding:30px;font-size:13px;">No data yet.</div>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <div class="panel-title"><span class="dot" style="background:#2e7d32;"></span> LASYS – Categories <a
                            href="LASYS/la_request.php" class="sec-link" style="margin-left:auto;">View All <i
                                class="fa fa-arrow-right"></i></a></div>
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#2e7d32;"><?= $la_total ?></div>
                            <div class="mini-stat-lbl">Total</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#28c76f;"><?= $la_done ?></div>
                            <div class="mini-stat-lbl">Done</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#ff9f43;"><?= $la_pending ?></div>
                            <div class="mini-stat-lbl">Pending</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val"><?= $la_month ?></div>
                            <div class="mini-stat-lbl">This Month</div>
                        </div>
                    </div>
                    <?php if (!empty($la_cats)): ?><canvas id="laCatChart" height="160"></canvas>
                    <?php else: ?>
                        <div style="text-align:center;color:#a0a8b5;padding:30px;font-size:13px;">No data yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sato Printer Status + Top Issues -->
            <div class="dash-3-2">
                <div class="panel">
                    <div class="panel-title"><span class="dot" style="background:#e65100;"></span> Sato Printer Status
                        <a href="SATO/sato_request.php" class="sec-link" style="margin-left:auto;">Manage <i
                                class="fa fa-arrow-right"></i></a>
                    </div>
                    <div class="mini-stats" style="margin-bottom:18px;">
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#e65100;"><?= $sato_total ?></div>
                            <div class="mini-stat-lbl">Total</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#28c76f;"><?= $sato_active ?></div>
                            <div class="mini-stat-lbl">Active</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val" style="color:#a0a8b5;"><?= $sato_inactive ?></div>
                            <div class="mini-stat-lbl">Inactive</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-val"><?= $sato_hist_total ?></div>
                            <div class="mini-stat-lbl">All Incidents</div>
                        </div>
                    </div>
                    <?php if (!empty($sato_printers_stat)): ?>
                        <div class="printer-grid">
                            <?php foreach ($sato_printers_stat as $p): ?>
                                <div class="printer-item">
                                    <div class="printer-dot"
                                        style="background:<?= $p['status'] === 'active' ? '#28c76f' : '#a0a8b5' ?>;"></div>
                                    <div>
                                        <div class="printer-name"><?= htmlspecialchars($p['printer_name']) ?></div>
                                        <div class="printer-sec"><?= htmlspecialchars($p['section_name'] ?? '—') ?></div>
                                    </div>
                                    <div class="printer-cnt"><?= $p['issue_count'] ?> issues</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;color:#a0a8b5;padding:30px;font-size:13px;">No printers yet.</div>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <div class="panel-title"><span class="dot" style="background:#e65100;"></span> Top Printer Issues <a
                            href="SATO/printerhistory.php" class="sec-link" style="margin-left:auto;">History <i
                                class="fa fa-arrow-right"></i></a></div>
                    <?php if (!empty($sato_issues)): ?>
                        <canvas id="issueDonut" height="150" style="margin-bottom:14px;"></canvas>
                        <div class="mc-list">
                            <?php $iClrs = ['#e65100', '#ff9f43', '#ffd54f', '#a0a8b5', '#78909c'];
                            $iMax = max(array_column($sato_issues, 'cnt')) ?: 1;
                            foreach ($sato_issues as $ki => $iss): ?>
                                <div class="mc-row">
                                    <div class="mc-row-left">
                                        <div class="mc-row-icon"
                                            style="background:<?= $iClrs[$ki] ?>22;color:<?= $iClrs[$ki] ?>;">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                        </div>
                                        <span
                                            style="font-size:12px;"><?= htmlspecialchars(mb_strimwidth($iss['issue_problem'], 0, 28, '…')) ?></span>
                                    </div>
                                    <div class="mc-bar-wrap">
                                        <div class="mc-bar"
                                            style="width:<?= round($iss['cnt'] / $iMax * 100) ?>%;background:<?= $iClrs[$ki] ?>;">
                                        </div>
                                    </div>
                                    <div class="mc-cnt"><?= $iss['cnt'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;color:#a0a8b5;padding:40px;font-size:13px;">No incidents yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity Table -->
            <div class="panel" style="margin-bottom:24px;">
                <div
                    style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div class="panel-title" style="margin-bottom:0;"><span class="dot"
                            style="background:#696cff;"></span> Recent Activity – All Modules</div>
                    <div class="dash-tabs" id="actTabs">
                        <button class="dash-tab active" onclick="switchActTab('all',this)">All</button>
                        <button class="dash-tab" onclick="switchActTab('mc',this)">Master Control</button>
                        <button class="dash-tab" onclick="switchActTab('qad',this)">QAD</button>
                        <button class="dash-tab" onclick="switchActTab('la',this)">LASYS</button>
                        <button class="dash-tab" onclick="switchActTab('sato',this)">Sato</button>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="act-table" id="actTable">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Date</th>
                                <th>Reference No.</th>
                                <th>Details / Category</th>
                                <th>Handled By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mc_recent as $r): ?>
                                <tr data-module="mc">
                                    <td><span class="type-badge type-mc"><i class="fa-solid fa-sliders"></i>
                                            <?= htmlspecialchars($r['type']) ?></span></td>
                                    <td><?= $r['date_requested'] ? date('M d, Y', strtotime($r['date_requested'])) : '—' ?>
                                    </td>
                                    <td style="font-weight:600;font-size:12.5px;">
                                        <?= htmlspecialchars($r['request_number']) ?>
                                    </td>
                                    <td style="color:#6e7a8a;">—</td>
                                    <td><?= htmlspecialchars($r['performed'] ?? '—') ?></td>
                                    <td><span class="pill-ok">Recorded</span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($qad_recent as $r): ?>
                                <tr data-module="qad">
                                    <td><span class="type-badge type-qad"><i class="fa-solid fa-flask"></i> QAD</span></td>
                                    <td><?= $r['date_requested'] ? date('M d, Y', strtotime($r['date_requested'])) : '—' ?>
                                    </td>
                                    <td style="font-weight:600;font-size:12.5px;">
                                        <?= htmlspecialchars($r['request_number']) ?>
                                    </td>
                                    <td style="color:#6e7a8a;"><?= htmlspecialchars($r['request_category'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($r['accomplished_by'] ?? '—') ?></td>
                                    <td><?= !empty($r['accomplished_by']) ? '<span class="pill-done">Done</span>' : '<span class="pill-pend">Pending</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($la_recent as $r): ?>
                                <tr data-module="la">
                                    <td><span class="type-badge type-la"><i class="fa-solid fa-tag"></i> LASYS</span></td>
                                    <td><?= $r['date_requested'] ? date('M d, Y', strtotime($r['date_requested'])) : '—' ?>
                                    </td>
                                    <td style="font-weight:600;font-size:12.5px;">
                                        <?= htmlspecialchars($r['request_number']) ?>
                                    </td>
                                    <td style="color:#6e7a8a;"><?= htmlspecialchars($r['request_category'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($r['accomplished_by'] ?? '—') ?></td>
                                    <td><?= !empty($r['accomplished_by']) ? '<span class="pill-done">Done</span>' : '<span class="pill-pend">Pending</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($sato_recent as $r): ?>
                                <tr data-module="sato">
                                    <td><span class="type-badge type-sato"><i class="fa-solid fa-print"></i> Sato</span>
                                    </td>
                                    <td><?= $r['date'] ? date('M d, Y', strtotime($r['date'])) : '—' ?></td>
                                    <td style="font-weight:600;font-size:12.5px;">
                                        <?= htmlspecialchars($r['printer_name']) ?>
                                    </td>
                                    <td style="color:#6e7a8a;">
                                        <?= htmlspecialchars(mb_strimwidth($r['issue_problem'] ?? '—', 0, 38, '…')) ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['action_taken'] ? mb_strimwidth($r['action_taken'], 0, 28, '…') : '—') ?>
                                    </td>
                                    <td><?php $rm = strtolower($r['remarks'] ?? '');
                                    if ($rm === 'complete')
                                        echo '<span class="pill-done">Complete</span>';
                                    elseif ($rm === 'ongoing')
                                        echo '<span class="pill-pend">Ongoing</span>';
                                    else
                                        echo '<span class="pill-off">' . htmlspecialchars($r['remarks'] ?: '—') . '</span>';
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleAcc(id) {
            const el = document.getElementById(id), isOpen = el.classList.contains('open');
            document.querySelectorAll('.nav-accordion').forEach(e => e.classList.remove('open'));
            if (!isOpen) el.classList.add('open');
        }
        function switchActTab(mod, btn) {
            document.querySelectorAll('#actTabs .dash-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('#actTable tbody tr').forEach(tr => {
                tr.style.display = (mod === 'all' || tr.dataset.module === mod) ? '' : 'none';
            });
        }

        Chart.defaults.font.family = "'Public Sans',sans-serif";
        Chart.defaults.color = '#a8aaae';

        /* ══════════════════════════════════════════
           PER-SYSTEM MONTHLY CHART
        ══════════════════════════════════════════ */
        const MONTHLY_DATA = <?= $monthlyJson ?>;
        const AVAIL_YEARS = <?= $availYearsJson ?>;
        let CHART_YEAR = <?= $chartYearJson ?>;

        const SYS = {
            mc: { label: 'Master Control', color: '#7e57c2', fill: '#7e57c212' },
            qad: { label: 'QAD', color: '#1976d2', fill: '#1976d212' },
            la: { label: 'LASYS', color: '#2e7d32', fill: '#2e7d3212' },
            sato: { label: 'Sato Printer', color: '#e65100', fill: '#e6510012' },
        };

        let visibleSys = new Set(['mc', 'qad', 'la', 'sato']);
        let chartType = 'line';
        let monthlyChart = null;
        const TODAY_MONTH = new Date().getMonth(); // 0-indexed

        // Populate year selector
        const sel = document.getElementById('chartYearSel');
        AVAIL_YEARS.forEach(y => {
            const opt = document.createElement('option');
            opt.value = y; opt.textContent = y;
            if (y === CHART_YEAR) opt.selected = true;
            sel.appendChild(opt);
        });

        function buildDatasets(data, type) {
            return Object.entries(SYS)
                .filter(([k]) => visibleSys.has(k))
                .map(([k, s]) => {
                    const base = {
                        label: s.label,
                        data: data[k],
                        borderColor: s.color,
                        backgroundColor: type === 'line' ? s.fill : s.color,
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.35,
                        fill: type === 'line',
                    };
                    if (type !== 'line') {
                        base.borderRadius = 5;
                        base.barPercentage = type === 'stacked' ? 0.7 : 0.55;
                    }
                    return base;
                });
        }

        function buildChartOptions(type) {
            const isStacked = type === 'stacked';
            return {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 }, padding: 14 }
                    },
                    tooltip: {
                        callbacks: {
                            footer: (items) => 'Total: ' + items.reduce((s, i) => s + i.raw, 0)
                        }
                    }
                },
                scales: {
                    x: { stacked: isStacked, grid: { display: false } },
                    y: {
                        stacked: isStacked,
                        grid: { color: '#f0f0f2' },
                        ticks: { precision: 0, stepSize: 1 },
                        beginAtZero: true
                    }
                }
            };
        }

        function renderMonthlyChart(data) {
            const ctx = document.getElementById('monthlyChart');
            if (monthlyChart) monthlyChart.destroy();
            const type = chartType === 'stacked' ? 'bar' : chartType;
            monthlyChart = new Chart(ctx, {
                type,
                data: { labels: data.months, datasets: buildDatasets(data, chartType) },
                options: buildChartOptions(chartType)
            });
        }

        function renderMonthlyTable(data, year) {
            const tbody = document.getElementById('monthlyTableBody');
            const tfoot = document.getElementById('monthlyTableFoot');
            const curYear = new Date().getFullYear();
            let totMC = 0, totQAD = 0, totLA = 0, totSATO = 0;
            tbody.innerHTML = data.months.map((m, i) => {
                const mc = data.mc[i], qad = data.qad[i], la = data.la[i], sato = data.sato[i];
                const total = mc + qad + la + sato;
                totMC += mc; totQAD += qad; totLA += la; totSATO += sato;
                const isCur = year === curYear && i === TODAY_MONTH;
                const z = v => v === 0 ? `<span class="zero">0</span>` : v;
                return `<tr class="${isCur ? 'cur-month' : ''}">
                <td>${m}${isCur ? ' <span style="font-size:10px;color:#696cff;font-weight:700;">● now</span>' : ''}</td>
                <td>${z(mc)}</td><td>${z(qad)}</td><td>${z(la)}</td><td>${z(sato)}</td>
                <td style="color:#696cff;">${total || '<span class=\"zero\">0</span>'}</td>
            </tr>`;
            }).join('');
            const grandTotal = totMC + totQAD + totLA + totSATO;
            tfoot.innerHTML = `<tr>
            <td>TOTAL</td>
            <td>${totMC}</td><td>${totQAD}</td><td>${totLA}</td><td>${totSATO}</td>
            <td style="color:#696cff;">${grandTotal}</td>
        </tr>`;
        }

        function initMonthlyView(data, year) {
            renderMonthlyChart(data);
            renderMonthlyTable(data, year);
        }

        function changeChartYear() {
            const y = parseInt(document.getElementById('chartYearSel').value);
            CHART_YEAR = y;
            // Reload page with year param to fetch fresh PHP data
            const url = new URL(window.location);
            url.searchParams.set('chart_year', y);
            window.location = url.toString();
        }

        function setChartType(type, btn) {
            chartType = type;
            document.querySelectorAll('[id^="btn-"]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderMonthlyChart(MONTHLY_DATA);
        }

        function toggleSystem(key, btn) {
            if (key === 'all') {
                const allActive = visibleSys.size === 4;
                visibleSys = allActive ? new Set() : new Set(['mc', 'qad', 'la', 'sato']);
                document.querySelectorAll('.sys-btn').forEach(b => {
                    allActive ? b.classList.remove('active') : b.classList.add('active');
                });
            } else {
                if (visibleSys.has(key)) { visibleSys.delete(key); btn.classList.remove('active'); }
                else { visibleSys.add(key); btn.classList.add('active'); }
                const allBtn = document.getElementById('sbtn-all');
                allBtn.classList.toggle('active', visibleSys.size === 4);
            }
            renderMonthlyChart(MONTHLY_DATA);
        }

        // Init
        initMonthlyView(MONTHLY_DATA, CHART_YEAR);

        /* ══════════════════════════════════════════
           6-MONTH STACKED TREND (existing)
        ══════════════════════════════════════════ */
        const td = <?= $trendJson ?>;
        new Chart(document.getElementById('trendChart'), {
            type: 'bar',
            data: {
                labels: td.map(d => d.month), datasets: [
                    { label: 'Master Control', data: td.map(d => d.mc), backgroundColor: '#7e57c2', borderRadius: 4, barPercentage: .6 },
                    { label: 'QAD', data: td.map(d => d.qad), backgroundColor: '#1976d2', borderRadius: 4, barPercentage: .6 },
                    { label: 'LASYS', data: td.map(d => d.la), backgroundColor: '#2e7d32', borderRadius: 4, barPercentage: .6 },
                ]
            },
            options: { plugins: { legend: { display: false } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, grid: { color: '#f0f0f2' }, ticks: { stepSize: 1 } } } }
        });

        <?php if (!empty($qad_cats)): ?>
            const qc = <?= $qadCatsJson ?>;
            new Chart(document.getElementById('qadCatChart'), {
                type: 'doughnut',
                data: { labels: qc.map(c => c.request_category || 'Other'), datasets: [{ data: qc.map(c => c.cnt), backgroundColor: ['#1976d2', '#42a5f5', '#90caf9', '#bbdefb', '#e3f2fd'], borderWidth: 2, borderColor: '#fff' }] },
                options: { cutout: '60%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
            });
        <?php endif; ?>

        <?php if (!empty($la_cats)): ?>
            const lc = <?= $laCatsJson ?>;
            new Chart(document.getElementById('laCatChart'), {
                type: 'doughnut',
                data: { labels: lc.map(c => c.request_category || 'Other'), datasets: [{ data: lc.map(c => c.cnt), backgroundColor: ['#2e7d32', '#43a047', '#66bb6a', '#a5d6a7', '#e8f5e9'], borderWidth: 2, borderColor: '#fff' }] },
                options: { cutout: '60%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
            });
        <?php endif; ?>

        <?php if (!empty($sato_issues)): ?>
            const si = <?= json_encode($sato_issues) ?>;
            new Chart(document.getElementById('issueDonut'), {
                type: 'doughnut',
                data: { labels: si.map(i => i.issue_problem.length > 20 ? i.issue_problem.substring(0, 20) + '…' : i.issue_problem), datasets: [{ data: si.map(i => i.cnt), backgroundColor: ['#e65100', '#ff9f43', '#ffd54f', '#a0a8b5', '#78909c'], borderWidth: 2, borderColor: '#fff' }] },
                options: { cutout: '55%', plugins: { legend: { display: false } } }
            });
        <?php endif; ?>

       window.IDLE_LOGOUT_URL = "logout.php";
    </script>
    <script src="js/idle-timeout.js"></script>
</body>
</html>