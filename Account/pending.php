<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

include('../dbconnection/config.php');

$sessionUser = [
    'id'        => $_SESSION['user_id'],
    'username'  => $_SESSION['username'],
    'full_name' => $_SESSION['full_name'],
    'role'      => $_SESSION['role'],
    'email'     => $_SESSION['email'],
    'color'     => $_SESSION['color'],
];
$isAdmin  = true;
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $sessionUser['full_name']), 0, 2)));

// ── Fetch pending & rejected users ──────────────────────────────
$pendingUsers  = $pdo->query("
    SELECT id, username, full_name, email, role, avatar_color, associate_id,
           section, team, created_at, status
    FROM users WHERE status = 'pending' ORDER BY created_at DESC
")->fetchAll();

$rejectedUsers = $pdo->query("
    SELECT id, username, full_name, email, role, avatar_color, associate_id,
           section, team, created_at, status
    FROM users WHERE status = 'inactive' ORDER BY created_at DESC
")->fetchAll();

$totalPending  = count($pendingUsers);
$totalRejected = count($rejectedUsers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals – Sneat</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>

        /* ══ Stat summary cards ══ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 26px;
        }
        @media(max-width:700px){ .summary-grid{ grid-template-columns:1fr 1fr; } }

        .sum-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .sum-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .sum-icon.orange { background: #ff9f4318; color: #ff9f43; }
        .sum-icon.red    { background: #ff3e1d12; color: #ff3e1d; }
        .sum-icon.green  { background: #28c76f18; color: #28c76f; }
        .sum-val  { font-size: 28px; font-weight: 700; color: #2d3a4a; line-height: 1; }
        .sum-label{ font-size: 13px; color: #8a93a2; margin-top: 4px; }

        /* ══ Tab switcher ══ */
        .tab-bar {
            display: flex;
            gap: 0;
            background: #f0f1f5;
            border-radius: 10px;
            padding: 4px;
            width: fit-content;
            margin-bottom: 22px;
        }
        .tab-btn {
            padding: 8px 22px;
            border: none;
            border-radius: 8px;
            background: transparent;
            font-size: 13.5px;
            font-weight: 600;
            color: #8a93a2;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .tab-btn.active {
            background: #fff;
            color: #2d3a4a;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .tab-btn .tab-count {
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
        }
        .tab-btn.active .tab-count.orange { background: #ff9f4320; color: #ff9f43; }
        .tab-btn.active .tab-count.red    { background: #ff3e1d12; color: #ff3e1d; }
        .tab-btn .tab-count { background: #e4e5ec; color: #8a93a2; }

        /* ══ Table card ══ */
        .table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px 14px;
            border-bottom: 1px solid #f0f1f5;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3a4a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f6f7f9;
            border: 1px solid #e8eaf0;
            border-radius: 8px;
            padding: 7px 13px;
            min-width: 220px;
        }
        .search-box input {
            border: none;
            background: transparent;
            font-size: 13px;
            font-family: inherit;
            color: #2d3a4a;
            outline: none;
            width: 100%;
        }
        .search-box input::placeholder { color: #a0a8b5; }

        /* ══ Table styles ══ */
        .pending-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pending-table thead th {
            background: #f8f9fb;
            font-size: 11.5px;
            font-weight: 700;
            color: #8a93a2;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 11px 16px;
            text-align: left;
            border-bottom: 1px solid #f0f1f5;
            white-space: nowrap;
        }
        .pending-table tbody tr {
            border-bottom: 1px solid #f8f9fb;
            transition: background .12s;
        }
        .pending-table tbody tr:last-child { border-bottom: none; }
        .pending-table tbody tr:hover { background: #fafbff; }
        .pending-table td {
            padding: 13px 16px;
            font-size: 13.5px;
            color: #2d3a4a;
            vertical-align: middle;
        }

        /* User cell */
        .user-cell { display: flex; align-items: center; gap: 11px; }
        .user-av {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
        }
        .user-cell-name  { font-weight: 600; font-size: 14px; color: #2d3a4a; }
        .user-cell-email { font-size: 12px; color: #8a93a2; margin-top: 1px; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 600;
            padding: 3px 10px; border-radius: 20px;
        }
        .badge.pending  { background: #fff8ec; color: #ff9f43; }
        .badge.inactive { background: #f5f5f5; color: #8a93a2; }
        .badge.admin    { background: #ff3e1d10; color: #ff3e1d; }
        .badge.user     { background: #696cff12; color: #696cff; }

        /* Action buttons */
        .action-cell { display: flex; gap: 6px; }
        .btn-approve-sm {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg,#28c76f,#20a558);
            color: #fff; border: none; border-radius: 7px;
            padding: 6px 14px; font-size: 12.5px; font-weight: 600;
            cursor: pointer; transition: opacity .15s;
        }
        .btn-approve-sm:hover { opacity: .85; }
        .btn-reject-sm {
            display: inline-flex; align-items: center; gap: 5px;
            background: #ff3e1d12; color: #ff3e1d; border: none;
            border-radius: 7px; padding: 6px 14px;
            font-size: 12.5px; font-weight: 600; cursor: pointer; transition: background .15s;
        }
        .btn-reject-sm:hover { background: #ff3e1d22; }
        .btn-restore-sm {
            display: inline-flex; align-items: center; gap: 5px;
            background: #696cff12; color: #696cff; border: none;
            border-radius: 7px; padding: 6px 14px;
            font-size: 12.5px; font-weight: 600; cursor: pointer; transition: background .15s;
        }
        .btn-restore-sm:hover { background: #696cff22; }
        .btn-delete-sm {
            display: inline-flex; align-items: center; gap: 5px;
            background: #f0f1f5; color: #8a93a2; border: none;
            border-radius: 7px; padding: 6px 14px;
            font-size: 12.5px; font-weight: 600; cursor: pointer; transition: background .15s;
        }
        .btn-delete-sm:hover { background: #ff3e1d12; color: #ff3e1d; }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 60px 20px;
        }
        .empty-state i { font-size: 46px; margin-bottom: 16px; display: block; }
        .empty-state-title { font-size: 16px; font-weight: 700; color: #2d3a4a; margin-bottom: 6px; }
        .empty-state-sub   { font-size: 13px; color: #8a93a2; }

        /* ══ Detail Modal ══ */
        .detail-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(30,30,60,.45);
            backdrop-filter: blur(4px);
            z-index: 9999; align-items: center; justify-content: center;
        }
        .detail-overlay.open { display: flex; }
        .detail-modal {
            background: #fff; border-radius: 16px;
            padding: 32px 32px 26px; max-width: 500px; width: 95%;
            box-shadow: 0 24px 60px rgba(0,0,0,.20);
            animation: popIn .3s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes popIn { from{transform:scale(.82);opacity:0} to{transform:scale(1);opacity:1} }
        .detail-header {
            display: flex; align-items: center; gap: 14px; margin-bottom: 20px;
        }
        .detail-av {
            width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: #fff; flex-shrink: 0;
        }
        .detail-name  { font-size: 16px; font-weight: 700; color: #2d3a4a; }
        .detail-email { font-size: 13px; color: #8a93a2; margin-top: 2px; }
        .detail-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px 18px;
            background: #f6f7f9; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px;
        }
        .detail-field label {
            font-size: 11px; font-weight: 600; color: #a0a8b5;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .detail-field span {
            display: block; font-size: 13.5px; font-weight: 500; color: #2d3a4a; margin-top: 2px;
        }
        .detail-notice {
            padding: 12px 16px; border-radius: 9px;
            font-size: 13px; color: #5a6070; margin-bottom: 20px;
            border-left: 4px solid;
        }
        .detail-notice.pending  { background: #fff8ec; border-color: #ff9f43; }
        .detail-notice.inactive { background: #f5f5f5; border-color: #8a93a2; }
        .detail-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-modal-cancel {
            background: #f0f1f5; color: #5a6070; border: none;
            border-radius: 8px; padding: 10px 20px;
            font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-modal-approve {
            background: linear-gradient(135deg,#28c76f,#20a558);
            color: #fff; border: none; border-radius: 8px;
            padding: 10px 24px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: opacity .15s;
        }
        .btn-modal-approve:hover { opacity: .88; }
        .btn-modal-reject {
            background: #ff3e1d12; color: #ff3e1d; border: none;
            border-radius: 8px; padding: 10px 22px;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: background .15s;
        }
        .btn-modal-reject:hover { background: #ff3e1d22; }
        .btn-modal-restore {
            background: #696cff12; color: #696cff; border: none;
            border-radius: 8px; padding: 10px 22px;
            font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-modal-restore:hover { background: #696cff22; }

        /* ══ Confirm delete modal ══ */
        .del-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(30,30,60,.45); backdrop-filter: blur(4px);
            z-index: 10000; align-items: center; justify-content: center;
        }
        .del-overlay.open { display: flex; }
        .del-box {
            background: #fff; border-radius: 16px; padding: 36px 36px 30px;
            max-width: 380px; width: 92%; text-align: center;
            box-shadow: 0 24px 60px rgba(0,0,0,.2);
            animation: popIn .3s cubic-bezier(.34,1.56,.64,1);
        }
        .del-icon-wrap {
            width: 68px; height: 68px; border-radius: 50%; background: #ff3e1d12;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
        }
        .del-icon-wrap i { font-size: 30px; color: #ff3e1d; }
        .del-title { font-size: 18px; font-weight: 700; color: #2d3a4a; margin-bottom: 8px; }
        .del-sub   { font-size: 13px; color: #6e7a8a; line-height: 1.6; margin-bottom: 24px; }
        .del-btns  { display: flex; gap: 10px; justify-content: center; }
        .btn-del-cancel  { background:#f0f1f5; color:#5a6070; border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-del-confirm { background:#ff3e1d; color:#fff; border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-del-confirm:hover { opacity:.88; }

        /* ══ Toast ══ */
        .toast {
            position: fixed; bottom: 26px; left: 50%; transform: translateX(-50%) translateY(20px);
            background: #2d3a4a; color: #fff; padding: 10px 24px;
            border-radius: 30px; font-size: 13.5px; font-weight: 500;
            opacity: 0; pointer-events: none; transition: all .3s; z-index: 99999;
            white-space: nowrap;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.success { background: #28c76f; }
        .toast.error   { background: #ff3e1d; }

        /* ══ Hidden tab panel ══ */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ══ Registered date chip ══ */
        .date-chip {
            font-size: 12px; color: #8a93a2;
            display: flex; align-items: center; gap: 5px;
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
            <li class="nav-item nav-accordion" id="masterControl">
                <a class="nav-link" href="#" onclick="toggleAcc('masterControl');return false;">
                    <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                    <span class="nav-text">Master Control</span>
                    <i class="fa fa-chevron-right nav-chevron"></i>
                </a>
                <ul class="nav-sub">
                    <li><a class="nav-sub-link" href="../Mastercontrol/cancellation.php">Change and Cancellation</a></li>
                    <li><a class="nav-sub-link" href="../Mastercontrol/newreguser.php">New User Registration</a></li>
                    <li><a class="nav-sub-link" href="../Mastercontrol/passwordreset.php">Password Request</a></li>
                    <li><a class="nav-sub-link" href="../Mastercontrol/requestrecord.php">Request Record</a></li>
                    <li><a class="nav-sub-link" href="../Mastercontrol/incident.php">Incident Report</a></li>
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
            <li class="nav-item nav-accordion open" id="userAcc">
                <a class="nav-link active" href="#" onclick="toggleAcc('userAcc');return false;">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">Users</span>
                    <i class="fa fa-chevron-right nav-chevron"></i>
                </a>
                <ul class="nav-sub">
                    <li><a class="nav-sub-link" href="tbl_userlist.php">List</a></li>
                    <li><a class="nav-sub-link" href="user_account.php">Account Settings</a></li>
                    <li><a class="nav-sub-link active" href="pending.php">Pending Approvals
                        <?php if ($totalPending > 0): ?>
                            <span style="background:#ff9f43;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:4px;"><?= $totalPending ?></span>
                        <?php endif; ?>
                    </a></li>
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
            <input type="text" placeholder="Search (CTRL + K)">
        </div>
        <div class="topbar-spacer"></div>
        <div style="display:flex;align-items:center;gap:6px;">
            <div class="topbar-icon"><i class="fa fa-globe"></i></div>
            <div class="topbar-icon"><i class="fa fa-sun"></i></div>
            <div class="topbar-icon"><i class="fa-solid fa-table-cells"></i></div>
            <div class="topbar-icon" style="position:relative;">
                <i class="fa fa-bell"></i>
                <?php if ($totalPending > 0): ?>
                    <span style="position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:#ff3e1d;"></span>
                <?php endif; ?>
            </div>
            <div class="avatar-top" style="background:<?= htmlspecialchars($sessionUser['color']) ?>;">
                <?= htmlspecialchars($initials) ?>
                <div class="user-dropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-name"><?= htmlspecialchars($sessionUser['full_name']) ?></div>
                        <div class="dropdown-email"><?= htmlspecialchars($sessionUser['email']) ?></div>
                        <span class="dropdown-role">Admin</span>
                    </div>
                    <a class="dropdown-item" href="user_account.php"><i class="fa fa-user"></i> My Profile</a>
                    <a class="dropdown-item" href="user_account.php"><i class="fa fa-gear"></i> Settings</a>
                    <a class="dropdown-item danger" href="../logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="#">Home</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <a href="tbl_userlist.php">User Management</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <span style="color:var(--text-mid);">Pending Approvals</span>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="sum-card">
                <div class="sum-icon orange"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="sum-val" id="cnt-pending"><?= $totalPending ?></div>
                    <div class="sum-label">Awaiting Approval</div>
                </div>
            </div>
            <div class="sum-card">
                <div class="sum-icon red"><i class="fa-solid fa-xmark"></i></div>
                <div>
                    <div class="sum-val" id="cnt-rejected"><?= $totalRejected ?></div>
                    <div class="sum-label">Rejected / Inactive</div>
                </div>
            </div>
            <div class="sum-card">
                <div class="sum-icon green"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="sum-val"><?= $totalPending + $totalRejected ?></div>
                    <div class="sum-label">Total in Review</div>
                </div>
            </div>
        </div>

        <!-- Tab Bar -->
        <div class="tab-bar">
            <button class="tab-btn active" id="tab-pending-btn" onclick="switchTab('pending')">
                <i class="fa-solid fa-clock"></i> Pending
                <span class="tab-count orange" id="tab-pending-count"><?= $totalPending ?></span>
            </button>
            <button class="tab-btn" id="tab-rejected-btn" onclick="switchTab('rejected')">
                <i class="fa-solid fa-ban"></i> Rejected
                <span class="tab-count red" id="tab-rejected-count"><?= $totalRejected ?></span>
            </button>
        </div>

        <!-- ── PENDING TAB ── -->
        <div class="tab-panel active" id="panel-pending">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">
                        <i class="fa-solid fa-clock" style="color:#ff9f43;"></i>
                        Pending Registrations
                    </div>
                    <div class="search-box">
                        <i class="fa fa-search" style="color:#a0a8b5;font-size:13px;"></i>
                        <input type="text" id="search-pending" placeholder="Search pending users…" oninput="filterTable('pending')">
                    </div>
                </div>

                <?php if (empty($pendingUsers)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-circle-check" style="color:#28c76f;"></i>
                        <div class="empty-state-title">All caught up!</div>
                        <div class="empty-state-sub">No pending registrations at the moment.</div>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="pending-table" id="tbl-pending">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="chk-all-pending" onchange="toggleCheckAll('pending',this)"></th>
                                    <th>User</th>
                                    <th>Associate ID</th>
                                    <th>Section / Team</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-pending">
                            <?php foreach ($pendingUsers as $u):
                                $ini = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $u['full_name']), 0, 2)));
                                $date = $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—';
                            ?>
                                <tr data-id="<?= $u['id'] ?>"
                                    data-name="<?= htmlspecialchars(strtolower($u['full_name'])) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>"
                                    data-assoc="<?= htmlspecialchars($u['associate_id'] ?? '') ?>">
                                    <td><input type="checkbox" class="row-chk-pending"></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-av" style="background:<?= htmlspecialchars($u['avatar_color']) ?>;"><?= $ini ?></div>
                                            <div>
                                                <div class="user-cell-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                                <div class="user-cell-email"><?= htmlspecialchars($u['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['associate_id'] ?? '—') ?></td>
                                    <td>
                                        <div style="font-size:13px;font-weight:600;color:#2d3a4a;"><?= htmlspecialchars($u['section'] ?? '—') ?></div>
                                        <div style="font-size:12px;color:#8a93a2;"><?= htmlspecialchars($u['team'] ?? '—') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $u['role'] ?>">
                                            <i class="fa-solid <?= $u['role'] === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><div class="date-chip"><i class="fa-regular fa-calendar"></i><?= $date ?></div></td>
                                    <td><span class="badge pending"><i class="fa-solid fa-clock"></i> Pending</span></td>
                                    <td>
                                        <div class="action-cell">
                                            <button class="btn-approve-sm" onclick="openDetail(<?= $u['id'] ?>,'pending')">
                                                <i class="fa-solid fa-eye"></i> Review
                                            </button>
                                            <button class="btn-approve-sm" onclick="quickAction(<?= $u['id'] ?>,'approve')" style="padding:6px 10px;" title="Approve">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button class="btn-reject-sm" onclick="quickAction(<?= $u['id'] ?>,'reject')" style="padding:6px 10px;" title="Reject">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bulk action bar -->
                    <div id="bulk-bar-pending" style="display:none;padding:12px 20px;background:#696cff0a;border-top:1px solid #f0f1f5;display:none;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span id="bulk-count-pending" style="font-size:13px;font-weight:600;color:#696cff;"></span>
                        <button class="btn-approve-sm" onclick="bulkAction('approve')"><i class="fa-solid fa-check"></i> Approve Selected</button>
                        <button class="btn-reject-sm"  onclick="bulkAction('reject')"><i class="fa-solid fa-xmark"></i> Reject Selected</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── REJECTED TAB ── -->
        <div class="tab-panel" id="panel-rejected">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">
                        <i class="fa-solid fa-ban" style="color:#ff3e1d;"></i>
                        Rejected / Inactive Users
                    </div>
                    <div class="search-box">
                        <i class="fa fa-search" style="color:#a0a8b5;font-size:13px;"></i>
                        <input type="text" id="search-rejected" placeholder="Search rejected users…" oninput="filterTable('rejected')">
                    </div>
                </div>

                <?php if (empty($rejectedUsers)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-ban" style="color:#e0e3ec;"></i>
                        <div class="empty-state-title">No rejected users</div>
                        <div class="empty-state-sub">Rejected registrations will appear here.</div>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="pending-table" id="tbl-rejected">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="chk-all-rejected" onchange="toggleCheckAll('rejected',this)"></th>
                                    <th>User</th>
                                    <th>Associate ID</th>
                                    <th>Section / Team</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-rejected">
                            <?php foreach ($rejectedUsers as $u):
                                $ini  = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $u['full_name']), 0, 2)));
                                $date = $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—';
                            ?>
                                <tr data-id="<?= $u['id'] ?>"
                                    data-name="<?= htmlspecialchars(strtolower($u['full_name'])) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>"
                                    data-assoc="<?= htmlspecialchars($u['associate_id'] ?? '') ?>">
                                    <td><input type="checkbox" class="row-chk-rejected"></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-av" style="background:<?= htmlspecialchars($u['avatar_color']) ?>;"><?= $ini ?></div>
                                            <div>
                                                <div class="user-cell-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                                <div class="user-cell-email"><?= htmlspecialchars($u['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['associate_id'] ?? '—') ?></td>
                                    <td>
                                        <div style="font-size:13px;font-weight:600;color:#2d3a4a;"><?= htmlspecialchars($u['section'] ?? '—') ?></div>
                                        <div style="font-size:12px;color:#8a93a2;"><?= htmlspecialchars($u['team'] ?? '—') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $u['role'] ?>">
                                            <i class="fa-solid <?= $u['role'] === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><div class="date-chip"><i class="fa-regular fa-calendar"></i><?= $date ?></div></td>
                                    <td><span class="badge inactive"><i class="fa-solid fa-ban"></i> Rejected</span></td>
                                    <td>
                                        <div class="action-cell">
                                            <button class="btn-restore-sm" onclick="openDetail(<?= $u['id'] ?>,'rejected')">
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>
                                            <button class="btn-restore-sm" onclick="quickAction(<?= $u['id'] ?>,'restore')" style="padding:6px 10px;" title="Restore to Pending">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                            <button class="btn-delete-sm" onclick="openDeleteConfirm(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')" style="padding:6px 10px;" title="Delete permanently">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div><!-- .page-wrapper -->

<!-- ══ DETAIL / REVIEW MODAL ══ -->
<div class="detail-overlay" id="detail-overlay">
    <div class="detail-modal">
        <div class="detail-header">
            <div class="detail-av" id="d-avatar"></div>
            <div>
                <div class="detail-name"  id="d-name"></div>
                <div class="detail-email" id="d-email"></div>
            </div>
        </div>
        <div class="detail-grid">
            <div class="detail-field"><label>Username</label><span id="d-username"></span></div>
            <div class="detail-field"><label>Associate ID</label><span id="d-associd"></span></div>
            <div class="detail-field"><label>Section</label><span id="d-section"></span></div>
            <div class="detail-field"><label>Team</label><span id="d-team"></span></div>
            <div class="detail-field"><label>Role</label><span id="d-role"></span></div>
            <div class="detail-field"><label>Registered</label><span id="d-date"></span></div>
        </div>
        <div class="detail-notice" id="d-notice"></div>
        <div class="detail-actions" id="d-actions"></div>
    </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ══ -->
<div class="del-overlay" id="del-overlay">
    <div class="del-box">
        <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="del-title">Delete Permanently?</div>
        <div class="del-sub">You are about to permanently delete <strong id="del-name-lbl"></strong>.<br>This cannot be undone.</div>
        <div class="del-btns">
            <button class="btn-del-cancel" onclick="closeDeleteConfirm()">Cancel</button>
            <button class="btn-del-confirm" onclick="confirmDelete()"><i class="fa-solid fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
/* ══════════════════════════════════════════════════════════════
   DATA — PHP → JS
══════════════════════════════════════════════════════════════ */
const PENDING_USERS  = <?= json_encode(array_values($pendingUsers))  ?>;
const REJECTED_USERS = <?= json_encode(array_values($rejectedUsers)) ?>;

/* live copies we mutate */
let pendingData  = [...PENDING_USERS];
let rejectedData = [...REJECTED_USERS];

let _deleteId   = null;
let _deleteName = null;
let _detailMode = null; // 'pending' | 'rejected'
let _detailId   = null;

/* ══════════════════════════════════════════════════════════════
   TAB SWITCHING
══════════════════════════════════════════════════════════════ */
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + tab).classList.add('active');
    document.getElementById('tab-' + tab + '-btn').classList.add('active');
}

/* ══════════════════════════════════════════════════════════════
   SEARCH / FILTER
══════════════════════════════════════════════════════════════ */
function filterTable(type) {
    const q    = document.getElementById('search-' + type).value.toLowerCase();
    const rows = document.querySelectorAll('#tbody-' + type + ' tr');
    rows.forEach(r => {
        const name  = r.dataset.name  || '';
        const email = r.dataset.email || '';
        const assoc = r.dataset.assoc || '';
        r.style.display = (!q || name.includes(q) || email.includes(q) || assoc.includes(q)) ? '' : 'none';
    });
}

/* ══════════════════════════════════════════════════════════════
   CHECK-ALL / BULK BAR
══════════════════════════════════════════════════════════════ */
function toggleCheckAll(type, master) {
    document.querySelectorAll('.row-chk-' + type).forEach(c => c.checked = master.checked);
    updateBulkBar();
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('row-chk-pending') ||
        e.target.classList.contains('row-chk-rejected')) {
        updateBulkBar();
    }
});

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-chk-pending:checked');
    const bar     = document.getElementById('bulk-bar-pending');
    const cnt     = document.getElementById('bulk-count-pending');
    if (!bar) return;
    if (checked.length > 0) {
        bar.style.display = 'flex';
        cnt.textContent   = checked.length + ' user' + (checked.length > 1 ? 's' : '') + ' selected';
    } else {
        bar.style.display = 'none';
    }
}

/* ══════════════════════════════════════════════════════════════
   DETAIL MODAL
══════════════════════════════════════════════════════════════ */
function openDetail(id, mode) {
    const list = mode === 'pending' ? pendingData : rejectedData;
    const u = list.find(x => x.id == id);
    if (!u) return;
    _detailId   = id;
    _detailMode = mode;

    const ini = (u.full_name || '').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    const av  = document.getElementById('d-avatar');
    av.textContent  = ini;
    av.style.background = u.avatar_color || '#696cff';

    document.getElementById('d-name').textContent    = u.full_name   || '';
    document.getElementById('d-email').textContent   = u.email       || '';
    document.getElementById('d-username').textContent = u.username   || '—';
    document.getElementById('d-associd').textContent  = u.associate_id || '—';
    document.getElementById('d-section').textContent  = u.section    || '—';
    document.getElementById('d-team').textContent     = u.team       || '—';
    document.getElementById('d-role').innerHTML = u.role === 'admin'
        ? '<span class="badge admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>'
        : '<span class="badge user"><i class="fa-solid fa-user"></i> User</span>';
    document.getElementById('d-date').textContent = u.created_at
        ? new Date(u.created_at).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})
        : '—';

    const notice = document.getElementById('d-notice');
    const actions = document.getElementById('d-actions');

    if (mode === 'pending') {
        notice.className = 'detail-notice pending';
        notice.innerHTML = '<i class="fa-solid fa-clock" style="color:#ff9f43;margin-right:6px;"></i> This user registered and is <strong>awaiting your approval</strong>.';
        actions.innerHTML = `
            <button class="btn-modal-cancel"  onclick="closeDetail()">Cancel</button>
            <button class="btn-modal-reject"  onclick="doAction(${id},'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
            <button class="btn-modal-approve" onclick="doAction(${id},'approve')"><i class="fa-solid fa-check"></i> Approve</button>`;
    } else {
        notice.className = 'detail-notice inactive';
        notice.innerHTML = '<i class="fa-solid fa-ban" style="color:#8a93a2;margin-right:6px;"></i> This user was <strong>rejected</strong>. You can restore them to pending or delete permanently.';
        actions.innerHTML = `
            <button class="btn-modal-cancel"  onclick="closeDetail()">Cancel</button>
            <button class="btn-modal-restore" onclick="doAction(${id},'restore')"><i class="fa-solid fa-rotate-left"></i> Restore to Pending</button>
            <button class="btn-modal-approve" onclick="doAction(${id},'approve')"><i class="fa-solid fa-check"></i> Approve Now</button>`;
    }

    document.getElementById('detail-overlay').classList.add('open');
}

function closeDetail() {
    document.getElementById('detail-overlay').classList.remove('open');
    _detailId = _detailMode = null;
}

/* ══════════════════════════════════════════════════════════════
   ACTIONS  (approve | reject | restore)
══════════════════════════════════════════════════════════════ */
function quickAction(id, action) { doAction(id, action); }


function doAction(id, action) {
    closeDetail();

    const userId = parseInt(id, 10);
    if (!userId || isNaN(userId)) { showToast('Error: invalid user ID.', 'error'); return; }

    let newStatus;
    if (action === 'approve')  newStatus = 'active';
    if (action === 'reject')   newStatus = 'inactive';
    if (action === 'restore')  newStatus = 'pending';

    fetch('../Crud/process_edit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + userId + '&status=' + encodeURIComponent(newStatus) + '&_approval=1'
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast(d.message || 'Action failed.', 'error'); return; }

        const name = d.user?.full_name || 'User';

        if (action === 'approve') {
            // Remove from pending, remove from rejected if present
            removeRow('pending',  id);
            removeRow('rejected', id);
            pendingData  = pendingData.filter(u => u.id != id);
            rejectedData = rejectedData.filter(u => u.id != id);
            showToast('✅ ' + name + ' has been approved!', 'success');
        } else if (action === 'reject') {
            // Move from pending → rejected table
            const u = pendingData.find(x => x.id == id);
            if (u) {
                u.status = 'inactive';
                rejectedData.unshift(u);
                pendingData = pendingData.filter(x => x.id != id);
                removeRow('pending', id);
                prependRow('rejected', u);
            }
            showToast('❌ ' + name + ' has been rejected.', 'error');
        } else if (action === 'restore') {
            // Move from rejected → pending table
            const u = rejectedData.find(x => x.id == id);
            if (u) {
                u.status = 'pending';
                pendingData.unshift(u);
                rejectedData = rejectedData.filter(x => x.id != id);
                removeRow('rejected', id);
                prependRow('pending', u);
            }
            showToast('🔄 ' + name + ' restored to pending.', 'success');
        }

        updateCounts();
    })
    .catch(() => showToast('Network error. Please try again.', 'error'));
}

/* ── Bulk approve / reject all checked pending ── */
function bulkAction(action) {
    const checked = [...document.querySelectorAll('.row-chk-pending:checked')];
    if (!checked.length) return;
    const ids = checked.map(c => parseInt(c.closest('tr').dataset.id));

    // Fire sequentially using reduce to avoid hammering the server
    ids.reduce((p, id) => p.then(() => new Promise(res => {
        const newStatus = action === 'approve' ? 'active' : 'inactive';
        fetch('../Crud/process_edit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + parseInt(id,10) + '&status=' + encodeURIComponent(newStatus) + '&_approval=1'
        }).then(r => r.json()).then(d => {
            if (d.success) {
                if (action === 'approve') {
                    removeRow('pending', id);
                    pendingData = pendingData.filter(u => u.id != id);
                } else {
                    const u = pendingData.find(x => x.id == id);
                    if (u) {
                        u.status = 'inactive';
                        rejectedData.unshift(u);
                        pendingData = pendingData.filter(x => x.id != id);
                        removeRow('pending', id);
                        prependRow('rejected', u);
                    }
                }
            }
            res();
        }).catch(res);
    })), Promise.resolve()).then(() => {
        updateCounts();
        document.getElementById('bulk-bar-pending').style.display = 'none';
        showToast(action === 'approve' ? '✅ Selected users approved!' : '❌ Selected users rejected.', action === 'approve' ? 'success' : 'error');
    });
}

/* ══════════════════════════════════════════════════════════════
   DELETE PERMANENTLY
══════════════════════════════════════════════════════════════ */
function openDeleteConfirm(id, name) {
    _deleteId   = id;
    _deleteName = name;
    document.getElementById('del-name-lbl').textContent = name;
    document.getElementById('del-overlay').classList.add('open');
}
function closeDeleteConfirm() {
    document.getElementById('del-overlay').classList.remove('open');
    _deleteId = _deleteName = null;
}
function confirmDelete() {
    if (!_deleteId) return;
    const id   = _deleteId;
    const name = _deleteName;
    closeDeleteConfirm();

    fetch('../Crud/process_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            removeRow('rejected', id);
            rejectedData = rejectedData.filter(u => u.id != id);
            updateCounts();
            showToast('🗑️ ' + name + ' permanently deleted.', 'error');
        } else {
            showToast(d.message || 'Delete failed.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
}

/* ══════════════════════════════════════════════════════════════
   DOM HELPERS
══════════════════════════════════════════════════════════════ */
function removeRow(type, id) {
    const tr = document.querySelector('#tbody-' + type + ' tr[data-id="' + id + '"]');
    if (tr) tr.remove();

    // Show empty state if no rows left
    const tbody = document.getElementById('tbody-' + type);
    if (tbody && tbody.querySelectorAll('tr').length === 0) {
        const card = document.getElementById('panel-' + type).querySelector('.table-card');
        const wrapper = card.querySelector('div[style*="overflow-x"]') || card.querySelector('table')?.parentElement;
        if (wrapper) wrapper.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-circle-check" style="color:#28c76f;font-size:46px;margin-bottom:16px;display:block;"></i>
                <div class="empty-state-title">${type === 'pending' ? 'All caught up!' : 'No rejected users'}</div>
                <div class="empty-state-sub">${type === 'pending' ? 'No pending registrations at the moment.' : 'Rejected registrations will appear here.'}</div>
            </div>`;
    }
}

function prependRow(type, u) {
    const tbody = document.getElementById('tbody-' + type);
    if (!tbody) return;

    // Remove empty state if present
    const panel = document.getElementById('panel-' + type);
    const empty = panel.querySelector('.empty-state');
    if (empty) {
        const card = panel.querySelector('.table-card');
        // Rebuild the table wrapper with header
        card.querySelector('div[style*="overflow-x"]')?.remove();
        // Simple reload since we'd need to reconstruct the whole table shell
        location.reload(); return;
    }

    const ini  = (u.full_name||'').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    const date = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
    const isRej = type === 'rejected';

    const tr = document.createElement('tr');
    tr.dataset.id    = u.id;
    tr.dataset.name  = (u.full_name||'').toLowerCase();
    tr.dataset.email = (u.email||'').toLowerCase();
    tr.dataset.assoc = u.associate_id||'';
    tr.innerHTML = `
        <td><input type="checkbox" class="row-chk-${type}"></td>
        <td>
            <div class="user-cell">
                <div class="user-av" style="background:${u.avatar_color||'#696cff'}">${ini}</div>
                <div>
                    <div class="user-cell-name">${escHtml(u.full_name||'')}</div>
                    <div class="user-cell-email">${escHtml(u.email||'')}</div>
                </div>
            </div>
        </td>
        <td>${escHtml(u.associate_id||'—')}</td>
        <td>
            <div style="font-size:13px;font-weight:600;color:#2d3a4a;">${escHtml(u.section||'—')}</div>
            <div style="font-size:12px;color:#8a93a2;">${escHtml(u.team||'—')}</div>
        </td>
        <td><span class="badge ${u.role}"><i class="fa-solid ${u.role==='admin'?'fa-shield-halved':'fa-user'}"></i> ${ucFirst(u.role||'user')}</span></td>
        <td><div class="date-chip"><i class="fa-regular fa-calendar"></i>${date}</div></td>
        <td><span class="badge ${isRej?'inactive':'pending'}"><i class="fa-solid ${isRej?'fa-ban':'fa-clock'}"></i> ${isRej?'Rejected':'Pending'}</span></td>
        <td>
            <div class="action-cell">
                ${isRej
                    ? `<button class="btn-restore-sm" onclick="openDetail(${u.id},'rejected')"><i class="fa-solid fa-eye"></i> View</button>
                       <button class="btn-restore-sm" onclick="quickAction(${u.id},'restore')" style="padding:6px 10px;" title="Restore"><i class="fa-solid fa-rotate-left"></i></button>
                       <button class="btn-delete-sm"  onclick="openDeleteConfirm(${u.id},'${escJs(u.full_name||'')}')" style="padding:6px 10px;" title="Delete"><i class="fa-solid fa-trash"></i></button>`
                    : `<button class="btn-approve-sm" onclick="openDetail(${u.id},'pending')"><i class="fa-solid fa-eye"></i> Review</button>
                       <button class="btn-approve-sm" onclick="quickAction(${u.id},'approve')" style="padding:6px 10px;" title="Approve"><i class="fa-solid fa-check"></i></button>
                       <button class="btn-reject-sm"  onclick="quickAction(${u.id},'reject')"  style="padding:6px 10px;" title="Reject"><i class="fa-solid fa-xmark"></i></button>`
                }
            </div>
        </td>`;
    tbody.prepend(tr);
}

function updateCounts() {
    const pc = pendingData.length;
    const rc = rejectedData.length;
    document.getElementById('cnt-pending').textContent  = pc;
    document.getElementById('cnt-rejected').textContent = rc;
    document.getElementById('tab-pending-count').textContent  = pc;
    document.getElementById('tab-rejected-count').textContent = rc;
    // Update sidebar badge
    const sideLink = document.querySelector('.nav-sub-link.active span');
    if (sideLink) sideLink.textContent = pc > 0 ? pc : '';
}

/* ══════════════════════════════════════════════════════════════
   UTILS
══════════════════════════════════════════════════════════════ */
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 3400);
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) { return String(s).replace(/'/g,"\\'"); }
function ucFirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

function toggleAcc(id) {
    const clicked = document.getElementById(id);
    const isOpen  = clicked.classList.contains('open');
    document.querySelectorAll('.nav-accordion').forEach(el => el.classList.remove('open'));
    if (!isOpen) clicked.classList.add('open');
}

/* Close detail modal on backdrop click */
document.getElementById('detail-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeDetail();
});
document.getElementById('del-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteConfirm();
});
</script>
</body>
</html>