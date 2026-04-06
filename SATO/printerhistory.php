<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
include('../dbconnection/config.php');

$sessionUser = ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'full_name' => $_SESSION['full_name'], 'role' => $_SESSION['role'], 'email' => $_SESSION['email'], 'color' => $_SESSION['color']];
$isAdmin = $sessionUser['role'] === 'admin';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $sessionUser['full_name']), 0, 2)));

// Printer filter from query string (e.g. ?printer=BX65)
$filterPrinter = trim($_GET['printer'] ?? '');

/* ══════════════════════════════════════════════════════════
   INLINE CRUD HANDLER
══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action'])) {
    header('Content-Type: application/json');

    switch ($_POST['__action']) {

        case 'add_history':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $pn = trim($_POST['printer_name'] ?? '');
            $dt = trim($_POST['date'] ?? '') ?: null;
            $issue = trim($_POST['issue_problem'] ?? '');
            $pic = trim($_POST['pic'] ?? '') ?: null;
            $action = trim($_POST['action_taken'] ?? '') ?: null;
            $rmk = trim($_POST['remarks'] ?? '') ?: null;
            if (!$pn || !$issue) {
                echo json_encode(['success' => false, 'message' => 'Printer Name and Issue are required.']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO sato_printer_history (printer_name, date, issue_problem, pic, action_taken, remarks) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$pn, $dt, $issue, $pic, $action, $rmk]);
            $newId = (int) $pdo->lastInsertId();
            echo json_encode(['success' => true, 'record' => ['id' => $newId, 'printer_name' => $pn, 'date' => $dt, 'issue_problem' => $issue, 'pic' => $pic, 'action_taken' => $action, 'remarks' => $rmk]]);
            exit;

        case 'edit_history':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $id = (int) ($_POST['id'] ?? 0);
            $pn = trim($_POST['printer_name'] ?? '');
            $dt = trim($_POST['date'] ?? '') ?: null;
            $issue = trim($_POST['issue_problem'] ?? '');
            $pic = trim($_POST['pic'] ?? '') ?: null;
            $action = trim($_POST['action_taken'] ?? '') ?: null;
            $rmk = trim($_POST['remarks'] ?? '') ?: null;
            if (!$id || !$pn || !$issue) {
                echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE sato_printer_history SET printer_name=?,date=?,issue_problem=?,pic=?,action_taken=?,remarks=? WHERE id=?');
            $stmt->execute([$pn, $dt, $issue, $pic, $action, $rmk, $id]);
            echo json_encode(['success' => true, 'record' => ['id' => $id, 'printer_name' => $pn, 'date' => $dt, 'issue_problem' => $issue, 'pic' => $pic, 'action_taken' => $action, 'remarks' => $rmk]]);
            exit;

        case 'delete_history':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }
            $pdo->prepare('DELETE FROM sato_printer_history WHERE id=?')->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'get_history':
            $id = (int) ($_POST['id'] ?? 0);
            $row = $pdo->prepare('SELECT * FROM sato_printer_history WHERE id=?');
            $row->execute([$id]);
            $rec = $row->fetch(PDO::FETCH_ASSOC);
            echo json_encode($rec ?: []);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }
}

// ── Normalize legacy remarks in DB ───────────────────────────────────────
try {
    $pdo->exec("UPDATE sato_printer_history SET remarks = CASE
        WHEN LOWER(remarks) IN ('completed','complete','done','done on-time','finished') THEN 'Completed'
        WHEN LOWER(remarks) IN ('on-going','on going','ongoing','in-process','in progress') THEN 'On-going'
        WHEN LOWER(remarks) IN ('pending','not yet done','awaiting') THEN 'Pending'
        ELSE remarks END
    WHERE remarks IS NOT NULL AND remarks NOT IN ('Completed','On-going','Pending')");
} catch (\Exception $e) {
}

// ── Fetch history records ──────────────────────────────────────────────────
if ($filterPrinter) {
    $stmt = $pdo->prepare('SELECT * FROM sato_printer_history WHERE printer_name = ? ORDER BY date DESC, id DESC');
    $stmt->execute([$filterPrinter]);
    $allHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $allHistory = $pdo->query('SELECT * FROM sato_printer_history ORDER BY date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

// Unique printers for dropdown filter
$printerList = $pdo->query('SELECT DISTINCT printer_name FROM sato_printer_history ORDER BY printer_name ASC')->fetchAll(PDO::FETCH_COLUMN);
// Also get from sato_printers table so we can add history for any printer
$allPrinters = $pdo->query('SELECT DISTINCT printer_name FROM sato_printers ORDER BY printer_name ASC')->fetchAll(PDO::FETCH_COLUMN);

// ── Users for PIC dropdown ──────────────────────────────────────────────────
$associates = [];
try {
    $associates = $pdo->query("SELECT id,full_name,username,role,associate_id FROM users WHERE status='active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    try {
        $associates = $pdo->query("SELECT id,full_name,username,role,associate_id FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e2) {
    }
}

// Stats
$totalHistory = count($allHistory);
$completeCount = count(array_filter($allHistory, fn($r) => strtolower($r['remarks'] ?? '') === 'completed'));
$ongoingCount = count(array_filter($allHistory, fn($r) => in_array(strtolower($r['remarks'] ?? ''), ['on-going', 'ongoing', 'in-process', 'in progress'])));
$pendingCount = count(array_filter($allHistory, fn($r) => strtolower($r['remarks'] ?? '') === 'pending'));
$thisMonthCount = count(array_filter($allHistory, fn($r) => strpos($r['date'] ?? '', date('Y-m')) === 0));

// This week: Monday–Sunday of current week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$thisWeekCount = count(array_filter($allHistory, fn($r) => ($r['date'] ?? '') >= $weekStart && ($r['date'] ?? '') <= $weekEnd));

// Most encountered issue (top issue_problem value)
$issueCounts = [];
foreach ($allHistory as $r) {
    $issue = trim($r['issue_problem'] ?? '');
    if ($issue)
        $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
}
arsort($issueCounts);
$topIssue = $issueCounts ? array_key_first($issueCounts) : '—';
$topIssueCount = $issueCounts ? reset($issueCounts) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printer History<?= $filterPrinter ? ' – ' . htmlspecialchars($filterPrinter) : '' ?> – System</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        :root {
            --acc: #0284c7;
            --acc2: #0369a1;
            --acc-bg: #0284c712;
            --acc-dark: #075985;
        }

        /* ── Stat grid ── */
        .stat-grid {
            grid-template-columns: repeat(7, 1fr) !important;
        }

        @media(max-width:1200px) {
            .stat-grid {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }

        @media(max-width:680px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        .stat-card {
            cursor: pointer;
            transition: transform .18s, box-shadow .18s, border-color .18s;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .10);
        }

        .stat-icon.sky {
            background: #0284c712;
            color: #0284c7;
        }

        .stat-icon.green {
            background: #28c76f12;
            color: #28c76f;
        }

        .stat-icon.amber {
            background: #f59e0b12;
            color: #f59e0b;
        }

        .stat-icon.teal {
            background: #00bcd412;
            color: #00bcd4;
        }

        .stat-icon.orange {
            background: #f9731612;
            color: #f97316;
        }

        .stat-icon.purple {
            background: #9c27b012;
            color: #9c27b0;
        }

        /* Top issue card special layout */
        .top-issue-val {
            font-size: 13px !important;
            font-weight: 700;
            color: #9c27b0;
            line-height: 1.3;
            word-break: break-word;
            margin-top: 2px;
        }

        .top-issue-count {
            font-size: 11px;
            color: #8a93a2;
            margin-top: 2px;
        }

        /* ── Printer filter banner ── */
        .printer-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #e0f2fe;
            border: 1.5px solid #7dd3fc;
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 18px;
            font-size: 14px;
            color: #0369a1;
        }

        .printer-banner strong {
            font-size: 16px;
        }

        .printer-banner a {
            margin-left: auto;
            color: #0369a1;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #7dd3fc;
            padding: 4px 12px;
            border-radius: 6px;
            background: #fff;
            transition: background .15s;
        }

        .printer-banner a:hover {
            background: #bae6fd;
        }

        /* ── Action cell ── */
        .action-cell {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .action-btn {
            border: none;
            background: transparent;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: background .15s, color .15s;
            color: #8a93a2;
            text-decoration: none;
        }

        .action-btn:hover {
            background: #f0f1f5;
            color: var(--acc);
        }

        .action-btn.del:hover {
            background: #fff0ee;
            color: #ff3e1d;
        }

        .action-btn.edit-btn:hover {
            background: #fff8ec;
            color: #ff9f43;
        }

        .action-btn.view-btn:hover {
            background: var(--acc-bg);
            color: var(--acc);
        }

        /* ── Remarks badge ── */
        .badge-rmk {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .badge-rmk.complete {
            background: #28c76f18;
            color: #28c76f;
        }

        .badge-rmk.pending {
            background: #f59e0b18;
            color: #d97706;
        }

        .badge-rmk.other {
            background: #6e7a8a18;
            color: #6e7a8a;
        }

        /* ── Printer badge ── */
        .printer-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--acc-bg);
            color: var(--acc2);
        }

        /* ── Stat card active filter ── */
        .stat-card.active-filter {
            outline: 2px solid var(--acc);
            box-shadow: 0 0 0 4px var(--acc-bg);
        }

        .stat-card.active-filter.green-card {
            outline-color: #28c76f;
            box-shadow: 0 0 0 4px #28c76f18;
        }

        .stat-card.active-filter.orange-card {
            outline-color: #ff9f43;
            box-shadow: 0 0 0 4px #ff9f4318;
        }

        .stat-card.active-filter.blue-card {
            outline-color: #696cff;
            box-shadow: 0 0 0 4px #696cff18;
        }

        .badge-rmk.ongoing {
            background: #696cff18;
            color: #696cff;
        }

        .badge-rmk.pending {
            background: #ff9f4318;
            color: #ff9f43;
        }

        /* ── PIC colored ── */
        .pic-name {
            color: #ef4444;
            font-weight: 600;
            font-size: 13px;
        }

        /* ══ Modals ══ */
        @keyframes vmIn {
            from {
                transform: scale(.85);
                opacity: 0
            }

            to {
                transform: scale(1);
                opacity: 1
            }
        }

        .ce-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 60, .45);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .ce-modal-overlay.open {
            display: flex;
        }

        .ce-modal {
            background: #fff;
            border-radius: 16px;
            width: 95%;
            max-width: 600px;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .ce-modal-header {
            padding: 22px 28px 18px;
            border-radius: 16px 16px 0 0;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #fff;
        }

        .ce-modal-header-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .ce-modal-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .ce-modal-header p {
            margin: 4px 0 0;
            font-size: 13px;
            opacity: .85;
        }

        .ce-modal-body {
            padding: 24px 28px;
        }

        .ce-modal-footer {
            padding: 16px 28px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .modal-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #5a6070;
            margin-bottom: 5px;
        }

        .modal-input {
            width: 100%;
            box-sizing: border-box;
            padding: 9px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13.5px;
            color: #2d3a4a;
            outline: none;
            transition: border .2s;
        }

        .modal-input:focus {
            border-color: var(--acc);
        }

        .modal-grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .modal-full {
            margin-bottom: 16px;
        }

        .form-section {
            font-size: 12px;
            font-weight: 700;
            color: #8a93a2;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin: 18px 0 12px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .modal-alert {
            display: none;
            background: #fff0ee;
            color: #ff3e1d;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 14px;
            align-items: center;
            gap: 8px;
        }

        .btn-modal-cancel {
            background: #f1f5f9;
            border: none;
            border-radius: 9px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-modal-cancel:hover {
            background: #e2e8f0;
        }

        .btn-modal-submit {
            border: none;
            border-radius: 9px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
        }

        .btn-modal-submit:hover {
            opacity: .88;
        }

        /* ── View modal ── */
        .view-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 60, .45);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .view-modal-overlay.open {
            display: flex;
        }

        .view-modal {
            background: #fff;
            border-radius: 16px;
            width: 95%;
            max-width: 520px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
            overflow: hidden;
        }

        .view-modal-header {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            padding: 22px 28px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #fff;
        }

        .view-icon-wrap {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .view-name {
            font-size: 18px;
            font-weight: 700;
        }

        .view-sub {
            font-size: 13px;
            opacity: .8;
            margin-top: 3px;
        }

        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: #f1f5f9;
        }

        .view-field {
            background: #fff;
            padding: 14px 20px;
        }

        .view-field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #8a93a2;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .view-field span {
            font-size: 14px;
            font-weight: 600;
            color: #2d3a4a;
        }

        .view-field.full {
            grid-column: span 2;
        }

        .view-modal-footer {
            padding: 16px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-close-view {
            background: #f1f5f9;
            border: none;
            border-radius: 9px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-close-view:hover {
            background: #e2e8f0;
        }

        .btn-edit-view {
            background: linear-gradient(135deg, #ff9f43, #f7b731);
            border: none;
            border-radius: 9px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
        }

        /* ── Delete modal ── */
        .del-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 60, .45);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .del-modal-overlay.open {
            display: flex;
        }

        .del-modal {
            background: #fff;
            border-radius: 16px;
            width: 92%;
            max-width: 380px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s;
        }

        .del-icon-wrap {
            width: 70px;
            height: 70px;
            background: #fff0ee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 30px;
            color: #ff3e1d;
        }

        .del-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3a4a;
            margin-bottom: 8px;
        }

        .del-sub {
            font-size: 13.5px;
            color: #6e7a8a;
            line-height: 1.65;
            margin-bottom: 26px;
        }

        .del-btns {
            display: flex;
            gap: 12px;
        }

        .btn-cancel-del {
            flex: 1;
            background: #f1f5f9;
            border: none;
            border-radius: 9px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-confirm-del {
            flex: 1;
            background: linear-gradient(135deg, #ff3e1d, #ff6b4a);
            border: none;
            border-radius: 9px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
        }

        /* ── Result modal ── */
        .result-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 60, .45);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .result-overlay.open {
            display: flex;
        }

        .result-box {
            background: #fff;
            border-radius: 16px;
            width: 92%;
            max-width: 360px;
            padding: 36px 28px 30px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .35s cubic-bezier(.34, 1.56, .64, 1);
        }

        .result-icon-wrap {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .result-icon-wrap.success {
            background: #28c76f18;
        }

        .result-icon-wrap.success i {
            color: #28c76f;
        }

        .result-icon-wrap.error {
            background: #ff3e1d12;
        }

        .result-icon-wrap.error i {
            color: #ff3e1d;
        }

        .result-icon-wrap i {
            font-size: 34px;
        }

        .result-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3a4a;
            margin-bottom: 8px;
        }

        .result-msg {
            font-size: 13.5px;
            color: #6e7a8a;
            line-height: 1.65;
            margin-bottom: 26px;
        }

        .btn-result-ok {
            background: linear-gradient(135deg, var(--acc), var(--acc2));
            border: none;
            border-radius: 9px;
            padding: 12px 36px;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .btn-result-ok.danger {
            background: linear-gradient(135deg, #ff3e1d, #ff6b4a);
        }

        .btn-result-ok:hover {
            opacity: .88;
        }

        .toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: #2d3a4a;
            color: #fff;
            padding: 12px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s;
            z-index: 10001;
        }

        .toast.show {
            opacity: 1;
        }
    </style>
</head>

<body>

    <!-- ════ SIDEBAR ════ -->
    <aside class="sidebar">
        <a class="sidebar-logo" href="#">
            <div class="logo-icon">AM</div><span class="logo-text">TEAM</span>
        </a>
        <nav>
            <ul>
                <li class="nav-section-label">Main</li>
                <li class="nav-item"><a class="nav-link" href="../index.php"><span class="nav-icon"><i
                                class="fa-solid fa-house"></i></span><span class="nav-text">Dashboard</span></a></li>
                <li class="nav-item nav-accordion" id="masterControl">
                    <a class="nav-link" href="#" onclick="toggleAcc('masterControl');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span><span class="nav-text">Master
                            Control</span><i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="../Mastercontrol/cancellation.php">Change and Cancellation</a>
                        </li>
                        <li><a class="nav-sub-link" href="../Mastercontrol/newreguser.php">New User Registration</a>
                        </li>
                        <li><a class="nav-sub-link" href="../Mastercontrol/passwordreset.php">Password Request</a></li>
                        <li><a class="nav-sub-link" href="../Mastercontrol/requestrecord.php">Request Record</a></li>
                        <li><a class="nav-sub-link" href="../Mastercontrol/incident.php">Incident Report</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="QADControl">
                    <a class="nav-link" href="#" onclick="toggleAcc('QADControl');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span><span class="nav-text">Queen's
                            Annes Drive</span><i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="../QAD/qad_request.php">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="Lasys">
                    <a class="nav-link" href="#" onclick="toggleAcc('Lasys');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span><span class="nav-text">Label
                            Assurance System</span><i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="../LASYS/la_request.php">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion open" id="printerAcc">
                    <a class="nav-link active" href="#" onclick="toggleAcc('printerAcc');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-print"></i></span><span class="nav-text">Sato
                            Printer</span><i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="sato_request.php">List of Printers</a></li>
                        <li><a class="nav-sub-link active" href="printerhistory.php">Printer History</a></li>
                    </ul>
                </li>
                <div class="nav-divider"></div>
                <li class="nav-section-label">Apps &amp; Pages</li>
                <li class="nav-item nav-accordion" id="userAcc">
                    <a class="nav-link" href="#" onclick="toggleAcc('userAcc');return false;"><span class="nav-icon"><i
                                class="fa-solid fa-users"></i></span><span class="nav-text">Users</span><i
                            class="fa fa-chevron-right nav-chevron"></i></a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link <?php echo ($current_page == '../Account/tbl_userlist.php') ? 'active' : ''; ?>"
                                href="../Account/tbl_userlist.php">List</a></li>
                        <li><a class="nav-sub-link <?php echo ($current_page == 'user_account.php') ? 'active' : ''; ?>"
                                href="../Account/user_account.php">Account Settings</a></li>
                        <li><a class="nav-sub-link <?php echo ($current_page == 'pending.php') ? 'active' : ''; ?>"
                                href="../Account/pending.php">Pending Approvals</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- ════ PAGE WRAPPER ════ -->
    <div class="page-wrapper">
        <header class="topbar">
            <div class="topbar-search"><i class="fa fa-search"
                    style="color:var(--text-light);font-size:13px;"></i><input type="text"
                    placeholder="Search (CTRL + K)" id="topbar-search" oninput="syncSearch()"></div>
            <div class="topbar-spacer"></div>
            <div style="display:flex;align-items:center;gap:6px;">
                <div class="topbar-icon"><i class="fa fa-globe"></i></div>
                <div class="topbar-icon"><i class="fa fa-sun"></i></div>
                <div class="topbar-icon"><i class="fa-solid fa-table-cells"></i></div>
                <div class="topbar-icon"><i class="fa fa-bell"></i></div>
                <div class="avatar-top" style="background:<?= htmlspecialchars($sessionUser['color']) ?>;">
                    <?= htmlspecialchars($initials) ?>
                    <div class="user-dropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-name"><?= htmlspecialchars($sessionUser['full_name']) ?></div>
                            <div class="dropdown-email"><?= htmlspecialchars($sessionUser['email']) ?></div>
                            <span class="dropdown-role"><?= $isAdmin ? 'Admin' : 'User' ?></span>
                        </div>
                        <a class="dropdown-item" href="user_account.php"><i class="fa fa-user"></i> My Profile</a>
                        <a class="dropdown-item" href="user_account.php"><i class="fa fa-gear"></i> Settings</a>
                        <a class="dropdown-item danger" href="../logout.php"><i class="fa fa-right-from-bracket"></i>
                            Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="breadcrumb">
                <a href="#">Home</a><span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                <a href="sato_request.php">Sato Printer</a><span><i class="fa fa-chevron-right"
                        style="font-size:9px;"></i></span>
                <span style="color:var(--text-mid);">Printer
                    History<?= $filterPrinter ? ' – ' . htmlspecialchars($filterPrinter) : '' ?></span>
            </div>

            <?php if (!$isAdmin): ?>
                <div class="access-notice"><i class="fa-solid fa-circle-info"></i> You are logged in as a
                    <strong>User</strong>. Management actions are restricted to Admins only.
                </div>
            <?php endif; ?>

            <!-- Printer filter banner -->
            <?php if ($filterPrinter): ?>
                <div class="printer-banner">
                    <i class="fa-solid fa-clock-rotate-left" style="font-size:20px;"></i>
                    <div>Showing history for <strong><?= htmlspecialchars($filterPrinter) ?></strong>
                        <span style="color:#64748b;font-size:12px;margin-left:8px;"><?= $totalHistory ?>
                            record<?= $totalHistory !== 1 ? 's' : '' ?></span>
                    </div>
                    <a href="printerhistory.php"><i class="fa fa-xmark"></i> Clear Filter</a>
                </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="stat-grid">
                <div class="stat-card" id="card-total" onclick="clearCardFilter()" title="Show all records">
                    <div>
                        <div class="stat-label">Total</div>
                        <div class="stat-value" id="sc-total"><?= $totalHistory ?></div>
                        <div class="stat-sub">All Records</div>
                    </div>
                    <div class="stat-icon sky"><i class="fa-solid fa-clock-rotate-left"></i></div>
                </div>
                <div class="stat-card green-card" id="card-complete" onclick="filterCard('completed')"
                    title="Filter: Completed">
                    <div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-value" id="sc-complete"><?= $completeCount ?></div>
                        <div class="stat-sub">Status: Completed</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-card blue-card" id="card-ongoing" onclick="filterCard('ongoing')"
                    title="Filter: On-going">
                    <div>
                        <div class="stat-label">On-going</div>
                        <div class="stat-value" id="sc-ongoing"><?= $ongoingCount ?></div>
                        <div class="stat-sub">Status: On-going</div>
                    </div>
                    <div class="stat-icon" style="background:#696cff18;color:#696cff;"><i
                            class="fa-solid fa-spinner"></i></div>
                </div>
                <div class="stat-card orange-card" id="card-pending" onclick="filterCard('pending')"
                    title="Filter: Pending">
                    <div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" id="sc-pending"><?= $pendingCount ?></div>
                        <div class="stat-sub">Status: Pending</div>
                    </div>
                    <div class="stat-icon amber"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="stat-card" id="card-month" onclick="filterCard('month')" title="Filter: This Month">
                    <div>
                        <div class="stat-label">This Month</div>
                        <div class="stat-value" id="sc-month"><?= $thisMonthCount ?></div>
                        <div class="stat-sub"><?= date('M Y') ?></div>
                    </div>
                    <div class="stat-icon teal"><i class="fa-solid fa-calendar-week"></i></div>
                </div>
                <div class="stat-card" id="card-week" onclick="filterCard('week')" title="Filter: This Week">
                    <div>
                        <div class="stat-label">This Week</div>
                        <div class="stat-value" id="sc-week"><?= $thisWeekCount ?></div>
                        <div class="stat-sub"><?= date('M d', strtotime($weekStart)) ?> –
                            <?= date('M d', strtotime($weekEnd)) ?>
                        </div>
                    </div>
                    <div class="stat-icon orange"><i class="fa-solid fa-calendar-days"></i></div>
                </div>
                <div class="stat-card" title="Most encountered issue">
                    <div>
                        <div class="stat-label">Top Issue</div>
                        <div class="top-issue-val" id="sc-top-issue"><?= htmlspecialchars($topIssue) ?></div>
                        <div class="top-issue-count">
                            <?= $topIssueCount > 0 ? $topIssueCount . 'x encountered' : 'No data yet' ?>
                        </div>
                    </div>
                    <div class="stat-icon purple"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="rows-select"><select id="per-page" onchange="perPageChanged()">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select></div>
                    <!-- Printer filter dropdown -->
                    <select id="printer-filter" onchange="filterByPrinter()"
                        style="padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#2d3a4a;background:#fff;">
                        <option value="">All Printers</option>
                        <?php foreach ($printerList as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $filterPrinter === $p ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="toolbar-spacer"></div>
                    <div class="search-input-wrap"><i class="fa fa-search"
                            style="color:var(--text-light);font-size:12px;"></i><input type="text" id="table-search"
                            placeholder="Search history..." oninput="renderTable()"></div>
                    <button class="btn btn-outline" onclick="exportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?>
                        <button class="btn btn-primary" style="background:linear-gradient(135deg,#0284c7,#0369a1);"
                            onclick="openAddModal()"><i class="fa fa-plus"></i> Add History</button>
                    <?php endif; ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
                            <th onclick="sortBy('id')" style="cursor:pointer;">ID <i class="fa fa-sort"
                                    style="font-size:10px;"></i></th>
                            <th onclick="sortBy('date')" style="cursor:pointer;">Date <i class="fa fa-sort"
                                    style="font-size:10px;"></i></th>
                            <th>Printer Name</th>
                            <th>Issue / Problem</th>
                            <th>PIC</th>
                            <th>Action Taken</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="histTableBody"></tbody>
                </table>
                <div class="pagination-row">
                    <div class="page-info" id="page-info">Showing 0 to 0 of 0 entries</div>
                    <div class="page-btns" id="page-btns"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- ════ ADD MODAL ════ -->
    <?php if ($isAdmin): ?>
        <div class="ce-modal-overlay" id="add-modal">
            <div class="ce-modal">
                <div class="ce-modal-header" style="background:linear-gradient(135deg,#0284c7,#0369a1);">
                    <div class="ce-modal-header-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div>
                        <h3>Add Printer History</h3>
                        <p>Log a new maintenance or issue record.</p>
                    </div>
                </div>
                <div class="ce-modal-body">
                    <div id="add-alert" class="modal-alert"></div>
                    <div class="form-section"><i class="fa-solid fa-print"></i> Printer Info</div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">Printer Name <span style="color:#ff3e1d;">*</span></label>
                            <select class="modal-input" id="add-printer_name">
                                <option value="">— Select Printer —</option>
                                <?php foreach ($allPrinters as $p): ?>
                                    <option value="<?= htmlspecialchars($p) ?>" <?= $filterPrinter === $p ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="modal-label">Date</label><input class="modal-input" id="add-date" type="date"
                                value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div class="modal-full">
                        <label class="modal-label">Issue / Problem <span style="color:#ff3e1d;">*</span></label>
                        <input class="modal-input" id="add-issue_problem" type="text"
                            placeholder="e.g. Scratch, Head clog, Paper jam">
                    </div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">PIC (Person in Charge)</label>
                            <select class="modal-input" id="add-pic">
                                <option value="">— Select Associate —</option>
                                <?php foreach ($associates as $a):
                                    $ai = $a['associate_id'] ? ' · ' . $a['associate_id'] : ''; ?>
                                    <option value="<?= htmlspecialchars($a['full_name']) ?>">
                                        <?= htmlspecialchars($a['full_name'] . $ai) ?>
                                        (<?= htmlspecialchars(ucfirst($a['role'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="modal-label">Remarks / Status</label>
                            <select class="modal-input" id="add-remarks">
                                <option value="">— Select Status —</option>
                                <option value="On-going">On-going</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-full">
                        <label class="modal-label">Action Taken</label>
                        <textarea class="modal-input" id="add-action_taken" rows="3"
                            placeholder="Describe the action taken..."></textarea>
                    </div>
                </div>
                <div class="ce-modal-footer">
                    <button class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
                    <button class="btn-modal-submit" style="background:linear-gradient(135deg,#0284c7,#0369a1);"
                        onclick="submitAdd()"><i class="fa fa-plus"></i> Add Record</button>
                </div>
            </div>
        </div>

        <!-- ════ EDIT MODAL ════ -->
        <div class="ce-modal-overlay" id="edit-modal">
            <div class="ce-modal">
                <div class="ce-modal-header" style="background:linear-gradient(135deg,#ff9f43,#f7b731);">
                    <div class="ce-modal-header-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div>
                        <h3>Edit History Record</h3>
                        <p>Update the history entry below.</p>
                    </div>
                </div>
                <div class="ce-modal-body">
                    <div id="edit-alert" class="modal-alert"></div>
                    <input type="hidden" id="edit-id">
                    <div class="form-section"><i class="fa-solid fa-print"></i> Printer Info</div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">Printer Name <span style="color:#ff3e1d;">*</span></label>
                            <select class="modal-input" id="edit-printer_name">
                                <option value="">— Select Printer —</option>
                                <?php foreach ($allPrinters as $p): ?>
                                    <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="modal-label">Date</label><input class="modal-input" id="edit-date" type="date">
                        </div>
                    </div>
                    <div class="modal-full">
                        <label class="modal-label">Issue / Problem <span style="color:#ff3e1d;">*</span></label>
                        <input class="modal-input" id="edit-issue_problem" type="text">
                    </div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">PIC (Person in Charge)</label>
                            <select class="modal-input" id="edit-pic">
                                <option value="">— Select Associate —</option>
                                <?php foreach ($associates as $a):
                                    $ai = $a['associate_id'] ? ' · ' . $a['associate_id'] : ''; ?>
                                    <option value="<?= htmlspecialchars($a['full_name']) ?>">
                                        <?= htmlspecialchars($a['full_name'] . $ai) ?>
                                        (<?= htmlspecialchars(ucfirst($a['role'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="modal-label">Remarks / Status</label>
                            <select class="modal-input" id="edit-remarks">
                                <option value="">— Select Status —</option>
                                <option value="On-going">On-going</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-full">
                        <label class="modal-label">Action Taken</label>
                        <textarea class="modal-input" id="edit-action_taken" rows="3"></textarea>
                    </div>
                </div>
                <div class="ce-modal-footer">
                    <button class="btn-modal-cancel" onclick="closeEditModal()">Cancel</button>
                    <button class="btn-modal-submit" style="background:linear-gradient(135deg,#ff9f43,#f7b731);"
                        onclick="submitEdit()"><i class="fa fa-floppy-disk"></i> Save Changes</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ════ VIEW MODAL ════ -->
    <div class="view-modal-overlay" id="view-modal">
        <div class="view-modal">
            <div class="view-modal-header">
                <div class="view-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <div class="view-name" id="vm-printer">—</div>
                    <div class="view-sub" id="vm-date">—</div>
                </div>
            </div>
            <div class="view-grid">
                <div class="view-field"><label>Issue / Problem</label><span id="vm-issue"></span></div>
                <div class="view-field"><label>PIC</label><span id="vm-pic" style="color:#ef4444;"></span></div>
                <div class="view-field full"><label>Action Taken</label><span id="vm-action"></span></div>
                <div class="view-field"><label>Remarks</label><span id="vm-remarks"></span></div>
                <div class="view-field"><label>Record ID</label><span id="vm-id"></span></div>
            </div>
            <div class="view-modal-footer">
                <button class="btn-close-view" onclick="closeViewModal()">Close</button>
                <?php if ($isAdmin): ?><button class="btn-edit-view" onclick="switchToEdit()"><i class="fa fa-pen"></i>
                        Edit</button><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ════ DELETE MODAL ════ -->
    <div class="del-modal-overlay" id="del-modal">
        <div class="del-modal">
            <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="del-title">Delete Record?</div>
            <div class="del-sub">You are about to delete history for <strong id="del-name"></strong>.<br>This action
                cannot be undone.</div>
            <div class="del-btns">
                <button class="btn-cancel-del" onclick="closeDelModal()">Cancel</button>
                <button class="btn-confirm-del" onclick="confirmDelete()"><i class="fa fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>

    <!-- ════ RESULT MODAL ════ -->
    <div class="result-overlay" id="result-modal">
        <div class="result-box">
            <div class="result-icon-wrap" id="result-icon-wrap"><i id="result-icon"></i></div>
            <div class="result-title" id="result-title"></div>
            <div class="result-msg" id="result-msg"></div>
            <button class="btn-result-ok" id="result-ok-btn" onclick="closeResultModal()">OK</button>
        </div>
    </div>
    <div class="toast" id="toast"></div>

    <script>
        const ALL_RECORDS = <?= json_encode(array_values($allHistory)) ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const INIT_FILTER = <?= json_encode($filterPrinter) ?>;

        let allRecords = [...ALL_RECORDS];
        let curPage = 1, perPage = 10;
        let _sortCol = 'date', _sortDir = -1;
        let _pendingDeleteId = null, _pendingDeleteName = null;
        let _currentViewRec = null;
        let _printerFilter = INIT_FILTER;

        /* ── Filter & Sort ── */
        const WEEK_START = '<?= $weekStart ?>';
        const WEEK_END = '<?= $weekEnd ?>';
        const THIS_MONTH = '<?= date('Y-m') ?>';
        let _cardFilter = null;

        function filterCard(type) {
            _cardFilter = (_cardFilter === type) ? null : type;
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            if (_cardFilter) {
                const map = { completed: 'card-complete', ongoing: 'card-ongoing', pending: 'card-pending', month: 'card-month', week: 'card-week' };
                const el = document.getElementById(map[_cardFilter]);
                if (el) el.classList.add('active-filter');
            }
            curPage = 1; renderTable(); updateStats();
        }
        function clearCardFilter() {
            _cardFilter = null;
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            curPage = 1; renderTable(); updateStats();
        }

        function getFiltered() {
            const q = (document.getElementById('table-search')?.value || '').toLowerCase();
            let rows = allRecords.filter(r => {
                const ms = !q || [r.printer_name, r.issue_problem, r.pic, r.action_taken, r.remarks, r.date]
                    .some(f => (f || '').toLowerCase().includes(q));
                const mp = !_printerFilter || (r.printer_name || '') === _printerFilter;
                let mc = true;
                if (_cardFilter === 'completed') mc = normRemarks(r.remarks) === 'completed';
                if (_cardFilter === 'ongoing') mc = normRemarks(r.remarks) === 'on-going';
                if (_cardFilter === 'pending') mc = normRemarks(r.remarks) === 'pending';
                if (_cardFilter === 'month') mc = (r.date || '').startsWith(THIS_MONTH);
                if (_cardFilter === 'week') mc = (r.date || '') >= WEEK_START && (r.date || '') <= WEEK_END;
                return ms && mp && mc;
            });
            rows.sort((a, b) => {
                const va = a[_sortCol] || '', vb = b[_sortCol] || '';
                return va < vb ? _sortDir : va > vb ? -_sortDir : 0;
            });
            return rows;
        }

        function sortBy(col) {
            if (_sortCol === col) _sortDir *= -1; else { _sortCol = col; _sortDir = -1; }
            curPage = 1; renderTable();
        }

        function filterByPrinter() {
            _printerFilter = document.getElementById('printer-filter').value;
            curPage = 1; renderTable();
            // Update URL without reload
            const url = new URL(window.location);
            _printerFilter ? url.searchParams.set('printer', _printerFilter) : url.searchParams.delete('printer');
            history.replaceState(null, '', url);
        }

        /* ── Remarks badge ── */
        function normRemarks(r) {
            const s = (r || '').trim().toLowerCase();
            if (['complete', 'completed', 'done', 'done on-time', 'done on time', 'finished'].includes(s)) return 'completed';
            if (['on-going', 'on going', 'ongoing', 'in-process', 'in process', 'in progress'].includes(s)) return 'on-going';
            if (['pending', 'not yet done', 'awaiting'].includes(s)) return 'pending';
            return s || '';
        }
        function remarksBadge(r) {
            if (!r) return '<span style="color:#c0c0c0;">—</span>';
            const nr = normRemarks(r);
            if (nr === 'completed') return `<span class="badge-rmk complete"><i class="fa-solid fa-circle-check" style="font-size:9px;"></i> Completed</span>`;
            if (nr === 'on-going') return `<span class="badge-rmk ongoing"><i class="fa-solid fa-spinner" style="font-size:9px;"></i> On-going</span>`;
            if (nr === 'pending') return `<span class="badge-rmk pending"><i class="fa-solid fa-clock" style="font-size:9px;"></i> Pending</span>`;
            return `<span class="badge-rmk other">${r}</span>`;
        }

        /* ── Render ── */
        function renderTable() {
            const f = getFiltered(), total = f.length, tp = Math.ceil(total / perPage) || 1;
            if (curPage > tp) curPage = tp;
            const start = (curPage - 1) * perPage, paged = f.slice(start, start + perPage);
            document.getElementById('histTableBody').innerHTML = paged.map(r => `
<tr>
  <td><input type="checkbox" class="row-cb" data-id="${r.id}"></td>
  <td style="font-size:12px;color:#8a93a2;">${r.id}</td>
  <td style="font-size:13px;color:var(--text-mid);white-space:nowrap;">${r.date || '—'}</td>
  <td><span class="printer-badge"><i class="fa-solid fa-print" style="font-size:10px;"></i> ${r.printer_name || '—'}</span></td>
  <td style="font-size:13px;color:#2d3a4a;max-width:180px;">${r.issue_problem || '—'}</td>
  <td><span class="pic-name">${r.pic || '—'}</span></td>
  <td style="font-size:13px;color:#4a5568;max-width:200px;">${r.action_taken || '—'}</td>
  <td>${remarksBadge(r.remarks)}</td>
  <td><div class="action-cell">
    <button class="action-btn view-btn" title="View" onclick="openViewModal(${r.id})"><i class="fa fa-eye"></i></button>
    ${IS_ADMIN ? `<button class="action-btn edit-btn" title="Edit" onclick="openEditModal(${r.id})"><i class="fa fa-pen"></i></button>
    <button class="action-btn del" title="Delete" onclick="openDelModal(${r.id},'${(r.printer_name || 'Record').replace(/'/g, '')}')" ><i class="fa fa-trash"></i></button>` : ''}
  </div></td>
</tr>`).join('');
            const from = total === 0 ? 0 : start + 1, to = Math.min(start + perPage, total);
            document.getElementById('page-info').textContent = `Showing ${from} to ${to} of ${total} entries`;
            renderPageBtns(tp); updateStats();
        }

        function updateStats() {
            document.getElementById('sc-total').textContent = allRecords.length;
            document.getElementById('sc-complete').textContent = allRecords.filter(r => normRemarks(r.remarks) === 'completed').length;
            document.getElementById('sc-ongoing').textContent = allRecords.filter(r => normRemarks(r.remarks) === 'on-going').length;
            document.getElementById('sc-pending').textContent = allRecords.filter(r => normRemarks(r.remarks) === 'pending').length;
            document.getElementById('sc-month').textContent = allRecords.filter(r => (r.date || '').startsWith(THIS_MONTH)).length;
            document.getElementById('sc-week').textContent = allRecords.filter(r => (r.date || '') >= WEEK_START && (r.date || '') <= WEEK_END).length;
        }

        function renderPageBtns(tp) {
            const c = document.getElementById('page-btns'); let html = '';
            html += `<button class="page-btn ${curPage === 1 ? 'disabled' : ''}" onclick="goPage(${curPage - 1})"><i class="fa fa-angles-left"></i></button>`;
            html += `<button class="page-btn ${curPage === 1 ? 'disabled' : ''}" onclick="goPage(${curPage - 1})"><i class="fa fa-angle-left"></i></button>`;
            let s = Math.max(1, curPage - 2), e = Math.min(tp, s + 4); if (e - s < 4) s = Math.max(1, e - 4);
            for (let i = s; i <= e; i++) html += `<button class="page-btn ${i === curPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
            html += `<button class="page-btn ${curPage === tp ? 'disabled' : ''}" onclick="goPage(${curPage + 1})"><i class="fa fa-angle-right"></i></button>`;
            html += `<button class="page-btn ${curPage === tp ? 'disabled' : ''}" onclick="goPage(${tp})"><i class="fa fa-angles-right"></i></button>`;
            c.innerHTML = html;
        }
        function goPage(p) { const t = Math.ceil(getFiltered().length / perPage) || 1; if (p < 1 || p > t) return; curPage = p; renderTable(); }
        function perPageChanged() { perPage = parseInt(document.getElementById('per-page').value); curPage = 1; renderTable(); }
        function toggleAll(cb) { document.querySelectorAll('.row-cb').forEach(r => r.checked = cb.checked); }
        function syncSearch() { document.getElementById('table-search').value = document.getElementById('topbar-search').value; curPage = 1; renderTable(); }

        /* ── Add ── */
        function openAddModal() { hideAlert('add-alert'); document.getElementById('add-modal').classList.add('open'); }
        function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); }
        document.getElementById('add-modal')?.addEventListener('click', function (e) { if (e.target === this) closeAddModal(); });

        function submitAdd() {
            const pn = document.getElementById('add-printer_name').value.trim();
            const dt = document.getElementById('add-date').value.trim();
            const issue = document.getElementById('add-issue_problem').value.trim();
            const pic = document.getElementById('add-pic').value.trim();
            const act = document.getElementById('add-action_taken').value.trim();
            const rmk = document.getElementById('add-remarks').value.trim();
            if (!pn || !issue) { showAlert('add-alert', 'Printer Name and Issue are required.'); return; }
            fetch(window.location.pathname, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ __action: 'add_history', printer_name: pn, date: dt, issue_problem: issue, pic, action_taken: act, remarks: rmk }).toString()
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    allRecords.unshift(d.record); curPage = 1; renderTable(); closeAddModal();
                    showResult('success', 'Record Added!', 'Printer history has been saved.');
                } else showAlert('add-alert', d.message || 'Failed to add.');
            }).catch(() => showAlert('add-alert', 'Network error.'));
        }

        /* ── Edit ── */
        function openEditModal(id) {
            const r = allRecords.find(x => x.id == id); if (!r) return;
            document.getElementById('edit-id').value = r.id;
            document.getElementById('edit-printer_name').value = r.printer_name || '';
            document.getElementById('edit-date').value = r.date || '';
            document.getElementById('edit-issue_problem').value = r.issue_problem || '';
            document.getElementById('edit-pic').value = r.pic || '';
            document.getElementById('edit-action_taken').value = r.action_taken || '';
            document.getElementById('edit-remarks').value = r.remarks || '';
            hideAlert('edit-alert'); document.getElementById('edit-modal').classList.add('open');
        }
        function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); }
        document.getElementById('edit-modal')?.addEventListener('click', function (e) { if (e.target === this) closeEditModal(); });

        function submitEdit() {
            const id = document.getElementById('edit-id').value;
            const pn = document.getElementById('edit-printer_name').value.trim();
            const dt = document.getElementById('edit-date').value.trim();
            const issue = document.getElementById('edit-issue_problem').value.trim();
            const pic = document.getElementById('edit-pic').value.trim();
            const act = document.getElementById('edit-action_taken').value.trim();
            const rmk = document.getElementById('edit-remarks').value.trim();
            if (!pn || !issue) { showAlert('edit-alert', 'Printer Name and Issue are required.'); return; }
            fetch(window.location.pathname, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ __action: 'edit_history', id, printer_name: pn, date: dt, issue_problem: issue, pic, action_taken: act, remarks: rmk }).toString()
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    const idx = allRecords.findIndex(r => r.id == id);
                    if (idx !== -1) allRecords[idx] = { ...allRecords[idx], ...d.record };
                    renderTable(); closeEditModal();
                    showResult('success', 'Record Updated!', 'History entry has been updated.');
                } else showAlert('edit-alert', d.message || 'Failed to update.');
            }).catch(() => showAlert('edit-alert', 'Network error.'));
        }

        /* ── View ── */
        function openViewModal(id) {
            const r = allRecords.find(x => x.id == id); if (!r) return; _currentViewRec = r;
            document.getElementById('vm-printer').textContent = r.printer_name || '—';
            document.getElementById('vm-date').textContent = r.date || '—';
            document.getElementById('vm-issue').textContent = r.issue_problem || '—';
            document.getElementById('vm-pic').textContent = r.pic || '—';
            document.getElementById('vm-action').textContent = r.action_taken || '—';
            document.getElementById('vm-remarks').innerHTML = remarksBadge(r.remarks);
            document.getElementById('vm-id').textContent = '#' + r.id;
            document.getElementById('view-modal').classList.add('open');
        }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('open'); _currentViewRec = null; }
        function switchToEdit() { if (!_currentViewRec) return; closeViewModal(); openEditModal(_currentViewRec.id); }

        /* ── Delete ── */
        function openDelModal(id, name) { _pendingDeleteId = id; _pendingDeleteName = name; document.getElementById('del-name').textContent = name; document.getElementById('del-modal').classList.add('open'); }
        function closeDelModal() { document.getElementById('del-modal').classList.remove('open'); _pendingDeleteId = _pendingDeleteName = null; }
        function confirmDelete() {
            if (!_pendingDeleteId) return; const dn = _pendingDeleteName;
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `__action=delete_history&id=${_pendingDeleteId}` })
                .then(r => r.json()).then(d => {
                    closeDelModal();
                    if (d.success) { allRecords = allRecords.filter(r => r.id != _pendingDeleteId); renderTable(); showResult('success', 'Deleted', `History record for "${dn}" has been removed.`); }
                    else showResult('error', 'Failed', d.message || 'Could not delete.');
                }).catch(() => { closeDelModal(); showResult('error', 'Network Error', 'Could not reach the server.'); });
        }

        /* ── Result ── */
        function showResult(type, title, msg) {
            const iw = document.getElementById('result-icon-wrap'), ic = document.getElementById('result-icon');
            iw.className = `result-icon-wrap ${type}`; ic.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
            document.getElementById('result-title').textContent = title; document.getElementById('result-msg').textContent = msg;
            document.getElementById('result-ok-btn').className = type === 'error' ? 'btn-result-ok danger' : 'btn-result-ok';
            document.getElementById('result-modal').classList.add('open');
        }
        function closeResultModal() { document.getElementById('result-modal').classList.remove('open'); }

        /* ── Helpers ── */
        function showAlert(elId, msg) { const el = document.getElementById(elId); if (!el) return; el.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${msg}`; el.style.display = 'flex'; }
        function hideAlert(elId) { const el = document.getElementById(elId); if (el) el.style.display = 'none'; }
        function showToast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.className = 'toast show'; clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 3200); }
        function toggleAcc(id) {
            const clicked = document.getElementById(id);
            const isOpen = clicked.classList.contains('open');
            document.querySelectorAll('.nav-accordion').forEach(el => el.classList.remove('open'));
            if (!isOpen) clicked.classList.add('open');
        }

        function exportCSV() {
            const rows = [['ID', 'Date', 'Printer Name', 'Issue/Problem', 'PIC', 'Action Taken', 'Remarks']];
            getFiltered().forEach(r => rows.push([r.id, r.date || '', r.printer_name || '', r.issue_problem || '', r.pic || '', r.action_taken || '', r.remarks || '']));
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
            a.download = `printer_history${_printerFilter ? '_' + _printerFilter : ''}.csv`; a.click();
            showToast('Exported!');
        }

        renderTable();
    </script>
</body>

</html>