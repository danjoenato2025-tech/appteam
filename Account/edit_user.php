<?php
/**
 * edit_user.php
 * ─────────────────────────────────────────────────────────────
 * Admin-only: Edit any user's profile with the same UI as
 * user_account.php. Access via: edit_user.php?id=X
 * ─────────────────────────────────────────────────────────────
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

include('../dbconnection/config.php');

// ── Admin guard ────────────────────────────────────────────────
if ($_SESSION['role'] !== 'admin') {
    header('Location: tbl_userlist.php');
    exit;
}

$adminId = (int) $_SESSION['user_id'];

// ── Target user from ?id= ─────────────────────────────────────
$targetId = (int) ($_GET['id'] ?? 0);
if (!$targetId) {
    header('Location: tbl_userlist.php');
    exit;
}

function fetchUser(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('
        SELECT id, username, full_name, email, role, status,
               avatar_color, avatar, associate_id, section, team,
               phone, address, created_at
        FROM users WHERE id = ? LIMIT 1
    ');
    $s->execute([$id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    foreach (['phone', 'address', 'section', 'team', 'associate_id', 'avatar'] as $k) {
        $row[$k] = $row[$k] ?? '';
    }
    return $row;
}

$targetUser = fetchUser($pdo, $targetId);
if (!$targetUser) {
    header('Location: tbl_userlist.php?err=notfound');
    exit;
}

// ── Admin's own session info (for topbar) ─────────────────────
$adminUser = fetchUser($pdo, $adminId);
$adminInitials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(array_values(array_filter(explode(' ', trim($adminUser['full_name'])))), 0, 2)
));

$successMsg = $errorMsg = '';

// ── Handle POST save ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $fn    = trim($_POST['first_name']   ?? '');
    $ln    = trim($_POST['last_name']    ?? '');
    $email = trim($_POST['email']        ?? '');
    $uname = trim($_POST['username']     ?? '');
    $assoc = trim($_POST['associate_id'] ?? '');
    $sec   = trim($_POST['section']      ?? '');
    $team  = trim($_POST['team']         ?? '');
    $phone = trim($_POST['phone']        ?? '');
    $addr  = trim($_POST['address']      ?? '');
    $role  = in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user';
    $status= in_array($_POST['status'] ?? '', ['active','pending','inactive']) ? $_POST['status'] : 'active';
    $fullName = trim("$fn $ln");

    if (!$fn || !$email || !$uname)
        $errorMsg = 'First name, email, and username are required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errorMsg = 'Please enter a valid email address.';
    elseif ($assoc && !preg_match('/^\d{7}$/', $assoc))
        $errorMsg = 'Associate ID must be exactly 7 digits.';
    else {
        $dup = $pdo->prepare('SELECT id FROM users WHERE (email=? OR username=?) AND id!=? LIMIT 1');
        $dup->execute([$email, $uname, $targetId]);
        if ($dup->fetch()) {
            $errorMsg = 'That email or username is already used by another account.';
        } else {
            $pdo->prepare('UPDATE users SET full_name=?,email=?,username=?,associate_id=?,section=?,team=?,phone=?,address=?,role=?,status=? WHERE id=?')
                ->execute([$fullName, $email, $uname, $assoc, $sec, $team, $phone, $addr, $role, $status, $targetId]);
            $targetUser = fetchUser($pdo, $targetId);
            $successMsg = 'User profile updated successfully!';
        }
    }
}

// ── Build display vars ────────────────────────────────────────
$nameParts = explode(' ', $targetUser['full_name'], 2);
$firstName = $nameParts[0] ?? '';
$lastName  = $nameParts[1] ?? '';

$nameWords = array_filter(explode(' ', trim($targetUser['full_name'])));
$targetInitials = implode('', array_map(
    fn($w) => strtoupper($w[0]),
    array_slice(array_values($nameWords), 0, 2)
));
if (!$targetInitials) $targetInitials = strtoupper(substr($targetUser['username'], 0, 2));

$isSelf = ($targetId === $adminId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User – <?= htmlspecialchars($targetUser['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Tabs ── */
        .acct-tabs { display:flex; gap:2px; margin-bottom:20px; }
        .acct-tab { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:8px;
            font-size:14px; font-weight:500; color:#6e7a8a; cursor:pointer; border:none; background:transparent;
            transition:all .15s; text-decoration:none; }
        .acct-tab:hover  { background:#f0f1f5; color:#2d3a4a; }
        .acct-tab.active { background:#696cff; color:#fff; }
        .acct-panel       { display:none; }
        .acct-panel.active{ display:block; }

        /* ── Cards ── */
        .acct-card { background:#fff; border-radius:14px; padding:28px 30px;
            box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:24px; }

        /* ── Admin badge banner ── */
        .admin-banner { display:flex; align-items:center; gap:10px; background:#fff8ec;
            border:1px solid #ffe0a0; border-radius:10px; padding:11px 18px; margin-bottom:20px;
            font-size:13px; color:#b45309; }
        .admin-banner i { color:#f59e0b; font-size:16px; }
        .admin-banner strong { color:#92400e; }

        /* ── Avatar ── */
        .avatar-row { display:flex; align-items:center; gap:22px; padding-bottom:22px;
            margin-bottom:22px; border-bottom:1px solid #f0f1f5; }
        .av-circle { width:86px; height:86px; border-radius:12px; display:flex; align-items:center;
            justify-content:center; font-size:28px; font-weight:700; color:#fff; flex-shrink:0;
            box-shadow:0 4px 14px rgba(0,0,0,.14); letter-spacing:1px; overflow:hidden; }
        .av-right  { display:flex; flex-direction:column; gap:8px; }
        .av-btns   { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-upload-photo { background:#696cff; color:#fff; border:none; border-radius:7px; padding:9px 16px;
            font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; transition:opacity .15s; }
        .btn-upload-photo:hover { opacity:.86; }
        .btn-reset-photo  { background:#f0f1f5; color:#5a6070; border:none; border-radius:7px;
            padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer; }
        .btn-reset-photo:hover { background:#e4e5ec; }
        .av-hint { font-size:12px; color:#a0a8b5; }

        /* ── Info strip ── */
        .info-strip { display:flex; gap:20px; flex-wrap:wrap; background:#f6f7f9; border-radius:10px;
            padding:14px 18px; margin-bottom:22px; }
        .is-item { display:flex; flex-direction:column; gap:3px; }
        .is-item label { font-size:10.5px; font-weight:700; color:#a0a8b5; text-transform:uppercase; letter-spacing:.5px; }
        .is-item span  { font-size:13.5px; font-weight:500; color:#2d3a4a; }
        .chip { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600;
            padding:3px 10px; border-radius:20px; }
        .chip.admin   { background:#ff3e1d12; color:#ff3e1d; }
        .chip.user    { background:#696cff12; color:#696cff; }
        .chip.active  { background:#e8f5e9;   color:#28c76f; }
        .chip.inactive{ background:#f5f5f5;   color:#8a93a2; }
        .chip.pending { background:#fff8ec;   color:#ff9f43; }

        /* ── Form ── */
        .f-section-title { font-size:14px; font-weight:700; color:#2d3a4a; margin-bottom:16px; }
        .f-divider { border:none; border-top:1px solid #f0f1f5; margin:24px 0; }
        .f-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px 22px; }
        .f-group { display:flex; flex-direction:column; gap:6px; }
        .f-label { font-size:13px; font-weight:600; color:#4a5568; }
        .f-label sup { color:#ff3e1d; }
        .f-input { border:1.5px solid #e0e2e8; border-radius:8px; padding:9px 12px; font-size:14px;
            font-family:inherit; color:#2d3a4a; background:#fff; outline:none; width:100%;
            box-sizing:border-box; transition:border-color .15s,box-shadow .15s; }
        .f-input:focus { border-color:#696cff; box-shadow:0 0 0 3px #696cff18; }
        .f-input.ro { background:#f6f7f9; color:#8a93a2; cursor:not-allowed; }
        .f-hint     { font-size:11.5px; color:#a0a8b5; }
        .f-hint.ok  { color:#28c76f; }
        .f-hint.err { color:#ff3e1d; }
        .f-ico-wrap { position:relative; }
        .f-ico-wrap .f-input { padding-left:36px; }
        .f-ico-wrap .ico { position:absolute; left:11px; top:50%; transform:translateY(-50%);
            color:#b0b8c4; font-size:13px; pointer-events:none; }

        /* ── Admin-only fields highlight ── */
        .admin-field .f-label::after { content:' (Admin)'; font-size:10px; color:#f59e0b; font-weight:500; }
        .admin-field .f-input { border-color:#f59e0b44; background:#fffbeb; }
        .admin-field .f-input:focus { border-color:#f59e0b; box-shadow:0 0 0 3px #f59e0b18; }

        /* ── Role selector ── */
        .role-selector { display:flex; gap:12px; }
        .role-option { display:flex; align-items:center; gap:8px; }
        .role-option input[type=radio] { accent-color:#696cff; width:16px; height:16px; cursor:pointer; }
        .role-option label { font-size:13.5px; font-weight:500; color:#4a5568; cursor:pointer;
            display:flex; align-items:center; gap:5px; }
        .role-option.admin label { color:#ff3e1d; }

        /* ── Actions ── */
        .f-actions { display:flex; gap:12px; margin-top:26px; align-items:center; }
        .btn-save { background:linear-gradient(135deg,#696cff,#9b59f5); color:#fff; border:none;
            border-radius:8px; padding:11px 26px; font-size:14px; font-weight:600; cursor:pointer;
            transition:opacity .15s; display:flex; align-items:center; gap:7px; }
        .btn-save:hover { opacity:.87; }
        .btn-cancel-f { background:#f0f1f5; color:#5a6070; border:none; border-radius:8px;
            padding:11px 20px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:6px; }
        .btn-cancel-f:hover { background:#e4e5ec; }

        /* ── Alerts ── */
        .acct-alert { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:9px;
            font-size:13.5px; font-weight:500; margin-bottom:20px; }
        .acct-alert.success { background:#e8f5e9; color:#218838; border-left:4px solid #28c76f; }
        .acct-alert.error   { background:#fff0ee; color:#c62828; border-left:4px solid #ff3e1d; }

        /* ── Danger zone ── */
        .danger-card { background:#fff; border-radius:14px; padding:28px 30px;
            box-shadow:0 2px 10px rgba(0,0,0,.06); }
        .danger-title { font-size:16px; font-weight:700; color:#2d3a4a; margin-bottom:16px; }
        .danger-warn  { background:#fff8ec; border:1px solid #ffe0a0; border-radius:9px;
            padding:14px 18px; margin-bottom:18px; }
        .danger-warn .dw1 { font-weight:700; color:#e65100; font-size:14px; margin-bottom:4px; }
        .danger-warn .dw2 { color:#8a6030; font-size:13px; }
        .danger-check { display:flex; align-items:center; gap:9px; font-size:13.5px; color:#4a5568;
            margin-bottom:20px; cursor:pointer; }
        .danger-check input { width:16px; height:16px; accent-color:#ff3e1d; cursor:pointer; }
        .btn-deactivate { background:#ff3e1d; color:#fff; border:none; border-radius:8px; padding:11px 22px;
            font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:7px;
            transition:opacity .15s; }
        .btn-deactivate:hover    { opacity:.87; }
        .btn-deactivate:disabled { opacity:.38; cursor:not-allowed; }

        /* ── Back link ── */
        .back-link { display:inline-flex; align-items:center; gap:7px; color:#696cff; font-size:13px;
            font-weight:600; text-decoration:none; margin-bottom:14px; }
        .back-link:hover { text-decoration:underline; }

        @media(max-width:640px){
            .f-grid { grid-template-columns:1fr; }
            .avatar-row { flex-direction:column; }
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
            <li class="nav-item"><a class="nav-link" href="../index.php"><span class="nav-icon"><i class="fa-solid fa-house"></i></span><span class="nav-text">Dashboard</span></a></li>
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
                    <li><a class="nav-sub-link active" href="tbl_userlist.php">List</a></li>
                    <li><a class="nav-sub-link" href="user_account.php">Account Settings</a></li>
                </ul>
            </li>
        </ul>
    </nav>
</aside>

<!-- ════ PAGE WRAPPER ════ -->
<div class="page-wrapper">
    <header class="topbar">
        <div class="topbar-search"><i class="fa fa-search" style="color:var(--text-light);font-size:13px;"></i>
            <input type="text" placeholder="Search (CTRL + K)"></div>
        <div class="topbar-spacer"></div>
        <div style="display:flex;align-items:center;gap:6px;">
            <div class="topbar-icon"><i class="fa fa-globe"></i></div>
            <div class="topbar-icon"><i class="fa fa-sun"></i></div>
            <div class="topbar-icon"><i class="fa-solid fa-table-cells"></i></div>
            <div class="topbar-icon"><i class="fa fa-bell"></i></div>
            <div class="avatar-top" style="background:<?= htmlspecialchars($adminUser['avatar_color'] ?? '#696cff') ?>;">
                <?= htmlspecialchars($adminInitials) ?>
                <div class="user-dropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-name"><?= htmlspecialchars($adminUser['full_name']) ?></div>
                        <div class="dropdown-email"><?= htmlspecialchars($adminUser['email']) ?></div>
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
            <a href="../index.php">Home</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <a href="tbl_userlist.php">Users</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <span style="color:var(--text-mid);">Edit – <?= htmlspecialchars($targetUser['full_name']) ?></span>
        </div>

        <!-- Back button -->
        <a href="tbl_userlist.php" class="back-link">
            <i class="fa fa-arrow-left"></i> Back to User List
        </a>

        <!-- Admin notice -->
        <div class="admin-banner">
            <i class="fa-solid fa-shield-halved"></i>
            <span>You are editing <strong><?= htmlspecialchars($targetUser['full_name']) ?></strong>'s profile as Admin.
            <?= $isSelf ? ' <em>(This is your own account)</em>' : '' ?>
            Fields highlighted in <strong style="color:#f59e0b;">amber</strong> are admin-only.</span>
        </div>

        <!-- Tabs -->
        <div class="acct-tabs">
            <button class="acct-tab active" id="tab-account" onclick="switchTab('account',this)">
                <i class="fa-solid fa-user"></i> Account
            </button>
            <button class="acct-tab" id="tab-security" onclick="switchTab('security',this)">
                <i class="fa-solid fa-lock"></i> Security
            </button>
        </div>

        <!-- ═══ PANEL: ACCOUNT ═══ -->
        <div id="panel-account" class="acct-panel active">

            <?php if ($successMsg): ?>
            <div class="acct-alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="acct-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="acct-card">

                <!-- Avatar -->
                <div class="avatar-row">
                    <div class="av-circle" id="av-circle" style="background:<?= htmlspecialchars($targetUser['avatar_color']) ?>;">
                        <?php if (!empty($targetUser['avatar'])): ?>
                            <img src="../<?= htmlspecialchars($targetUser['avatar']) ?>?v=<?= time() ?>"
                                 alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                        <?php else: ?>
                            <span id="av-initials"><?= htmlspecialchars($targetInitials) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="av-right">
                        <div class="av-btns">
                            <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/gif,image/webp"
                                   style="display:none" onchange="uploadAvatar(this)">
                            <button class="btn-upload-photo" type="button"
                                    onclick="document.getElementById('avatar-file-input').click()">
                                <i class="fa-solid fa-upload"></i> Upload new photo
                            </button>
                            <button class="btn-reset-photo" type="button" onclick="resetAvatar()">Reset</button>
                        </div>
                        <div class="av-hint">Allowed JPG, GIF or PNG. Max size of 800K</div>
                        <div id="av-status" style="font-size:12px;margin-top:4px;"></div>
                    </div>
                </div>

                <!-- Info strip -->
                <div class="info-strip">
                    <div class="is-item">
                        <label>Role</label>
                        <span><span class="chip <?= $targetUser['role'] ?>">
                            <i class="fa-solid <?= $targetUser['role'] === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                            <?= ucfirst($targetUser['role']) ?>
                        </span></span>
                    </div>
                    <div class="is-item">
                        <label>Status</label>
                        <span><span class="chip <?= $targetUser['status'] ?>"><?= ucfirst($targetUser['status']) ?></span></span>
                    </div>
                    <div class="is-item">
                        <label>Associate ID</label>
                        <span><?= htmlspecialchars($targetUser['associate_id'] ?: '—') ?></span>
                    </div>
                    <div class="is-item">
                        <label>Section</label>
                        <span><?= htmlspecialchars($targetUser['section'] ?: '—') ?></span>
                    </div>
                    <div class="is-item">
                        <label>Team</label>
                        <span><?= htmlspecialchars($targetUser['team'] ?: '—') ?></span>
                    </div>
                    <div class="is-item">
                        <label>Member Since</label>
                        <span><?= date('M d, Y', strtotime($targetUser['created_at'])) ?></span>
                    </div>
                </div>

                <!-- Form -->
                <form method="POST" action="edit_user.php?id=<?= $targetId ?>" id="profile-form">
                    <input type="hidden" name="action" value="update_user">

                    <!-- Personal Information -->
                    <div class="f-section-title">Personal Information</div>
                    <div class="f-grid">

                        <div class="f-group">
                            <label class="f-label">First Name <sup>*</sup></label>
                            <input class="f-input" type="text" name="first_name"
                                   value="<?= htmlspecialchars($firstName) ?>" placeholder="First Name" required>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Last Name</label>
                            <input class="f-input" type="text" name="last_name"
                                   value="<?= htmlspecialchars($lastName) ?>" placeholder="Last Name">
                        </div>
                        <div class="f-group">
                            <label class="f-label">E-mail <sup>*</sup></label>
                            <div class="f-ico-wrap"><i class="ico fa-solid fa-envelope"></i>
                                <input class="f-input" type="email" name="email"
                                       value="<?= htmlspecialchars($targetUser['email']) ?>"
                                       placeholder="john@example.com" required>
                            </div>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Organization</label>
                            <input class="f-input ro" type="text"
                                   value="<?= htmlspecialchars($targetUser['section'] ?: 'N/A') ?>" readonly>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Phone Number</label>
                            <div class="f-ico-wrap"><i class="ico fa-solid fa-phone"></i>
                                <input class="f-input" type="text" name="phone"
                                       value="<?= htmlspecialchars($targetUser['phone']) ?>"
                                       placeholder="+63 912 345 6789">
                            </div>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Address</label>
                            <div class="f-ico-wrap"><i class="ico fa-solid fa-location-dot"></i>
                                <input class="f-input" type="text" name="address"
                                       value="<?= htmlspecialchars($targetUser['address']) ?>"
                                       placeholder="Street, City">
                            </div>
                        </div>

                    </div>

                    <hr class="f-divider">

                    <!-- Work Information -->
                    <div class="f-section-title">Work Information</div>
                    <div class="f-grid">

                        <div class="f-group">
                            <label class="f-label">Associate ID <span style="font-weight:400;font-size:11px;color:#a0a8b5;">(7 digits)</span></label>
                            <input class="f-input" type="text" name="associate_id" id="assoc-inp"
                                   value="<?= htmlspecialchars($targetUser['associate_id']) ?>"
                                   maxlength="7" pattern="\d{7}" inputmode="numeric" placeholder="1234567"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,7);liveAssoc(this);">
                            <div class="f-hint" id="assoc-hint">Numbers only · exactly 7 digits</div>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Username <sup>*</sup></label>
                            <div class="f-ico-wrap"><i class="ico fa-solid fa-at"></i>
                                <input class="f-input" type="text" name="username"
                                       value="<?= htmlspecialchars($targetUser['username']) ?>"
                                       placeholder="username" required>
                            </div>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Section</label>
                            <select class="f-input" name="section">
                                <option value="" disabled <?= !$targetUser['section'] ? 'selected' : '' ?>>Select a section</option>
                                <option value="ICT"     <?= $targetUser['section'] === 'ICT'     ? 'selected' : '' ?>>ICT</option>
                                <option value="HR"      <?= $targetUser['section'] === 'HR'      ? 'selected' : '' ?>>HR</option>
                                <option value="FINANCE" <?= $targetUser['section'] === 'FINANCE' ? 'selected' : '' ?>>FINANCE</option>
                            </select>
                        </div>
                        <div class="f-group">
                            <label class="f-label">Team</label>
                            <select class="f-input" name="team">
                                <option value="" disabled <?= !$targetUser['team'] ? 'selected' : '' ?>>Select a team</option>
                                <option value="Application Management Team"     <?= $targetUser['team'] === 'Application Management Team'     ? 'selected' : '' ?>>Application Management Team</option>
                                <option value="User Management Team"            <?= $targetUser['team'] === 'User Management Team'            ? 'selected' : '' ?>>User Management Team</option>
                                <option value="Computer Validation Team"        <?= $targetUser['team'] === 'Computer Validation Team'        ? 'selected' : '' ?>>Computer Validation Team</option>
                                <option value="Troubleshooting Operation Team"  <?= $targetUser['team'] === 'Troubleshooting Operation Team'  ? 'selected' : '' ?>>Troubleshooting Operation Team</option>
                            </select>
                        </div>

                    </div>

                    <hr class="f-divider">

                    <!-- Admin-only: Role & Status -->
                    <div class="f-section-title" style="color:#f59e0b;display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-shield-halved" style="font-size:13px;"></i> Admin Controls
                    </div>
                    <div class="f-grid">

                        <div class="f-group admin-field">
                            <label class="f-label">Role</label>
                            <div class="role-selector" style="margin-top:4px;">
                                <div class="role-option">
                                    <input type="radio" name="role" id="role-user" value="user"
                                           <?= $targetUser['role'] === 'user' ? 'checked' : '' ?>>
                                    <label for="role-user"><i class="fa-solid fa-user"></i> User</label>
                                </div>
                                <div class="role-option admin">
                                    <input type="radio" name="role" id="role-admin" value="admin"
                                           <?= $targetUser['role'] === 'admin' ? 'checked' : '' ?>>
                                    <label for="role-admin"><i class="fa-solid fa-shield-halved"></i> Admin</label>
                                </div>
                            </div>
                        </div>

                        <div class="f-group admin-field">
                            <label class="f-label">Status</label>
                            <select class="f-input" name="status">
                                <option value="active"   <?= $targetUser['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                <option value="pending"  <?= $targetUser['status'] === 'pending'  ? 'selected' : '' ?>>Pending</option>
                                <option value="inactive" <?= $targetUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                    </div>

                    <div class="f-actions">
                        <button type="submit" class="btn-save">
                            <i class="fa-solid fa-floppy-disk"></i> Save Changes
                        </button>
                        <a href="tbl_userlist.php" class="btn-cancel-f">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </a>
                    </div>

                </form>
            </div>

            <!-- Danger zone (admin can deactivate any user) -->
            <?php if (!$isSelf): ?>
            <div class="danger-card">
                <div class="danger-title">Danger Zone</div>
                <div class="danger-warn">
                    <p class="dw1">Deactivate this user account?</p>
                    <p class="dw2">This will set <strong><?= htmlspecialchars($targetUser['full_name']) ?></strong>'s status to Inactive and they will no longer be able to log in.</p>
                </div>
                <label class="danger-check">
                    <input type="checkbox" id="chk-deactivate"
                           onchange="document.getElementById('btn-deactivate').disabled=!this.checked">
                    I confirm deactivating this account
                </label>
                <button class="btn-deactivate" id="btn-deactivate" disabled onclick="doDeactivate()">
                    <i class="fa-solid fa-user-slash"></i> Deactivate Account
                </button>
            </div>
            <?php endif; ?>

        </div><!-- /panel-account -->

        <!-- ═══ PANEL: SECURITY ═══ -->
        <div id="panel-security" class="acct-panel">
            <div class="acct-card">
                <div class="f-section-title">Reset Password</div>
                <p style="font-size:13px;color:#6e7a8a;margin-bottom:20px;">
                    Set a new password for <strong><?= htmlspecialchars($targetUser['full_name']) ?></strong>.
                    They will need to use this password on their next login.
                </p>
                <div id="pw-alert" style="display:none;" class="acct-alert error"></div>
                <div class="f-grid">
                    <div class="f-group">
                        <label class="f-label">New Password</label>
                        <div class="f-ico-wrap"><i class="ico fa-solid fa-lock"></i>
                            <input class="f-input" type="password" id="new-pw" placeholder="Min. 8 characters">
                        </div>
                    </div>
                    <div class="f-group">
                        <label class="f-label">Confirm Password</label>
                        <div class="f-ico-wrap"><i class="ico fa-solid fa-lock"></i>
                            <input class="f-input" type="password" id="confirm-pw" placeholder="Repeat password">
                        </div>
                    </div>
                </div>
                <div class="f-actions">
                    <button type="button" class="btn-save" onclick="submitPasswordReset()">
                        <i class="fa-solid fa-key"></i> Reset Password
                    </button>
                </div>
            </div>
        </div>

    </main>
</div><!-- /page-wrapper -->

<script>
    /* ── Tabs ── */
    function switchTab(key, btn) {
        document.querySelectorAll('.acct-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.acct-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + key).classList.add('active');
    }

    /* ── Associate ID live hint ── */
    function liveAssoc(el) {
        const hint = document.getElementById('assoc-hint');
        const v = el.value;
        if (!v)        { hint.textContent = 'Numbers only · exactly 7 digits'; hint.className = 'f-hint'; }
        else if (v.length < 7) { hint.textContent = v.length + ' / 7 digits'; hint.className = 'f-hint err'; }
        else           { hint.textContent = '✓ Valid Associate ID';             hint.className = 'f-hint ok'; }
    }
    liveAssoc(document.getElementById('assoc-inp'));

    /* ── Deactivate ── */
    function doDeactivate() {
        if (!confirm('This will deactivate <?= addslashes($targetUser['full_name']) ?>\'s account. Are you sure?')) return;
        fetch('../Crud/process_deactivate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=<?= $targetId ?>'
        }).then(r => r.json()).then(d => {
            if (d.success) window.location.href = 'tbl_userlist.php?msg=deactivated';
            else alert(d.message || 'Error.');
        });
    }

    /* ── Password reset ── */
    function submitPasswordReset() {
        const pw  = document.getElementById('new-pw').value.trim();
        const cpw = document.getElementById('confirm-pw').value.trim();
        const alert = document.getElementById('pw-alert');
        alert.style.display = 'none';

        if (!pw || pw.length < 8) {
            alert.textContent = 'Password must be at least 8 characters.';
            alert.className = 'acct-alert error'; alert.style.display = 'flex'; return;
        }
        if (pw !== cpw) {
            alert.textContent = 'Passwords do not match.';
            alert.className = 'acct-alert error'; alert.style.display = 'flex'; return;
        }
        fetch('../Crud/process_reset_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id: '<?= $targetId ?>', password: pw }).toString()
        }).then(r => r.json()).then(d => {
            if (d.success) {
                alert.textContent = '✔ Password reset successfully!';
                alert.className = 'acct-alert success'; alert.style.display = 'flex';
                document.getElementById('new-pw').value = '';
                document.getElementById('confirm-pw').value = '';
            } else {
                alert.textContent = '✖ ' + (d.message || 'Reset failed.');
                alert.className = 'acct-alert error'; alert.style.display = 'flex';
            }
        }).catch(() => {
            alert.textContent = '✖ Network error.';
            alert.className = 'acct-alert error'; alert.style.display = 'flex';
        });
    }

    /* ── Avatar upload ── */
    function uploadAvatar(input) {
        const status = document.getElementById('av-status');
        const file = input.files[0]; if (!file) return;
        const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!allowed.includes(file.type)) { status.style.color='#ff3e1d'; status.textContent='✖ Only JPG, PNG, GIF, or WEBP allowed.'; return; }
        if (file.size > 800*1024)          { status.style.color='#ff3e1d'; status.textContent='✖ File exceeds 800K limit.'; return; }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('av-circle').innerHTML = `<img src="${e.target.result}" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">`;
        };
        reader.readAsDataURL(file);
        status.style.color='#696cff'; status.textContent='Uploading…';
        const fd = new FormData(); fd.append('avatar', file); fd.append('id', '<?= $targetId ?>');
        fetch('../Crud/process_upload_avatar.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d=>{
                if (d.success) { status.style.color='#28c76f'; status.textContent='✔ Photo updated!'; }
                else           { status.style.color='#ff3e1d'; status.textContent='✖ '+(d.message||'Upload failed.'); }
            }).catch(()=>{ status.style.color='#ff3e1d'; status.textContent='✖ Network error.'; });
    }

    function resetAvatar() {
        if (!confirm('Reset this user\'s photo back to initials?')) return;
        const status = document.getElementById('av-status');
        fetch('../Crud/process_upload_avatar.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'id=<?= $targetId ?>&reset=1'
        }).then(r=>r.json()).then(d=>{
            if (d.success) {
                document.getElementById('av-circle').innerHTML = `<span id="av-initials"><?= htmlspecialchars($targetInitials) ?></span>`;
                status.style.color='#28c76f'; status.textContent='✔ Photo reset.';
                document.getElementById('avatar-file-input').value='';
            } else { status.style.color='#ff3e1d'; status.textContent='✖ '+(d.message||'Reset failed.'); }
        });
    }

    /* ── Sidebar accordion ── */
    function toggleAcc(id) {
        const clicked = document.getElementById(id);
        const isOpen  = clicked.classList.contains('open');
        document.querySelectorAll('.nav-accordion').forEach(el => el.classList.remove('open'));
        if (!isOpen) clicked.classList.add('open');
    }
</script>
</body>
</html>