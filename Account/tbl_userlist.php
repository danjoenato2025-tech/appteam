<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

include('../dbconnection/config.php');

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

// ── Fetch all users ──────────────────────────────────────────
$allUsers = $pdo->query('
    SELECT id, username, full_name, email, role, plan, billing, status,
           avatar_color, associate_id, section, team, created_at
    FROM users ORDER BY id ASC
')->fetchAll();

$totalUsers = count($allUsers);
$paidUsers = count(array_filter($allUsers, fn($u) => $u['plan'] !== 'Basic'));
$activeUsers = count(array_filter($allUsers, fn($u) => $u['status'] === 'active'));
$pendingUsers = count(array_filter($allUsers, fn($u) => $u['status'] === 'pending'));
$inactiveUsers = count(array_filter($allUsers, fn($u) => $u['status'] === 'inactive'));
$adminUsers = count(array_filter($allUsers, fn($u) => $u['role'] === 'admin'));
$regularUsers = count(array_filter($allUsers, fn($u) => $u['role'] === 'user'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management – Sneat</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        /* ── Clickable user name → edit_user.php ── */
        a.clickable-name {
            color: #2d3a4a;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: color .15s;
        }

        a.clickable-name:hover {
            color: #696cff;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        a.user-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            transition: opacity .15s, box-shadow .15s;
        }

        a.user-avatar:hover {
            opacity: .85;
            box-shadow: 0 0 0 3px #696cff44;
        }

        /* ══ 6-column stat grid ══ */
        .stat-grid {
            grid-template-columns: repeat(6, 1fr) !important;
        }

        @media (max-width: 1200px) {
            .stat-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        @media (max-width: 700px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        /* ══ Extra stat icon colours ══ */
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

        /* ══ Clickable stat cards ══ */
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
            cursor: pointer;
        }

        /* colour-matched active borders */
        .stat-card.active-filter.fc-green {
            border-color: #28c76f;
            box-shadow: 0 0 0 3px #28c76f22;
        }

        .stat-card.active-filter.fc-grey {
            border-color: #8a93a2;
            box-shadow: 0 0 0 3px #8a93a222;
        }

        .stat-card.active-filter.fc-orange {
            border-color: #ff9f43;
            box-shadow: 0 0 0 3px #ff9f4322;
        }

        .stat-card.active-filter.fc-red {
            border-color: #ff3e1d;
            box-shadow: 0 0 0 3px #ff3e1d22;
        }

        .stat-card.active-filter.fc-blue {
            border-color: #696cff;
            box-shadow: 0 0 0 3px #696cff22;
        }

        /* tooltip hint */
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

        /* ══ Role selector ══ */
        .role-selector {
            display: flex;
            gap: 10px;
            margin-top: 4px;
        }

        .role-option {
            flex: 1;
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
        }

        .role-option label {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #e0e2e8;
            border-radius: 8px;
            padding: 9px 13px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 500;
            color: #5a6070;
            background: #fafafa;
            transition: all .2s;
            user-select: none;
        }

        .role-option input[type="radio"]:checked+label {
            border-color: #696cff;
            background: #696cff12;
            color: #696cff;
        }

        .role-option.admin input[type="radio"]:checked+label {
            border-color: #ff3e1d;
            background: #ff3e1d10;
            color: #ff3e1d;
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

        .action-btn:disabled {
            opacity: .3;
            cursor: not-allowed;
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
            max-width: 480px;
            width: 94%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
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

        .view-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .view-name {
            font-size: 17px;
            font-weight: 700;
            color: #2d3a4a;
        }

        .view-email {
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

        /* ══ Edit Modal ══ */
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
            max-width: 540px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        /* ══ Delete Confirm Modal ══ */
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

        /* ══ Badge shared ══ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .badge.active {
            background: #e8f5e9;
            color: #28c76f;
        }

        .badge.inactive {
            background: #f5f5f5;
            color: #8a93a2;
        }

        .badge.pending {
            background: #fff8ec;
            color: #ff9f43;
        }

        .badge.admin {
            background: #ff3e1d10;
            color: #ff3e1d;
        }

        .badge.user {
            background: #696cff12;
            color: #696cff;
        }

        /* ══ Result Message Box (shown AFTER action completes) ══ */
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
            min-width: 110px;
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
            transition: opacity .2s;
        }

        .btn-result-ok.danger {
            background: linear-gradient(135deg, #ff3e1d, #ff6b4a);
        }

        .btn-result-ok:hover {
            opacity: .88;
        }

        /* ══ Notification Bell ══ */
        .notif-bell-wrap {
            position: relative;
            display: inline-flex;
        }

        .notif-bell-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: #696cff;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background .15s;
            position: relative;
        }

        .notif-bell-btn:hover {
            background: #696cff12;
        }

        .notif-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            min-width: 17px;
            height: 17px;
            border-radius: 50%;
            background: #ff3e1d;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            animation: badgePop .35s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes badgePop {
            from {
                transform: scale(0)
            }

            to {
                transform: scale(1)
            }
        }

        /* Notification dropdown */
        .notif-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 340px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 16px 50px rgba(0, 0, 0, .16);
            z-index: 99999;
            overflow: hidden;
            animation: vmIn .25s cubic-bezier(.34, 1.56, .64, 1);
        }

        .notif-dropdown.open {
            display: block;
        }

        .notif-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px 10px;
            border-bottom: 1px solid #f0f1f5;
        }

        .notif-dropdown-header span {
            font-size: 14px;
            font-weight: 700;
            color: #2d3a4a;
        }

        .notif-mark-all {
            font-size: 12px;
            color: #696cff;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
        }

        .notif-list {
            max-height: 320px;
            overflow-y: auto;
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 18px;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background .12s;
        }

        .notif-item:hover {
            background: #f6f7f9;
        }

        .notif-item.unread {
            background: #696cff08;
        }

        .notif-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .notif-item-body {
            flex: 1;
        }

        .notif-item-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #2d3a4a;
        }

        .notif-item-msg {
            font-size: 12px;
            color: #6e7a8a;
            margin-top: 2px;
        }

        .notif-item-time {
            font-size: 11px;
            color: #a0a8b5;
            margin-top: 4px;
        }

        .notif-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #696cff;
            margin-top: 6px;
            flex-shrink: 0;
        }

        .notif-empty {
            text-align: center;
            padding: 28px 18px;
            font-size: 13px;
            color: #a0a8b5;
        }

        .notif-footer {
            text-align: center;
            padding: 10px;
            border-top: 1px solid #f0f1f5;
        }

        .notif-footer a {
            font-size: 13px;
            font-weight: 600;
            color: #696cff;
            text-decoration: none;
        }

        /* ══ Approval Modal ══ */
        .approval-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 30, 60, .45);
            backdrop-filter: blur(4px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }

        .approval-modal-overlay.open {
            display: flex;
        }

        .approval-modal {
            background: #fff;
            border-radius: 16px;
            padding: 32px 32px 26px;
            max-width: 500px;
            width: 95%;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .2);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .approval-modal-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
        }

        .approval-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .approval-user-name {
            font-size: 16px;
            font-weight: 700;
            color: #2d3a4a;
        }

        .approval-user-email {
            font-size: 13px;
            color: #8a93a2;
            margin-top: 2px;
        }

        .approval-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 18px;
            background: #f6f7f9;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 22px;
        }

        .approval-field label {
            font-size: 11px;
            font-weight: 600;
            color: #a0a8b5;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .approval-field span {
            display: block;
            font-size: 13.5px;
            font-weight: 500;
            color: #2d3a4a;
            margin-top: 2px;
        }

        .approval-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-reject {
            background: #ff3e1d12;
            color: #ff3e1d;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-reject:hover {
            background: #ff3e1d20;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28c76f, #20a558);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .15s;
        }

        .btn-approve:hover {
            opacity: .88;
        }

        .btn-cancel-approval {
            background: #f0f1f5;
            color: #5a6070;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
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
                    <a class="nav-link active" href="#" onclick="toggleAcc('masterControl');return false;">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Master Control</span>
                        <i class="fa fa-chevron-right nav-chevron"></i>
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
                    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
                    <ul class="nav-sub">
                        <li><a class="nav-sub-link <?php echo ($current_page == '../Account/tbl_userlist.php') ? 'active' : ''; ?>"
                                href="../Account/tbl_userlist.php">List</a></li>
                        <li><a class="nav-sub-link <?php echo ($current_page == '../Account/user_account.php') ? 'active' : ''; ?>"
                                href="../Account/user_account.php">Account Settings</a></li>
                        <li><a class="nav-sub-link <?php echo ($current_page == '../Account/pending.php') ? 'active' : ''; ?>"
                                href="../Account/pending.php">Pending Approvals</a></li>
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
                <a href="#">User Management</a>
                <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                <span style="color:var(--text-mid);">User List</span>
            </div>

            <?php if (!$isAdmin): ?>
                <div class="access-notice">
                    <i class="fa-solid fa-circle-info"></i>
                    You are logged in as a <strong>User</strong>. Management actions are restricted to Admins only.
                </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="stat-grid">
                <!-- 1. Total Users → clear all filters -->
                <div class="stat-card" id="sc-total" onclick="filterByCard('total')" data-tip="Show all users">
                    <div>
                        <div class="stat-label">Session</div>
                        <div class="stat-value" id="stat-total"><?= $totalUsers ?> <span
                                class="stat-change up">(+29%)</span></div>
                        <div class="stat-sub">Total Users</div>
                    </div>
                    <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                </div>
                <!-- 2. Active Users -->
                <div class="stat-card fc-green" id="sc-active" onclick="filterByCard('active')"
                    data-tip="Filter: Active only">
                    <div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-value" id="stat-active"><?= $activeUsers ?> <span
                                class="stat-change up">(+12%)</span></div>
                        <div class="stat-sub">Currently active</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                </div>
                <!-- 3. Inactive Users -->
                <div class="stat-card fc-grey" id="sc-inactive" onclick="filterByCard('inactive')"
                    data-tip="Filter: Inactive only">
                    <div>
                        <div class="stat-label">Inactive Users</div>
                        <div class="stat-value" id="stat-inactive"><?= $inactiveUsers ?> <span
                                class="stat-change down">(-5%)</span></div>
                        <div class="stat-sub">Deactivated accounts</div>
                    </div>
                    <div class="stat-icon grey"><i class="fa-solid fa-user-slash"></i></div>
                </div>
                <!-- 4. Pending Users -->
                <div class="stat-card fc-orange" id="sc-pending" onclick="filterByCard('pending')"
                    data-tip="Filter: Pending only">
                    <div>
                        <div class="stat-label">Pending Users</div>
                        <div class="stat-value" id="stat-pending"><?= $pendingUsers ?> <span
                                class="stat-change up">(+42%)</span></div>
                        <div class="stat-sub">Awaiting approval</div>
                    </div>
                    <div class="stat-icon orange"><i class="fa-solid fa-user-clock"></i></div>
                </div>
                <!-- 5. Admin Count -->
                <div class="stat-card fc-red" id="sc-admin" onclick="filterByCard('admin')"
                    data-tip="Filter: Admins only">
                    <div>
                        <div class="stat-label">Admins</div>
                        <div class="stat-value" id="stat-admin"><?= $adminUsers ?></div>
                        <div class="stat-sub">Admin role accounts</div>
                    </div>
                    <div class="stat-icon red"><i class="fa-solid fa-shield-halved"></i></div>
                </div>
                <!-- 6. Regular User Count -->
                <div class="stat-card fc-blue" id="sc-users" onclick="filterByCard('user')"
                    data-tip="Filter: Users only">
                    <div>
                        <div class="stat-label">Users</div>
                        <div class="stat-value" id="stat-users"><?= $regularUsers ?></div>
                        <div class="stat-sub">Standard role accounts</div>
                    </div>
                    <div class="stat-icon blue"><i class="fa-solid fa-user"></i></div>
                </div>
            </div>

            <!-- Table -->
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
                        <input type="text" id="table-search" placeholder="Search User" oninput="renderTable()">
                    </div>
                    <button class="btn btn-outline" onclick="exportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?>
                        <button class="btn btn-primary" onclick="openAddModal()"><i class="fa fa-plus"></i> Add New
                            User</button>
                        <!-- Notification Bell (admin only) -->
                        <div class="notif-bell-wrap" id="notif-bell-wrap">
                            <button class="notif-bell-btn" onclick="toggleNotifDropdown()" title="Registration requests">
                                <i class="fa-solid fa-bell"></i>
                                <?php
                                $pendingCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'pending'));
                                if ($pendingCount > 0): ?>
                                    <span class="notif-badge" id="notif-badge"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </button>
                            <div class="notif-dropdown" id="notif-dropdown">
                                <div class="notif-dropdown-header">
                                    <span>🔔 Pending Registrations</span>
                                    <button class="notif-mark-all" onclick="closeNotifDropdown()">✕ Close</button>
                                </div>
                                <div class="notif-list" id="notif-list">
                                    <?php
                                    $pendingUsers2 = array_filter($allUsers, fn($u) => $u['status'] === 'pending');
                                    if (empty($pendingUsers2)): ?>
                                        <div class="notif-empty">
                                            <i class="fa-solid fa-circle-check"
                                                style="font-size:26px;color:#28c76f;display:block;margin-bottom:8px;"></i>
                                            No pending registrations
                                        </div>
                                    <?php else:
                                        foreach ($pendingUsers2 as $pu):
                                            $initPu = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $pu['full_name']), 0, 2)));
                                            $timeAgo = $pu['created_at'] ? date('M j, Y g:i A', strtotime($pu['created_at'])) : 'Unknown';
                                            ?>
                                            <div class="notif-item unread"
                                                onclick="openApprovalModal(<?= $pu['id'] ?>); closeNotifDropdown();">
                                                <div class="notif-avatar-sm"
                                                    style="background:<?= htmlspecialchars($pu['avatar_color']) ?>;"><?= $initPu ?>
                                                </div>
                                                <div class="notif-item-body">
                                                    <div class="notif-item-name"><?= htmlspecialchars($pu['full_name']) ?></div>
                                                    <div class="notif-item-msg">Registered and awaiting approval</div>
                                                    <div class="notif-item-time"><?= $timeAgo ?></div>
                                                </div>
                                                <div class="notif-dot"></div>
                                            </div>
                                        <?php endforeach; endif; ?>
                                </div>
                                <?php if (!empty($pendingUsers2)): ?>
                                    <div class="notif-footer">
                                        <a href="#" onclick="filterByPending(); closeNotifDropdown(); return false;">View all
                                            pending users →</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="cb" id="select-all" onchange="toggleAll(this)"></th>
                            <th>User</th>
                            <th>Associate ID</th>
                            <th>Section / Team</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody"></tbody>
                </table>

                <div class="pagination-row">
                    <div class="page-info" id="page-info">Showing 0 to 0 of 0 entries</div>
                    <div class="page-btns" id="page-btns"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- ══════════════════════════════════════════════════════════
     ADD USER MODAL
     Fields: Username | Full Name | Email | Associate ID (7 digits)
             Section | Team | Role (radio) | Status
     NO Plan field
