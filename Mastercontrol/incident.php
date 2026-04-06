<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
include('../dbconnection/config.php');

/* ══════════════════════════════════════════════════════════
   INLINE CRUD HANDLER
══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action'])) {
    header('Content-Type: application/json');
    $isAdminAjax = ($_SESSION['role'] === 'admin');

    switch ($_POST['__action']) {

        case 'get_next_request_number':
            $year = date('y');
            $prefix = "MC-IC-{$year}-";
            $stmt = $pdo->prepare("SELECT request_number FROM incident_record WHERE request_number LIKE ? ORDER BY record_id DESC LIMIT 1");
            $stmt->execute([$prefix . '%']);
            $last = $stmt->fetchColumn();
            $seq = $last ? str_pad((int) substr($last, strlen($prefix)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
            echo json_encode(['success' => true, 'request_number' => $prefix . $seq]);
            exit;

        case 'add_record':
            if (!$isAdminAjax) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $dr = trim($_POST['date_requested'] ?? '');
            $drx = trim($_POST['date_received'] ?? '') ?: null;
            $rn = trim($_POST['request_number'] ?? '');
            $en = trim($_POST['ext_number'] ?? '') ?: null;
            $rs = trim($_POST['request_section'] ?? '') ?: null;
            $inf = trim($_POST['information'] ?? '') ?: null;
            $id2 = trim($_POST['imp_date'] ?? '') ?: null;
            $rc = trim($_POST['root_cause'] ?? '') ?: null;
            $pf = trim($_POST['performed'] ?? '') ?: null;
            if (!$dr || !$rn) {
                echo json_encode(['success' => false, 'message' => 'Date Requested and Request Number are required.']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO incident_record (date_requested,date_received,request_number,ext_number,request_section,information,imp_date,root_cause,performed) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$dr, $drx, $rn, $en, $rs, $inf, $id2, $rc, $pf]);
            echo json_encode(['success' => true, 'record' => ['record_id' => (int) $pdo->lastInsertId(), 'date_requested' => $dr, 'date_received' => $drx, 'request_number' => $rn, 'ext_number' => $en, 'request_section' => $rs, 'information' => $inf, 'imp_date' => $id2, 'root_cause' => $rc, 'performed' => $pf]]);
            exit;

        case 'edit_record':
            if (!$isAdminAjax) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $id = (int) ($_POST['id'] ?? 0);
            $dr = trim($_POST['date_requested'] ?? '');
            $drx = trim($_POST['date_received'] ?? '') ?: null;
            $rn = trim($_POST['request_number'] ?? '');
            $en = trim($_POST['ext_number'] ?? '') ?: null;
            $rs = trim($_POST['request_section'] ?? '') ?: null;
            $inf = trim($_POST['information'] ?? '') ?: null;
            $id2 = trim($_POST['imp_date'] ?? '') ?: null;
            $rc = trim($_POST['root_cause'] ?? '') ?: null;
            $pf = trim($_POST['performed'] ?? '') ?: null;
            if (!$id || !$dr || !$rn) {
                echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE incident_record SET date_requested=?,date_received=?,request_number=?,ext_number=?,request_section=?,information=?,imp_date=?,root_cause=?,performed=? WHERE record_id=?');
            $stmt->execute([$dr, $drx, $rn, $en, $rs, $inf, $id2, $rc, $pf, $id]);
            echo json_encode(['success' => true, 'record' => ['record_id' => $id, 'date_requested' => $dr, 'date_received' => $drx, 'request_number' => $rn, 'ext_number' => $en, 'request_section' => $rs, 'information' => $inf, 'imp_date' => $id2, 'root_cause' => $rc, 'performed' => $pf]]);
            exit;

        case 'delete_record':
            if (!$isAdminAjax) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }
            $pdo->prepare('DELETE FROM incident_record WHERE record_id=?')->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }
}

$sessionUser = ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'full_name' => $_SESSION['full_name'], 'role' => $_SESSION['role'], 'email' => $_SESSION['email'], 'color' => $_SESSION['color']];
$isAdmin = $sessionUser['role'] === 'admin';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $sessionUser['full_name']), 0, 2)));

$allRecords = $pdo->query('SELECT record_id,date_requested,date_received,request_number,ext_number,request_section,information,imp_date,root_cause,performed FROM incident_record ORDER BY record_id DESC')->fetchAll();

$year = date('y');
$prefix = "MC-IC-{$year}-";
$stmt = $pdo->prepare("SELECT request_number FROM incident_record WHERE request_number LIKE ? ORDER BY record_id DESC LIMIT 1");
$stmt->execute([$prefix . '%']);
$lastRN = $stmt->fetchColumn();
$nextSeq = $lastRN ? str_pad((int) substr($lastRN, strlen($prefix)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$nextRequestNumber = $prefix . $nextSeq;

$totalRecords = count($allRecords);
$todayRecords = count(array_filter($allRecords, fn($r) => $r['date_requested'] === date('Y-m-d')));
$pendingRecords = count(array_filter($allRecords, fn($r) => empty($r['date_received'])));
$doneRecords = count(array_filter($allRecords, fn($r) => !empty($r['date_received'])));
$thisMonth = count(array_filter($allRecords, fn($r) => strpos($r['date_requested'] ?? '', date('Y-m')) === 0));
$withRootCause = count(array_filter($allRecords, fn($r) => !empty($r['root_cause'])));

$associates = [];
try {
    $associates = $pdo->query("SELECT id,full_name,username,role,associate_id,avatar_color FROM users WHERE status='active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    try {
        $associates = $pdo->query("SELECT id,full_name,username,role,associate_id,avatar_color FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e2) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Report – System</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        /* ── accent: red/orange ── */
        :root {
            --acc: #ff3e1d;
            --acc2: #c0392b;
            --acc-bg: #ff3e1d12;
        }

        .stat-grid {
            grid-template-columns: repeat(6, 1fr) !important;
        }

        @media(max-width:1200px) {
            .stat-grid {
                grid-template-columns: repeat(3, 1fr) !important;
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
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .10);
        }

        .stat-card.active-filter {
            border-color: var(--acc);
            box-shadow: 0 0 0 3px var(--acc-bg);
        }

        .stat-card.active-filter.fc-green {
            border-color: #28c76f;
            box-shadow: 0 0 0 3px #28c76f22;
        }

        .stat-card.active-filter.fc-orange {
            border-color: #ff9f43;
            box-shadow: 0 0 0 3px #ff9f4322;
        }

        .stat-card.active-filter.fc-teal {
            border-color: #00bcd4;
            box-shadow: 0 0 0 3px #00bcd422;
        }

        .stat-card.active-filter.fc-blue {
            border-color: #696cff;
            box-shadow: 0 0 0 3px #696cff22;
        }

        .stat-card.active-filter.fc-red {
            border-color: var(--acc);
            box-shadow: 0 0 0 3px var(--acc-bg);
        }

        .stat-card.active-filter .stat-label::after {
            content: ' ✕';
            font-size: 10px;
            color: var(--acc);
            font-weight: 700;
        }

        .stat-card::after {
            content: attr(data-tip);
            position: absolute;
            bottom: -28px;
            left: 50%;
            transform: translateX(-50%);
            background: #2d3a4a;
            color: #fff;
            font-size: 11px;
            padding: 3px 9px;
            border-radius: 5px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s;
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-icon.crimson {
            background: var(--acc-bg);
            color: var(--acc);
        }

        .stat-icon.teal {
            background: #00bcd412;
            color: #00bcd4;
        }

        .stat-icon.blue {
            background: #696cff12;
            color: #696cff;
        }

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
        }

        .action-btn:hover {
            background: #f0f1f5;
            color: #696cff;
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
            background: #fff0ee;
            color: var(--acc);
        }

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

        /* Modals */
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
            max-width: 560px;
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
            font-size: 18px;
            flex-shrink: 0;
        }

        .ce-modal-header h3 {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }

        .ce-modal-header p {
            font-size: 12px;
            opacity: .85;
            margin: 2px 0 0;
        }

        .ce-modal-body {
            padding: 24px 28px 26px;
        }

        .form-section {
            font-size: 11px;
            font-weight: 700;
            color: var(--acc);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin: 18px 0 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ffe5e0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-section:first-child {
            margin-top: 0;
        }

        .modal-label {
            font-size: 11.5px;
            font-weight: 600;
            color: #6e7a8a;
            text-transform: uppercase;
            letter-spacing: .4px;
            display: block;
            margin-bottom: 5px;
        }

        .modal-input {
            width: 100%;
            box-sizing: border-box;
            padding: 9px 12px;
            border: 1.5px solid #e4e5ec;
            border-radius: 8px;
            font-size: 13.5px;
            color: #2d3a4a;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            background: #fafbfc;
            font-family: inherit;
        }

        .modal-input:focus {
            border-color: var(--acc);
            box-shadow: 0 0 0 3px #ff3e1d18;
            background: #fff;
        }

        select.modal-input {
            cursor: pointer;
        }

        textarea.modal-input {
            resize: vertical;
            min-height: 72px;
        }

        .rn-chip {
            width: 100%;
            box-sizing: border-box;
            padding: 9px 12px;
            border: 1.5px solid #ffd5cf;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--acc);
            background: #fff5f3;
            letter-spacing: .5px;
        }

        .rn-hint {
            font-size: 11px;
            color: #a0a8b5;
            margin-top: 4px;
        }

        .modal-grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .modal-field {
            margin-bottom: 14px;
        }

        .modal-field:last-child {
            margin-bottom: 0;
        }

        .ce-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 28px 22px;
        }

        .btn-modal-cancel {
            background: #f0f1f5;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-modal-cancel:hover {
            background: #e4e5ec;
        }

        .btn-modal-submit {
            border: none;
            border-radius: 8px;
            padding: 10px 26px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .btn-modal-submit:hover {
            opacity: .88;
        }

        .modal-alert {
            display: none;
            align-items: center;
            gap: 8px;
            background: #fff0ee;
            border: 1px solid #ffd5cf;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #d43a1a;
            margin-bottom: 16px;
        }

        /* View */
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
            padding: 36px 36px 30px;
            max-width: 540px;
            width: 94%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .view-modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .view-icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--acc), var(--acc2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
        }

        .view-name {
            font-size: 17px;
            font-weight: 700;
            color: #2d3a4a;
        }

        .view-sub {
            font-size: 13px;
            color: #8a93a2;
            margin-top: 2px;
        }

        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
            background: #f6f7f9;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .view-field label {
            font-size: 11px;
            font-weight: 600;
            color: #a0a8b5;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .view-field span {
            display: block;
            font-size: 13.5px;
            font-weight: 500;
            color: #2d3a4a;
            margin-top: 3px;
        }

        .view-field.full {
            grid-column: 1/-1;
        }

        .view-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-close-view {
            background: #f0f1f5;
            border: none;
            border-radius: 8px;
            padding: 9px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-close-view:hover {
            background: #e4e5ec;
        }

        .btn-edit-view {
            background: linear-gradient(135deg, var(--acc), var(--acc2));
            border: none;
            border-radius: 8px;
            padding: 9px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .btn-edit-view:hover {
            opacity: .88;
        }

        /* Delete / Result */
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
            padding: 36px 36px 30px;
            max-width: 400px;
            width: 92%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .del-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #ff3e1d12;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .del-icon-wrap i {
            font-size: 32px;
            color: #ff3e1d;
        }

        .del-title {
            font-size: 19px;
            font-weight: 700;
            color: #2d3a4a;
            margin-bottom: 8px;
        }

        .del-sub {
            font-size: 13.5px;
            color: #6e7a8a;
            line-height: 1.6;
            margin-bottom: 26px;
        }

        .del-btns {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-cancel-del {
            background: #f0f1f5;
            border: none;
            border-radius: 8px;
            padding: 10px 26px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-confirm-del {
            background: #ff3e1d;
            border: none;
            border-radius: 8px;
            padding: 10px 26px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .btn-confirm-del:hover {
            opacity: .88;
        }

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
            padding: 40px 36px 32px;
            max-width: 380px;
            width: 92%;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .2);
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

        .result-icon-wrap.error {
            background: #ff3e1d12;
        }

        .result-icon-wrap i {
            font-size: 34px;
        }

        .result-icon-wrap.success i {
            color: #28c76f;
        }

        .result-icon-wrap.error i {
            color: #ff3e1d;
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

        .result-detail {
            background: #f6f7f9;
            border-radius: 10px;
            padding: 13px 16px;
            text-align: left;
            font-size: 13px;
            color: #4a5568;
            line-height: 1.9;
            margin-bottom: 24px;
        }

        .result-detail .rd-row {
            display: flex;
            gap: 8px;
        }

        .result-detail .rd-label {
            font-weight: 600;
            color: #2d3a4a;
            min-width: 130px;
        }

        .btn-result-ok {
            background: linear-gradient(135deg, #28c76f, #20a857);
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

        .ic-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--acc-bg);
            color: var(--acc);
        }
    </style>
</head>

<body>


    <!-- ════ SIDEBAR ════ -->
    <aside class="sidebar">
        <a class="sidebar-logo" href="#">
            <div class="logo-icon">AM</div>
            <span class="logo-text">TEAM</span>
        </a>
        <nav>
            <ul>
                <li class="nav-section-label">Main</li>
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item nav-accordion open" id="masterControl">
                    <a class="nav-link active" href="#" onclick="toggleAcc('masterControl');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Master Control</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="cancellation.php">Change and Cancellation</a></li>
                        <li><a class="nav-sub-link" href="newreguser.php">New User Registration</a></li>
                        <li><a class="nav-sub-link" href="passwordreset.php">Password Request</a></li>
                        <li><a class="nav-sub-link" href="requestrecord.php">Request Record</a></li>
                        <li><a class="nav-sub-link active" href="incident.php">Incident Report</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="QADControl">
                    <a class="nav-link" href="#" onclick="toggleAcc('QADControl');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Queen's Annes Drive</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="../QAD/qad_request.php">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="Lasys">
                    <a class="nav-link" href="#" onclick="toggleAcc('Lasys');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Label Assurance System</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="../LASYS/la_request.php">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="printerAcc">
                    <a class="nav-link" href="#" onclick="toggleAcc('printerAcc');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-print"></i></span>
                        <span class="nav-text">Sato Printer</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="../SATO/sato_request.php">List of Printer</a></li>
                        <li><a class="nav-sub-link" href="../SATO/printerhistory.php">Printer History</a></li>
                    </ul>
                </li>
                <div class="nav-divider"></div>
                <li class="nav-section-label">Apps &amp; Pages</li>
                <li class="nav-item nav-accordion" id="userAcc">
                    <a class="nav-link" href="#" onclick="toggleAcc('userAcc');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                        <span class="nav-text">Users</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
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
                            <div class="dropdown-email"><?= htmlspecialchars($sessionUser['email']) ?></div><span
                                class="dropdown-role"><?= $isAdmin ? 'Admin' : 'User' ?></span>
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
            <div class="breadcrumb"><a href="#">Home</a><span><i class="fa fa-chevron-right"
                        style="font-size:9px;"></i></span><a href="#">Master Control</a><span><i
                        class="fa fa-chevron-right" style="font-size:9px;"></i></span><span
                    style="color:var(--text-mid);">Incident Report</span></div>
            <?php if (!$isAdmin): ?>
                <div class="access-notice"><i class="fa-solid fa-circle-info"></i> You are logged in as a
                    <strong>User</strong>. Management actions are restricted to Admins only.
                </div><?php endif; ?>

            <!-- Stat Cards -->
            <div class="stat-grid">
                <div class="stat-card" id="sc-total" onclick="filterByCard('total')" data-tip="Show all">
                    <div>
                        <div class="stat-label">Total</div>
                        <div class="stat-value" id="stat-total"><?= $totalRecords ?></div>
                        <div class="stat-sub">All Incidents</div>
                    </div>
                    <div class="stat-icon crimson"><i class="fa-solid fa-circle-exclamation"></i></div>
                </div>
                <div class="stat-card fc-green" id="sc-done" onclick="filterByCard('done')" data-tip="Filter: Received">
                    <div>
                        <div class="stat-label">Received</div>
                        <div class="stat-value" id="stat-done"><?= $doneRecords ?></div>
                        <div class="stat-sub">Date received set</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-card fc-orange" id="sc-pending" onclick="filterByCard('pending')"
                    data-tip="Filter: Pending">
                    <div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" id="stat-pending"><?= $pendingRecords ?></div>
                        <div class="stat-sub">Open incidents</div>
                    </div>
                    <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="stat-card fc-teal" id="sc-today" onclick="filterByCard('today')" data-tip="Filter: Today">
                    <div>
                        <div class="stat-label">Today</div>
                        <div class="stat-value" id="stat-today"><?= $todayRecords ?></div>
                        <div class="stat-sub">Reported today</div>
                    </div>
                    <div class="stat-icon teal"><i class="fa-solid fa-calendar-day"></i></div>
                </div>
                <div class="stat-card fc-blue" id="sc-month" onclick="filterByCard('month')"
                    data-tip="Filter: This Month">
                    <div>
                        <div class="stat-label">This Month</div>
                        <div class="stat-value" id="stat-month"><?= $thisMonth ?></div>
                        <div class="stat-sub"><?= date('F Y') ?></div>
                    </div>
                    <div class="stat-icon blue"><i class="fa-solid fa-calendar-week"></i></div>
                </div>
                <div class="stat-card fc-red" id="sc-rootcause" onclick="filterByCard('rootcause')"
                    data-tip="Filter: Has Root Cause">
                    <div>
                        <div class="stat-label">Root Cause</div>
                        <div class="stat-value" id="stat-rootcause"><?= $withRootCause ?></div>
                        <div class="stat-sub">With root cause</div>
                    </div>
                    <div class="stat-icon crimson"><i class="fa-solid fa-magnifying-glass"></i></div>
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
                    <div class="toolbar-spacer"></div>
                    <div class="search-input-wrap"><i class="fa fa-search"
                            style="color:var(--text-light);font-size:12px;"></i><input type="text" id="table-search"
                            placeholder="Search Incident" oninput="renderTable()"></div>
                    <button class="btn btn-outline" onclick="exportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?><button class="btn btn-primary" onclick="openAddModal()"><i
                                class="fa fa-plus"></i> Add Record</button><?php endif; ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="cb" id="select-all" onchange="toggleAll(this)"></th>
                            <th>Request No.</th>
                            <th>Ext. No.</th>
                            <th>Section</th>
                            <th>Date Requested</th>
                            <th>Date Received</th>
                            <th>Imp. Date</th>
                            <th>Performed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recordTableBody"></tbody>
                </table>
                <div class="pagination-row">
                    <div class="page-info" id="page-info">Showing 0 to 0 of 0 entries</div>
                    <div class="page-btns" id="page-btns"></div>
                </div>
            </div>
        </main>
    </div>

    <?php if ($isAdmin): ?>
        <!-- ADD MODAL -->
        <div class="ce-modal-overlay" id="add-modal">
            <div class="ce-modal">
                <div class="ce-modal-header" style="background:linear-gradient(135deg,#ff3e1d,#c0392b);">
                    <div class="ce-modal-header-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                    <div>
                        <h3>Add New Incident Report</h3>
                        <p>Fill in the fields below. Request Number is auto-generated.</p>
                    </div>
                </div>
                <div class="ce-modal-body">
                    <div id="modal-alert" class="modal-alert"></div>
                    <div class="form-section"><i class="fa-solid fa-calendar-days"></i> Request Dates</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Date Requested <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="add-date_requested" type="date"></div>
                        <div><label class="modal-label">Date Received</label><input class="modal-input"
                                id="add-date_received" type="date"></div>
                    </div>
                    <div class="form-section"><i class="fa-solid fa-hashtag"></i> Request Info</div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">Request Number</label>
                            <div class="rn-chip" id="add-rn-display"><?= htmlspecialchars($nextRequestNumber) ?></div>
                            <input type="hidden" id="add-request_number"
                                value="<?= htmlspecialchars($nextRequestNumber) ?>">
                            <div class="rn-hint">Next sequential number for current year.</div>
                        </div>
                        <div><label class="modal-label">Extension Number</label><input class="modal-input"
                                id="add-ext_number" type="text" placeholder="e.g. 0001"></div>
                    </div>
                    <div class="modal-field"><label class="modal-label">Requesting Section</label><input class="modal-input"
                            id="add-request_section" type="text" placeholder="e.g. ICT / Warehouse"></div>
                    <div class="form-section"><i class="fa-solid fa-file-lines"></i> Incident Details</div>
                    <div class="modal-field"><label class="modal-label">Information</label><textarea class="modal-input"
                            id="add-information" rows="3" placeholder="Describe the incident..."></textarea></div>
                    <div class="modal-field"><label class="modal-label">Master Control Root Cause</label><textarea
                            class="modal-input" id="add-root_cause" rows="3"
                            placeholder="Root cause analysis..."></textarea></div>
                    <div class="form-section"><i class="fa-solid fa-user-check"></i> Action</div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">Performed By</label>
                            <select class="modal-input" id="add-performed">
                                <option value="">— Select Associate —</option>
                                <?php foreach ($associates as $a):
                                    $rl = ucfirst($a['role'] ?? '');
                                    $ai = $a['associate_id'] ? ' · ' . $a['associate_id'] : ''; ?>
                                    <option value="<?= htmlspecialchars($a['full_name']) ?>">
                                        <?= htmlspecialchars($a['full_name'] . $ai) ?> (<?= htmlspecialchars($rl) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="modal-label">Implementation Date</label><input class="modal-input"
                                id="add-imp_date" type="date"></div>
                    </div>
                </div>
                <div class="ce-modal-footer">
                    <button class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
                    <button class="btn-modal-submit" style="background:linear-gradient(135deg,#ff3e1d,#c0392b);"
                        onclick="submitAddRecord()"><i class="fa fa-plus"></i> Add Record</button>
                </div>
            </div>
        </div>
        <!-- EDIT MODAL -->
        <div class="ce-modal-overlay" id="edit-modal">
            <div class="ce-modal">
                <div class="ce-modal-header" style="background:linear-gradient(135deg,#ff9f43,#f7b731);">
                    <div class="ce-modal-header-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div>
                        <h3>Edit Incident Report</h3>
                        <p>Update the record's information below.</p>
                    </div>
                </div>
                <div class="ce-modal-body">
                    <div id="edit-modal-alert" class="modal-alert"></div>
                    <input type="hidden" id="edit-record_id">
                    <div class="form-section"><i class="fa-solid fa-calendar-days"></i> Request Dates</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Date Requested <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="edit-date_requested" type="date"></div>
                        <div><label class="modal-label">Date Received</label><input class="modal-input"
                                id="edit-date_received" type="date"></div>
                    </div>
                    <div class="form-section"><i class="fa-solid fa-hashtag"></i> Request Info</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Request Number <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="edit-request_number" type="text"></div>
                        <div><label class="modal-label">Extension Number</label><input class="modal-input"
                                id="edit-ext_number" type="text"></div>
                    </div>
                    <div class="modal-field"><label class="modal-label">Requesting Section</label><input class="modal-input"
                            id="edit-request_section" type="text"></div>
                    <div class="form-section"><i class="fa-solid fa-file-lines"></i> Incident Details</div>
                    <div class="modal-field"><label class="modal-label">Information</label><textarea class="modal-input"
                            id="edit-information" rows="3"></textarea></div>
                    <div class="modal-field"><label class="modal-label">Master Control Root Cause</label><textarea
                            class="modal-input" id="edit-root_cause" rows="3"></textarea></div>
                    <div class="form-section"><i class="fa-solid fa-user-check"></i> Action</div>
                    <div class="modal-grid2">
                        <div>
                            <label class="modal-label">Performed By</label>
                            <select class="modal-input" id="edit-performed">
                                <option value="">— Select Associate —</option>
                                <?php foreach ($associates as $a):
                                    $rl = ucfirst($a['role'] ?? '');
                                    $ai = $a['associate_id'] ? ' · ' . $a['associate_id'] : ''; ?>
                                    <option value="<?= htmlspecialchars($a['full_name']) ?>">
                                        <?= htmlspecialchars($a['full_name'] . $ai) ?> (<?= htmlspecialchars($rl) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="modal-label">Implementation Date</label><input class="modal-input"
                                id="edit-imp_date" type="date"></div>
                    </div>
                </div>
                <div class="ce-modal-footer">
                    <button class="btn-modal-cancel" onclick="closeEditModal()">Cancel</button>
                    <button class="btn-modal-submit" style="background:linear-gradient(135deg,#ff9f43,#f7b731);"
                        onclick="submitEditRecord()"><i class="fa fa-floppy-disk"></i> Save Changes</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- VIEW MODAL -->
    <div class="view-modal-overlay" id="view-modal">
        <div class="view-modal">
            <div class="view-modal-header">
                <div class="view-icon-wrap"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div>
                    <div class="view-name" id="view-request_number">—</div>
                    <div class="view-sub" id="view-request_section_sub">—</div>
                </div>
            </div>
            <div class="view-grid">
                <div class="view-field"><label>Date Requested</label><span id="view-date_requested"></span></div>
                <div class="view-field"><label>Date Received</label><span id="view-date_received"></span></div>
                <div class="view-field"><label>Ext. Number</label><span id="view-ext_number"></span></div>
                <div class="view-field"><label>Section</label><span id="view-request_section"></span></div>
                <div class="view-field"><label>Performed By</label><span id="view-performed"></span></div>
                <div class="view-field"><label>Imp. Date</label><span id="view-imp_date"></span></div>
                <div class="view-field full"><label>Information</label><span id="view-information"></span></div>
                <div class="view-field full"><label>Root Cause</label><span id="view-root_cause"></span></div>
            </div>
            <div class="view-modal-footer">
                <button class="btn-close-view" onclick="closeViewModal()">Close</button>
                <!-- <?php if ($isAdmin): ?><button class="btn-edit-view" onclick="switchToEdit()"><i class="fa fa-pen"></i>
                        Edit</button><?php endif; ?> -->
            </div>
        </div>
    </div>
    <!-- DELETE -->
    <div class="del-modal-overlay" id="del-modal">
        <div class="del-modal">
            <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="del-title">Delete Incident?</div>
            <div class="del-sub">You are about to delete <strong id="del-name"></strong>.<br>This action cannot be
                undone.</div>
            <div class="del-btns"><button class="btn-cancel-del" onclick="closeDelModal()">Cancel</button><button
                    class="btn-confirm-del" onclick="confirmDelete()"><i class="fa fa-trash"></i> Delete</button></div>
        </div>
    </div>
    <!-- RESULT -->
    <div class="result-overlay" id="result-modal">
        <div class="result-box">
            <div class="result-icon-wrap" id="result-icon-wrap"><i id="result-icon"></i></div>
            <div class="result-title" id="result-title"></div>
            <div class="result-msg" id="result-msg"></div>
            <div class="result-detail" id="result-detail" style="display:none;"></div>
            <button class="btn-result-ok" id="result-ok-btn" onclick="closeResultModal()">OK</button>
        </div>
    </div>
    <div class="toast" id="toast"></div>

    <script>
        const ALL_RECORDS = <?= json_encode(array_values($allRecords)) ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const TODAY = '<?= date('Y-m-d') ?>'; const THIS_MONTH = '<?= date('Y-m') ?>';
        let allRecords = [...ALL_RECORDS], curPage = 1, perPage = 10;
        let _pendingDeleteId = null, _pendingDeleteName = null, _currentViewRecord = null, _filterType = null, _activeCardFilter = null;

        function getFiltered() {
            const q = (document.getElementById('table-search')?.value || '').toLowerCase();
            return allRecords.filter(r => {
                const ms = !q || [r.request_number, r.ext_number, r.request_section, r.information, r.root_cause, r.performed, r.date_requested, r.date_received].some(f => (f || '').toLowerCase().includes(q));
                let mf = true;
                if (_filterType === 'done') mf = !!r.date_received;
                if (_filterType === 'pending') mf = !r.date_received;
                if (_filterType === 'today') mf = r.date_requested === TODAY;
                if (_filterType === 'month') mf = (r.date_requested || '').startsWith(THIS_MONTH);
                if (_filterType === 'rootcause') mf = !!r.root_cause;
                return ms && mf;
            });
        }
        function renderTable() {
            const f = getFiltered(), total = f.length, tp = Math.ceil(total / perPage) || 1;
            if (curPage > tp) curPage = tp;
            const start = (curPage - 1) * perPage, paged = f.slice(start, start + perPage);
            document.getElementById('recordTableBody').innerHTML = paged.map(r => `
<tr>
  <td><input type="checkbox" class="cb row-cb" data-id="${r.record_id}"></td>
  <td><span class="ic-badge"><i class="fa-solid fa-hashtag" style="font-size:10px;"></i> ${r.request_number || '—'}</span></td>
  <td style="font-size:13.5px;color:var(--text-mid);">${r.ext_number || '—'}</td>
  <td style="font-size:13.5px;color:var(--text-mid);">${r.request_section || '—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.date_requested || '—'}</td>
  <td style="font-size:13px;">${r.date_received ? `<span style="color:#28c76f;font-weight:600;">${r.date_received}</span>` : `<span style="color:#ff9f43;font-size:12px;font-weight:600;">Pending</span>`}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.imp_date || '—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.performed || '—'}</td>
  <td><div class="action-cell">
    <button class="action-btn view-btn" title="View" onclick='openViewModal(${r.record_id})'><i class="fa fa-eye"></i></button>
    ${IS_ADMIN ? `<button class="action-btn edit-btn" title="Edit" onclick='openEditModal(${r.record_id})'><i class="fa fa-pen"></i></button>
    <button class="action-btn del" title="Delete" onclick='openDelModal(${r.record_id},"${(r.request_number || "Record #" + r.record_id).replace(/"/g, "")}")'><i class="fa fa-trash"></i></button>` : ''}
  </div></td>
</tr>`).join('');
            const from = total === 0 ? 0 : start + 1, to = Math.min(start + perPage, total);
            document.getElementById('page-info').textContent = `Showing ${from} to ${to} of ${total} entries`;
            renderPageBtns(tp); updateStats();
        }
        function renderPageBtns(tp) {
            const c = document.getElementById('page-btns'), max = 5; let html = '';
            html += `<button class="page-btn ${curPage === 1 ? 'disabled' : ''}" onclick="goPage(${curPage - 1})"><i class="fa fa-angles-left"></i></button>`;
            html += `<button class="page-btn ${curPage === 1 ? 'disabled' : ''}" onclick="goPage(${curPage - 1})"><i class="fa fa-angle-left"></i></button>`;
            let s = Math.max(1, curPage - Math.floor(max / 2)), e = Math.min(tp, s + max - 1);
            if (e - s < max - 1) s = Math.max(1, e - max + 1);
            for (let i = s; i <= e; i++)html += `<button class="page-btn ${i === curPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
            html += `<button class="page-btn ${curPage === tp ? 'disabled' : ''}" onclick="goPage(${curPage + 1})"><i class="fa fa-angle-right"></i></button>`;
            html += `<button class="page-btn ${curPage === tp ? 'disabled' : ''}" onclick="goPage(${tp})"><i class="fa fa-angles-right"></i></button>`;
            c.innerHTML = html;
        }
        function goPage(p) { const t = Math.ceil(getFiltered().length / perPage) || 1; if (p < 1 || p > t) return; curPage = p; renderTable(); }
        function perPageChanged() { perPage = parseInt(document.getElementById('per-page').value); curPage = 1; renderTable(); }
        function updateStats() {
            document.getElementById('stat-total').textContent = allRecords.length;
            document.getElementById('stat-done').textContent = allRecords.filter(r => !!r.date_received).length;
            document.getElementById('stat-pending').textContent = allRecords.filter(r => !r.date_received).length;
            document.getElementById('stat-today').textContent = allRecords.filter(r => r.date_requested === TODAY).length;
            document.getElementById('stat-month').textContent = allRecords.filter(r => (r.date_requested || '').startsWith(THIS_MONTH)).length;
            document.getElementById('stat-rootcause').textContent = allRecords.filter(r => !!r.root_cause).length;
        }
        function filterByCard(type) {
            if (_activeCardFilter === type || type === 'total') { _filterType = null; _activeCardFilter = null; _highlightCard(null); curPage = 1; renderTable(); return; }
            _filterType = type; _activeCardFilter = type; _highlightCard(type); curPage = 1; renderTable();
        }
        function _highlightCard(type) {
            const map = { done: 'sc-done', pending: 'sc-pending', today: 'sc-today', month: 'sc-month', rootcause: 'sc-rootcause' };
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            if (type && map[type]) document.getElementById(map[type])?.classList.add('active-filter');
        }
        function syncSearch() { document.getElementById('table-search').value = document.getElementById('topbar-search').value; curPage = 1; renderTable(); }
        function toggleAll(cb) { document.querySelectorAll('.row-cb').forEach(r => r.checked = cb.checked); }
        function showResult(type, title, msg, rows = []) {
            const iw = document.getElementById('result-icon-wrap'), ic = document.getElementById('result-icon');
            iw.className = `result-icon-wrap ${type}`; ic.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
            document.getElementById('result-title').textContent = title; document.getElementById('result-msg').textContent = msg;
            document.getElementById('result-ok-btn').className = type === 'error' ? 'btn-result-ok danger' : 'btn-result-ok';
            const de = document.getElementById('result-detail');
            if (rows.length) { de.innerHTML = rows.map(([l, v]) => `<div class="rd-row"><span class="rd-label">${l}</span><span>${v}</span></div>`).join(''); de.style.display = 'block'; } else de.style.display = 'none';
            document.getElementById('result-modal').classList.add('open');
        }
        function closeResultModal() { document.getElementById('result-modal').classList.remove('open'); }
        function openViewModal(id) {
            const r = allRecords.find(x => x.record_id == id); if (!r) return; _currentViewRecord = r;
            document.getElementById('view-request_number').textContent = r.request_number || '—';
            document.getElementById('view-request_section_sub').textContent = r.request_section || '—';
            document.getElementById('view-date_requested').textContent = r.date_requested || '—';
            document.getElementById('view-date_received').textContent = r.date_received || 'Pending';
            document.getElementById('view-ext_number').textContent = r.ext_number || '—';
            document.getElementById('view-request_section').textContent = r.request_section || '—';
            document.getElementById('view-performed').textContent = r.performed || '—';
            document.getElementById('view-imp_date').textContent = r.imp_date || '—';
            document.getElementById('view-information').textContent = r.information || '—';
            document.getElementById('view-root_cause').textContent = r.root_cause || '—';
            document.getElementById('view-modal').classList.add('open');
        }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('open'); _currentViewRecord = null; }
        function switchToEdit() { if (!_currentViewRecord) return; closeViewModal(); openEditModal(_currentViewRecord.record_id); }
        function openAddModal() {
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '__action=get_next_request_number' })
                .then(r => r.json()).then(d => { if (d.success) { document.getElementById('add-rn-display').textContent = d.request_number; document.getElementById('add-request_number').value = d.request_number; } }).catch(() => { });
            ['add-date_requested', 'add-date_received', 'add-ext_number', 'add-request_section', 'add-information', 'add-root_cause', 'add-imp_date'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('add-performed').value = '';
            hideAlert('modal-alert'); document.getElementById('add-modal').classList.add('open');
        }
        function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); }
        document.getElementById('add-modal')?.addEventListener('click', function (e) { if (e.target === this) closeAddModal(); });
        function submitAddRecord() {
            const date_requested = document.getElementById('add-date_requested').value.trim();
            const date_received = document.getElementById('add-date_received').value.trim();
            const request_number = document.getElementById('add-request_number').value.trim();
            const ext_number = document.getElementById('add-ext_number').value.trim();
            const request_section = document.getElementById('add-request_section').value.trim();
            const information = document.getElementById('add-information').value.trim();
            const root_cause = document.getElementById('add-root_cause').value.trim();
            const performed = document.getElementById('add-performed').value.trim();
            const imp_date = document.getElementById('add-imp_date').value.trim();
            hideAlert('modal-alert');
            if (!date_requested || !request_number) { showAlert('modal-alert', 'Date Requested and Request Number are required.'); return; }
            fetch(window.location.pathname, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ __action: 'add_record', date_requested, date_received, request_number, ext_number, request_section, information, root_cause, performed, imp_date }).toString()
            })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        allRecords.unshift(d.record); curPage = 1; renderTable(); closeAddModal();
                        showResult('success', 'Incident Added!', 'Incident report has been saved.', [['🚨 Request No.', d.record.request_number], ['📁 Section', d.record.request_section || '—'], ['👨‍💼 Performed', d.record.performed || '—']]);
                    }
                    else showAlert('modal-alert', d.message || 'Failed to add.');
                }).catch(() => showAlert('modal-alert', 'Network error.'));
        }
        function openEditModal(id) {
            const r = allRecords.find(x => x.record_id == id); if (!r) return;
            document.getElementById('edit-record_id').value = r.record_id;
            document.getElementById('edit-date_requested').value = r.date_requested || '';
            document.getElementById('edit-date_received').value = r.date_received || '';
            document.getElementById('edit-request_number').value = r.request_number || '';
            document.getElementById('edit-ext_number').value = r.ext_number || '';
            document.getElementById('edit-request_section').value = r.request_section || '';
            document.getElementById('edit-information').value = r.information || '';
            document.getElementById('edit-root_cause').value = r.root_cause || '';
            document.getElementById('edit-performed').value = r.performed || '';
            document.getElementById('edit-imp_date').value = r.imp_date || '';
            hideAlert('edit-modal-alert'); document.getElementById('edit-modal').classList.add('open');
        }
        function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); }
        document.getElementById('edit-modal')?.addEventListener('click', function (e) { if (e.target === this) closeEditModal(); });
        function submitEditRecord() {
            const id = document.getElementById('edit-record_id').value;
            const date_requested = document.getElementById('edit-date_requested').value.trim();
            const date_received = document.getElementById('edit-date_received').value.trim();
            const request_number = document.getElementById('edit-request_number').value.trim();
            const ext_number = document.getElementById('edit-ext_number').value.trim();
            const request_section = document.getElementById('edit-request_section').value.trim();
            const information = document.getElementById('edit-information').value.trim();
            const root_cause = document.getElementById('edit-root_cause').value.trim();
            const performed = document.getElementById('edit-performed').value.trim();
            const imp_date = document.getElementById('edit-imp_date').value.trim();
            hideAlert('edit-modal-alert');
            if (!date_requested || !request_number) { showAlert('edit-modal-alert', 'Date Requested and Request Number are required.'); return; }
            fetch(window.location.pathname, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ __action: 'edit_record', id, date_requested, date_received, request_number, ext_number, request_section, information, root_cause, performed, imp_date }).toString()
            })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        const idx = allRecords.findIndex(r => r.record_id == id); if (idx !== -1) allRecords[idx] = { ...allRecords[idx], ...d.record }; renderTable(); closeEditModal();
                        showResult('success', 'Record Updated!', 'Incident report has been updated.', [['🚨 Request No.', d.record.request_number], ['📁 Section', d.record.request_section || '—']]);
                    }
                    else showAlert('edit-modal-alert', d.message || 'Failed to update.');
                }).catch(() => showAlert('edit-modal-alert', 'Network error.'));
        }
        function openDelModal(id, name) { _pendingDeleteId = id; _pendingDeleteName = name; document.getElementById('del-name').textContent = name; document.getElementById('del-modal').classList.add('open'); }
        function closeDelModal() { document.getElementById('del-modal').classList.remove('open'); _pendingDeleteId = _pendingDeleteName = null; }
        function confirmDelete() {
            if (!_pendingDeleteId) return; const dn = _pendingDeleteName;
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `__action=delete_record&id=${_pendingDeleteId}` })
                .then(r => r.json()).then(d => { closeDelModal(); if (d.success) { allRecords = allRecords.filter(r => r.record_id != _pendingDeleteId); renderTable(); showResult('success', 'Deleted', `"${dn}" removed.`); } else showResult('error', 'Failed', d.message || 'Could not delete.'); })
                .catch(() => { closeDelModal(); showResult('error', 'Network Error', 'Could not reach the server.'); });
        }
        function showAlert(elId, msg) { const el = document.getElementById(elId); if (!el) return; el.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${msg}`; el.style.display = 'flex'; }
        function hideAlert(elId) { const el = document.getElementById(elId); if (el) el.style.display = 'none'; }
        function showToast(msg, type = 'success') { const t = document.getElementById('toast'); t.textContent = msg; t.className = `toast ${type} show`; clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 3200); }
        function exportCSV() {
            const rows = [['Record ID', 'Request No.', 'Ext No.', 'Section', 'Date Requested', 'Date Received', 'Imp Date', 'Performed', 'Information', 'Root Cause']];
            getFiltered().forEach(r => rows.push([r.record_id, r.request_number || '', r.ext_number || '', r.request_section || '', r.date_requested || '', r.date_received || '', r.imp_date || '', r.performed || '', r.information || '', r.root_cause || '']));
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' })); a.download = 'incident_records.csv'; a.click();
            showToast('Exported!', 'success');
        }
        function toggleAcc(id) { document.getElementById(id).classList.toggle('open'); }
        renderTable();
    </script>
</body>

</html>