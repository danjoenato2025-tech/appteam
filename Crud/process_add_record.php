<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

include('../dbconnection/config.php');

/* ══════════════════════════════════════════════════════════════
   INLINE AJAX HANDLER — this file handles its own CRUD.
   JS fetch() calls POST back to THIS same file with __action.
   Returns JSON and exits — no separate process_*.php needed.
══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action'])) {
    header('Content-Type: application/json');

    // Re-build session check (already done above but guard again)
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $isAdminAjax = ($_SESSION['role'] === 'admin');

    switch ($_POST['__action']) {

        // ── ADD ──────────────────────────────────────────────
        case 'add_record':
            if (!$isAdminAjax) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $dr = trim($_POST['date_requested'] ?? '');
            $drx = trim($_POST['date_received'] ?? '') ?: null;
            $rn = trim($_POST['request_number'] ?? '');
            $en = trim($_POST['ext_number'] ?? '') ?: null;
            $rs = trim($_POST['request_section'] ?? '');
            $pf = trim($_POST['performed'] ?? '') ?: null;
            $id2 = trim($_POST['imp_date'] ?? '') ?: null;
            $inf = trim($_POST['information'] ?? '') ?: null;
            $rsn = trim($_POST['reason'] ?? '') ?: null;
            if (!$dr || !$rn || !$rs) {
                echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
                exit;
            }
            $s = $pdo->prepare('INSERT INTO request_record (date_requested,date_received,request_number,ext_number,request_section,information,reason,performed,imp_date) VALUES (?,?,?,?,?,?,?,?,?)');
            $s->execute([$dr, $drx, $rn, $en, $rs, $inf, $rsn, $pf, $id2]);
            echo json_encode(['success' => true, 'record' => ['record_id' => (int) $pdo->lastInsertId(), 'date_requested' => $dr, 'date_received' => $drx, 'request_number' => $rn, 'ext_number' => $en, 'request_section' => $rs, 'information' => $inf, 'reason' => $rsn, 'performed' => $pf, 'imp_date' => $id2]]);
            exit;

        // ── EDIT ─────────────────────────────────────────────
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
            $rs = trim($_POST['request_section'] ?? '');
            $pf = trim($_POST['performed'] ?? '') ?: null;
            $id2 = trim($_POST['imp_date'] ?? '') ?: null;
            $inf = trim($_POST['information'] ?? '') ?: null;
            $rsn = trim($_POST['reason'] ?? '') ?: null;
            if (!$id || !$dr || !$rn || !$rs) {
                echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
                exit;
            }
            $s = $pdo->prepare('UPDATE request_record SET date_requested=?,date_received=?,request_number=?,ext_number=?,request_section=?,information=?,reason=?,performed=?,imp_date=? WHERE record_id=?');
            $s->execute([$dr, $drx, $rn, $en, $rs, $inf, $rsn, $pf, $id2, $id]);
            echo json_encode(['success' => true, 'record' => ['record_id' => $id, 'date_requested' => $dr, 'date_received' => $drx, 'request_number' => $rn, 'ext_number' => $en, 'request_section' => $rs, 'information' => $inf, 'reason' => $rsn, 'performed' => $pf, 'imp_date' => $id2]]);
            exit;

        // ── DELETE ───────────────────────────────────────────
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
            $pdo->prepare('DELETE FROM request_record WHERE record_id=?')->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }
}

$sessionUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'full_name' => $_SESSION['full_name'],
    'role' => $_SESSION['role'],
    'email' => $_SESSION['email'],
    'color' => $_SESSION['color'],
];
$isAdmin = $sessionUser['role'] === 'admin';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $sessionUser['full_name']), 0, 2)));

// ── Fetch all request records ─────────────────────────────────────────
$allRecords = $pdo->query('
    SELECT record_id, date_requested, date_received, request_number,
           ext_number, request_section, information, reason, performed, imp_date
    FROM request_record ORDER BY record_id DESC
')->fetchAll();

$totalRecords = count($allRecords);
$todayRecords = count(array_filter($allRecords, fn($r) => $r['date_requested'] === date('Y-m-d')));
$pendingRecords = count(array_filter($allRecords, fn($r) => empty($r['date_received'])));
$doneRecords = count(array_filter($allRecords, fn($r) => !empty($r['date_received'])));
$thisMonth = count(array_filter($allRecords, fn($r) => strpos($r['date_requested'] ?? '', date('Y-m')) === 0));
$withInfo = count(array_filter($allRecords, fn($r) => !empty($r['information'])));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Record – System</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        /* ══ 6-column stat grid ══ */
        .stat-grid {
            grid-template-columns: repeat(6, 1fr) !important;
        }

        @media(max-width:1200px) {
            .stat-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        @media(max-width:700px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        .stat-icon.grey {
            background: #f0f1f5;
            color: #8a93a2;
        }

        .stat-icon.red {
            background: #ff3e1d12;
            color: #ff3e1d;
        }

        .stat-icon.blue {
            background: #696cff12;
            color: #696cff;
        }

        .stat-icon.teal {
            background: #00bcd412;
            color: #00bcd4;
        }

        .stat-icon.pink {
            background: #e91e6312;
            color: #e91e63;
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
            border-color: #696cff;
            box-shadow: 0 0 0 3px #696cff22;
        }

        .stat-card.active-filter .stat-label::after {
            content: ' ✕';
            font-size: 10px;
            color: #696cff;
            font-weight: 700;
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

        .stat-card.active-filter.fc-pink {
            border-color: #e91e63;
            box-shadow: 0 0 0 3px #e91e6322;
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

        /* ══ Action buttons ══ */
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
            background: #edfaf4;
            color: #28c76f;
        }

        /* ══ View Modal ══ */
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
            max-width: 520px;
            width: 94%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes vmIn {
            from {
                transform: scale(.8);
                opacity: 0
            }

            to {
                transform: scale(1);
                opacity: 1
            }
        }

        .view-modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .view-icon-wrap {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #696cff, #9b59f5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
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
            background: linear-gradient(135deg, #696cff, #9b59f5);
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

        /* ══ Add/Edit Modal ══ */
        .edit-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 60, .45);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .edit-modal-overlay.open {
            display: flex;
        }

        .edit-modal {
            background: #fff;
            border-radius: 14px;
            padding: 30px 30px 24px;
            max-width: 600px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        /* ══ Delete Modal ══ */
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

        /* ══ Result Modal ══ */
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
            background: linear-gradient(135deg, #696cff, #9b59f5);
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

        /* ══ Section badge ══ */
        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: #696cff12;
            color: #696cff;
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
                        <li><a class="nav-sub-link" href="#">Change and Cancellation</a></li>
                        <li><a class="nav-sub-link" href="#">New User Registration</a></li>
                        <li><a class="nav-sub-link" href="#">Password Request</a></li>
                        <li><a class="nav-sub-link active" href="requestrecord_page.php">Request Record</a></li>
                        <li><a class="nav-sub-link" href="#">Incident Report</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="QADControl">
                    <a class="nav-link" href="#" onclick="toggleAcc('QADControl');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Queen's Annes Drive</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="#">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="Lasys">
                    <a class="nav-link" href="#" onclick="toggleAcc('Lasys');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Label Assurance System</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="#">Monitoring Request</a></li>
                    </ul>
                </li>
                <li class="nav-item nav-accordion" id="printerAcc">
                    <a class="nav-link" href="#" onclick="toggleAcc('printerAcc');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-print"></i></span>
                        <span class="nav-text">Sato Printer</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
                    </a>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link" href="#">List of Printer</a></li>
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
                        <li><a class="nav-sub-link <?php echo ($current_page == 'tbl_userlist.php') ? 'active' : ''; ?>"
                                href="tbl_userlist.php">List</a></li>
                        <li><a class="nav-sub-link <?php echo ($current_page == 'user_account.php') ? 'active' : ''; ?>"
                                href="user_account.php">Account Settings</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- ════ PAGE WRAPPER ════ -->
    <div class="page-wrapper">
        <header class="topbar">
            <div class="topbar-search">
                <i class="fa fa-search" style="color:var(--text-light);font-size:13px;"></i>
                <input type="text" placeholder="Search (CTRL + K)" id="topbar-search" oninput="syncSearch()">
            </div>
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
                <a href="#">Home</a>
                <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                <a href="#">Master Control</a>
                <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                <span style="color:var(--text-mid);">Request Record</span>
            </div>

            <?php if (!$isAdmin): ?>
                <div class="access-notice">
                    <i class="fa-solid fa-circle-info"></i>
                    You are logged in as a <strong>User</strong>. Management actions are restricted to Admins only.
                </div>
            <?php endif; ?>

            <!-- ── Stat Cards ── -->
            <div class="stat-grid">
                <div class="stat-card" id="sc-total" onclick="filterByCard('total')" data-tip="Show all records">
                    <div>
                        <div class="stat-label">Session</div>
                        <div class="stat-value" id="stat-total"><?= $totalRecords ?></div>
                        <div class="stat-sub">Total Records</div>
                    </div>
                    <div class="stat-icon purple"><i class="fa-solid fa-folder-open"></i></div>
                </div>
                <div class="stat-card fc-green" id="sc-received" onclick="filterByCard('received')"
                    data-tip="Filter: Received">
                    <div>
                        <div class="stat-label">Received</div>
                        <div class="stat-value" id="stat-received"><?= $doneRecords ?></div>
                        <div class="stat-sub">Date received set</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-card fc-orange" id="sc-pending" onclick="filterByCard('pending')"
                    data-tip="Filter: Pending">
                    <div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" id="stat-pending"><?= $pendingRecords ?></div>
                        <div class="stat-sub">No received date</div>
                    </div>
                    <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="stat-card fc-teal" id="sc-today" onclick="filterByCard('today')" data-tip="Filter: Today">
                    <div>
                        <div class="stat-label">Today</div>
                        <div class="stat-value" id="stat-today"><?= $todayRecords ?></div>
                        <div class="stat-sub">Requested today</div>
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
                <div class="stat-card fc-pink" id="sc-info" onclick="filterByCard('info')"
                    data-tip="Filter: Has Information">
                    <div>
                        <div class="stat-label">With Info</div>
                        <div class="stat-value" id="stat-info"><?= $withInfo ?></div>
                        <div class="stat-sub">Has information field</div>
                    </div>
                    <div class="stat-icon pink"><i class="fa-solid fa-circle-info"></i></div>
                </div>
            </div>

            <!-- ── Table ── -->
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="rows-select">
                        <select id="per-page" onchange="perPageChanged()">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="toolbar-spacer"></div>
                    <div class="search-input-wrap">
                        <i class="fa fa-search" style="color:var(--text-light);font-size:12px;"></i>
                        <input type="text" id="table-search" placeholder="Search Record" oninput="renderTable()">
                    </div>
                    <button class="btn btn-outline" onclick="exportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?>
                        <button class="btn btn-primary" onclick="openAddModal()"><i class="fa fa-plus"></i> Add
                            Record</button>
                    <?php endif; ?>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="cb" id="select-all" onchange="toggleAll(this)"></th>
                            <th>Request No.</th>
                            <th>Ext. Number</th>
                            <th>Section</th>
                            <th>Date Requested</th>
                            <th>Date Received</th>
                            <th>Performed</th>
                            <th>Imp. Date</th>
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

    <!-- ════ ADD RECORD MODAL ════ -->
    <?php if ($isAdmin): ?>
        <div class="modal-overlay" id="add-modal">
            <div class="modal">
                <div class="modal-title">Add New Request Record</div>
                <div class="modal-sub">Fill in all the required fields below.</div>
                <div id="modal-alert" style="display:none;" class="modal-alert error"></div>

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Date Requested</label>
                        <input class="modal-input" id="add-date_requested" type="date">
                    </div>
                    <div>
                        <label class="modal-label">Date Received</label>
                        <input class="modal-input" id="add-date_received" type="date">
                    </div>
                </div>

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Request Number</label>
                        <input class="modal-input" id="add-request_number" type="text" placeholder="e.g. REQ-2024-001">
                    </div>
                    <div>
                        <label class="modal-label">Ext. Number</label>
                        <input class="modal-input" id="add-ext_number" type="text" placeholder="e.g. EXT-001">
                    </div>
                </div>

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Request Section</label>
                        <input class="modal-input" id="add-request_section" type="text" placeholder="e.g. IT Department">
                    </div>
                    <div>
                        <label class="modal-label">Performed By</label>
                        <input class="modal-input" id="add-performed" type="text" placeholder="e.g. John Doe">
                    </div>
                </div>

                <label class="modal-label">Imp. Date</label>
                <input class="modal-input" id="add-imp_date" type="date">

                <label class="modal-label">Information</label>
                <textarea class="modal-input" id="add-information" rows="3" placeholder="Enter relevant information..."
                    style="resize:vertical;"></textarea>

                <label class="modal-label">Reason</label>
                <textarea class="modal-input" id="add-reason" rows="3" placeholder="Enter reason for request..."
                    style="resize:vertical;"></textarea>

                <div class="modal-row" style="margin-top:18px;">
                    <button class="modal-cancel" onclick="closeAddModal()">Cancel</button>
                    <button class="modal-submit" onclick="submitAddRecord()">Add Record</button>
                </div>
            </div>
        </div>

        <!-- ════ EDIT RECORD MODAL ════ -->
        <div class="edit-modal-overlay" id="edit-modal">
            <div class="edit-modal">
                <div class="modal-title">Edit Request Record</div>
                <div class="modal-sub">Update the record's information below.</div>
                <div id="edit-modal-alert" style="display:none;" class="modal-alert error"></div>
                <input type="hidden" id="edit-record_id">

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Date Requested</label>
                        <input class="modal-input" id="edit-date_requested" type="date">
                    </div>
                    <div>
                        <label class="modal-label">Date Received</label>
                        <input class="modal-input" id="edit-date_received" type="date">
                    </div>
                </div>

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Request Number</label>
                        <input class="modal-input" id="edit-request_number" type="text">
                    </div>
                    <div>
                        <label class="modal-label">Ext. Number</label>
                        <input class="modal-input" id="edit-ext_number" type="text">
                    </div>
                </div>

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Request Section</label>
                        <input class="modal-input" id="edit-request_section" type="text">
                    </div>
                    <div>
                        <label class="modal-label">Performed By</label>
                        <input class="modal-input" id="edit-performed" type="text">
                    </div>
                </div>

                <label class="modal-label">Imp. Date</label>
                <input class="modal-input" id="edit-imp_date" type="date">

                <label class="modal-label">Information</label>
                <textarea class="modal-input" id="edit-information" rows="3" style="resize:vertical;"></textarea>

                <label class="modal-label">Reason</label>
                <textarea class="modal-input" id="edit-reason" rows="3" style="resize:vertical;"></textarea>

                <div class="modal-row" style="margin-top:18px;">
                    <button class="modal-cancel" onclick="closeEditModal()">Cancel</button>
                    <button class="modal-submit" onclick="submitEditRecord()">Save Changes</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ════ VIEW RECORD MODAL ════ -->
    <div class="view-modal-overlay" id="view-modal">
        <div class="view-modal">
            <div class="view-modal-header">
                <div class="view-icon-wrap"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <div class="view-name" id="view-request_number">—</div>
                    <div class="view-sub" id="view-request_section">—</div>
                </div>
            </div>
            <div class="view-grid">
                <div class="view-field"><label>Date Requested</label><span id="view-date_requested"></span></div>
                <div class="view-field"><label>Date Received</label><span id="view-date_received"></span></div>
                <div class="view-field"><label>Ext. Number</label><span id="view-ext_number"></span></div>
                <div class="view-field"><label>Performed By</label><span id="view-performed"></span></div>
                <div class="view-field"><label>Imp. Date</label><span id="view-imp_date"></span></div>
                <div class="view-field"><label>Record ID</label><span id="view-record_id"></span></div>
                <div class="view-field full"><label>Information</label><span id="view-information"></span></div>
                <div class="view-field full"><label>Reason</label><span id="view-reason"></span></div>
            </div>
            <div class="view-modal-footer">
                <button class="btn-close-view" onclick="closeViewModal()">Close</button>
                <?php if ($isAdmin): ?>
                    <button class="btn-edit-view" onclick="switchToEdit()"><i class="fa fa-pen"></i> Edit Record</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ════ DELETE CONFIRM MODAL ════ -->
    <div class="del-modal-overlay" id="del-modal">
        <div class="del-modal">
            <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="del-title">Delete Record?</div>
            <div class="del-sub">You are about to delete request <strong id="del-name"></strong>.<br>This action cannot
                be undone.</div>
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
            <div class="result-detail" id="result-detail" style="display:none;"></div>
            <button class="btn-result-ok" id="result-ok-btn" onclick="closeResultModal()">OK</button>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        /* ════════════════════════════════════════════════════════
           DATA & STATE
        ════════════════════════════════════════════════════════ */
        const ALL_RECORDS = <?= json_encode(array_values($allRecords)) ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const TODAY = '<?= date('Y-m-d') ?>';
        const THIS_MONTH = '<?= date('Y-m') ?>';

        let allRecords = [...ALL_RECORDS];
        let curPage = 1;
        let perPage = 10;

        let _pendingDeleteId = null;
        let _pendingDeleteName = null;
        let _currentViewRecord = null;
        let _filterType = null;
        let _activeCardFilter = null;

        /* ════════════════════════════════════════════════════════
           FILTER LOGIC
        ════════════════════════════════════════════════════════ */
        function getFiltered() {
            const q = (document.getElementById('table-search')?.value || '').toLowerCase();
            return allRecords.filter(r => {
                const matchSearch = !q || [
                    r.request_number, r.ext_number, r.request_section,
                    r.performed, r.information, r.reason,
                    r.date_requested, r.date_received, r.imp_date
                ].some(f => (f || '').toLowerCase().includes(q));

                let matchFilter = true;
                if (_filterType === 'received') matchFilter = !!r.date_received;
                if (_filterType === 'pending') matchFilter = !r.date_received;
                if (_filterType === 'today') matchFilter = r.date_requested === TODAY;
                if (_filterType === 'month') matchFilter = (r.date_requested || '').startsWith(THIS_MONTH);
                if (_filterType === 'info') matchFilter = !!r.information;

                return matchSearch && matchFilter;
            });
        }

        /* ════════════════════════════════════════════════════════
           TABLE RENDERING
        ════════════════════════════════════════════════════════ */
        function renderTable() {
            const filtered = getFiltered();
            const total = filtered.length;
            const totalPages = Math.ceil(total / perPage) || 1;
            if (curPage > totalPages) curPage = totalPages;

            const start = (curPage - 1) * perPage;
            const paged = filtered.slice(start, start + perPage);

            document.getElementById('recordTableBody').innerHTML = paged.map(r => `
<tr>
  <td><input type="checkbox" class="cb row-cb" data-id="${r.record_id}"></td>
  <td><span class="section-badge"><i class="fa-solid fa-hashtag" style="font-size:10px;"></i> ${r.request_number || '—'}</span></td>
  <td style="color:var(--text-mid);font-size:13.5px;">${r.ext_number || '—'}</td>
  <td style="font-size:13.5px;color:var(--text-mid);">${r.request_section || '—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.date_requested || '—'}</td>
  <td style="font-size:13px;">
    ${r.date_received
                    ? `<span style="color:#28c76f;font-weight:600;">${r.date_received}</span>`
                    : `<span style="color:#ff9f43;font-size:12px;font-weight:600;">Pending</span>`}
  </td>
  <td style="font-size:13px;color:var(--text-mid);">${r.performed || '—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.imp_date || '—'}</td>
  <td>
    <div class="action-cell">
      <button class="action-btn view-btn" title="View"   onclick='openViewModal(${r.record_id})'><i class="fa fa-eye"></i></button>
      ${IS_ADMIN ? `
      <button class="action-btn edit-btn" title="Edit"   onclick='openEditModal(${r.record_id})'><i class="fa fa-pen"></i></button>
      <button class="action-btn del"      title="Delete" onclick='openDelModal(${r.record_id},"${(r.request_number || "Record #" + r.record_id).replace(/"/g, "")}")'><i class="fa fa-trash"></i></button>` : ''}
    </div>
  </td>
</tr>`).join('');

            const from = total === 0 ? 0 : start + 1;
            const to = Math.min(start + perPage, total);
            document.getElementById('page-info').textContent = `Showing ${from} to ${to} of ${total} entries`;
            renderPageBtns(totalPages);
            updateStats();
        }

        function renderPageBtns(totalPages) {
            const c = document.getElementById('page-btns');
            const max = 5;
            let html = '';
            html += `<button class="page-btn ${curPage === 1 ? 'disabled' : ''}" onclick="goPage(${curPage - 1})"><i class="fa fa-angles-left"></i></button>`;
            html += `<button class="page-btn ${curPage === 1 ? 'disabled' : ''}" onclick="goPage(${curPage - 1})"><i class="fa fa-angle-left"></i></button>`;
            let s = Math.max(1, curPage - Math.floor(max / 2));
            let e = Math.min(totalPages, s + max - 1);
            if (e - s < max - 1) s = Math.max(1, e - max + 1);
            for (let i = s; i <= e; i++) {
                html += `<button class="page-btn ${i === curPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
            }
            html += `<button class="page-btn ${curPage === totalPages ? 'disabled' : ''}" onclick="goPage(${curPage + 1})"><i class="fa fa-angle-right"></i></button>`;
            html += `<button class="page-btn ${curPage === totalPages ? 'disabled' : ''}" onclick="goPage(${totalPages})"><i class="fa fa-angles-right"></i></button>`;
            c.innerHTML = html;
        }

        function goPage(p) {
            const total = Math.ceil(getFiltered().length / perPage) || 1;
            if (p < 1 || p > total) return;
            curPage = p; renderTable();
        }
        function perPageChanged() {
            perPage = parseInt(document.getElementById('per-page').value);
            curPage = 1; renderTable();
        }

        function updateStats() {
            document.getElementById('stat-total').textContent = allRecords.length;
            document.getElementById('stat-received').textContent = allRecords.filter(r => !!r.date_received).length;
            document.getElementById('stat-pending').textContent = allRecords.filter(r => !r.date_received).length;
            document.getElementById('stat-today').textContent = allRecords.filter(r => r.date_requested === TODAY).length;
            document.getElementById('stat-month').textContent = allRecords.filter(r => (r.date_requested || '').startsWith(THIS_MONTH)).length;
            document.getElementById('stat-info').textContent = allRecords.filter(r => !!r.information).length;
        }

        /* ════════════════════════════════════════════════════════
           STAT CARD FILTER
        ════════════════════════════════════════════════════════ */
        function filterByCard(type) {
            if (_activeCardFilter === type || type === 'total') {
                _filterType = null; _activeCardFilter = null;
                _highlightCard(null); curPage = 1; renderTable(); return;
            }
            _filterType = type; _activeCardFilter = type;
            _highlightCard(type); curPage = 1; renderTable();
        }

        function _highlightCard(type) {
            const map = {
                received: 'sc-received', pending: 'sc-pending', today: 'sc-today',
                month: 'sc-month', info: 'sc-info'
            };
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            if (type && map[type]) document.getElementById(map[type])?.classList.add('active-filter');
        }

        function syncSearch() {
            document.getElementById('table-search').value = document.getElementById('topbar-search').value;
            curPage = 1; renderTable();
        }
        function toggleAll(cb) {
            document.querySelectorAll('.row-cb').forEach(r => r.checked = cb.checked);
        }

        /* ════════════════════════════════════════════════════════
           RESULT BOX
        ════════════════════════════════════════════════════════ */
        function showResult(type, title, message, detailRows = []) {
            const iconWrap = document.getElementById('result-icon-wrap');
            const icon = document.getElementById('result-icon');
            iconWrap.className = `result-icon-wrap ${type}`;
            icon.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
            document.getElementById('result-title').textContent = title;
            document.getElementById('result-msg').textContent = message;
            document.getElementById('result-ok-btn').className = type === 'error' ? 'btn-result-ok danger' : 'btn-result-ok';
            const detailEl = document.getElementById('result-detail');
            if (detailRows.length) {
                detailEl.innerHTML = detailRows.map(([l, v]) => `<div class="rd-row"><span class="rd-label">${l}</span><span>${v}</span></div>`).join('');
                detailEl.style.display = 'block';
            } else { detailEl.style.display = 'none'; }
            document.getElementById('result-modal').classList.add('open');
        }
        function closeResultModal() { document.getElementById('result-modal').classList.remove('open'); }

        /* ════════════════════════════════════════════════════════
           VIEW MODAL
        ════════════════════════════════════════════════════════ */
        function openViewModal(id) {
            const r = allRecords.find(x => x.record_id == id);
            if (!r) return;
            _currentViewRecord = r;
            document.getElementById('view-record_id').textContent = r.record_id;
            document.getElementById('view-request_number').textContent = r.request_number || '—';
            document.getElementById('view-request_section').textContent = r.request_section || '—';
            document.getElementById('view-date_requested').textContent = r.date_requested || '—';
            document.getElementById('view-date_received').textContent = r.date_received || 'Pending';
            document.getElementById('view-ext_number').textContent = r.ext_number || '—';
            document.getElementById('view-performed').textContent = r.performed || '—';
            document.getElementById('view-imp_date').textContent = r.imp_date || '—';
            document.getElementById('view-information').textContent = r.information || '—';
            document.getElementById('view-reason').textContent = r.reason || '—';
            document.getElementById('view-modal').classList.add('open');
        }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('open'); _currentViewRecord = null; }
        function switchToEdit() { if (!_currentViewRecord) return; closeViewModal(); openEditModal(_currentViewRecord.record_id); }

        /* ════════════════════════════════════════════════════════
           ADD MODAL
        ════════════════════════════════════════════════════════ */
        function openAddModal() {
            ['add-date_requested', 'add-date_received', 'add-request_number', 'add-ext_number',
                'add-request_section', 'add-performed', 'add-imp_date', 'add-information', 'add-reason']
                .forEach(id => document.getElementById(id).value = '');
            hideAlert('modal-alert');
            document.getElementById('add-modal').classList.add('open');
        }
        function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); }
        document.getElementById('add-modal')?.addEventListener('click', function (e) { if (e.target === this) closeAddModal(); });

        function submitAddRecord() {
            const date_requested = document.getElementById('add-date_requested').value.trim();
            const date_received = document.getElementById('add-date_received').value.trim();
            const request_number = document.getElementById('add-request_number').value.trim();
            const ext_number = document.getElementById('add-ext_number').value.trim();
            const request_section = document.getElementById('add-request_section').value.trim();
            const performed = document.getElementById('add-performed').value.trim();
            const imp_date = document.getElementById('add-imp_date').value.trim();
            const information = document.getElementById('add-information').value.trim();
            const reason = document.getElementById('add-reason').value.trim();
            hideAlert('modal-alert');

            if (!date_requested || !request_number || !request_section) {
                showAlert('modal-alert', 'Date Requested, Request Number, and Section are required.'); return;
            }

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ __action: 'add_record', date_requested, date_received, request_number, ext_number, request_section, performed, imp_date, information, reason }).toString()
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        allRecords.unshift(d.record);
                        curPage = 1; renderTable(); closeAddModal();
                        showResult('success', 'Record Added!', 'The request record has been saved.', [
                            ['📋 Request No.', d.record.request_number],
                            ['🏢 Section', d.record.request_section],
                            ['📅 Date', d.record.date_requested],
                            ['👤 Performed', d.record.performed || '—'],
                        ]);
                    } else { showAlert('modal-alert', d.message || 'Failed to add record.'); }
                })
                .catch(() => showAlert('modal-alert', 'Network error. Please try again.'));
        }

        /* ════════════════════════════════════════════════════════
           EDIT MODAL
        ════════════════════════════════════════════════════════ */
        function openEditModal(id) {
            const r = allRecords.find(x => x.record_id == id);
            if (!r) return;
            document.getElementById('edit-record_id').value = r.record_id;
            document.getElementById('edit-date_requested').value = r.date_requested || '';
            document.getElementById('edit-date_received').value = r.date_received || '';
            document.getElementById('edit-request_number').value = r.request_number || '';
            document.getElementById('edit-ext_number').value = r.ext_number || '';
            document.getElementById('edit-request_section').value = r.request_section || '';
            document.getElementById('edit-performed').value = r.performed || '';
            document.getElementById('edit-imp_date').value = r.imp_date || '';
            document.getElementById('edit-information').value = r.information || '';
            document.getElementById('edit-reason').value = r.reason || '';
            hideAlert('edit-modal-alert');
            document.getElementById('edit-modal').classList.add('open');
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
            const performed = document.getElementById('edit-performed').value.trim();
            const imp_date = document.getElementById('edit-imp_date').value.trim();
            const information = document.getElementById('edit-information').value.trim();
            const reason = document.getElementById('edit-reason').value.trim();
            hideAlert('edit-modal-alert');

            if (!date_requested || !request_number || !request_section) {
                showAlert('edit-modal-alert', 'Date Requested, Request Number, and Section are required.'); return;
            }

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ __action: 'edit_record', id, date_requested, date_received, request_number, ext_number, request_section, performed, imp_date, information, reason }).toString()
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const idx = allRecords.findIndex(r => r.record_id == id);
                        if (idx !== -1) allRecords[idx] = { ...allRecords[idx], ...d.record };
                        renderTable(); closeEditModal();
                        showResult('success', 'Record Updated!', 'The request record has been updated.', [
                            ['📋 Request No.', d.record.request_number],
                            ['🏢 Section', d.record.request_section],
                            ['📅 Date', d.record.date_requested],
                        ]);
                    } else { showAlert('edit-modal-alert', d.message || 'Failed to update record.'); }
                })
                .catch(() => showAlert('edit-modal-alert', 'Network error. Please try again.'));
        }

        /* ════════════════════════════════════════════════════════
           DELETE MODAL
        ════════════════════════════════════════════════════════ */
        function openDelModal(id, name) {
            _pendingDeleteId = id;
            _pendingDeleteName = name;
            document.getElementById('del-name').textContent = name;
            document.getElementById('del-modal').classList.add('open');
        }
        function closeDelModal() {
            document.getElementById('del-modal').classList.remove('open');
            _pendingDeleteId = _pendingDeleteName = null;
        }
        function confirmDelete() {
            if (!_pendingDeleteId) return;
            const deletedName = _pendingDeleteName;
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `__action=delete_record&id=${_pendingDeleteId}`
            })
                .then(r => r.json())
                .then(d => {
                    closeDelModal();
                    if (d.success) {
                        allRecords = allRecords.filter(r => r.record_id != _pendingDeleteId);
                        renderTable();
                        showResult('success', 'Record Deleted', `"${deletedName}" has been permanently removed.`);
                    } else {
                        showResult('error', 'Delete Failed', d.message || 'Could not delete this record.');
                    }
                })
                .catch(() => { closeDelModal(); showResult('error', 'Network Error', 'Could not reach the server.'); });
        }

        /* ════════════════════════════════════════════════════════
           HELPERS
        ════════════════════════════════════════════════════════ */
        function showAlert(elId, msg) {
            const el = document.getElementById(elId);
            if (!el) return;
            el.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${msg}`;
            el.style.display = 'flex';
        }
        function hideAlert(elId) { const el = document.getElementById(elId); if (el) el.style.display = 'none'; }

        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = `toast ${type} show`;
            clearTimeout(t._t);
            t._t = setTimeout(() => t.classList.remove('show'), 3200);
        }

        function exportCSV() {
            const rows = [['Record ID', 'Request No.', 'Ext Number', 'Section', 'Date Requested', 'Date Received', 'Performed', 'Imp Date', 'Information', 'Reason']];
            getFiltered().forEach(r => rows.push([
                r.record_id, r.request_number || '', r.ext_number || '', r.request_section || '',
                r.date_requested || '', r.date_received || '', r.performed || '',
                r.imp_date || '', r.information || '', r.reason || ''
            ]));
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
            a.download = 'request_records.csv'; a.click();
            showToast('Exported successfully!', 'success');
        }

        function toggleAcc(id) { document.getElementById(id).classList.toggle('open'); }

        /* ── Init ── */
        renderTable();
    </script>
</body>

</html>