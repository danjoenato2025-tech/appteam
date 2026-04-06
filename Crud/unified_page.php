<?php
/**
 * unified_page.php
 * Combines: Request Record + User Management into one page
 * All CRUD is handled inline (no separate process_*.php files needed)
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

include('../dbconnection/config.php');

header('Content-Type: text/html; charset=UTF-8');

$sessionUser = [
    'id'        => $_SESSION['user_id'],
    'username'  => $_SESSION['username'],
    'full_name' => $_SESSION['full_name'],
    'role'      => $_SESSION['role'],
    'email'     => $_SESSION['email'],
    'color'     => $_SESSION['color'],
];
$isAdmin  = $sessionUser['role'] === 'admin';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
            array_slice(explode(' ', $sessionUser['full_name']), 0, 2)));

/* ══════════════════════════════════════════════════════════════
   INLINE AJAX HANDLERS  (JSON responses, then exit)
══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action'])) {
    header('Content-Type: application/json');
    $action = $_POST['__action'];

    /* ── REQUEST RECORD CRUD ─────────────────────────── */
    if ($action === 'add_record') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
        $dr  = trim($_POST['date_requested']  ?? '');
        $drx = trim($_POST['date_received']   ?? '') ?: null;
        $rn  = trim($_POST['request_number']  ?? '');
        $en  = trim($_POST['ext_number']      ?? '') ?: null;
        $rs  = trim($_POST['request_section'] ?? '');
        $pf  = trim($_POST['performed']       ?? '') ?: null;
        $id2 = trim($_POST['imp_date']        ?? '') ?: null;
        $inf = trim($_POST['information']     ?? '') ?: null;
        $rsn = trim($_POST['reason']          ?? '') ?: null;
        if (!$dr || !$rn || !$rs) { echo json_encode(['success'=>false,'message'=>'Required fields missing.']); exit; }
        $s = $pdo->prepare('INSERT INTO request_record (date_requested,date_received,request_number,ext_number,request_section,information,reason,performed,imp_date) VALUES (?,?,?,?,?,?,?,?,?)');
        $s->execute([$dr,$drx,$rn,$en,$rs,$inf,$rsn,$pf,$id2]);
        echo json_encode(['success'=>true,'record'=>['record_id'=>(int)$pdo->lastInsertId(),'date_requested'=>$dr,'date_received'=>$drx,'request_number'=>$rn,'ext_number'=>$en,'request_section'=>$rs,'information'=>$inf,'reason'=>$rsn,'performed'=>$pf,'imp_date'=>$id2]]);
        exit;
    }

    if ($action === 'edit_record') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
        $id  = (int)($_POST['id'] ?? 0);
        $dr  = trim($_POST['date_requested']  ?? '');
        $drx = trim($_POST['date_received']   ?? '') ?: null;
        $rn  = trim($_POST['request_number']  ?? '');
        $en  = trim($_POST['ext_number']      ?? '') ?: null;
        $rs  = trim($_POST['request_section'] ?? '');
        $pf  = trim($_POST['performed']       ?? '') ?: null;
        $id2 = trim($_POST['imp_date']        ?? '') ?: null;
        $inf = trim($_POST['information']     ?? '') ?: null;
        $rsn = trim($_POST['reason']          ?? '') ?: null;
        if (!$id || !$dr || !$rn || !$rs) { echo json_encode(['success'=>false,'message'=>'Required fields missing.']); exit; }
        $s = $pdo->prepare('UPDATE request_record SET date_requested=?,date_received=?,request_number=?,ext_number=?,request_section=?,information=?,reason=?,performed=?,imp_date=? WHERE record_id=?');
        $s->execute([$dr,$drx,$rn,$en,$rs,$inf,$rsn,$pf,$id2,$id]);
        echo json_encode(['success'=>true,'record'=>['record_id'=>$id,'date_requested'=>$dr,'date_received'=>$drx,'request_number'=>$rn,'ext_number'=>$en,'request_section'=>$rs,'information'=>$inf,'reason'=>$rsn,'performed'=>$pf,'imp_date'=>$id2]]);
        exit;
    }

    if ($action === 'delete_record') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit; }
        $pdo->prepare('DELETE FROM request_record WHERE record_id=?')->execute([$id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    /* ── USER CRUD ───────────────────────────────────── */
    if ($action === 'add_user') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
        $username     = trim($_POST['username']     ?? '');
        $full_name    = trim($_POST['full_name']    ?? '');
        $email        = trim($_POST['email']        ?? '');
        $associate_id = trim($_POST['associate_id'] ?? '');
        $section      = trim($_POST['section']      ?? '');
        $team         = trim($_POST['team']         ?? '');
        $role         = in_array($_POST['role']??'',['admin','user']) ? $_POST['role'] : 'user';
        $status       = in_array($_POST['status']??'',['active','inactive','pending']) ? $_POST['status'] : 'active';
        if (!$username||!$full_name||!$email||!$associate_id||!$section||!$team) { echo json_encode(['success'=>false,'message'=>'All fields are required.']); exit; }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email.']); exit; }
        if (!preg_match('/^\d{7}$/',$associate_id)) { echo json_encode(['success'=>false,'message'=>'Associate ID must be 7 digits.']); exit; }
        $chk = $pdo->prepare('SELECT id FROM users WHERE email=? OR username=? OR associate_id=? LIMIT 1');
        $chk->execute([$email,$username,$associate_id]);
        if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Email, username, or Associate ID already exists.']); exit; }
        $colors = ['#a8b9f8','#ffb347','#f4a2a2','#6ecf8b','#c2c2c2','#7bc8f6','#d4a5f5','#f9c784'];
        $color  = $colors[array_rand($colors)];
        $hash   = password_hash('changeme', PASSWORD_DEFAULT);
        $s = $pdo->prepare('INSERT INTO users (username,full_name,email,password,role,plan,billing,status,avatar_color,associate_id,section,team) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$username,$full_name,$email,$hash,$role,'Basic','Manual – Cash',$status,$color,$associate_id,$section,$team]);
        $row = $pdo->prepare('SELECT id,username,full_name,email,role,plan,billing,status,avatar_color,associate_id,section,team,created_at FROM users WHERE id=?');
        $row->execute([$pdo->lastInsertId()]);
        echo json_encode(['success'=>true,'user'=>$row->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'edit_user') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
        $id           = (int)($_POST['id'] ?? 0);
        $username     = trim($_POST['username']     ?? '');
        $full_name    = trim($_POST['full_name']    ?? '');
        $email        = trim($_POST['email']        ?? '');
        $associate_id = trim($_POST['associate_id'] ?? '');
        $section      = trim($_POST['section']      ?? '');
        $team         = trim($_POST['team']         ?? '');
        $role         = in_array($_POST['role']??'',['admin','user']) ? $_POST['role'] : 'user';
        $status       = in_array($_POST['status']??'',['active','inactive','pending']) ? $_POST['status'] : 'active';
        if (!$id||!$username||!$full_name||!$email||!$associate_id||!$section||!$team) { echo json_encode(['success'=>false,'message'=>'All fields are required.']); exit; }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email.']); exit; }
        if (!preg_match('/^\d{7}$/',$associate_id)) { echo json_encode(['success'=>false,'message'=>'Associate ID must be 7 digits.']); exit; }
        $s = $pdo->prepare('UPDATE users SET username=?,full_name=?,email=?,role=?,status=?,associate_id=?,section=?,team=? WHERE id=?');
        $s->execute([$username,$full_name,$email,$role,$status,$associate_id,$section,$team,$id]);
        $row = $pdo->prepare('SELECT id,username,full_name,email,role,plan,billing,status,avatar_color,associate_id,section,team,created_at FROM users WHERE id=?');
        $row->execute([$id]);
        echo json_encode(['success'=>true,'user'=>$row->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'delete_user') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id || $id === (int)$sessionUser['id']) { echo json_encode(['success'=>false,'message'=>'Cannot delete this user.']); exit; }
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   PAGE DATA
══════════════════════════════════════════════════════════════ */
// Request Records
$allRecords = $pdo->query('SELECT record_id,date_requested,date_received,request_number,ext_number,request_section,information,reason,performed,imp_date FROM request_record ORDER BY record_id DESC')->fetchAll(PDO::FETCH_ASSOC);

$totalRecords   = count($allRecords);
$todayRecords   = count(array_filter($allRecords, fn($r) => $r['date_requested'] === date('Y-m-d')));
$pendingRecords = count(array_filter($allRecords, fn($r) => empty($r['date_received'])));
$doneRecords    = count(array_filter($allRecords, fn($r) => !empty($r['date_received'])));
$thisMonthRec   = count(array_filter($allRecords, fn($r) => strpos($r['date_requested'] ?? '', date('Y-m')) === 0));
$withInfo       = count(array_filter($allRecords, fn($r) => !empty($r['information'])));

// Users
$allUsers     = $pdo->query('SELECT id,username,full_name,email,role,plan,billing,status,avatar_color,associate_id,section,team,created_at FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$totalUsers   = count($allUsers);
$activeUsers  = count(array_filter($allUsers, fn($u) => $u['status']==='active'));
$inactiveUsers= count(array_filter($allUsers, fn($u) => $u['status']==='inactive'));
$pendingUsers = count(array_filter($allUsers, fn($u) => $u['status']==='pending'));
$adminUsers   = count(array_filter($allUsers, fn($u) => $u['role']==='admin'));
$regularUsers = count(array_filter($allUsers, fn($u) => $u['role']==='user'));

$activePage = $_GET['tab'] ?? 'records'; // 'records' | 'users'
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Control – Unified</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        /* ══ Stat grids ══ */
        .stat-grid { grid-template-columns: repeat(6,1fr) !important; }
        @media(max-width:1200px){ .stat-grid{ grid-template-columns:repeat(3,1fr)!important; } }
        @media(max-width:700px) { .stat-grid{ grid-template-columns:repeat(2,1fr)!important; } }

        /* ══ Tab switcher ══ */
        .tab-bar {
            display: flex;
            gap: 4px;
            background: #f0f1f5;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 24px;
            width: fit-content;
        }
        .tab-btn {
            padding: 8px 22px;
            border: none;
            background: transparent;
            border-radius: 7px;
            font-family: 'Public Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: #6e7a8a;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
        }
        .tab-btn.active {
            background: #fff;
            color: #696cff;
            box-shadow: 0 2px 8px rgba(0,0,0,.10);
        }
        .tab-btn .tab-count {
            background: #696cff18;
            color: #696cff;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
        }
        .tab-btn.active-users .tab-count { background:#28c76f18; color:#28c76f; }

        /* ══ Tab panels ══ */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ══ Shared stat card styles ══ */
        .stat-icon.grey  { background:#f0f1f5; color:#8a93a2; }
        .stat-icon.red   { background:#ff3e1d12; color:#ff3e1d; }
        .stat-icon.blue  { background:#696cff12; color:#696cff; }
        .stat-icon.teal  { background:#00bcd412; color:#00bcd4; }
        .stat-icon.pink  { background:#e91e6312; color:#e91e63; }

        .stat-card {
            cursor:pointer;
            transition:transform .18s,box-shadow .18s,border-color .18s;
            border:2px solid transparent; position:relative;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.10); }
        .stat-card.active-filter { border-color:#696cff; box-shadow:0 0 0 3px #696cff22; }
        .stat-card.active-filter .stat-label::after { content:' ✕'; font-size:10px; color:#696cff; font-weight:700; }
        .stat-card.active-filter.fc-green  { border-color:#28c76f; box-shadow:0 0 0 3px #28c76f22; }
        .stat-card.active-filter.fc-grey   { border-color:#8a93a2; box-shadow:0 0 0 3px #8a93a222; }
        .stat-card.active-filter.fc-orange { border-color:#ff9f43; box-shadow:0 0 0 3px #ff9f4322; }
        .stat-card.active-filter.fc-red    { border-color:#ff3e1d; box-shadow:0 0 0 3px #ff3e1d22; }
        .stat-card.active-filter.fc-blue   { border-color:#696cff; box-shadow:0 0 0 3px #696cff22; }
        .stat-card.active-filter.fc-teal   { border-color:#00bcd4; box-shadow:0 0 0 3px #00bcd422; }
        .stat-card.active-filter.fc-pink   { border-color:#e91e63; box-shadow:0 0 0 3px #e91e6322; }
        .stat-card::after {
            content:attr(data-tip); position:absolute; bottom:-28px; left:50%; transform:translateX(-50%);
            background:#2d3a4a; color:#fff; font-size:11px; padding:3px 9px; border-radius:5px;
            white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .2s;
        }
        .stat-card:hover::after { opacity:1; }

        /* ══ Action buttons ══ */
        .action-cell { display:flex; gap:5px; align-items:center; }
        .action-btn { border:none; background:transparent; cursor:pointer; width:30px; height:30px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:13px; transition:background .15s,color .15s; color:#8a93a2; }
        .action-btn:hover      { background:#f0f1f5; color:#696cff; }
        .action-btn.del:hover  { background:#fff0ee; color:#ff3e1d; }
        .action-btn.edit-btn:hover { background:#fff8ec; color:#ff9f43; }
        .action-btn.view-btn:hover { background:#edfaf4; color:#28c76f; }
        .action-btn:disabled { opacity:.3; cursor:not-allowed; }

        /* ══ Role selector ══ */
        .role-selector { display:flex; gap:10px; margin-top:4px; }
        .role-option { flex:1; position:relative; }
        .role-option input[type="radio"] { position:absolute; opacity:0; width:0; }
        .role-option label { display:flex; align-items:center; gap:8px; border:2px solid #e0e2e8; border-radius:8px; padding:9px 13px; cursor:pointer; font-size:13.5px; font-weight:500; color:#5a6070; background:#fafafa; transition:all .2s; user-select:none; }
        .role-option input[type="radio"]:checked+label { border-color:#696cff; background:#696cff12; color:#696cff; }
        .role-option.admin input[type="radio"]:checked+label { border-color:#ff3e1d; background:#ff3e1d10; color:#ff3e1d; }

        /* ══ Badges ══ */
        .badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; }
        .badge.active   { background:#e8f5e9; color:#28c76f; }
        .badge.inactive { background:#f5f5f5; color:#8a93a2; }
        .badge.pending  { background:#fff8ec; color:#ff9f43; }
        .badge.admin    { background:#ff3e1d10; color:#ff3e1d; }
        .badge.user     { background:#696cff12; color:#696cff; }
        .section-badge  { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; background:#696cff12; color:#696cff; }

        /* ══ Modals shared ══ */
        .view-modal-overlay,.edit-modal-overlay,.del-modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(30,30,60,.45); backdrop-filter:blur(4px);
            z-index:9999; align-items:center; justify-content:center;
        }
        .view-modal-overlay.open,.edit-modal-overlay.open,.del-modal-overlay.open { display:flex; }
        @keyframes vmIn { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }

        .view-modal {
            background:#fff; border-radius:16px; padding:36px 36px 30px;
            max-width:520px; width:94%; box-shadow:0 20px 60px rgba(0,0,0,.18);
            animation:vmIn .3s cubic-bezier(.34,1.56,.64,1); max-height:90vh; overflow-y:auto;
        }
        .edit-modal {
            background:#fff; border-radius:14px; padding:30px 30px 24px;
            max-width:600px; width:95%; max-height:90vh; overflow-y:auto;
            box-shadow:0 20px 60px rgba(0,0,0,.18); animation:vmIn .3s cubic-bezier(.34,1.56,.64,1);
        }
        .del-modal {
            background:#fff; border-radius:16px; padding:36px 36px 30px;
            max-width:400px; width:92%; text-align:center;
            box-shadow:0 20px 60px rgba(0,0,0,.18); animation:vmIn .3s cubic-bezier(.34,1.56,.64,1);
        }

        .view-modal-header { display:flex; align-items:center; gap:16px; margin-bottom:24px; }
        .view-icon-wrap { width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg,#696cff,#9b59f5); display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff; flex-shrink:0; }
        .view-avatar { width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:#fff; flex-shrink:0; }
        .view-name { font-size:17px; font-weight:700; color:#2d3a4a; }
        .view-sub  { font-size:13px; color:#8a93a2; margin-top:2px; }
        .view-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; background:#f6f7f9; border-radius:10px; padding:18px 20px; margin-bottom:24px; }
        .view-field label { font-size:11px; font-weight:600; color:#a0a8b5; text-transform:uppercase; letter-spacing:.5px; }
        .view-field span  { display:block; font-size:13.5px; font-weight:500; color:#2d3a4a; margin-top:3px; }
        .view-field.full  { grid-column:1/-1; }
        .view-modal-footer { display:flex; justify-content:flex-end; gap:10px; }
        .btn-close-view { background:#f0f1f5; border:none; border-radius:8px; padding:9px 22px; font-size:14px; font-weight:600; color:#5a6070; cursor:pointer; }
        .btn-close-view:hover { background:#e4e5ec; }
        .btn-edit-view  { background:linear-gradient(135deg,#696cff,#9b59f5); border:none; border-radius:8px; padding:9px 22px; font-size:14px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-edit-view:hover { opacity:.88; }

        .del-icon-wrap { width:72px; height:72px; border-radius:50%; background:#ff3e1d12; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
        .del-icon-wrap i { font-size:32px; color:#ff3e1d; }
        .del-title { font-size:19px; font-weight:700; color:#2d3a4a; margin-bottom:8px; }
        .del-sub   { font-size:13.5px; color:#6e7a8a; line-height:1.6; margin-bottom:26px; }
        .del-btns  { display:flex; gap:10px; justify-content:center; }
        .btn-cancel-del  { background:#f0f1f5; border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; color:#5a6070; cursor:pointer; }
        .btn-confirm-del { background:#ff3e1d; border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-confirm-del:hover { opacity:.88; }

        /* ══ Result Modal ══ */
        .result-overlay { display:none; position:fixed; inset:0; background:rgba(30,30,60,.45); backdrop-filter:blur(4px); z-index:10000; align-items:center; justify-content:center; }
        .result-overlay.open { display:flex; }
        .result-box { background:#fff; border-radius:16px; padding:40px 36px 32px; max-width:380px; width:92%; text-align:center; box-shadow:0 24px 60px rgba(0,0,0,.2); animation:vmIn .35s cubic-bezier(.34,1.56,.64,1); }
        .result-icon-wrap { width:76px; height:76px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
        .result-icon-wrap.success { background:#28c76f18; }
        .result-icon-wrap.error   { background:#ff3e1d12; }
        .result-icon-wrap i { font-size:34px; }
        .result-icon-wrap.success i { color:#28c76f; }
        .result-icon-wrap.error   i { color:#ff3e1d; }
        .result-title { font-size:20px; font-weight:700; color:#2d3a4a; margin-bottom:8px; }
        .result-msg   { font-size:13.5px; color:#6e7a8a; line-height:1.65; margin-bottom:26px; }
        .result-detail { background:#f6f7f9; border-radius:10px; padding:13px 16px; text-align:left; font-size:13px; color:#4a5568; line-height:1.9; margin-bottom:24px; }
        .result-detail .rd-row   { display:flex; gap:8px; }
        .result-detail .rd-label { font-weight:600; color:#2d3a4a; min-width:130px; }
        .btn-result-ok { background:linear-gradient(135deg,#696cff,#9b59f5); border:none; border-radius:9px; padding:12px 36px; font-size:15px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-result-ok.danger { background:linear-gradient(135deg,#ff3e1d,#ff6b4a); }
        .btn-result-ok:hover { opacity:.88; }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a class="sidebar-logo" href="#">
        <div class="logo-icon">AM</div>
        <span class="logo-text">TEAM</span>
    </a>
    <nav><ul>
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
                <li><a class="nav-sub-link active" href="unified_page.php?tab=records">Request Record</a></li>
                <li><a class="nav-sub-link" href="#">Incident Report</a></li>
            </ul>
        </li>
        <li class="nav-item nav-accordion" id="QADControl">
            <a class="nav-link" href="#" onclick="toggleAcc('QADControl');return false;">
                <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                <span class="nav-text">Queen's Annes Drive</span>
                <i class="fa fa-chevron-right nav-chevron"></i>
            </a>
            <ul class="nav-sub"><li><a class="nav-sub-link" href="#">Monitoring Request</a></li></ul>
        </li>
        <li class="nav-item nav-accordion" id="Lasys">
            <a class="nav-link" href="#" onclick="toggleAcc('Lasys');return false;">
                <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                <span class="nav-text">Label Assurance System</span>
                <i class="fa fa-chevron-right nav-chevron"></i>
            </a>
            <ul class="nav-sub"><li><a class="nav-sub-link" href="#">Monitoring Request</a></li></ul>
        </li>
        <li class="nav-item nav-accordion" id="printerAcc">
            <a class="nav-link" href="#" onclick="toggleAcc('printerAcc');return false;">
                <span class="nav-icon"><i class="fa-solid fa-print"></i></span>
                <span class="nav-text">Sato Printer</span>
                <i class="fa fa-chevron-right nav-chevron"></i>
            </a>
            <ul class="nav-sub"><li><a class="nav-sub-link" href="#">List of Printer</a></li></ul>
        </li>
        <div class="nav-divider"></div>
        <li class="nav-section-label">Apps &amp; Pages</li>
        <li class="nav-item nav-accordion" id="userAcc">
            <a class="nav-link" href="#" onclick="toggleAcc('userAcc');return false;">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                <span class="nav-text">Users</span>
                <i class="fa fa-chevron-right nav-chevron"></i>
            </a>
            <ul class="nav-sub">
                <li><a class="nav-sub-link" href="unified_page.php?tab=users">List</a></li>
                <li><a class="nav-sub-link" href="user_account.php">Account Settings</a></li>
            </ul>
        </li>
    </ul></nav>
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
                    <a class="dropdown-item danger" href="../logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="breadcrumb">
            <a href="#">Home</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <span style="color:var(--text-mid);">Master Control</span>
        </div>

        <?php if (!$isAdmin): ?>
        <div class="access-notice">
            <i class="fa-solid fa-circle-info"></i>
            You are logged in as a <strong>User</strong>. Management actions are restricted to Admins only.
        </div>
        <?php endif; ?>

        <!-- ── TAB BAR ── -->
        <div class="tab-bar">
            <button class="tab-btn <?= $activePage==='records'?'active':'' ?>" id="tab-btn-records" onclick="switchTab('records')">
                <i class="fa-solid fa-folder-open"></i> Request Records
                <span class="tab-count" id="tab-count-records"><?= $totalRecords ?></span>
            </button>
            <button class="tab-btn <?= $activePage==='users'?'active active-users':'' ?>" id="tab-btn-users" onclick="switchTab('users')">
                <i class="fa-solid fa-users"></i> User Management
                <span class="tab-count" id="tab-count-users"><?= $totalUsers ?></span>
            </button>
        </div>

        <!-- ════════════════════════════════════════════════
             TAB 1: REQUEST RECORDS
        ════════════════════════════════════════════════ -->
        <div class="tab-panel <?= $activePage==='records'?'active':'' ?>" id="panel-records">

            <!-- Stat Cards -->
            <div class="stat-grid">
                <div class="stat-card" id="rsc-total" onclick="rFilterCard('total')" data-tip="Show all records">
                    <div><div class="stat-label">Session</div><div class="stat-value" id="rstat-total"><?= $totalRecords ?></div><div class="stat-sub">Total Records</div></div>
                    <div class="stat-icon purple"><i class="fa-solid fa-folder-open"></i></div>
                </div>
                <div class="stat-card fc-green" id="rsc-received" onclick="rFilterCard('received')" data-tip="Filter: Received">
                    <div><div class="stat-label">Received</div><div class="stat-value" id="rstat-received"><?= $doneRecords ?></div><div class="stat-sub">Date received set</div></div>
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-card fc-orange" id="rsc-pending" onclick="rFilterCard('pending')" data-tip="Filter: Pending">
                    <div><div class="stat-label">Pending</div><div class="stat-value" id="rstat-pending"><?= $pendingRecords ?></div><div class="stat-sub">No received date</div></div>
                    <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="stat-card fc-teal" id="rsc-today" onclick="rFilterCard('today')" data-tip="Filter: Today">
                    <div><div class="stat-label">Today</div><div class="stat-value" id="rstat-today"><?= $todayRecords ?></div><div class="stat-sub">Requested today</div></div>
                    <div class="stat-icon teal"><i class="fa-solid fa-calendar-day"></i></div>
                </div>
                <div class="stat-card fc-blue" id="rsc-month" onclick="rFilterCard('month')" data-tip="Filter: This Month">
                    <div><div class="stat-label">This Month</div><div class="stat-value" id="rstat-month"><?= $thisMonthRec ?></div><div class="stat-sub"><?= date('F Y') ?></div></div>
                    <div class="stat-icon blue"><i class="fa-solid fa-calendar-week"></i></div>
                </div>
                <div class="stat-card fc-pink" id="rsc-info" onclick="rFilterCard('info')" data-tip="Filter: Has Information">
                    <div><div class="stat-label">With Info</div><div class="stat-value" id="rstat-info"><?= $withInfo ?></div><div class="stat-sub">Has information field</div></div>
                    <div class="stat-icon pink"><i class="fa-solid fa-circle-info"></i></div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="rows-select">
                        <select id="r-per-page" onchange="rPerPageChanged()">
                            <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                        </select>
                    </div>
                    <div class="toolbar-spacer"></div>
                    <div class="search-input-wrap">
                        <i class="fa fa-search" style="color:var(--text-light);font-size:12px;"></i>
                        <input type="text" id="r-search" placeholder="Search Record" oninput="rRenderTable()">
                    </div>
                    <button class="btn btn-outline" onclick="rExportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary" onclick="rOpenAdd()"><i class="fa fa-plus"></i> Add Record</button>
                    <?php endif; ?>
                </div>
                <table>
                    <thead><tr>
                        <th><input type="checkbox" class="cb" onchange="toggleAll('r-tbody',this)"></th>
                        <th>Request No.</th><th>Ext. Number</th><th>Section</th>
                        <th>Date Requested</th><th>Date Received</th><th>Performed</th><th>Imp. Date</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="r-tbody"></tbody>
                </table>
                <div class="pagination-row">
                    <div class="page-info" id="r-page-info">Showing 0 to 0 of 0 entries</div>
                    <div class="page-btns" id="r-page-btns"></div>
                </div>
            </div>
        </div><!-- /panel-records -->

        <!-- ════════════════════════════════════════════════
             TAB 2: USER MANAGEMENT
        ════════════════════════════════════════════════ -->
        <div class="tab-panel <?= $activePage==='users'?'active':'' ?>" id="panel-users">

            <!-- Stat Cards -->
            <div class="stat-grid">
                <div class="stat-card" id="usc-total" onclick="uFilterCard('total')" data-tip="Show all users">
                    <div><div class="stat-label">Session</div><div class="stat-value" id="ustat-total"><?= $totalUsers ?> <span class="stat-change up">(+29%)</span></div><div class="stat-sub">Total Users</div></div>
                    <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="stat-card fc-green" id="usc-active" onclick="uFilterCard('active')" data-tip="Filter: Active only">
                    <div><div class="stat-label">Active Users</div><div class="stat-value" id="ustat-active"><?= $activeUsers ?> <span class="stat-change up">(+12%)</span></div><div class="stat-sub">Currently active</div></div>
                    <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                </div>
                <div class="stat-card fc-grey" id="usc-inactive" onclick="uFilterCard('inactive')" data-tip="Filter: Inactive only">
                    <div><div class="stat-label">Inactive Users</div><div class="stat-value" id="ustat-inactive"><?= $inactiveUsers ?> <span class="stat-change down">(-5%)</span></div><div class="stat-sub">Deactivated accounts</div></div>
                    <div class="stat-icon grey"><i class="fa-solid fa-user-slash"></i></div>
                </div>
                <div class="stat-card fc-orange" id="usc-pending" onclick="uFilterCard('pending')" data-tip="Filter: Pending only">
                    <div><div class="stat-label">Pending Users</div><div class="stat-value" id="ustat-pending"><?= $pendingUsers ?> <span class="stat-change up">(+42%)</span></div><div class="stat-sub">Awaiting approval</div></div>
                    <div class="stat-icon orange"><i class="fa-solid fa-user-clock"></i></div>
                </div>
                <div class="stat-card fc-red" id="usc-admin" onclick="uFilterCard('admin')" data-tip="Filter: Admins only">
                    <div><div class="stat-label">Admins</div><div class="stat-value" id="ustat-admin"><?= $adminUsers ?></div><div class="stat-sub">Admin role accounts</div></div>
                    <div class="stat-icon red"><i class="fa-solid fa-shield-halved"></i></div>
                </div>
                <div class="stat-card fc-blue" id="usc-users" onclick="uFilterCard('user')" data-tip="Filter: Users only">
                    <div><div class="stat-label">Users</div><div class="stat-value" id="ustat-users"><?= $regularUsers ?></div><div class="stat-sub">Standard role accounts</div></div>
                    <div class="stat-icon blue"><i class="fa-solid fa-user"></i></div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="rows-select">
                        <select id="u-per-page" onchange="uPerPageChanged()">
                            <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                        </select>
                    </div>
                    <div class="toolbar-spacer"></div>
                    <div class="search-input-wrap">
                        <i class="fa fa-search" style="color:var(--text-light);font-size:12px;"></i>
                        <input type="text" id="u-search" placeholder="Search User" oninput="uRenderTable()">
                    </div>
                    <button class="btn btn-outline" onclick="uExportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary" onclick="uOpenAdd()"><i class="fa fa-plus"></i> Add New User</button>
                    <?php endif; ?>
                </div>
                <table>
                    <thead><tr>
                        <th><input type="checkbox" class="cb" onchange="toggleAll('u-tbody',this)"></th>
                        <th>User</th><th>Associate ID</th><th>Section / Team</th><th>Role</th><th>Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="u-tbody"></tbody>
                </table>
                <div class="pagination-row">
                    <div class="page-info" id="u-page-info">Showing 0 to 0 of 0 entries</div>
                    <div class="page-btns" id="u-page-btns"></div>
                </div>
            </div>
        </div><!-- /panel-users -->
    </main>
</div>

<!-- ════ RECORD MODALS ════ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="r-add-modal">
    <div class="modal">
        <div class="modal-title">Add New Request Record</div>
        <div class="modal-sub">Fill in all required fields below.</div>
        <div id="r-add-alert" style="display:none;" class="modal-alert error"></div>
        <div class="modal-grid2">
            <div><label class="modal-label">Date Requested</label><input class="modal-input" id="ra-date_requested" type="date"></div>
            <div><label class="modal-label">Date Received</label><input class="modal-input" id="ra-date_received" type="date"></div>
        </div>
        <div class="modal-grid2">
            <div><label class="modal-label">Request Number</label><input class="modal-input" id="ra-request_number" type="text" placeholder="e.g. REQ-2024-001"></div>
            <div><label class="modal-label">Ext. Number</label><input class="modal-input" id="ra-ext_number" type="text" placeholder="e.g. EXT-001"></div>
        </div>
        <div class="modal-grid2">
            <div><label class="modal-label">Request Section</label><input class="modal-input" id="ra-request_section" type="text" placeholder="e.g. IT Department"></div>
            <div><label class="modal-label">Performed By</label><input class="modal-input" id="ra-performed" type="text" placeholder="e.g. John Doe"></div>
        </div>
        <label class="modal-label">Imp. Date</label>
        <input class="modal-input" id="ra-imp_date" type="date">
        <label class="modal-label">Information</label>
        <textarea class="modal-input" id="ra-information" rows="3" placeholder="Enter relevant information..." style="resize:vertical;"></textarea>
        <label class="modal-label">Reason</label>
        <textarea class="modal-input" id="ra-reason" rows="3" placeholder="Enter reason for request..." style="resize:vertical;"></textarea>
        <div class="modal-row" style="margin-top:18px;">
            <button class="modal-cancel" onclick="rCloseAdd()">Cancel</button>
            <button class="modal-submit" onclick="rSubmitAdd()">Add Record</button>
        </div>
    </div>
</div>

<div class="edit-modal-overlay" id="r-edit-modal">
    <div class="edit-modal">
        <div class="modal-title">Edit Request Record</div>
        <div class="modal-sub">Update the record's information below.</div>
        <div id="r-edit-alert" style="display:none;" class="modal-alert error"></div>
        <input type="hidden" id="re-id">
        <div class="modal-grid2">
            <div><label class="modal-label">Date Requested</label><input class="modal-input" id="re-date_requested" type="date"></div>
            <div><label class="modal-label">Date Received</label><input class="modal-input" id="re-date_received" type="date"></div>
        </div>
        <div class="modal-grid2">
            <div><label class="modal-label">Request Number</label><input class="modal-input" id="re-request_number" type="text"></div>
            <div><label class="modal-label">Ext. Number</label><input class="modal-input" id="re-ext_number" type="text"></div>
        </div>
        <div class="modal-grid2">
            <div><label class="modal-label">Request Section</label><input class="modal-input" id="re-request_section" type="text"></div>
            <div><label class="modal-label">Performed By</label><input class="modal-input" id="re-performed" type="text"></div>
        </div>
        <label class="modal-label">Imp. Date</label>
        <input class="modal-input" id="re-imp_date" type="date">
        <label class="modal-label">Information</label>
        <textarea class="modal-input" id="re-information" rows="3" style="resize:vertical;"></textarea>
        <label class="modal-label">Reason</label>
        <textarea class="modal-input" id="re-reason" rows="3" style="resize:vertical;"></textarea>
        <div class="modal-row" style="margin-top:18px;">
            <button class="modal-cancel" onclick="rCloseEdit()">Cancel</button>
            <button class="modal-submit" onclick="rSubmitEdit()">Save Changes</button>
        </div>
    </div>
</div>

<!-- USER MODALS -->
<div class="modal-overlay" id="u-add-modal">
    <div class="modal">
        <div class="modal-title">Add New User</div>
        <div class="modal-sub">Default password will be <strong>changeme</strong>.</div>
        <div id="u-add-alert" style="display:none;" class="modal-alert error"></div>
        <div class="modal-grid2">
            <div><label class="modal-label">Username</label><input class="modal-input" id="ua-username" type="text" placeholder="johndoe"></div>
            <div><label class="modal-label">Full Name</label><input class="modal-input" id="ua-full_name" type="text" placeholder="First Name, Last Name"></div>
        </div>
        <label class="modal-label">Email</label>
        <input class="modal-input" id="ua-email" type="email" placeholder="john@example.com">
        <label class="modal-label">Associate ID <span style="font-size:11px;color:#a0a8b5;">(7 digits)</span></label>
        <input class="modal-input" id="ua-associate_id" type="text" placeholder="e.g. 1234567" maxlength="7" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,7)">
        <div class="modal-grid2">
            <div>
                <label class="modal-label">Section</label>
                <select class="modal-input" id="ua-section">
                    <option value="" disabled selected>Select a section</option>
                    <option value="ICT">ICT</option><option value="HR">HR</option><option value="FINANCE">FINANCE</option>
                </select>
            </div>
            <div>
                <label class="modal-label">Team</label>
                <select class="modal-input" id="ua-team">
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
            <div class="role-option"><input type="radio" name="ua-role" id="ua-role-user" value="user" checked><label for="ua-role-user"><i class="fa-solid fa-user"></i> User</label></div>
            <div class="role-option admin"><input type="radio" name="ua-role" id="ua-role-admin" value="admin"><label for="ua-role-admin"><i class="fa-solid fa-shield-halved"></i> Admin</label></div>
        </div>
        <label class="modal-label">Status</label>
        <select class="modal-select" id="ua-status">
            <option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option>
        </select>
        <div class="modal-row" style="margin-top:18px;">
            <button class="modal-cancel" onclick="uCloseAdd()">Cancel</button>
            <button class="modal-submit" onclick="uSubmitAdd()">Add User</button>
        </div>
    </div>
</div>

<div class="edit-modal-overlay" id="u-edit-modal">
    <div class="edit-modal">
        <div class="modal-title">Edit User</div>
        <div class="modal-sub">Update the user's information below.</div>
        <div id="u-edit-alert" style="display:none;" class="modal-alert error"></div>
        <input type="hidden" id="ue-id">
        <div class="modal-grid2">
            <div><label class="modal-label">Username</label><input class="modal-input" id="ue-username" type="text"></div>
            <div><label class="modal-label">Full Name</label><input class="modal-input" id="ue-full_name" type="text"></div>
        </div>
        <label class="modal-label">Email</label><input class="modal-input" id="ue-email" type="email">
        <label class="modal-label">Associate ID <span style="font-size:11px;color:#a0a8b5;">(7 digits)</span></label>
        <input class="modal-input" id="ue-associate_id" type="text" maxlength="7" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,7)">
        <div class="modal-grid2">
            <div>
                <label class="modal-label">Section</label>
                <select class="modal-input" id="ue-section">
                    <option value="" disabled>Select a section</option>
                    <option value="ICT">ICT</option><option value="HR">HR</option><option value="FINANCE">FINANCE</option>
                </select>
            </div>
            <div>
                <label class="modal-label">Team</label>
                <select class="modal-input" id="ue-team">
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
            <div class="role-option"><input type="radio" name="ue-role" id="ue-role-user" value="user"><label for="ue-role-user"><i class="fa-solid fa-user"></i> User</label></div>
            <div class="role-option admin"><input type="radio" name="ue-role" id="ue-role-admin" value="admin"><label for="ue-role-admin"><i class="fa-solid fa-shield-halved"></i> Admin</label></div>
        </div>
        <label class="modal-label">Status</label>
        <select class="modal-select" id="ue-status">
            <option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option>
        </select>
        <div class="modal-row" style="margin-top:18px;">
            <button class="modal-cancel" onclick="uCloseEdit()">Cancel</button>
            <button class="modal-submit" onclick="uSubmitEdit()">Save Changes</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- VIEW RECORD MODAL -->
<div class="view-modal-overlay" id="r-view-modal">
    <div class="view-modal">
        <div class="view-modal-header">
            <div class="view-icon-wrap"><i class="fa-solid fa-file-lines"></i></div>
            <div><div class="view-name" id="rv-request_number">—</div><div class="view-sub" id="rv-request_section">—</div></div>
        </div>
        <div class="view-grid">
            <div class="view-field"><label>Date Requested</label><span id="rv-date_requested"></span></div>
            <div class="view-field"><label>Date Received</label><span id="rv-date_received"></span></div>
            <div class="view-field"><label>Ext. Number</label><span id="rv-ext_number"></span></div>
            <div class="view-field"><label>Performed By</label><span id="rv-performed"></span></div>
            <div class="view-field"><label>Imp. Date</label><span id="rv-imp_date"></span></div>
            <div class="view-field"><label>Record ID</label><span id="rv-record_id"></span></div>
            <div class="view-field full"><label>Information</label><span id="rv-information"></span></div>
            <div class="view-field full"><label>Reason</label><span id="rv-reason"></span></div>
        </div>
        <div class="view-modal-footer">
            <button class="btn-close-view" onclick="rCloseView()">Close</button>
            <?php if ($isAdmin): ?>
            <button class="btn-edit-view" onclick="rSwitchEdit()"><i class="fa fa-pen"></i> Edit Record</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- VIEW USER MODAL -->
<div class="view-modal-overlay" id="u-view-modal">
    <div class="view-modal">
        <div class="view-modal-header">
            <div class="view-avatar" id="uv-avatar"></div>
            <div><div class="view-name" id="uv-name"></div><div class="view-sub" id="uv-email"></div></div>
        </div>
        <div class="view-grid">
            <div class="view-field"><label>Username</label><span id="uv-username"></span></div>
            <div class="view-field"><label>Associate ID</label><span id="uv-associate_id"></span></div>
            <div class="view-field"><label>Section</label><span id="uv-section"></span></div>
            <div class="view-field"><label>Team</label><span id="uv-team"></span></div>
            <div class="view-field"><label>Role</label><span id="uv-role"></span></div>
            <div class="view-field"><label>Status</label><span id="uv-status"></span></div>
            <div class="view-field full"><label>Member Since</label><span id="uv-created_at"></span></div>
        </div>
        <div class="view-modal-footer">
            <button class="btn-close-view" onclick="uCloseView()">Close</button>
            <?php if ($isAdmin): ?>
            <button class="btn-edit-view" onclick="uSwitchEdit()"><i class="fa fa-pen"></i> Edit User</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SHARED DELETE MODAL -->
<div class="del-modal-overlay" id="del-modal">
    <div class="del-modal">
        <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="del-title" id="del-title">Delete?</div>
        <div class="del-sub">You are about to delete <strong id="del-name"></strong>.<br>This action cannot be undone.</div>
        <div class="del-btns">
            <button class="btn-cancel-del" onclick="closeDelModal()">Cancel</button>
            <button class="btn-confirm-del" onclick="confirmDelete()"><i class="fa fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<!-- RESULT MODAL -->
<div class="result-overlay" id="result-modal">
    <div class="result-box">
        <div class="result-icon-wrap" id="result-icon-wrap"><i id="result-icon"></i></div>
        <div class="result-title" id="result-title"></div>
        <div class="result-msg"   id="result-msg"></div>
        <div class="result-detail" id="result-detail" style="display:none;"></div>
        <button class="btn-result-ok" id="result-ok-btn" onclick="closeResult()">OK</button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ══════════════════════════════════════════════════════
   SHARED DATA
══════════════════════════════════════════════════════ */
const SELF_URL   = window.location.pathname;
const IS_ADMIN   = <?= $isAdmin ? 'true' : 'false' ?>;
const SESSION_ID = <?= (int)$sessionUser['id'] ?>;
const TODAY      = '<?= date('Y-m-d') ?>';
const THIS_MONTH = '<?= date('Y-m') ?>';

function post(data) {
    return fetch(SELF_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data).toString()
    }).then(r => r.json());
}

/* ══ TAB SWITCHER ══ */
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active','active-users'));
    document.getElementById('panel-' + tab).classList.add('active');
    const btn = document.getElementById('tab-btn-' + tab);
    btn.classList.add('active');
    if (tab === 'users') btn.classList.add('active-users');
    history.replaceState(null, '', '?tab=' + tab);
    syncSearch();
}

function syncSearch() {
    const q = document.getElementById('topbar-search').value;
    const rS = document.getElementById('r-search');
    const uS = document.getElementById('u-search');
    if (rS) rS.value = q;
    if (uS) uS.value = q;
    rRenderTable(); uRenderTable();
}

function toggleAll(tbodyId, cb) {
    document.querySelectorAll(`#${tbodyId} .row-cb`).forEach(r => r.checked = cb.checked);
}

/* ══ SHARED HELPERS ══ */
function showAlert(id, msg) { const e=document.getElementById(id); if(!e)return; e.innerHTML=`<i class="fa-solid fa-circle-exclamation"></i> ${msg}`; e.style.display='flex'; }
function hideAlert(id) { const e=document.getElementById(id); if(e) e.style.display='none'; }
function showToast(msg,type='success') { const t=document.getElementById('toast'); t.textContent=msg; t.className=`toast ${type} show`; clearTimeout(t._t); t._t=setTimeout(()=>t.classList.remove('show'),3200); }

function showResult(type,title,message,detailRows=[]) {
    const iw=document.getElementById('result-icon-wrap'), ic=document.getElementById('result-icon');
    iw.className=`result-icon-wrap ${type}`;
    ic.className=type==='success'?'fa-solid fa-circle-check':'fa-solid fa-circle-xmark';
    document.getElementById('result-title').textContent=title;
    document.getElementById('result-msg').textContent=message;
    document.getElementById('result-ok-btn').className=type==='error'?'btn-result-ok danger':'btn-result-ok';
    const de=document.getElementById('result-detail');
    if(detailRows.length){ de.innerHTML=detailRows.map(([l,v])=>`<div class="rd-row"><span class="rd-label">${l}</span><span>${v||'—'}</span></div>`).join(''); de.style.display='block'; }
    else de.style.display='none';
    document.getElementById('result-modal').classList.add('open');
}
function closeResult() { document.getElementById('result-modal').classList.remove('open'); }

/* shared delete state */
let _delAction=null, _delId=null, _delName=null;
function openDelModal(action,id,name,title='Delete?') {
    _delAction=action; _delId=id; _delName=name;
    document.getElementById('del-title').textContent=title;
    document.getElementById('del-name').textContent=name;
    document.getElementById('del-modal').classList.add('open');
}
function closeDelModal() { document.getElementById('del-modal').classList.remove('open'); _delAction=_delId=_delName=null; }
function confirmDelete() {
    if (!_delAction) return;
    const nm=_delName;
    post({ __action: _delAction, id: _delId }).then(d => {
        closeDelModal();
        if (d.success) {
            if (_delAction==='delete_record') { allRecords=allRecords.filter(r=>r.record_id!=_delId); rRenderTable(); rUpdateStats(); }
            else { allUsers=allUsers.filter(u=>u.id!=_delId); uRenderTable(); uUpdateStats(); }
            showResult('success','Deleted Successfully!',`"${nm}" has been permanently removed.`);
        } else { showResult('error','Delete Failed',d.message||'Could not delete. Please try again.'); }
    }).catch(()=>{ closeDelModal(); showResult('error','Network Error','Could not reach the server.'); });
}

function pageBtns(containerId, curPage, totalPages, goFn) {
    const c=document.getElementById(containerId); let html='';
    html+=`<button class="page-btn ${curPage===1?'disabled':''}" onclick="${goFn}(${curPage-1})"><i class="fa fa-angles-left"></i></button>`;
    html+=`<button class="page-btn ${curPage===1?'disabled':''}" onclick="${goFn}(${curPage-1})"><i class="fa fa-angle-left"></i></button>`;
    let s=Math.max(1,curPage-2), e=Math.min(totalPages,s+4);
    if(e-s<4) s=Math.max(1,e-4);
    for(let i=s;i<=e;i++) html+=`<button class="page-btn ${i===curPage?'active':''}" onclick="${goFn}(${i})">${i}</button>`;
    html+=`<button class="page-btn ${curPage===totalPages?'disabled':''}" onclick="${goFn}(${curPage+1})"><i class="fa fa-angle-right"></i></button>`;
    html+=`<button class="page-btn ${curPage===totalPages?'disabled':''}" onclick="${goFn}(${totalPages})"><i class="fa fa-angles-right"></i></button>`;
    c.innerHTML=html;
}

/* ══════════════════════════════════════════════════════
   ██████  RECORDS TAB
══════════════════════════════════════════════════════ */
let allRecords = <?= json_encode(array_values($allRecords)) ?>;
let rPage=1, rPer=10, rFilter=null, _curViewRec=null;

function rGetFiltered() {
    const q=(document.getElementById('r-search')?.value||'').toLowerCase();
    return allRecords.filter(r=>{
        const ms=!q||[r.request_number,r.ext_number,r.request_section,r.performed,r.information,r.reason,r.date_requested,r.date_received,r.imp_date].some(f=>(f||'').toLowerCase().includes(q));
        let mf=true;
        if(rFilter==='received') mf=!!r.date_received;
        if(rFilter==='pending')  mf=!r.date_received;
        if(rFilter==='today')    mf=r.date_requested===TODAY;
        if(rFilter==='month')    mf=(r.date_requested||'').indexOf(THIS_MONTH)===0;
        if(rFilter==='info')     mf=!!r.information;
        return ms&&mf;
    });
}

function rRenderTable() {
    const filtered=rGetFiltered(), total=filtered.length;
    const totalPages=Math.ceil(total/rPer)||1;
    if(rPage>totalPages) rPage=totalPages;
    const start=(rPage-1)*rPer, paged=filtered.slice(start,start+rPer);
    document.getElementById('r-tbody').innerHTML=paged.map(r=>`
<tr>
  <td><input type="checkbox" class="cb row-cb" data-id="${r.record_id}"></td>
  <td><span class="section-badge"><i class="fa-solid fa-hashtag" style="font-size:10px;"></i> ${r.request_number||'—'}</span></td>
  <td style="color:var(--text-mid);font-size:13.5px;">${r.ext_number||'—'}</td>
  <td style="font-size:13.5px;color:var(--text-mid);">${r.request_section||'—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.date_requested||'—'}</td>
  <td style="font-size:13px;">${r.date_received?`<span style="color:#28c76f;font-weight:600;">${r.date_received}</span>`:'<span style="color:#ff9f43;font-size:12px;font-weight:600;">Pending</span>'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.performed||'—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.imp_date||'—'}</td>
  <td><div class="action-cell">
    <button class="action-btn view-btn" title="View" onclick="rOpenView(${r.record_id})"><i class="fa fa-eye"></i></button>
    ${IS_ADMIN?`<button class="action-btn edit-btn" title="Edit" onclick="rOpenEdit(${r.record_id})"><i class="fa fa-pen"></i></button>
    <button class="action-btn del" title="Delete" onclick="openDelModal('delete_record',${r.record_id},'${(r.request_number||'Record #'+r.record_id).replace(/'/g,"\\'")}','Delete Record?')"><i class="fa fa-trash"></i></button>`:''}
  </div></td>
</tr>`).join('');
    const from=total?start+1:0, to=Math.min(start+rPer,total);
    document.getElementById('r-page-info').textContent=`Showing ${from} to ${to} of ${total} entries`;
    pageBtns('r-page-btns',rPage,totalPages,'rGoPage');
    rUpdateStats();
    document.getElementById('tab-count-records').textContent=allRecords.length;
}
function rGoPage(p){const t=Math.ceil(rGetFiltered().length/rPer)||1;if(p<1||p>t)return;rPage=p;rRenderTable();}
function rPerPageChanged(){rPer=parseInt(document.getElementById('r-per-page').value);rPage=1;rRenderTable();}
function rUpdateStats(){
    document.getElementById('rstat-total').textContent=allRecords.length;
    document.getElementById('rstat-received').textContent=allRecords.filter(r=>!!r.date_received).length;
    document.getElementById('rstat-pending').textContent=allRecords.filter(r=>!r.date_received).length;
    document.getElementById('rstat-today').textContent=allRecords.filter(r=>r.date_requested===TODAY).length;
    document.getElementById('rstat-month').textContent=allRecords.filter(r=>(r.date_requested||'').indexOf(THIS_MONTH)===0).length;
    document.getElementById('rstat-info').textContent=allRecords.filter(r=>!!r.information).length;
}
function rFilterCard(type){
    if(rFilter===type||type==='total'){rFilter=null;document.querySelectorAll('[id^="rsc-"]').forEach(c=>c.classList.remove('active-filter'));rPage=1;rRenderTable();return;}
    rFilter=type;
    document.querySelectorAll('[id^="rsc-"]').forEach(c=>c.classList.remove('active-filter'));
    const map={received:'rsc-received',pending:'rsc-pending',today:'rsc-today',month:'rsc-month',info:'rsc-info'};
    if(map[type]) document.getElementById(map[type])?.classList.add('active-filter');
    rPage=1;rRenderTable();
}

/* Record view modal */
function rOpenView(id){
    const r=allRecords.find(x=>x.record_id==id); if(!r)return; _curViewRec=r;
    document.getElementById('rv-record_id').textContent=r.record_id;
    document.getElementById('rv-request_number').textContent=r.request_number||'—';
    document.getElementById('rv-request_section').textContent=r.request_section||'—';
    document.getElementById('rv-date_requested').textContent=r.date_requested||'—';
    document.getElementById('rv-date_received').textContent=r.date_received||'Pending';
    document.getElementById('rv-ext_number').textContent=r.ext_number||'—';
    document.getElementById('rv-performed').textContent=r.performed||'—';
    document.getElementById('rv-imp_date').textContent=r.imp_date||'—';
    document.getElementById('rv-information').textContent=r.information||'—';
    document.getElementById('rv-reason').textContent=r.reason||'—';
    document.getElementById('r-view-modal').classList.add('open');
}
function rCloseView(){document.getElementById('r-view-modal').classList.remove('open');_curViewRec=null;}
function rSwitchEdit(){if(!_curViewRec)return;rCloseView();rOpenEdit(_curViewRec.record_id);}

/* Record add modal */
function rOpenAdd(){
    ['ra-date_requested','ra-date_received','ra-request_number','ra-ext_number','ra-request_section','ra-performed','ra-imp_date','ra-information','ra-reason']
        .forEach(id=>document.getElementById(id).value='');
    hideAlert('r-add-alert');
    document.getElementById('r-add-modal').classList.add('open');
}
function rCloseAdd(){document.getElementById('r-add-modal').classList.remove('open');}
document.getElementById('r-add-modal')?.addEventListener('click',function(e){if(e.target===this)rCloseAdd();});

function rSubmitAdd(){
    const dr=document.getElementById('ra-date_requested').value.trim();
    const rn=document.getElementById('ra-request_number').value.trim();
    const rs=document.getElementById('ra-request_section').value.trim();
    hideAlert('r-add-alert');
    if(!dr||!rn||!rs){showAlert('r-add-alert','Date Requested, Request Number and Section are required.');return;}
    post({__action:'add_record',date_requested:dr,date_received:document.getElementById('ra-date_received').value,request_number:rn,ext_number:document.getElementById('ra-ext_number').value,request_section:rs,performed:document.getElementById('ra-performed').value,imp_date:document.getElementById('ra-imp_date').value,information:document.getElementById('ra-information').value,reason:document.getElementById('ra-reason').value})
    .then(d=>{
        if(d.success){allRecords.unshift(d.record);rPage=1;rRenderTable();rCloseAdd();showResult('success','Record Added!','The request record has been saved.',[['📋 Request No.',d.record.request_number],['🏢 Section',d.record.request_section],['📅 Date',d.record.date_requested],['👤 Performed',d.record.performed]]);}
        else showAlert('r-add-alert',d.message||'Failed to add record.');
    }).catch(()=>showAlert('r-add-alert','Network error.'));
}

/* Record edit modal */
function rOpenEdit(id){
    const r=allRecords.find(x=>x.record_id==id); if(!r)return;
    document.getElementById('re-id').value=r.record_id;
    document.getElementById('re-date_requested').value=r.date_requested||'';
    document.getElementById('re-date_received').value=r.date_received||'';
    document.getElementById('re-request_number').value=r.request_number||'';
    document.getElementById('re-ext_number').value=r.ext_number||'';
    document.getElementById('re-request_section').value=r.request_section||'';
    document.getElementById('re-performed').value=r.performed||'';
    document.getElementById('re-imp_date').value=r.imp_date||'';
    document.getElementById('re-information').value=r.information||'';
    document.getElementById('re-reason').value=r.reason||'';
    hideAlert('r-edit-alert');
    document.getElementById('r-edit-modal').classList.add('open');
}
function rCloseEdit(){document.getElementById('r-edit-modal').classList.remove('open');}
document.getElementById('r-edit-modal')?.addEventListener('click',function(e){if(e.target===this)rCloseEdit();});

function rSubmitEdit(){
    const id=document.getElementById('re-id').value;
    const dr=document.getElementById('re-date_requested').value.trim();
    const rn=document.getElementById('re-request_number').value.trim();
    const rs=document.getElementById('re-request_section').value.trim();
    hideAlert('r-edit-alert');
    if(!dr||!rn||!rs){showAlert('r-edit-alert','Date Requested, Request Number and Section are required.');return;}
    post({__action:'edit_record',id,date_requested:dr,date_received:document.getElementById('re-date_received').value,request_number:rn,ext_number:document.getElementById('re-ext_number').value,request_section:rs,performed:document.getElementById('re-performed').value,imp_date:document.getElementById('re-imp_date').value,information:document.getElementById('re-information').value,reason:document.getElementById('re-reason').value})
    .then(d=>{
        if(d.success){const i=allRecords.findIndex(r=>r.record_id==id);if(i!==-1)allRecords[i]={...allRecords[i],...d.record};rRenderTable();rCloseEdit();showResult('success','Record Updated!','The record has been updated.',[['📋 Request No.',d.record.request_number],['🏢 Section',d.record.request_section]]);}
        else showAlert('r-edit-alert',d.message||'Failed to update.');
    }).catch(()=>showAlert('r-edit-alert','Network error.'));
}

function rExportCSV(){
    const rows=[['Record ID','Request No.','Ext No.','Section','Date Requested','Date Received','Performed','Imp Date','Information','Reason']];
    rGetFiltered().forEach(r=>rows.push([r.record_id,r.request_number||'',r.ext_number||'',r.request_section||'',r.date_requested||'',r.date_received||'',r.performed||'',r.imp_date||'',r.information||'',r.reason||'']));
    const csv=rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download='request_records.csv'; a.click();
    showToast('Exported!');
}

/* ══════════════════════════════════════════════════════
   ██████  USERS TAB
══════════════════════════════════════════════════════ */
let allUsers = <?= json_encode(array_values($allUsers)) ?>;
let uPage=1, uPer=10, uFilterRole='', uFilterStatus='', _curViewUser=null;

const statusBadge={
    active:'<span class="badge active">Active</span>',
    inactive:'<span class="badge inactive">Inactive</span>',
    pending:'<span class="badge pending">Pending</span>'
};

function getInitials(name){return name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();}

function uGetFiltered(){
    const q=(document.getElementById('u-search')?.value||'').toLowerCase();
    return allUsers.filter(u=>
        (!q||[u.full_name,u.email,u.username,u.associate_id||'',u.section||'',u.team||''].some(f=>f.toLowerCase().includes(q)))&&
        (!uFilterRole||u.role===uFilterRole)&&(!uFilterStatus||u.status===uFilterStatus)
    );
}

function uRenderTable(){
    const filtered=uGetFiltered(), total=filtered.length;
    const totalPages=Math.ceil(total/uPer)||1;
    if(uPage>totalPages) uPage=totalPages;
    const start=(uPage-1)*uPer, paged=filtered.slice(start,start+uPer);
    document.getElementById('u-tbody').innerHTML=paged.map(u=>{
        const init=getInitials(u.full_name), isSelf=u.id==SESSION_ID;
        return `<tr>
  <td><input type="checkbox" class="cb row-cb" data-id="${u.id}"></td>
  <td><div class="user-cell"><div class="user-avatar" style="background:${u.avatar_color}">${init}</div><div><div class="user-name">${u.full_name}${isSelf?' <span style="font-size:10px;background:#e8f5e9;color:#28c76f;padding:2px 7px;border-radius:4px;font-weight:600;">You</span>':''}</div><div class="user-email">${u.email}</div></div></div></td>
  <td style="color:var(--text-mid);font-size:13.5px;">${u.associate_id||'—'}</td>
  <td><div style="font-size:13.5px;color:var(--text-mid);">${u.section||'—'}</div><div style="font-size:12px;color:var(--text-light);">${u.team||''}</div></td>
  <td>${u.role==='admin'?'<span class="badge admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>':'<span class="badge user"><i class="fa-solid fa-user"></i> User</span>'}</td>
  <td>${statusBadge[u.status]||u.status}</td>
  <td><div class="action-cell">
    <button class="action-btn view-btn" title="View" onclick="uOpenView(${u.id})"><i class="fa fa-eye"></i></button>
    ${IS_ADMIN?`<button class="action-btn edit-btn" title="Edit" onclick="uOpenEdit(${u.id})"><i class="fa fa-pen"></i></button>
    <button class="action-btn del" title="Delete" onclick="openDelModal('delete_user',${u.id},'${u.full_name.replace(/'/g,"\\'")}','Delete User?')" ${isSelf?'disabled':''}><i class="fa fa-trash"></i></button>`:''}
  </div></td>
</tr>`;}).join('');
    const from=total?start+1:0, to=Math.min(start+uPer,total);
    document.getElementById('u-page-info').textContent=`Showing ${from} to ${to} of ${total} entries`;
    pageBtns('u-page-btns',uPage,totalPages,'uGoPage');
    uUpdateStats();
    document.getElementById('tab-count-users').textContent=allUsers.length;
}
function uGoPage(p){const t=Math.ceil(uGetFiltered().length/uPer)||1;if(p<1||p>t)return;uPage=p;uRenderTable();}
function uPerPageChanged(){uPer=parseInt(document.getElementById('u-per-page').value);uPage=1;uRenderTable();}
function uUpdateStats(){
    document.getElementById('ustat-total').innerHTML=`${allUsers.length} <span class="stat-change up">(+29%)</span>`;
    document.getElementById('ustat-active').innerHTML=`${allUsers.filter(u=>u.status==='active').length} <span class="stat-change up">(+12%)</span>`;
    document.getElementById('ustat-inactive').innerHTML=`${allUsers.filter(u=>u.status==='inactive').length} <span class="stat-change down">(-5%)</span>`;
    document.getElementById('ustat-pending').innerHTML=`${allUsers.filter(u=>u.status==='pending').length} <span class="stat-change up">(+42%)</span>`;
    document.getElementById('ustat-admin').textContent=allUsers.filter(u=>u.role==='admin').length;
    document.getElementById('ustat-users').textContent=allUsers.filter(u=>u.role==='user').length;
}
function uFilterCard(type){
    const wasActive=(type!=='total')&&((type==='admin'||type==='user')?uFilterRole===type:uFilterStatus===type);
    uFilterRole=''; uFilterStatus='';
    document.querySelectorAll('[id^="usc-"]').forEach(c=>c.classList.remove('active-filter'));
    if(!wasActive&&type!=='total'){
        if(type==='admin'||type==='user') uFilterRole=type;
        else uFilterStatus=type;
        const map={active:'usc-active',inactive:'usc-inactive',pending:'usc-pending',admin:'usc-admin',user:'usc-users'};
        if(map[type]) document.getElementById(map[type])?.classList.add('active-filter');
    }
    uPage=1; uRenderTable();
}

/* User view modal */
function uOpenView(id){
    const u=allUsers.find(x=>x.id==id); if(!u)return; _curViewUser=u;
    const init=getInitials(u.full_name);
    document.getElementById('uv-avatar').textContent=init;
    document.getElementById('uv-avatar').style.background=u.avatar_color;
    document.getElementById('uv-name').textContent=u.full_name;
    document.getElementById('uv-email').textContent=u.email;
    document.getElementById('uv-username').textContent=u.username;
    document.getElementById('uv-associate_id').textContent=u.associate_id||'—';
    document.getElementById('uv-section').textContent=u.section||'—';
    document.getElementById('uv-team').textContent=u.team||'—';
    document.getElementById('uv-created_at').textContent=u.created_at||'—';
    document.getElementById('uv-role').innerHTML=u.role==='admin'?'<span class="badge admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>':'<span class="badge user"><i class="fa-solid fa-user"></i> User</span>';
    document.getElementById('uv-status').innerHTML=statusBadge[u.status]||u.status;
    document.getElementById('u-view-modal').classList.add('open');
}
function uCloseView(){document.getElementById('u-view-modal').classList.remove('open');_curViewUser=null;}
function uSwitchEdit(){if(!_curViewUser)return;uCloseView();uOpenEdit(_curViewUser.id);}

/* User add modal */
function uOpenAdd(){
    ['ua-username','ua-full_name','ua-email','ua-associate_id'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('ua-section').value=''; document.getElementById('ua-team').value='';
    document.getElementById('ua-role-user').checked=true; document.getElementById('ua-status').value='active';
    hideAlert('u-add-alert'); document.getElementById('u-add-modal').classList.add('open');
}
function uCloseAdd(){document.getElementById('u-add-modal').classList.remove('open');}
document.getElementById('u-add-modal')?.addEventListener('click',function(e){if(e.target===this)uCloseAdd();});

function uSubmitAdd(){
    const username=document.getElementById('ua-username').value.trim();
    const full_name=document.getElementById('ua-full_name').value.trim();
    const email=document.getElementById('ua-email').value.trim();
    const associate_id=document.getElementById('ua-associate_id').value.trim();
    const section=document.getElementById('ua-section').value;
    const team=document.getElementById('ua-team').value;
    const role=document.querySelector('input[name="ua-role"]:checked').value;
    const status=document.getElementById('ua-status').value;
    hideAlert('u-add-alert');
    if(!username||!full_name||!email||!associate_id||!section||!team){showAlert('u-add-alert','All fields are required.');return;}
    if(!/^\d{7}$/.test(associate_id)){showAlert('u-add-alert','Associate ID must be exactly 7 digits.');return;}
    post({__action:'add_user',username,full_name,email,associate_id,section,team,role,status})
    .then(d=>{
        if(d.success){allUsers.push(d.user);uPage=1;uRenderTable();uCloseAdd();showResult('success','User Added Successfully!','The new account has been created.',[['👤 Full Name',d.user.full_name],['🪪 Associate ID',d.user.associate_id],['🏢 Section',d.user.section],['👥 Team',d.user.team],['🔐 Role',d.user.role],['📧 Email',d.user.email],['🔑 Password','changeme']]);}
        else showAlert('u-add-alert',d.message||'Failed to add user.');
    }).catch(()=>showAlert('u-add-alert','Network error.'));
}

/* User edit modal */
function uOpenEdit(id){
    const u=allUsers.find(x=>x.id==id); if(!u)return;
    document.getElementById('ue-id').value=u.id;
    document.getElementById('ue-username').value=u.username||'';
    document.getElementById('ue-full_name').value=u.full_name||'';
    document.getElementById('ue-email').value=u.email||'';
    document.getElementById('ue-associate_id').value=u.associate_id||'';
    document.getElementById('ue-status').value=u.status||'active';
    document.getElementById(u.role==='admin'?'ue-role-admin':'ue-role-user').checked=true;
    const se=document.getElementById('ue-section'); se.value=u.section||'';
    if(se.value!==(u.section||'')&&u.section){se.add(new Option(u.section,u.section,true,true));}
    const te=document.getElementById('ue-team'); te.value=u.team||'';
    if(te.value!==(u.team||'')&&u.team){te.add(new Option(u.team,u.team,true,true));}
    hideAlert('u-edit-alert'); document.getElementById('u-edit-modal').classList.add('open');
}
function uCloseEdit(){document.getElementById('u-edit-modal').classList.remove('open');}
document.getElementById('u-edit-modal')?.addEventListener('click',function(e){if(e.target===this)uCloseEdit();});

function uSubmitEdit(){
    const id=document.getElementById('ue-id').value;
    const username=document.getElementById('ue-username').value.trim();
    const full_name=document.getElementById('ue-full_name').value.trim();
    const email=document.getElementById('ue-email').value.trim();
    const associate_id=document.getElementById('ue-associate_id').value.trim();
    const section=document.getElementById('ue-section').value;
    const team=document.getElementById('ue-team').value;
    const role=document.querySelector('input[name="ue-role"]:checked').value;
    const status=document.getElementById('ue-status').value;
    hideAlert('u-edit-alert');
    if(!username||!full_name||!email||!associate_id||!section||!team){showAlert('u-edit-alert','All fields are required.');return;}
    if(!/^\d{7}$/.test(associate_id)){showAlert('u-edit-alert','Associate ID must be exactly 7 digits.');return;}
    post({__action:'edit_user',id,username,full_name,email,associate_id,section,team,role,status})
    .then(d=>{
        if(d.success){const i=allUsers.findIndex(u=>u.id==id);if(i!==-1)allUsers[i]={...allUsers[i],...d.user};uRenderTable();uCloseEdit();showResult('success','User Updated!','The user\'s info has been saved.',[['👤 Full Name',d.user.full_name],['🪪 Associate ID',d.user.associate_id],['🔐 Role',d.user.role],['📋 Status',d.user.status]]);}
        else showAlert('u-edit-alert',d.message||'Failed to update.');
    }).catch(()=>showAlert('u-edit-alert','Network error.'));
}

function uExportCSV(){
    const rows=[['Name','Email','Username','Associate ID','Section','Team','Role','Status']];
    uGetFiltered().forEach(u=>rows.push([u.full_name,u.email,u.username,u.associate_id||'',u.section||'',u.team||'',u.role,u.status]));
    const csv=rows.map(r=>r.join(',')).join('\n');
    const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download='users.csv'; a.click();
    showToast('Exported!');
}

function toggleAcc(id){document.getElementById(id).classList.toggle('open');}

/* ── Init ── */
rRenderTable();
uRenderTable();
</script>
</body>
</html>