══════════════════════════════════════════════════════════ -->
    <?php if ($isAdmin): ?>
        <div class="modal-overlay" id="add-modal">
            <div class="modal">
                <div class="modal-title">Add New User</div>
                <div class="modal-sub">Fill in all fields including a password for this account.</div>
                <div id="modal-alert" style="display:none;" class="modal-alert error"></div>

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Username</label>
                        <input class="modal-input" id="add-username" type="text" placeholder="johndoe">
                    </div>
                    <div>
                        <label class="modal-label">Full Name</label>
                        <input class="modal-input" id="add-full_name" type="text" placeholder="First Name, Last Name">
                    </div>
                </div>

                <label class="modal-label">Email</label>
                <input class="modal-input" id="add-email" type="email" placeholder="john@example.com">

                <!-- Associate ID – 7 digits only -->
                <label class="modal-label">Associate ID <span style="font-size:11px;color:#a0a8b5;">(7
                        digits)</span></label>
                <input class="modal-input" id="add-associate_id" type="text" placeholder="e.g. 1234567" maxlength="7"
                    pattern="\d{7}" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,7)">

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Section</label>
                        <select class="modal-input" id="add-section">
                            <option value="" disabled selected>Select a section</option>
                            <option value="ICT">ICT</option>
                            <option value="HR">HR</option>
                            <option value="FINANCE">FINANCE</option>
                        </select>
                    </div>
                    <div>
                        <label class="modal-label">Team</label>
                        <select class="modal-input" id="add-team">
                            <option value="" disabled selected>Select a team</option>
                            <option value="Application Management Team">Application Management Team</option>
                            <option value="User Management Team">User Management Team</option>
                            <option value="Computer Validation Team">Computer Validation Team</option>
                            <option value="Troubleshooting Operation Team">Troubleshooting Operation Team</option>
                        </select>
                    </div>
                </div>

                <label class="modal-label">Role</label>
                <div class="role-selector" style="margin-bottom:14px;">
                    <div class="role-option">
                        <input type="radio" name="add-role" id="add-role-user" value="user" checked>
                        <label for="add-role-user"><i class="fa-solid fa-user"></i> User</label>
                    </div>
                    <div class="role-option admin">
                        <input type="radio" name="add-role" id="add-role-admin" value="admin">
                        <label for="add-role-admin"><i class="fa-solid fa-shield-halved"></i> Admin</label>
                    </div>
                </div>

                <label class="modal-label">Status</label>
                <select class="modal-select" id="add-status">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>

                <!-- Password field -->
                <label class="modal-label" style="margin-top:12px;">Password <span
                        style="font-size:11px;color:#a0a8b5;">(min. 6 characters)</span></label>
                <div style="position:relative;">
                    <input class="modal-input" id="add-password" type="password" placeholder="Set account password"
                        style="padding-right:40px;">
                    <button type="button" onclick="toggleModalPass('add-password','add-eye')"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#8a93a2;font-size:14px;padding:2px 4px;">
                        <i class="fa-regular fa-eye-slash" id="add-eye"></i>
                    </button>
                </div>
                <!-- Strength bar -->
                <div style="height:4px;background:#eee;border-radius:4px;margin:6px 0 2px;overflow:hidden;">
                    <div id="add-strength-fill"
                        style="height:100%;width:0;border-radius:4px;transition:width .3s,background .3s;"></div>
                </div>
                <div id="add-strength-label" style="font-size:11px;color:#a0a8b5;margin-bottom:12px;min-height:14px;"></div>

                <div class="modal-row" style="margin-top:6px;">
                    <button class="modal-cancel" onclick="closeAddModal()">Cancel</button>
                    <button class="modal-submit" onclick="submitAddUser()">Add User</button>
                </div>
            </div>
        </div>

        <!-- ══ EDIT USER MODAL ══ -->
        <div class="edit-modal-overlay" id="edit-modal">
            <div class="edit-modal">
                <div class="modal-title">Edit User</div>
                <div class="modal-sub">Update the user's information below.</div>
                <div id="edit-modal-alert" style="display:none;" class="modal-alert error"></div>
                <input type="hidden" id="edit-id">

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Username</label>
                        <input class="modal-input" id="edit-username" type="text">
                    </div>
                    <div>
                        <label class="modal-label">Full Name</label>
                        <input class="modal-input" id="edit-full_name" type="text">
                    </div>
                </div>

                <label class="modal-label">Email</label>
                <input class="modal-input" id="edit-email" type="email">

                <!-- Associate ID – 7 digits only -->
                <label class="modal-label">Associate ID <span style="font-size:11px;color:#a0a8b5;">(7
                        digits)</span></label>
                <input class="modal-input" id="edit-associate_id" type="text" maxlength="7" pattern="\d{7}"
                    inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,7)">

                <div class="modal-grid2">
                    <div>
                        <label class="modal-label">Section</label>
                        <select class="modal-input" id="edit-section">
                            <option value="" disabled>Select a section</option>
                            <option value="ICT">ICT</option>
                            <option value="HR">HR</option>
                            <option value="FINANCE">FINANCE</option>
                        </select>
                    </div>
                    <div>
                        <label class="modal-label">Team</label>
                        <select class="modal-input" id="edit-team">
                            <option value="" disabled>Select a team</option>
                            <option value="Application Management Team">Application Management Team</option>
                            <option value="User Management Team">User Management Team</option>
                            <option value="Computer Validation Team">Computer Validation Team</option>
                            <option value="Troubleshooting Operation Team">Troubleshooting Operation Team</option>
                        </select>
                    </div>
                </div>

                <label class="modal-label">Role</label>
                <div class="role-selector" style="margin-bottom:14px;">
                    <div class="role-option">
                        <input type="radio" name="edit-role" id="edit-role-user" value="user">
                        <label for="edit-role-user"><i class="fa-solid fa-user"></i> User</label>
                    </div>
                    <div class="role-option admin">
                        <input type="radio" name="edit-role" id="edit-role-admin" value="admin">
                        <label for="edit-role-admin"><i class="fa-solid fa-shield-halved"></i> Admin</label>
                    </div>
                </div>

                <label class="modal-label">Status</label>
                <select class="modal-select" id="edit-status">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>

                <div class="modal-row" style="margin-top:18px;">
                    <button class="modal-cancel" onclick="closeEditModal()">Cancel</button>
                    <button class="modal-submit" onclick="submitEditUser()">Save Changes</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ══ VIEW USER MODAL ══ -->
    <div class="view-modal-overlay" id="view-modal">
        <div class="view-modal">
            <div class="view-modal-header">
                <div class="view-avatar" id="view-avatar"></div>
                <div>
                    <div class="view-name" id="view-name"></div>
                    <div class="view-email" id="view-email"></div>
                </div>
            </div>
            <div class="view-grid">
                <div class="view-field"><label>Username</label><span id="view-username"></span></div>
                <div class="view-field"><label>Associate ID</label><span id="view-associate_id"></span></div>
                <div class="view-field"><label>Section</label><span id="view-section"></span></div>
                <div class="view-field"><label>Team</label><span id="view-team"></span></div>
                <div class="view-field"><label>Role</label><span id="view-role"></span></div>
                <div class="view-field"><label>Status</label><span id="view-status"></span></div>
                <div class="view-field" style="grid-column:1/-1;"><label>Member Since</label><span
                        id="view-created_at"></span></div>
            </div>
            <div class="view-modal-footer">
                <button class="btn-close-view" onclick="closeViewModal()">Close</button>
                <?php if ($isAdmin): ?>
                    <button class="btn-edit-view" onclick="switchToEdit()"><i class="fa fa-pen"></i> Edit User</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ══ DELETE CONFIRM MODAL ══ -->
    <div class="del-modal-overlay" id="del-modal">
        <div class="del-modal">
            <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="del-title">Delete User?</div>
            <div class="del-sub">
                You are about to delete <strong id="del-name"></strong>.<br>
                This action cannot be undone.
            </div>
            <div class="del-btns">
                <button class="btn-cancel-del" onclick="closeDelModal()">Cancel</button>
                <button class="btn-confirm-del" onclick="confirmDelete()"><i class="fa fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
     RESULT MESSAGE BOX
     Shown after: Add User ✅ | Edit User ✅ | Delete User ✅
     Also shown for errors ❌
══════════════════════════════════════════════════════════ -->
    <div class="result-overlay" id="result-modal">
        <div class="result-box">
            <div class="result-icon-wrap" id="result-icon-wrap">
                <i id="result-icon"></i>
            </div>
            <div class="result-title" id="result-title"></div>
            <div class="result-msg" id="result-msg"></div>
            <div class="result-detail" id="result-detail" style="display:none;"></div>
            <button class="btn-result-ok" id="result-ok-btn" onclick="closeResultModal()">OK</button>
        </div>
    </div>

    <!-- ══ APPROVAL MODAL ══ -->
    <?php if ($isAdmin): ?>
        <div class="approval-modal-overlay" id="approval-modal">
            <div class="approval-modal">
                <div class="approval-modal-header">
                    <div class="approval-avatar" id="approval-avatar"></div>
                    <div>
                        <div class="approval-user-name" id="approval-name"></div>
                        <div class="approval-user-email" id="approval-email"></div>
                    </div>
                </div>
                <div class="approval-info-grid">
                    <div class="approval-field"><label>Username</label><span id="approval-username"></span></div>
                    <div class="approval-field"><label>Associate ID</label><span id="approval-assocId"></span></div>
                    <div class="approval-field"><label>Section</label><span id="approval-section"></span></div>
                    <div class="approval-field"><label>Team</label><span id="approval-team"></span></div>
                    <div class="approval-field"><label>Role</label><span id="approval-role"></span></div>
                    <div class="approval-field"><label>Registered</label><span id="approval-date"></span></div>
                </div>
                <div
                    style="margin-bottom:20px;padding:12px 16px;background:#fff8ec;border-radius:9px;border-left:4px solid #ff9f43;font-size:13px;color:#5a6070;">
                    <i class="fa-solid fa-clock" style="color:#ff9f43;margin-right:6px;"></i>
                    This user registered through the public registration form and is <strong>awaiting your
                        approval</strong>.
                </div>
                <div class="approval-actions">
                    <button class="btn-cancel-approval" onclick="closeApprovalModal()">Cancel</button>
                    <button class="btn-reject" onclick="processApproval('reject')"><i class="fa-solid fa-xmark"></i>
                        Reject</button>
                    <button class="btn-approve" onclick="processApproval('approve')"><i class="fa-solid fa-check"></i>
                        Approve</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Toast (kept for minor notices) -->
    <div class="toast" id="toast"></div>

    <script>
        /* ══════════════════════════════════════════════════════════
           DATA & STATE
        ══════════════════════════════════════════════════════════ */
        const ALL_USERS = <?= json_encode(array_values($allUsers)) ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const SESSION_ID = <?= (int) $sessionUser['id'] ?>;

        let allUsers = [...ALL_USERS];
        let curPage = 1;
        let perPage = 10;

        let _pendingDeleteId = null;
        let _pendingDeleteName = null;
        let _currentViewUser = null;

        /* ══════════════════════════════════════════════════════════
           TABLE RENDERING
        ══════════════════════════════════════════════════════════ */
        const statusBadge = {
            active: '<span class="badge active">Active</span>',
            inactive: '<span class="badge inactive">Inactive</span>',
            pending: '<span class="badge pending">Pending</span>',
        };

        /* ══════════════════════════════════════════════════════════
           ACTIVE FILTER STATE  (replaces hidden <select> elements)
        ══════════════════════════════════════════════════════════ */
        let _filterRole = '';   // 'admin' | 'user' | ''
        let _filterStatus = '';   // 'active' | 'inactive' | 'pending' | ''
        let _activeCardFilter = null;

        function getFiltered() {
            const q = (document.getElementById('table-search')?.value || '').toLowerCase();
            return allUsers.filter(u =>
                (!q || [u.full_name, u.email, u.username, u.associate_id || '', u.section || '', u.team || '']
                    .some(f => f.toLowerCase().includes(q))) &&
                (!_filterRole || u.role === _filterRole) &&
                (!_filterStatus || u.status === _filterStatus)
            );
        }

        function getInitials(name) {
            return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        }

        function renderTable() {
            const filtered = getFiltered();
            const total = filtered.length;
            const totalPages = Math.ceil(total / perPage) || 1;
            if (curPage > totalPages) curPage = totalPages;

            const start = (curPage - 1) * perPage;
            const paged = filtered.slice(start, start + perPage);

            document.getElementById('userTableBody').innerHTML = paged.map(u => {
                const init = getInitials(u.full_name);
                const isSelf = u.id === SESSION_ID;
                return `
<tr>
  <td><input type="checkbox" class="cb row-cb" data-id="${u.id}"></td>
  <td>
    <div class="user-cell">
      <a href="edit_user.php?id=${u.id}" class="user-avatar" style="background:${u.avatar_color};text-decoration:none;" title="Edit ${u.full_name}">${init}</a>
      <div>
        <div>
          ${IS_ADMIN
                        ? `<a href="edit_user.php?id=${u.id}" class="user-name clickable-name" title="Edit ${u.full_name}">${u.full_name}</a>`
                        : `<span class="user-name">${u.full_name}</span>`
                    }
          ${isSelf ? '<span style="font-size:10px;background:#e8f5e9;color:#28c76f;padding:2px 7px;border-radius:4px;font-weight:600;margin-left:4px;">You</span>' : ''}
        </div>
        <div class="user-email">${u.email}</div>
      </div>
    </div>
  </td>
  <td style="color:var(--text-mid);font-size:13.5px;">${u.associate_id || '—'}</td>
  <td>
    <div style="font-size:13.5px;color:var(--text-mid);">${u.section || '—'}</div>
    <div style="font-size:12px;color:var(--text-light);">${u.team || ''}</div>
  </td>
  <td>
    ${u.role === 'admin'
                        ? '<span class="badge admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>'
                        : '<span class="badge user"><i class="fa-solid fa-user"></i> User</span>'}
  </td>
  <td>${statusBadge[u.status] || u.status}</td>
  <td>
    <div class="action-cell">
      <button class="action-btn view-btn" title="View"   onclick="openViewModal(${u.id})"><i class="fa fa-eye"></i></button>
      ${IS_ADMIN ? `
      <button class="action-btn edit-btn" title="Edit"   onclick="openEditModal(${u.id})"><i class="fa fa-pen"></i></button>
      <button class="action-btn del"      title="Delete" onclick="openDelModal(${u.id},'${u.full_name.replace(/'/g, "\\\'")}')"
        ${isSelf ? 'disabled' : ''}><i class="fa fa-trash"></i></button>` : ''}
    </div>
  </td>
</tr>`;
            }).join('');

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
            document.getElementById('stat-total').innerHTML = `${allUsers.length} <span class="stat-change up">(+29%)</span>`;
            document.getElementById('stat-active').innerHTML = `${allUsers.filter(u => u.status === 'active').length} <span class="stat-change up">(+12%)</span>`;
            document.getElementById('stat-inactive').innerHTML = `${allUsers.filter(u => u.status === 'inactive').length} <span class="stat-change down">(-5%)</span>`;
            document.getElementById('stat-pending').innerHTML = `${allUsers.filter(u => u.status === 'pending').length} <span class="stat-change up">(+42%)</span>`;
            document.getElementById('stat-admin').textContent = allUsers.filter(u => u.role === 'admin').length;
            document.getElementById('stat-users').textContent = allUsers.filter(u => u.role === 'user').length;
        }

        /* ══════════════════════════════════════════════════════════
           STAT CARD CLICK FILTER
           Clicking a card sets the matching dropdown + re-renders.
           Clicking the already-active card (or Total) resets all.
           type: 'total' | 'active' | 'inactive' | 'pending' | 'admin' | 'user'
        ══════════════════════════════════════════════════════════ */
        /* ══════════════════════════════════════════════════════════
           STAT CARD CLICK FILTER
           Uses _filterRole / _filterStatus JS variables directly.
           Clicking the same card again resets (toggle off).
           type: 'total'|'active'|'inactive'|'pending'|'admin'|'user'
        ══════════════════════════════════════════════════════════ */
        function filterByCard(type) {
            // Toggle off — clicking active card resets everything
            if (_activeCardFilter === type || type === 'total') {
                _filterRole = '';
                _filterStatus = '';
                _activeCardFilter = null;
                _highlightCard(null);
                curPage = 1;
                renderTable();
                return;
            }

            // Apply filter
            _filterRole = '';
            _filterStatus = '';
            _activeCardFilter = type;

            switch (type) {
                case 'active':
                case 'inactive':
                case 'pending':
                    _filterStatus = type;
                    break;
                case 'admin':
                case 'user':
                    _filterRole = type;
                    break;
            }

            _highlightCard(type);
            curPage = 1;
            renderTable();
        }

        function _highlightCard(type) {
            const map = {
                active: 'sc-active',
                inactive: 'sc-inactive',
                pending: 'sc-pending',
                admin: 'sc-admin',
                user: 'sc-users',
            };
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            if (type && map[type]) {
                document.getElementById(map[type])?.classList.add('active-filter');
            }
        }
        function syncSearch() {
            document.getElementById('table-search').value = document.getElementById('topbar-search').value;
            curPage = 1; renderTable();
        }
        function toggleAll(cb) {
            document.querySelectorAll('.row-cb').forEach(r => r.checked = cb.checked);
        }

        /* ══════════════════════════════════════════════════════════
           RESULT MESSAGE BOX
           showResult(type, title, message, detailRows)
           type = 'success' | 'error'
           detailRows = [ ['Label', 'Value'], ... ]  (optional)
        ══════════════════════════════════════════════════════════ */
        function showResult(type, title, message, detailRows = []) {
            const iconWrap = document.getElementById('result-icon-wrap');
            const icon = document.getElementById('result-icon');
            const titleEl = document.getElementById('result-title');
            const msgEl = document.getElementById('result-msg');
            const detailEl = document.getElementById('result-detail');
            const okBtn = document.getElementById('result-ok-btn');

            iconWrap.className = `result-icon-wrap ${type}`;
            icon.className = type === 'success'
                ? 'fa-solid fa-circle-check'
                : 'fa-solid fa-circle-xmark';
            titleEl.textContent = title;
            msgEl.textContent = message;
            okBtn.className = type === 'error'
                ? 'btn-result-ok danger'
                : 'btn-result-ok';

            if (detailRows.length) {
                detailEl.innerHTML = detailRows.map(([label, val]) =>
                    `<div class="rd-row"><span class="rd-label">${label}</span><span>${val}</span></div>`
                ).join('');
                detailEl.style.display = 'block';
            } else {
                detailEl.style.display = 'none';
            }

            document.getElementById('result-modal').classList.add('open');
        }
        function closeResultModal() {
            document.getElementById('result-modal').classList.remove('open');
        }

        /* ══════════════════════════════════════════════════════════
           VIEW MODAL
        ══════════════════════════════════════════════════════════ */
        function openViewModal(id) {
            const u = allUsers.find(x => x.id === id);
            if (!u) return;
            _currentViewUser = u;
            const init = getInitials(u.full_name);
            document.getElementById('view-avatar').textContent = init;
            document.getElementById('view-avatar').style.background = u.avatar_color;
            document.getElementById('view-name').textContent = u.full_name;
            document.getElementById('view-email').textContent = u.email;
            document.getElementById('view-username').textContent = u.username;
            document.getElementById('view-associate_id').textContent = u.associate_id || '—';
            document.getElementById('view-section').textContent = u.section || '—';
            document.getElementById('view-team').textContent = u.team || '—';
            document.getElementById('view-created_at').textContent = u.created_at || '—';
            document.getElementById('view-role').innerHTML = u.role === 'admin'
                ? '<span class="badge admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>'
                : '<span class="badge user"><i class="fa-solid fa-user"></i> User</span>';
            document.getElementById('view-status').innerHTML = statusBadge[u.status] || u.status;
            document.getElementById('view-modal').classList.add('open');
        }
        function closeViewModal() {
            document.getElementById('view-modal').classList.remove('open');
            _currentViewUser = null;
        }
        function switchToEdit() {
            if (!_currentViewUser) return;
            closeViewModal();
            openEditModal(_currentViewUser.id);
        }

        /* ══════════════════════════════════════════════════════════
           ADD MODAL
        ══════════════════════════════════════════════════════════ */
        function openAddModal() {
            document.getElementById('add-username').value = '';
            document.getElementById('add-full_name').value = '';
            document.getElementById('add-email').value = '';
            document.getElementById('add-associate_id').value = '';
            document.getElementById('add-section').value = '';
            document.getElementById('add-team').value = '';
            document.getElementById('add-role-user').checked = true;
            document.getElementById('add-status').value = 'active';
            document.getElementById('add-password').value = '';
            const fill = document.getElementById('add-strength-fill');
            if (fill) { fill.style.width = '0'; fill.style.background = 'transparent'; }
            const lbl = document.getElementById('add-strength-label');
            if (lbl) lbl.textContent = '';
            hideAlert('modal-alert');
            document.getElementById('add-modal').classList.add('open');
        }
        function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); }
        document.getElementById('add-modal')?.addEventListener('click', function (e) {
            if (e.target === this) closeAddModal();
        });

        function submitAddUser() {
            const full_name = document.getElementById('add-full_name').value.trim();
            const username = document.getElementById('add-username').value.trim();
            const email = document.getElementById('add-email').value.trim();
            const associate_id = document.getElementById('add-associate_id').value.trim();
            const section = document.getElementById('add-section').value.trim();
            const team = document.getElementById('add-team').value.trim();
            const role = document.querySelector('input[name="add-role"]:checked').value;
            const status = document.getElementById('add-status').value;
            const password = document.getElementById('add-password').value;
            hideAlert('modal-alert');

            if (!full_name || !username || !email || !associate_id || !section || !team) {
                showAlert('modal-alert', 'All fields are required.'); return;
            }
            if (!/^\d{7}$/.test(associate_id)) {
                showAlert('modal-alert', 'Associate ID must be exactly 7 digits.'); return;
            }
            if (!password || password.length < 6) {
                showAlert('modal-alert', 'Password must be at least 6 characters.'); return;
            }

            fetch('../Crud/process_add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ full_name, username, email, associate_id, section, team, role, status, password }).toString()
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        allUsers.push(d.user);
                        curPage = 1;
                        renderTable();
                        closeAddModal();
                        showResult('success', 'User Added Successfully!', 'The new user account has been created.', [
                            ['👤 Full Name', d.user.full_name],
                            ['🪪 Associate ID', d.user.associate_id],
                            ['🏢 Section', d.user.section],
                            ['👥 Team', d.user.team],
                            ['🔐 Role', d.user.role.charAt(0).toUpperCase() + d.user.role.slice(1)],
                            ['📧 Email', d.user.email],
                        ]);
                    } else {
                        showAlert('modal-alert', d.message || 'Failed to add user.');
                    }
                })
                .catch(() => showAlert('modal-alert', 'Network error. Please try again.'));
        }

        /* ══════════════════════════════════════════════════════════
           EDIT MODAL
        ══════════════════════════════════════════════════════════ */
        function openEditModal(id) {
            const u = allUsers.find(x => x.id === id);
            if (!u) return;
            document.getElementById('edit-id').value = u.id;
            document.getElementById('edit-username').value = u.username || '';
            document.getElementById('edit-full_name').value = u.full_name || '';
            document.getElementById('edit-email').value = u.email || '';
            document.getElementById('edit-associate_id').value = u.associate_id || '';
            document.getElementById('edit-status').value = u.status || 'active';
            document.getElementById(u.role === 'admin' ? 'edit-role-admin' : 'edit-role-user').checked = true;

            // Populate section dropdown — match exact option value
            const secEl = document.getElementById('edit-section');
            secEl.value = u.section || '';
            // If no match found, add a temporary option so the value is visible
            if (secEl.value !== (u.section || '') && u.section) {
                const opt = new Option(u.section, u.section, true, true);
                secEl.add(opt);
            }

            // Populate team dropdown — match exact option value
            const teamEl = document.getElementById('edit-team');
            teamEl.value = u.team || '';
            if (teamEl.value !== (u.team || '') && u.team) {
                const opt = new Option(u.team, u.team, true, true);
                teamEl.add(opt);
            }

            hideAlert('edit-modal-alert');
            document.getElementById('edit-modal').classList.add('open');
        }
        function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); }
        document.getElementById('edit-modal')?.addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        function submitEditUser() {
            const id = document.getElementById('edit-id').value;
            const username = document.getElementById('edit-username').value.trim();
            const full_name = document.getElementById('edit-full_name').value.trim();
            const email = document.getElementById('edit-email').value.trim();
            const associate_id = document.getElementById('edit-associate_id').value.trim();
            const section = document.getElementById('edit-section').value.trim();
            const team = document.getElementById('edit-team').value.trim();
            const role = document.querySelector('input[name="edit-role"]:checked').value;
            const status = document.getElementById('edit-status').value;
            hideAlert('edit-modal-alert');

            if (!username || !full_name || !email || !associate_id || !section || !team) {
                showAlert('edit-modal-alert', 'All fields are required.'); return;
            }
            if (!/^\d{7}$/.test(associate_id)) {
                showAlert('edit-modal-alert', 'Associate ID must be exactly 7 digits.'); return;
            }

            fetch('../Crud/process_edit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id, username, full_name, email, associate_id, section, team, role, status }).toString()
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const idx = allUsers.findIndex(u => u.id == id);
                        if (idx !== -1) allUsers[idx] = { ...allUsers[idx], ...d.user };
                        renderTable();
                        closeEditModal();
                        // ✅ Success message box
                        showResult('success', 'User Updated Successfully!', 'The user\'s information has been saved.', [
                            ['👤 Full Name', d.user.full_name],
                            ['🪪 Associate ID', d.user.associate_id],
                            ['🏢 Section', d.user.section],
                            ['👥 Team', d.user.team],
                            ['🔐 Role', d.user.role.charAt(0).toUpperCase() + d.user.role.slice(1)],
                            ['📋 Status', d.user.status.charAt(0).toUpperCase() + d.user.status.slice(1)],
                        ]);
                    } else {
                        showAlert('edit-modal-alert', d.message || 'Failed to update user.');
                    }
                })
                .catch(() => showAlert('edit-modal-alert', 'Network error. Please try again.'));
        }

        /* ══════════════════════════════════════════════════════════
           DELETE MODAL
        ══════════════════════════════════════════════════════════ */
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

            fetch('../Crud/process_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${_pendingDeleteId}`
            })
                .then(r => r.json())
                .then(d => {
                    closeDelModal();
                    if (d.success) {
                        allUsers = allUsers.filter(u => u.id !== _pendingDeleteId);
                        renderTable();
                        // ✅ Success message box
                        showResult('success', 'User Deleted', `The account of "${deletedName}" has been permanently removed.`);
                    } else {
                        showResult('error', 'Delete Failed', d.message || 'Could not delete this user. Please try again.');
                    }
                })
                .catch(() => {
                    closeDelModal();
                    showResult('error', 'Network Error', 'Could not reach the server. Please check your connection.');
                });
        }

        /* ══════════════════════════════════════════════════════════
           HELPERS
        ══════════════════════════════════════════════════════════ */
        function showAlert(elId, msg) {
            const el = document.getElementById(elId);
            if (!el) return;
            el.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${msg}`;
            el.style.display = 'flex';
        }
        function hideAlert(elId) {
            const el = document.getElementById(elId);
            if (el) el.style.display = 'none';
        }
        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = `toast ${type} show`;
            clearTimeout(t._t);
            t._t = setTimeout(() => t.classList.remove('show'), 3200);
        }
        function exportCSV() {
            const rows = [['Name', 'Email', 'Username', 'Associate ID', 'Section', 'Team', 'Role', 'Status']];
            getFiltered().forEach(u => rows.push([
                u.full_name, u.email, u.username,
                u.associate_id || '', u.section || '', u.team || '',
                u.role, u.status
            ]));
            const csv = rows.map(r => r.join(',')).join('\n');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
            a.download = 'users.csv'; a.click();
            showToast('Exported successfully!', 'success');
        }
        function toggleAcc(id) {
            const clicked = document.getElementById(id);
            const isOpen = clicked.classList.contains('open');
            // Close ALL accordions first
            document.querySelectorAll('.nav-accordion').forEach(el => el.classList.remove('open'));
            // Re-open clicked one only if it was closed before
            if (!isOpen) clicked.classList.add('open');
        }

        /* ── Init ── */
        renderTable();

        /* ══════════════════════════════════════════════════════════
           PASSWORD FIELD IN ADD MODAL
        ══════════════════════════════════════════════════════════ */
        function toggleModalPass(inputId, iconId) {
            const inp = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            icon.className = show ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
        }

        document.getElementById('add-password')?.addEventListener('input', function () {
            const val = this.value;
            const fill = document.getElementById('add-strength-fill');
            const lbl = document.getElementById('add-strength-label');
            let score = 0;
            if (val.length >= 6) score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const map = [
                { w: '0%', bg: 'transparent', t: '' },
                { w: '25%', bg: '#ff3e1d', t: 'Weak' },
                { w: '50%', bg: '#ff9f43', t: 'Fair' },
                { w: '75%', bg: '#00cfe8', t: 'Good' },
                { w: '100%', bg: '#28c76f', t: 'Strong' },
            ];
            fill.style.width = map[score].w;
            fill.style.background = map[score].bg;
            lbl.textContent = map[score].t;
            lbl.style.color = map[score].bg;
        });

        /* ══════════════════════════════════════════════════════════
           NOTIFICATION BELL
        ══════════════════════════════════════════════════════════ */
        function toggleNotifDropdown() {
            document.getElementById('notif-dropdown').classList.toggle('open');
        }
        function closeNotifDropdown() {
            document.getElementById('notif-dropdown')?.classList.remove('open');
        }
        // Close when clicking outside
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('notif-bell-wrap');
            if (wrap && !wrap.contains(e.target)) closeNotifDropdown();
        });

        function filterByPending() {
            _filterStatus = 'pending';
            _filterRole = '';
            _activeCardFilter = null;
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            curPage = 1;
            renderTable();
        }

        /* ══════════════════════════════════════════════════════════
           APPROVAL MODAL
        ══════════════════════════════════════════════════════════ */
        let _approvalUserId = null;

        function openApprovalModal(id) {
            const u = allUsers.find(x => x.id === id);
            if (!u) return;
            _approvalUserId = id;

            const initials = (u.full_name || '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
            const av = document.getElementById('approval-avatar');
            av.textContent = initials;
            av.style.background = u.avatar_color || '#696cff';

            document.getElementById('approval-name').textContent = u.full_name || '';
            document.getElementById('approval-email').textContent = u.email || '';
            document.getElementById('approval-username').textContent = u.username || '';
            document.getElementById('approval-assocId').textContent = u.associate_id || '—';
            document.getElementById('approval-section').textContent = u.section || '—';
            document.getElementById('approval-team').textContent = u.team || '—';
            document.getElementById('approval-role').textContent = u.role ? (u.role.charAt(0).toUpperCase() + u.role.slice(1)) : '—';
            document.getElementById('approval-date').textContent = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

            document.getElementById('approval-modal').classList.add('open');
        }

        function closeApprovalModal() {
            document.getElementById('approval-modal').classList.remove('open');
            _approvalUserId = null;
        }

        function processApproval(action) {
            if (!_approvalUserId) return;
            const newStatus = action === 'approve' ? 'active' : 'inactive';

            fetch('../Crud/process_edit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id: _approvalUserId,
                    status: newStatus,
                    _approval: '1'
                }).toString()
            })
                .then(r => r.json())
                .then(d => {
                    closeApprovalModal();
                    if (d.success) {
                        // Update local data
                        const idx = allUsers.findIndex(u => u.id == _approvalUserId);
                        if (idx !== -1) allUsers[idx].status = newStatus;
                        renderTable();
                        // Update badge count
                        const newPending = allUsers.filter(u => u.status === 'pending').length;
                        const badge = document.getElementById('notif-badge');
                        if (badge) { badge.textContent = newPending; if (newPending === 0) badge.remove(); }
                        const userName = allUsers.find(u => u.id == _approvalUserId)?.full_name || 'User';
                        showResult(
                            action === 'approve' ? 'success' : 'error',
                            action === 'approve' ? 'User Approved! ✅' : 'User Rejected',
                            action === 'approve'
                                ? `${d.user?.full_name || 'The user'}'s account is now active and they can log in.`
                                : `${d.user?.full_name || 'The user'}'s account has been set to inactive.`
                        );
                    } else {
                        showResult('error', 'Action Failed', d.message || 'Could not update this user.');
                    }
                })
                .catch(() => {
                    closeApprovalModal();
                    showResult('error', 'Network Error', 'Could not reach the server. Please try again.');
                });
        }
    </script>
</body>

</html>