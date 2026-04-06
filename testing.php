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

        case 'get_next_student_number':
            $year = date('y');
            $prefix = "SMES-STU-{$year}-";
            $stmt = $pdo->prepare("SELECT student_number FROM student_record WHERE student_number LIKE ? ORDER BY record_id DESC LIMIT 1");
            $stmt->execute([$prefix . '%']);
            $last = $stmt->fetchColumn();
            $seq = $last ? str_pad((int) substr($last, strlen($prefix)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
            echo json_encode(['success' => true, 'student_number' => $prefix . $seq]);
            exit;

        case 'add_record':
            if (!$isAdminAjax) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
            $sn   = trim($_POST['student_number']   ?? '');
            $ln   = trim($_POST['last_name']         ?? '') ?: null;
            $fn   = trim($_POST['first_name']        ?? '') ?: null;
            $mi   = trim($_POST['middle_name']       ?? '') ?: null;
            $full = ($ln && $fn) ? $ln . ', ' . $fn . ($mi ? ' ' . $mi[0] . '.' : '') : ($ln ?? $fn ?? null);
            $lrn  = trim($_POST['lrn']               ?? '') ?: null;
            $dob  = trim($_POST['date_of_birth']     ?? '') ?: null;
            $age  = trim($_POST['age']               ?? '') ?: null;
            $gen  = trim($_POST['gender']            ?? '') ?: null;
            $gr   = trim($_POST['grade_level']       ?? '') ?: null;
            $sec  = trim($_POST['section']           ?? '') ?: null;
            $sy   = trim($_POST['school_year']       ?? '') ?: null;
            $par  = trim($_POST['parent_guardian']   ?? '') ?: null;
            $con  = trim($_POST['contact_number']    ?? '') ?: null;
            $addr = trim($_POST['address']           ?? '') ?: null;
            $stat = trim($_POST['status']            ?? '') ?: null;
            $tin  = trim($_POST['transferred_in']    ?? '') ?: null;
            $tout = trim($_POST['transferred_out']   ?? '') ?: null;
            $ba   = trim($_POST['balik_aral']        ?? '') ?: null;
            $rep  = trim($_POST['repeater']          ?? '') ?: null;
            $rmk  = trim($_POST['remarks']           ?? '') ?: null;
            $enc  = trim($_POST['encoded_by']        ?? '') ?: null;
            $edt  = date('Y-m-d');
            // Validate LRN: must be exactly 11 digits if provided
            if ($lrn && !preg_match('/^\d{11}$/', $lrn)) { echo json_encode(['success' => false, 'message' => 'LRN must be exactly 11 digits.']); exit; }
            if (!$sn || !$ln || !$fn) { echo json_encode(['success' => false, 'message' => 'Student Number, Last Name, and First Name are required.']); exit; }
            $stmt = $pdo->prepare('INSERT INTO student_record (student_number,last_name,first_name,middle_name,full_name,lrn,date_of_birth,age,gender,grade_level,section,school_year,parent_guardian,contact_number,address,status,transferred_in,transferred_out,balik_aral,repeater,remarks,encoded_by,date_encoded) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$sn,$ln,$fn,$mi,$full,$lrn,$dob,$age,$gen,$gr,$sec,$sy,$par,$con,$addr,$stat,$tin,$tout,$ba,$rep,$rmk,$enc,$edt]);
            echo json_encode(['success' => true, 'record' => ['record_id'=>(int)$pdo->lastInsertId(),'student_number'=>$sn,'last_name'=>$ln,'first_name'=>$fn,'middle_name'=>$mi,'full_name'=>$full,'lrn'=>$lrn,'date_of_birth'=>$dob,'age'=>$age,'gender'=>$gen,'grade_level'=>$gr,'section'=>$sec,'school_year'=>$sy,'parent_guardian'=>$par,'contact_number'=>$con,'address'=>$addr,'status'=>$stat,'transferred_in'=>$tin,'transferred_out'=>$tout,'balik_aral'=>$ba,'repeater'=>$rep,'remarks'=>$rmk,'encoded_by'=>$enc,'date_encoded'=>$edt]]);
            exit;

        case 'edit_record':
            if (!$isAdminAjax) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
            $id   = (int)($_POST['id'] ?? 0);
            $sn   = trim($_POST['student_number']   ?? '');
            $ln   = trim($_POST['last_name']         ?? '') ?: null;
            $fn   = trim($_POST['first_name']        ?? '') ?: null;
            $mi   = trim($_POST['middle_name']       ?? '') ?: null;
            $full = ($ln && $fn) ? $ln . ', ' . $fn . ($mi ? ' ' . $mi[0] . '.' : '') : ($ln ?? $fn ?? null);
            $lrn  = trim($_POST['lrn']               ?? '') ?: null;
            $dob  = trim($_POST['date_of_birth']     ?? '') ?: null;
            $age  = trim($_POST['age']               ?? '') ?: null;
            $gen  = trim($_POST['gender']            ?? '') ?: null;
            $gr   = trim($_POST['grade_level']       ?? '') ?: null;
            $sec  = trim($_POST['section']           ?? '') ?: null;
            $sy   = trim($_POST['school_year']       ?? '') ?: null;
            $par  = trim($_POST['parent_guardian']   ?? '') ?: null;
            $con  = trim($_POST['contact_number']    ?? '') ?: null;
            $addr = trim($_POST['address']           ?? '') ?: null;
            $stat = trim($_POST['status']            ?? '') ?: null;
            $tin  = trim($_POST['transferred_in']    ?? '') ?: null;
            $tout = trim($_POST['transferred_out']   ?? '') ?: null;
            $ba   = trim($_POST['balik_aral']        ?? '') ?: null;
            $rep  = trim($_POST['repeater']          ?? '') ?: null;
            $rmk  = trim($_POST['remarks']           ?? '') ?: null;
            $enc  = trim($_POST['encoded_by']        ?? '') ?: null;
            if ($lrn && !preg_match('/^\d{11}$/', $lrn)) { echo json_encode(['success' => false, 'message' => 'LRN must be exactly 11 digits.']); exit; }
            if (!$id || !$sn || !$ln || !$fn) { echo json_encode(['success' => false, 'message' => 'Required fields missing.']); exit; }
            $stmt = $pdo->prepare('UPDATE student_record SET student_number=?,last_name=?,first_name=?,middle_name=?,full_name=?,lrn=?,date_of_birth=?,age=?,gender=?,grade_level=?,section=?,school_year=?,parent_guardian=?,contact_number=?,address=?,status=?,transferred_in=?,transferred_out=?,balik_aral=?,repeater=?,remarks=?,encoded_by=? WHERE record_id=?');
            $stmt->execute([$sn,$ln,$fn,$mi,$full,$lrn,$dob,$age,$gen,$gr,$sec,$sy,$par,$con,$addr,$stat,$tin,$tout,$ba,$rep,$rmk,$enc,$id]);
            echo json_encode(['success' => true, 'record' => ['record_id'=>$id,'student_number'=>$sn,'last_name'=>$ln,'first_name'=>$fn,'middle_name'=>$mi,'full_name'=>$full,'lrn'=>$lrn,'date_of_birth'=>$dob,'age'=>$age,'gender'=>$gen,'grade_level'=>$gr,'section'=>$sec,'school_year'=>$sy,'parent_guardian'=>$par,'contact_number'=>$con,'address'=>$addr,'status'=>$stat,'transferred_in'=>$tin,'transferred_out'=>$tout,'balik_aral'=>$ba,'repeater'=>$rep,'remarks'=>$rmk,'encoded_by'=>$enc]]);
            exit;

        case 'delete_record':
            if (!$isAdminAjax) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }
            $pdo->prepare('DELETE FROM student_record WHERE record_id=?')->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }
}

$sessionUser = ['id'=>$_SESSION['user_id'],'username'=>$_SESSION['username'],'full_name'=>$_SESSION['full_name'],'role'=>$_SESSION['role'],'email'=>$_SESSION['email'],'color'=>$_SESSION['color']];
$isAdmin  = $sessionUser['role'] === 'admin';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $sessionUser['full_name']), 0, 2)));

$allRecords = $pdo->query('SELECT record_id,student_number,last_name,first_name,middle_name,full_name,lrn,date_of_birth,age,gender,grade_level,section,school_year,parent_guardian,contact_number,address,status,transferred_in,transferred_out,balik_aral,repeater,remarks,encoded_by,date_encoded FROM student_record ORDER BY record_id DESC')->fetchAll(PDO::FETCH_ASSOC);

$year   = date('y');
$prefix = "SMES-STU-{$year}-";
$stmt   = $pdo->prepare("SELECT student_number FROM student_record WHERE student_number LIKE ? ORDER BY record_id DESC LIMIT 1");
$stmt->execute([$prefix . '%']);
$lastSN       = $stmt->fetchColumn();
$nextSeq      = $lastSN ? str_pad((int) substr($lastSN, strlen($prefix)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$nextStudentNumber = $prefix . $nextSeq;

$totalRecords    = count($allRecords);
$enrolledRecords = count(array_filter($allRecords, fn($r) => strtolower($r['status'] ?? '') === 'enrolled'));
$pendingRecords  = count(array_filter($allRecords, fn($r) => strtolower($r['status'] ?? '') === 'pending'));
$withdrawnRecords= count(array_filter($allRecords, fn($r) => strtolower($r['status'] ?? '') === 'withdrawn'));
$thisMonth       = count(array_filter($allRecords, fn($r) => strpos($r['date_encoded'] ?? '', date('Y-m')) === 0));

$associates = [];
try {
    $associates = $pdo->query("SELECT id,full_name,username,role,associate_id,avatar_color FROM users WHERE status='active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    try { $associates = $pdo->query("SELECT id,full_name,username,role,associate_id,avatar_color FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e2) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Student Registration – SMES Grading System</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        /* ── Stat Grid ── */
        .stat-grid { grid-template-columns: repeat(5,1fr) !important; }
        @media(max-width:1100px){ .stat-grid { grid-template-columns:repeat(3,1fr) !important; } }
        @media(max-width:680px){  .stat-grid { grid-template-columns:repeat(2,1fr) !important; } }

        .stat-card { cursor:pointer; transition:transform .18s,box-shadow .18s,border-color .18s; border:2px solid transparent; position:relative; }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.10); }
        .stat-card.active-filter          { border-color:#696cff; box-shadow:0 0 0 3px #696cff22; }
        .stat-card.active-filter.fc-green { border-color:#28c76f; box-shadow:0 0 0 3px #28c76f22; }
        .stat-card.active-filter.fc-orange{ border-color:#ff9f43; box-shadow:0 0 0 3px #ff9f4322; }
        .stat-card.active-filter.fc-red   { border-color:#ff3e1d; box-shadow:0 0 0 3px #ff3e1d22; }
        .stat-card.active-filter.fc-blue  { border-color:#696cff; box-shadow:0 0 0 3px #696cff22; }
        .stat-card.active-filter .stat-label::after { content:' ✕'; font-size:10px; color:#696cff; font-weight:700; }
        .stat-card::after { content:attr(data-tip); position:absolute; bottom:-28px; left:50%; transform:translateX(-50%); background:#2d3a4a; color:#fff; font-size:11px; padding:3px 9px; border-radius:5px; white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .2s; }
        .stat-card:hover::after { opacity:1; }
        .stat-icon.teal  { background:#00bcd412; color:#00bcd4; }
        .stat-icon.blue  { background:#696cff12; color:#696cff; }
        .stat-icon.red   { background:#ff3e1d12; color:#ff3e1d; }

        /* ── Action Buttons ── */
        .action-cell { display:flex; gap:5px; align-items:center; }
        .action-btn { border:none; background:transparent; cursor:pointer; width:30px; height:30px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:13px; transition:background .15s,color .15s; color:#8a93a2; }
        .action-btn:hover       { background:#f0f1f5; color:#696cff; }
        .action-btn.del:hover   { background:#fff0ee; color:#ff3e1d; }
        .action-btn.edit-btn:hover { background:#fff8ec; color:#ff9f43; }
        .action-btn.view-btn:hover { background:#edfaf4; color:#28c76f; }

        /* ── Status Badges ── */
        .badge { display:inline-flex; align-items:center; gap:4px; font-size:11.5px; font-weight:600; padding:3px 10px; border-radius:20px; }
        .badge-enrolled  { background:#28c76f18; color:#28c76f; }
        .badge-pending   { background:#ff9f4318; color:#ff9f43; }
        .badge-withdrawn { background:#ff3e1d12; color:#ff3e1d; }
        .badge-default   { background:#e4e5ec;   color:#8a93a2; }

        /* ── Modals ── */
        @keyframes vmIn { from{transform:scale(.85);opacity:0} to{transform:scale(1);opacity:1} }

        .ce-modal-overlay { display:none; position:fixed; inset:0; background:rgba(30,30,60,.45); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .ce-modal-overlay.open { display:flex; }
        .ce-modal { background:#fff; border-radius:16px; width:95%; max-width:620px; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.18); animation:vmIn .3s cubic-bezier(.34,1.56,.64,1); }
        .ce-modal-header { padding:22px 28px 18px; border-radius:16px 16px 0 0; display:flex; align-items:center; gap:14px; color:#fff; }
        .ce-modal-header-icon { width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .ce-modal-header h3 { font-size:16px; font-weight:700; margin:0; }
        .ce-modal-header p  { font-size:12px; opacity:.85; margin:2px 0 0; }
        .ce-modal-body { padding:24px 28px 26px; }
        .form-section { font-size:11px; font-weight:700; color:#696cff; text-transform:uppercase; letter-spacing:.6px; margin:18px 0 10px; padding-bottom:5px; border-bottom:1px solid #ebebff; display:flex; align-items:center; gap:6px; }
        .form-section:first-child { margin-top:0; }
        .modal-label  { font-size:11.5px; font-weight:600; color:#6e7a8a; text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:5px; }
        .modal-input  { width:100%; box-sizing:border-box; padding:9px 12px; border:1.5px solid #e4e5ec; border-radius:8px; font-size:13.5px; color:#2d3a4a; outline:none; transition:border-color .2s,box-shadow .2s; background:#fafbfc; font-family:inherit; }
        .modal-input:focus { border-color:#696cff; box-shadow:0 0 0 3px #696cff18; background:#fff; }
        select.modal-input { cursor:pointer; }
        .rn-chip  { width:100%; box-sizing:border-box; padding:9px 12px; border:1.5px solid #e4e5ec; border-radius:8px; font-size:14px; font-weight:700; color:#696cff; background:#f3f3ff; letter-spacing:.5px; }
        .rn-hint  { font-size:11px; color:#a0a8b5; margin-top:4px; }
        .modal-grid2  { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
        .modal-grid3  { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:14px; }
        .modal-field  { margin-bottom:14px; }
        .modal-field:last-child { margin-bottom:0; }
        .ce-modal-footer { display:flex; justify-content:flex-end; gap:10px; padding:14px 28px 22px; }
        .btn-modal-cancel { background:#f0f1f5; border:none; border-radius:8px; padding:10px 24px; font-size:14px; font-weight:600; color:#5a6070; cursor:pointer; }
        .btn-modal-cancel:hover { background:#e4e5ec; }
        .btn-modal-submit { border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-modal-submit:hover { opacity:.88; }
        .modal-alert { display:none; align-items:center; gap:8px; background:#fff0ee; border:1px solid #ffd5cf; border-radius:8px; padding:10px 14px; font-size:13px; color:#d43a1a; margin-bottom:16px; }

        /* ── View Modal ── */
        .view-modal-overlay { display:none; position:fixed; inset:0; background:rgba(30,30,60,.45); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .view-modal-overlay.open { display:flex; }
        .view-modal { background:#fff; border-radius:16px; padding:36px 36px 30px; max-width:580px; width:94%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.18); animation:vmIn .3s cubic-bezier(.34,1.56,.64,1); }
        .view-modal-header { display:flex; align-items:center; gap:16px; margin-bottom:24px; }
        .view-icon-wrap { width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#696cff,#5457c4); display:flex; align-items:center; justify-content:center; font-size:20px; color:#fff; flex-shrink:0; }
        .view-name { font-size:17px; font-weight:700; color:#2d3a4a; }
        .view-sub  { font-size:13px; color:#8a93a2; margin-top:2px; }
        .view-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; background:#f6f7f9; border-radius:10px; padding:18px 20px; margin-bottom:24px; }
        .view-field label { font-size:11px; font-weight:600; color:#a0a8b5; text-transform:uppercase; letter-spacing:.5px; }
        .view-field span  { display:block; font-size:13.5px; font-weight:500; color:#2d3a4a; margin-top:3px; }
        .view-field.full  { grid-column:1/-1; }
        .view-modal-footer { display:flex; justify-content:flex-end; gap:10px; }
        .btn-close-view { background:#f0f1f5; border:none; border-radius:8px; padding:9px 22px; font-size:14px; font-weight:600; color:#5a6070; cursor:pointer; }
        .btn-close-view:hover { background:#e4e5ec; }
        .btn-edit-view  { background:linear-gradient(135deg,#696cff,#5457c4); border:none; border-radius:8px; padding:9px 22px; font-size:14px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-edit-view:hover { opacity:.88; }

        /* ── Delete Modal ── */
        .del-modal-overlay { display:none; position:fixed; inset:0; background:rgba(30,30,60,.45); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; }
        .del-modal-overlay.open { display:flex; }
        .del-modal { background:#fff; border-radius:16px; padding:36px 36px 30px; max-width:400px; width:92%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.18); animation:vmIn .3s cubic-bezier(.34,1.56,.64,1); }
        .del-icon-wrap { width:72px; height:72px; border-radius:50%; background:#ff3e1d12; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
        .del-icon-wrap i { font-size:32px; color:#ff3e1d; }
        .del-title { font-size:19px; font-weight:700; color:#2d3a4a; margin-bottom:8px; }
        .del-sub   { font-size:13.5px; color:#6e7a8a; line-height:1.6; margin-bottom:26px; }
        .del-btns  { display:flex; gap:10px; justify-content:center; }
        .btn-cancel-del  { background:#f0f1f5; border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; color:#5a6070; cursor:pointer; }
        .btn-confirm-del { background:#ff3e1d; border:none; border-radius:8px; padding:10px 26px; font-size:14px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-confirm-del:hover { opacity:.88; }

        /* ── Result Modal ── */
        .result-overlay { display:none; position:fixed; inset:0; background:rgba(30,30,60,.45); backdrop-filter:blur(4px); z-index:10000; align-items:center; justify-content:center; }
        .result-overlay.open { display:flex; }
        .result-box { background:#fff; border-radius:16px; padding:40px 36px 32px; max-width:380px; width:92%; text-align:center; box-shadow:0 24px 60px rgba(0,0,0,.2); animation:vmIn .35s cubic-bezier(.34,1.56,.64,1); }
        .result-icon-wrap { width:76px; height:76px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
        .result-icon-wrap.success { background:#28c76f18; }
        .result-icon-wrap.error   { background:#ff3e1d12; }
        .result-icon-wrap i { font-size:34px; }
        .result-icon-wrap.success i { color:#28c76f; }
        .result-icon-wrap.error   i { color:#ff3e1d; }
        .result-title  { font-size:20px; font-weight:700; color:#2d3a4a; margin-bottom:8px; }
        .result-msg    { font-size:13.5px; color:#6e7a8a; line-height:1.65; margin-bottom:26px; }
        .result-detail { background:#f6f7f9; border-radius:10px; padding:13px 16px; text-align:left; font-size:13px; color:#4a5568; line-height:1.9; margin-bottom:24px; }
        .result-detail .rd-row   { display:flex; gap:8px; }
        .result-detail .rd-label { font-weight:600; color:#2d3a4a; min-width:130px; }
        .btn-result-ok { background:linear-gradient(135deg,#28c76f,#20a857); border:none; border-radius:9px; padding:12px 36px; font-size:15px; font-weight:600; color:#fff; cursor:pointer; }
        .btn-result-ok.danger { background:linear-gradient(135deg,#ff3e1d,#ff6b4a); }
        .btn-result-ok:hover { opacity:.88; }

        /* ── Toast ── */
        .toast { position:fixed; bottom:28px; right:28px; z-index:99999; background:#2d3a4a; color:#fff; padding:12px 22px; border-radius:10px; font-size:14px; font-weight:500; opacity:0; pointer-events:none; transition:opacity .3s; box-shadow:0 6px 24px rgba(0,0,0,.15); }
        .toast.show { opacity:1; pointer-events:auto; }
        .toast.success { background:#28c76f; }
        .toast.error   { background:#ff3e1d; }

        /* ── Student Number badge ── */
        .sn-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; background:#696cff18; color:#696cff; }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <a class="sidebar-logo" href="#">
        <div class="logo-icon">SM</div>
        <span class="logo-text">SMES</span>
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
            <li class="nav-item nav-accordion open" id="enrollmentControl">
                <a class="nav-link active" href="#" onclick="toggleAcc('enrollmentControl');return false;">
                    <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                    <span class="nav-text">Enrollment</span>
                    <i class="fa fa-chevron-right nav-chevron"></i>
                </a>
                <ul class="nav-sub">
                    <li><a class="nav-sub-link active" href="newstudent.php">New Student Registration</a></li>
                    <li><a class="nav-sub-link" href="reenrollment.php">Re-Enrollment</a></li>
                    <li><a class="nav-sub-link" href="transferee.php">Transferee</a></li>
                </ul>
            </li>
            <li class="nav-item nav-accordion" id="gradesControl">
                <a class="nav-link" href="#" onclick="toggleAcc('gradesControl');return false;">
                    <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                    <span class="nav-text">Grades</span>
                    <i class="fa fa-chevron-right nav-chevron"></i>
                </a>
                <ul class="nav-sub">
                    <li><a class="nav-sub-link" href="../grades/entry.php">Grade Entry</a></li>
                    <li><a class="nav-sub-link" href="../grades/reports.php">Grade Reports</a></li>
                    <li><a class="nav-sub-link" href="../grades/cards.php">Report Cards</a></li>
                </ul>
            </li>
            <li class="nav-item nav-accordion" id="classesControl">
                <a class="nav-link" href="#" onclick="toggleAcc('classesControl');return false;">
                    <span class="nav-icon"><i class="fa-solid fa-chalkboard-teacher"></i></span>
                    <span class="nav-text">Classes</span>
                    <i class="fa fa-chevron-right nav-chevron"></i>
                </a>
                <ul class="nav-sub">
                    <li><a class="nav-sub-link" href="../classes/sections.php">Sections</a></li>
                    <li><a class="nav-sub-link" href="../classes/subjects.php">Subjects</a></li>
                    <li><a class="nav-sub-link" href="../classes/schedule.php">Schedule</a></li>
                </ul>
            </li>
            <li class="nav-section-label">Admin</li>
            <li class="nav-item">
                <a class="nav-link" href="../users/index.php">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../settings/index.php">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- ════ CONTENT WRAPPER ════ -->
<div class="content-wrap">
    <header class="topbar">
        <button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-open')">
            <i class="fa fa-bars"></i>
        </button>
        <div class="topbar-title">SMES Grading System</div>
        <div class="topbar-right">
            <div class="user-chip" onclick="this.querySelector('.dropdown').classList.toggle('open')">
                <div class="user-avatar" style="background:<?= htmlspecialchars($sessionUser['color'] ?? '#696cff') ?>;"><?= $initials ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($sessionUser['full_name']) ?></div>
                    <div class="user-role"><?= ucfirst(htmlspecialchars($sessionUser['role'])) ?></div>
                </div>
                <i class="fa fa-chevron-down" style="font-size:10px;color:#8a93a2;"></i>
                <div class="dropdown">
                    <a class="dropdown-item" href="../profile.php"><i class="fa fa-user"></i> Profile</a>
                    <a class="dropdown-item" href="../settings/index.php"><i class="fa fa-gear"></i> Settings</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item danger" href="../logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="breadcrumb">
            <a href="#">Home</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <a href="#">Enrollment</a>
            <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
            <span style="color:var(--text-mid);">New Student Registration</span>
        </div>

        <?php if (!$isAdmin): ?>
        <div class="access-notice"><i class="fa-solid fa-circle-info"></i> You are logged in as a <strong>User</strong>. Management actions are restricted to Admins only.</div>
        <?php endif; ?>

        <!-- ── STAT CARDS ── -->
        <div class="stat-grid">
            <div class="stat-card" id="sc-total" onclick="filterByCard('total')" data-tip="Show all">
                <div>
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value" id="stat-total"><?= $totalRecords ?></div>
                    <div class="stat-sub">All Records</div>
                </div>
                <div class="stat-icon purple"><i class="fa-solid fa-user-graduate"></i></div>
            </div>
            <div class="stat-card fc-green" id="sc-enrolled" onclick="filterByCard('enrolled')" data-tip="Filter: Enrolled">
                <div>
                    <div class="stat-label">Enrolled</div>
                    <div class="stat-value" id="stat-enrolled"><?= $enrolledRecords ?></div>
                    <div class="stat-sub">Status: Enrolled</div>
                </div>
                <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            </div>
            <div class="stat-card fc-orange" id="sc-pending" onclick="filterByCard('pending')" data-tip="Filter: Pending">
                <div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" id="stat-pending"><?= $pendingRecords ?></div>
                    <div class="stat-sub">Status: Pending</div>
                </div>
                <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
            </div>
            <div class="stat-card fc-red" id="sc-withdrawn" onclick="filterByCard('withdrawn')" data-tip="Filter: Withdrawn">
                <div>
                    <div class="stat-label">Withdrawn</div>
                    <div class="stat-value" id="stat-withdrawn"><?= $withdrawnRecords ?></div>
                    <div class="stat-sub">Status: Withdrawn</div>
                </div>
                <div class="stat-icon red"><i class="fa-solid fa-user-minus"></i></div>
            </div>
            <div class="stat-card fc-blue" id="sc-month" onclick="filterByCard('month')" data-tip="Filter: This Month">
                <div>
                    <div class="stat-label">This Month</div>
                    <div class="stat-value" id="stat-month"><?= $thisMonth ?></div>
                    <div class="stat-sub"><?= date('F Y') ?></div>
                </div>
                <div class="stat-icon blue"><i class="fa-solid fa-calendar-week"></i></div>
            </div>
        </div>

        <!-- ── TABLE CARD ── -->
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
                    <input type="text" id="table-search" placeholder="Search Student" oninput="renderTable()">
                </div>
                <button class="btn btn-outline" onclick="exportCSV()"><i class="fa fa-download"></i> Export</button>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fa fa-plus"></i> Add Student</button>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" class="cb" id="select-all" onchange="toggleAll(this)"></th>
                        <th>Student No.</th>
                        <th>LRN</th>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Grade Level</th>
                        <th>Section</th>
                        <th>Birthdate</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Trans. In</th>
                        <th>Trans. Out</th>
                        <th>Balik-Aral</th>
                        <th>Repeater</th>
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

<!-- ════ TOAST ════ -->
<div class="toast" id="toast"></div>

<?php if ($isAdmin): ?>
<!-- ════ ADD MODAL ════ -->
<div class="ce-modal-overlay" id="add-modal">
    <div class="ce-modal">
        <div class="ce-modal-header" style="background:linear-gradient(135deg,#696cff,#5457c4);">
            <div class="ce-modal-header-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div>
                <h3>Add New Student</h3>
                <p>Fill in all required fields. Student number is auto-generated.</p>
            </div>
        </div>
        <div class="ce-modal-body">
            <div id="modal-alert" class="modal-alert"></div>

            <div class="form-section"><i class="fa-solid fa-hashtag"></i> Student Number</div>
            <div class="modal-field">
                <label class="modal-label">Student Number</label>
                <div class="rn-chip" id="add-sn-display"><?= htmlspecialchars($nextStudentNumber) ?></div>
                <input type="hidden" id="add-student_number" value="<?= htmlspecialchars($nextStudentNumber) ?>">
                <div class="rn-hint">Auto-generated sequential number for current year.</div>
            </div>

            <div class="form-section"><i class="fa-solid fa-id-card"></i> Learner Reference Number (LRN)</div>
            <div class="modal-field">
                <label class="modal-label">LRN <span style="color:#8a93a2;font-weight:400;">(11 digits)</span></label>
                <input class="modal-input" id="add-lrn" type="text" maxlength="11" inputmode="numeric" pattern="\d{11}" placeholder="e.g. 10012345678" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
                <div class="rn-hint" id="add-lrn-hint" style="display:none;color:#ff3e1d;">LRN must be exactly 11 digits.</div>
            </div>

            <div class="form-section"><i class="fa-solid fa-user"></i> Personal Information</div>
            <div class="modal-grid3">
                <div><label class="modal-label">Last Name <span style="color:#ff3e1d;">*</span></label><input class="modal-input" id="add-last_name" type="text" placeholder="Last Name"></div>
                <div><label class="modal-label">First Name <span style="color:#ff3e1d;">*</span></label><input class="modal-input" id="add-first_name" type="text" placeholder="First Name"></div>
                <div><label class="modal-label">Middle Name</label><input class="modal-input" id="add-middle_name" type="text" placeholder="Middle Name"></div>
            </div>
            <div class="modal-grid3">
                <div>
                    <label class="modal-label">Birthdate</label>
                    <input class="modal-input" id="add-date_of_birth" type="date" onchange="calcAge('add')">
                </div>
                <div>
                    <label class="modal-label">Age</label>
                    <input class="modal-input" id="add-age" type="number" min="3" max="25" placeholder="Age" readonly style="background:#f3f3ff;color:#696cff;font-weight:600;">
                </div>
                <div>
                    <label class="modal-label">Gender</label>
                    <select class="modal-input" id="add-gender">
                        <option value="">— Select —</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            <div class="modal-field">
                <label class="modal-label">Address</label>
                <input class="modal-input" id="add-address" type="text" placeholder="Home Address">
            </div>

            <div class="form-section"><i class="fa-solid fa-school"></i> Academic Information</div>
            <div class="modal-grid3">
                <div>
                    <label class="modal-label">Grade Level <span style="color:#ff3e1d;">*</span></label>
                    <select class="modal-input" id="add-grade_level">
                        <option value="">— Grade —</option>
                        <option value="Kinder">Kinder</option>
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                        <option value="Grade 7">Grade 7</option>
                        <option value="Grade 8">Grade 8</option>
                        <option value="Grade 9">Grade 9</option>
                        <option value="Grade 10">Grade 10</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                    </select>
                </div>
                <div><label class="modal-label">Section</label><input class="modal-input" id="add-section" type="text" placeholder="e.g. Sampaguita"></div>
                <div><label class="modal-label">School Year</label><input class="modal-input" id="add-school_year" type="text" placeholder="e.g. 2024-2025"></div>
            </div>

            <div class="form-section"><i class="fa-solid fa-right-left"></i> Student Classification</div>
            <div class="modal-grid2">
                <div>
                    <label class="modal-label">Status <span style="color:#ff3e1d;">*</span></label>
                    <select class="modal-input" id="add-status">
                        <option value="">— Select Status —</option>
                        <option value="Enrolled">Enrolled</option>
                        <option value="Pending">Pending</option>
                        <option value="Withdrawn">Withdrawn</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Transferred In</label>
                    <select class="modal-input" id="add-transferred_in">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
            <div class="modal-grid3">
                <div>
                    <label class="modal-label">Transferred Out</label>
                    <select class="modal-input" id="add-transferred_out">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Balik-Aral</label>
                    <select class="modal-input" id="add-balik_aral">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Repeater</label>
                    <select class="modal-input" id="add-repeater">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            <div class="form-section"><i class="fa-solid fa-people-roof"></i> Parent / Guardian</div>
            <div class="modal-grid2">
                <div><label class="modal-label">Parent / Guardian Name</label><input class="modal-input" id="add-parent_guardian" type="text" placeholder="Full Name"></div>
                <div><label class="modal-label">Contact Number</label><input class="modal-input" id="add-contact_number" type="text" placeholder="09XX-XXX-XXXX"></div>
            </div>

            <div class="form-section"><i class="fa-solid fa-user-check"></i> Registration Details</div>
            <div class="modal-grid2">
                <div>
                    <label class="modal-label">Encoded By</label>
                    <select class="modal-input" id="add-encoded_by">
                        <option value="">— Select Staff —</option>
                        <?php foreach ($associates as $a):
                            $rl = ucfirst($a['role'] ?? '');
                            $ai = $a['associate_id'] ? ' · '.$a['associate_id'] : ''; ?>
                        <option value="<?= htmlspecialchars($a['full_name']) ?>">
                            <?= htmlspecialchars($a['full_name'].$ai) ?> (<?= htmlspecialchars($rl) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Remarks</label>
                    <input class="modal-input" id="add-remarks" type="text" placeholder="Optional notes...">
                </div>
            </div>
        </div>
        <div class="ce-modal-footer">
            <button class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
            <button class="btn-modal-submit" style="background:linear-gradient(135deg,#696cff,#5457c4);" onclick="submitAddRecord()"><i class="fa fa-save"></i> Save Student</button>
        </div>
    </div>
</div>

<!-- ════ EDIT MODAL ════ -->
<div class="ce-modal-overlay" id="edit-modal">
    <div class="ce-modal">
        <div class="ce-modal-header" style="background:linear-gradient(135deg,#ff9f43,#e08d34);">
            <div class="ce-modal-header-icon"><i class="fa-solid fa-user-pen"></i></div>
            <div>
                <h3>Edit Student Record</h3>
                <p>Update the student information below.</p>
            </div>
        </div>
        <div class="ce-modal-body">
            <input type="hidden" id="edit-record_id">
            <div id="edit-modal-alert" class="modal-alert"></div>

            <div class="form-section"><i class="fa-solid fa-hashtag"></i> Student Number</div>
            <div class="modal-field">
                <label class="modal-label">Student Number</label>
                <input class="modal-input" id="edit-student_number" type="text" readonly style="background:#f3f3ff;color:#696cff;font-weight:700;">
            </div>

            <div class="form-section"><i class="fa-solid fa-id-card"></i> Learner Reference Number (LRN)</div>
            <div class="modal-field">
                <label class="modal-label">LRN <span style="color:#8a93a2;font-weight:400;">(11 digits)</span></label>
                <input class="modal-input" id="edit-lrn" type="text" maxlength="11" inputmode="numeric" pattern="\d{11}" placeholder="e.g. 10012345678" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
                <div class="rn-hint" id="edit-lrn-hint" style="display:none;color:#ff3e1d;">LRN must be exactly 11 digits.</div>
            </div>

            <div class="form-section"><i class="fa-solid fa-user"></i> Personal Information</div>
            <div class="modal-grid3">
                <div><label class="modal-label">Last Name <span style="color:#ff3e1d;">*</span></label><input class="modal-input" id="edit-last_name" type="text"></div>
                <div><label class="modal-label">First Name <span style="color:#ff3e1d;">*</span></label><input class="modal-input" id="edit-first_name" type="text"></div>
                <div><label class="modal-label">Middle Name</label><input class="modal-input" id="edit-middle_name" type="text"></div>
            </div>
            <div class="modal-grid3">
                <div>
                    <label class="modal-label">Birthdate</label>
                    <input class="modal-input" id="edit-date_of_birth" type="date" onchange="calcAge('edit')">
                </div>
                <div>
                    <label class="modal-label">Age</label>
                    <input class="modal-input" id="edit-age" type="number" min="3" max="25" placeholder="Age" readonly style="background:#f3f3ff;color:#696cff;font-weight:600;">
                </div>
                <div>
                    <label class="modal-label">Gender</label>
                    <select class="modal-input" id="edit-gender">
                        <option value="">— Select —</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            <div class="modal-field">
                <label class="modal-label">Address</label>
                <input class="modal-input" id="edit-address" type="text">
            </div>

            <div class="form-section"><i class="fa-solid fa-school"></i> Academic Information</div>
            <div class="modal-grid3">
                <div>
                    <label class="modal-label">Grade Level <span style="color:#ff3e1d;">*</span></label>
                    <select class="modal-input" id="edit-grade_level">
                        <option value="">— Grade —</option>
                        <option value="Kinder">Kinder</option>
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                        <option value="Grade 7">Grade 7</option>
                        <option value="Grade 8">Grade 8</option>
                        <option value="Grade 9">Grade 9</option>
                        <option value="Grade 10">Grade 10</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                    </select>
                </div>
                <div><label class="modal-label">Section</label><input class="modal-input" id="edit-section" type="text"></div>
                <div><label class="modal-label">School Year</label><input class="modal-input" id="edit-school_year" type="text"></div>
            </div>

            <div class="form-section"><i class="fa-solid fa-right-left"></i> Student Classification</div>
            <div class="modal-grid2">
                <div>
                    <label class="modal-label">Status <span style="color:#ff3e1d;">*</span></label>
                    <select class="modal-input" id="edit-status">
                        <option value="">— Select Status —</option>
                        <option value="Enrolled">Enrolled</option>
                        <option value="Pending">Pending</option>
                        <option value="Withdrawn">Withdrawn</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Transferred In</label>
                    <select class="modal-input" id="edit-transferred_in">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
            <div class="modal-grid3">
                <div>
                    <label class="modal-label">Transferred Out</label>
                    <select class="modal-input" id="edit-transferred_out">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Balik-Aral</label>
                    <select class="modal-input" id="edit-balik_aral">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Repeater</label>
                    <select class="modal-input" id="edit-repeater">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>

            <div class="form-section"><i class="fa-solid fa-people-roof"></i> Parent / Guardian</div>
            <div class="modal-grid2">
                <div><label class="modal-label">Parent / Guardian Name</label><input class="modal-input" id="edit-parent_guardian" type="text"></div>
                <div><label class="modal-label">Contact Number</label><input class="modal-input" id="edit-contact_number" type="text"></div>
            </div>

            <div class="form-section"><i class="fa-solid fa-user-check"></i> Registration Details</div>
            <div class="modal-grid2">
                <div>
                    <label class="modal-label">Encoded By</label>
                    <select class="modal-input" id="edit-encoded_by">
                        <option value="">— Select Staff —</option>
                        <?php foreach ($associates as $a):
                            $rl = ucfirst($a['role'] ?? '');
                            $ai = $a['associate_id'] ? ' · '.$a['associate_id'] : ''; ?>
                        <option value="<?= htmlspecialchars($a['full_name']) ?>">
                            <?= htmlspecialchars($a['full_name'].$ai) ?> (<?= htmlspecialchars($rl) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="modal-label">Remarks</label>
                    <input class="modal-input" id="edit-remarks" type="text">
                </div>
            </div>
        </div>
        <div class="ce-modal-footer">
            <button class="btn-modal-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn-modal-submit" style="background:linear-gradient(135deg,#ff9f43,#e08d34);" onclick="submitEditRecord()"><i class="fa fa-save"></i> Update Student</button>
        </div>
    </div>
</div>

<!-- ════ DELETE MODAL ════ -->
<div class="del-modal-overlay" id="del-modal">
    <div class="del-modal">
        <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="del-title">Delete Student Record?</div>
        <div class="del-sub">You are about to permanently delete <strong id="del-name"></strong>. This action cannot be undone.</div>
        <div class="del-btns">
            <button class="btn-cancel-del" onclick="closeDelModal()">Cancel</button>
            <button class="btn-confirm-del" onclick="confirmDelete()"><i class="fa fa-trash"></i> Delete</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════ VIEW MODAL ════ -->
<div class="view-modal-overlay" id="view-modal">
    <div class="view-modal">
        <div class="view-modal-header">
            <div class="view-icon-wrap"><i class="fa-solid fa-user-graduate"></i></div>
            <div>
                <div class="view-name" id="view-full-name">—</div>
                <div class="view-sub" id="view-student-number">—</div>
            </div>
        </div>
        <div class="view-grid">
            <div class="view-field full"><label>LRN</label><span id="vw-lrn">—</span></div>
            <div class="view-field"><label>Grade Level</label><span id="vw-grade_level">—</span></div>
            <div class="view-field"><label>Section</label><span id="vw-section">—</span></div>
            <div class="view-field"><label>School Year</label><span id="vw-school_year">—</span></div>
            <div class="view-field"><label>Gender</label><span id="vw-gender">—</span></div>
            <div class="view-field"><label>Birthdate</label><span id="vw-date_of_birth">—</span></div>
            <div class="view-field"><label>Age</label><span id="vw-age">—</span></div>
            <div class="view-field"><label>Status</label><span id="vw-status">—</span></div>
            <div class="view-field full"><label>Address</label><span id="vw-address">—</span></div>
            <div class="view-field"><label>Transferred In</label><span id="vw-transferred_in">—</span></div>
            <div class="view-field"><label>Transferred Out</label><span id="vw-transferred_out">—</span></div>
            <div class="view-field"><label>Balik-Aral</label><span id="vw-balik_aral">—</span></div>
            <div class="view-field"><label>Repeater</label><span id="vw-repeater">—</span></div>
            <div class="view-field"><label>Parent / Guardian</label><span id="vw-parent_guardian">—</span></div>
            <div class="view-field"><label>Contact Number</label><span id="vw-contact_number">—</span></div>
            <div class="view-field"><label>Encoded By</label><span id="vw-encoded_by">—</span></div>
            <div class="view-field"><label>Date Encoded</label><span id="vw-date_encoded">—</span></div>
            <div class="view-field full"><label>Remarks</label><span id="vw-remarks">—</span></div>
        </div>
        <div class="view-modal-footer">
            <button class="btn-close-view" onclick="closeViewModal()">Close</button>
            <?php if ($isAdmin): ?>
            <button class="btn-edit-view" onclick="openEditFromView()"><i class="fa fa-pen"></i> Edit</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════ RESULT MODAL ════ -->
<div class="result-overlay" id="result-modal">
    <div class="result-box">
        <div class="result-icon-wrap" id="result-icon-wrap">
            <i id="result-icon" class="fa-solid fa-circle-check"></i>
        </div>
        <div class="result-title" id="result-title">Success</div>
        <div class="result-msg"   id="result-msg"></div>
        <div class="result-detail" id="result-detail" style="display:none;"></div>
        <button class="btn-result-ok" id="result-ok-btn" onclick="closeResultModal()">OK</button>
    </div>
</div>

<!-- ════ SCRIPT ════ -->
<script>
    /* ─ Raw Data from PHP ─ */
    let allRecords = <?= json_encode($allRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    /* ─ Pagination & Filter State ─ */
    let curPage = 1, perPage = 10, activeFilter = null;
    let _pendingDeleteId = null, _pendingDeleteName = null, _viewRecordId = null;

    /* ─ Helpers ─ */
    function esc(v) { return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function dash(v) { return v ? esc(v) : '—'; }

    function statusBadge(s) {
        const sl = (s ?? '').toLowerCase();
        if (sl === 'enrolled')  return `<span class="badge badge-enrolled"><i class="fa-solid fa-circle-check" style="font-size:9px;"></i> ${esc(s)}</span>`;
        if (sl === 'pending')   return `<span class="badge badge-pending"><i class="fa-solid fa-clock" style="font-size:9px;"></i> ${esc(s)}</span>`;
        if (sl === 'withdrawn') return `<span class="badge badge-withdrawn"><i class="fa-solid fa-user-minus" style="font-size:9px;"></i> ${esc(s)}</span>`;
        return s ? `<span class="badge badge-default">${esc(s)}</span>` : '—';
    }

    function yesNoBadge(v) {
        if ((v ?? '').toLowerCase() === 'yes') return `<span class="badge" style="background:#28c76f18;color:#28c76f;font-size:11px;">Yes</span>`;
        if ((v ?? '').toLowerCase() === 'no')  return `<span class="badge badge-default" style="font-size:11px;">No</span>`;
        return '—';
    }

    function calcAge(prefix) {
        const dob = document.getElementById(prefix + '-date_of_birth').value;
        if (!dob) return;
        const diff = Date.now() - new Date(dob).getTime();
        const age  = Math.floor(diff / (365.25 * 24 * 3600 * 1000));
        const el   = document.getElementById(prefix + '-age');
        if (el) el.value = age >= 0 ? age : '';
    }

    /* ─ Filter ─ */
    function getFiltered() {
        const q = document.getElementById('table-search').value.trim().toLowerCase();
        return allRecords.filter(r => {
            const matchSearch = !q || Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q));
            let matchFilter = true;
            if (activeFilter === 'enrolled')  matchFilter = (r.status ?? '').toLowerCase() === 'enrolled';
            if (activeFilter === 'pending')   matchFilter = (r.status ?? '').toLowerCase() === 'pending';
            if (activeFilter === 'withdrawn') matchFilter = (r.status ?? '').toLowerCase() === 'withdrawn';
            if (activeFilter === 'month')     matchFilter = (r.date_encoded ?? '').startsWith(new Date().toISOString().slice(0,7));
            return matchSearch && matchFilter;
        });
    }

    function filterByCard(type) {
        activeFilter = (activeFilter === type) ? null : type;
        curPage = 1;
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
        if (activeFilter) {
            const map = { total:'sc-total', enrolled:'sc-enrolled', pending:'sc-pending', withdrawn:'sc-withdrawn', month:'sc-month' };
            if (map[activeFilter]) document.getElementById(map[activeFilter])?.classList.add('active-filter');
        }
        renderTable();
    }

    /* ─ Render ─ */
    function renderTable() {
        const filtered = getFiltered();
        const total    = filtered.length;
        const pages    = Math.max(1, Math.ceil(total / perPage));
        if (curPage > pages) curPage = pages;
        const start    = (curPage - 1) * perPage;
        const slice    = filtered.slice(start, start + perPage);
        const isAdmin  = <?= $isAdmin ? 'true' : 'false' ?>;

        const tbody = document.getElementById('recordTableBody');
        if (!slice.length) {
            tbody.innerHTML = `<tr><td colspan="16" style="text-align:center;color:#a0a8b5;padding:40px 0;font-size:13px;"><i class="fa-solid fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>No records found.</td></tr>`;
        } else {
            tbody.innerHTML = slice.map(r => `
                <tr>
                    <td><input type="checkbox" class="cb row-cb" data-id="${r.record_id}"></td>
                    <td><span class="sn-badge">${esc(r.student_number)}</span></td>
                    <td>${dash(r.lrn)}</td>
                    <td><strong>${dash(r.last_name)}</strong></td>
                    <td>${dash(r.first_name)}</td>
                    <td>${dash(r.grade_level)}</td>
                    <td>${dash(r.section)}</td>
                    <td>${dash(r.date_of_birth)}</td>
                    <td>${dash(r.age)}</td>
                    <td>${dash(r.gender)}</td>
                    <td>${statusBadge(r.status)}</td>
                    <td>${yesNoBadge(r.transferred_in)}</td>
                    <td>${yesNoBadge(r.transferred_out)}</td>
                    <td>${yesNoBadge(r.balik_aral)}</td>
                    <td>${yesNoBadge(r.repeater)}</td>
                    <td>
                        <div class="action-cell">
                            <button class="action-btn view-btn" title="View" onclick="openViewModal(${r.record_id})"><i class="fa-solid fa-eye"></i></button>
                            ${isAdmin ? `<button class="action-btn edit-btn" title="Edit" onclick="openEditModal(${r.record_id})"><i class="fa-solid fa-pen"></i></button>` : ''}
                            ${isAdmin ? `<button class="action-btn del" title="Delete" onclick="openDelModal(${r.record_id}, '${esc(r.full_name || (r.last_name+' '+r.first_name))}')"><i class="fa-solid fa-trash"></i></button>` : ''}
                        </div>
                    </td>
                </tr>`).join('');
        }

        document.getElementById('page-info').textContent = total
            ? `Showing ${start+1} to ${Math.min(start+perPage, total)} of ${total} entries`
            : 'No entries';

        const pb = document.getElementById('page-btns');
        pb.innerHTML = '';
        const mkBtn = (lbl, pg, dis, active) => {
            const b = document.createElement('button');
            b.className = 'page-btn' + (active ? ' active' : '');
            b.innerHTML = lbl; b.disabled = dis;
            b.onclick = () => { curPage = pg; renderTable(); };
            pb.appendChild(b);
        };
        mkBtn('<i class="fa fa-chevron-left"></i>', curPage-1, curPage===1, false);
        for (let p=1; p<=pages; p++) {
            if (pages>7 && p>2 && p<pages-1 && Math.abs(p-curPage)>1) { if (p===3||p===pages-2) pb.insertAdjacentHTML('beforeend','<span style="padding:0 4px;color:#a0a8b5;">…</span>'); continue; }
            mkBtn(p, p, false, p===curPage);
        }
        mkBtn('<i class="fa fa-chevron-right"></i>', curPage+1, curPage===pages, false);

        /* update stat counts */
        document.getElementById('stat-total').textContent    = allRecords.length;
        document.getElementById('stat-enrolled').textContent  = allRecords.filter(r=>(r.status??'').toLowerCase()==='enrolled').length;
        document.getElementById('stat-pending').textContent   = allRecords.filter(r=>(r.status??'').toLowerCase()==='pending').length;
        document.getElementById('stat-withdrawn').textContent = allRecords.filter(r=>(r.status??'').toLowerCase()==='withdrawn').length;
        const ym = new Date().toISOString().slice(0,7);
        document.getElementById('stat-month').textContent    = allRecords.filter(r=>(r.date_encoded??'').startsWith(ym)).length;
    }

    function perPageChanged() { perPage = parseInt(document.getElementById('per-page').value); curPage=1; renderTable(); }
    function toggleAll(cb)    { document.querySelectorAll('.row-cb').forEach(c => c.checked = cb.checked); }

    /* ─ View Modal ─ */
    function openViewModal(id) {
        const r = allRecords.find(x => x.record_id == id); if (!r) return;
        _viewRecordId = id;
        document.getElementById('view-full-name').textContent    = r.full_name || ((r.last_name||'') + ', ' + (r.first_name||''));
        document.getElementById('view-student-number').textContent = r.student_number || '—';
        ['lrn','grade_level','section','school_year','gender','date_of_birth','age','status','address','transferred_in','transferred_out','balik_aral','repeater','parent_guardian','contact_number','encoded_by','date_encoded','remarks']
            .forEach(f => { const el=document.getElementById('vw-'+f); if(el) el.textContent=r[f]||'—'; });
        document.getElementById('view-modal').classList.add('open');
    }
    function closeViewModal() { document.getElementById('view-modal').classList.remove('open'); }
    function openEditFromView() { closeViewModal(); if(_viewRecordId) openEditModal(_viewRecordId); }
    document.getElementById('view-modal')?.addEventListener('click', function(e){ if(e.target===this) closeViewModal(); });

    /* ─ Result Modal ─ */
    function showResult(type, title, msg, details=[]) {
        const iw = document.getElementById('result-icon-wrap');
        const ic = document.getElementById('result-icon');
        iw.className = 'result-icon-wrap ' + type;
        ic.className = type==='success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
        document.getElementById('result-title').textContent = title;
        document.getElementById('result-msg').textContent   = msg;
        const det = document.getElementById('result-detail');
        const okb = document.getElementById('result-ok-btn');
        okb.className = 'btn-result-ok' + (type==='error'?' danger':'');
        if (details.length) {
            det.innerHTML = details.map(([l,v])=>`<div class="rd-row"><span class="rd-label">${esc(l)}</span><span>${esc(v||'—')}</span></div>`).join('');
            det.style.display = '';
        } else det.style.display = 'none';
        document.getElementById('result-modal').classList.add('open');
    }
    function closeResultModal() { document.getElementById('result-modal').classList.remove('open'); }

    /* ─ Add Modal ─ */
    function openAddModal() {
        fetch(window.location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'__action=get_next_student_number' })
            .then(r=>r.json()).then(d=>{ if(d.success){ document.getElementById('add-sn-display').textContent=d.student_number; document.getElementById('add-student_number').value=d.student_number; } }).catch(()=>{});
        ['add-lrn','add-last_name','add-first_name','add-middle_name','add-date_of_birth','add-age','add-address','add-section','add-school_year','add-parent_guardian','add-contact_number','add-remarks'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
        ['add-gender','add-grade_level','add-status','add-transferred_in','add-transferred_out','add-balik_aral','add-repeater','add-encoded_by'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
        document.getElementById('add-lrn-hint').style.display='none';
        hideAlert('modal-alert');
        document.getElementById('add-modal').classList.add('open');
    }
    function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); }
    document.getElementById('add-modal')?.addEventListener('click', function(e){ if(e.target===this) closeAddModal(); });

    function submitAddRecord() {
        const sn   = document.getElementById('add-student_number').value.trim();
        const lrn  = document.getElementById('add-lrn').value.trim();
        const ln   = document.getElementById('add-last_name').value.trim();
        const fn   = document.getElementById('add-first_name').value.trim();
        const mi   = document.getElementById('add-middle_name').value.trim();
        const dob  = document.getElementById('add-date_of_birth').value.trim();
        const age  = document.getElementById('add-age').value.trim();
        const gen  = document.getElementById('add-gender').value.trim();
        const gr   = document.getElementById('add-grade_level').value.trim();
        const sec  = document.getElementById('add-section').value.trim();
        const sy   = document.getElementById('add-school_year').value.trim();
        const par  = document.getElementById('add-parent_guardian').value.trim();
        const con  = document.getElementById('add-contact_number').value.trim();
        const addr = document.getElementById('add-address').value.trim();
        const stat = document.getElementById('add-status').value.trim();
        const tin  = document.getElementById('add-transferred_in').value.trim();
        const tout = document.getElementById('add-transferred_out').value.trim();
        const ba   = document.getElementById('add-balik_aral').value.trim();
        const rep  = document.getElementById('add-repeater').value.trim();
        const enc  = document.getElementById('add-encoded_by').value.trim();
        const rmk  = document.getElementById('add-remarks').value.trim();
        hideAlert('modal-alert');
        document.getElementById('add-lrn-hint').style.display='none';
        // Validate LRN
        if (lrn && !/^\d{11}$/.test(lrn)) {
            document.getElementById('add-lrn-hint').style.display='block';
            showAlert('modal-alert','LRN must be exactly 11 digits.'); return;
        }
        if (!sn||!ln||!fn) { showAlert('modal-alert','Last Name and First Name are required.'); return; }
        if (!gr) { showAlert('modal-alert','Grade Level is required.'); return; }
        fetch(window.location.pathname, {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({__action:'add_record',student_number:sn,lrn,last_name:ln,first_name:fn,middle_name:mi,date_of_birth:dob,age,gender:gen,grade_level:gr,section:sec,school_year:sy,parent_guardian:par,contact_number:con,address:addr,status:stat,transferred_in:tin,transferred_out:tout,balik_aral:ba,repeater:rep,encoded_by:enc,remarks:rmk}).toString()
        }).then(r=>r.json()).then(d=>{
            if (d.success) {
                allRecords.unshift(d.record); curPage=1; renderTable(); closeAddModal();
                showResult('success','Student Added!','New student record has been saved.',[['🎓 Student No.',d.record.student_number],['🆔 LRN',d.record.lrn||'—'],['👤 Name',d.record.full_name||'—'],['📚 Grade',d.record.grade_level||'—'],['📋 Status',d.record.status||'—']]);
            } else showAlert('modal-alert', d.message||'Failed to add record.');
        }).catch(()=>showAlert('modal-alert','Network error. Please try again.'));
    }

    /* ─ Edit Modal ─ */
    function openEditModal(id) {
        const r = allRecords.find(x=>x.record_id==id); if(!r) return;
        document.getElementById('edit-record_id').value          = r.record_id;
        document.getElementById('edit-student_number').value     = r.student_number||'';
        document.getElementById('edit-lrn').value                = r.lrn||'';
        document.getElementById('edit-last_name').value          = r.last_name||'';
        document.getElementById('edit-first_name').value         = r.first_name||'';
        document.getElementById('edit-middle_name').value        = r.middle_name||'';
        document.getElementById('edit-date_of_birth').value      = r.date_of_birth||'';
        document.getElementById('edit-age').value                = r.age||'';
        document.getElementById('edit-gender').value             = r.gender||'';
        document.getElementById('edit-grade_level').value        = r.grade_level||'';
        document.getElementById('edit-section').value            = r.section||'';
        document.getElementById('edit-school_year').value        = r.school_year||'';
        document.getElementById('edit-address').value            = r.address||'';
        document.getElementById('edit-status').value             = r.status||'';
        document.getElementById('edit-transferred_in').value     = r.transferred_in||'';
        document.getElementById('edit-transferred_out').value    = r.transferred_out||'';
        document.getElementById('edit-balik_aral').value         = r.balik_aral||'';
        document.getElementById('edit-repeater').value           = r.repeater||'';
        document.getElementById('edit-parent_guardian').value    = r.parent_guardian||'';
        document.getElementById('edit-contact_number').value     = r.contact_number||'';
        document.getElementById('edit-encoded_by').value         = r.encoded_by||'';
        document.getElementById('edit-remarks').value            = r.remarks||'';
        document.getElementById('edit-lrn-hint').style.display   = 'none';
        hideAlert('edit-modal-alert');
        document.getElementById('edit-modal').classList.add('open');
    }
    function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); }
    document.getElementById('edit-modal')?.addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });

    function submitEditRecord() {
        const id   = document.getElementById('edit-record_id').value;
        const sn   = document.getElementById('edit-student_number').value.trim();
        const lrn  = document.getElementById('edit-lrn').value.trim();
        const ln   = document.getElementById('edit-last_name').value.trim();
        const fn   = document.getElementById('edit-first_name').value.trim();
        const mi   = document.getElementById('edit-middle_name').value.trim();
        const dob  = document.getElementById('edit-date_of_birth').value.trim();
        const age  = document.getElementById('edit-age').value.trim();
        const gen  = document.getElementById('edit-gender').value.trim();
        const gr   = document.getElementById('edit-grade_level').value.trim();
        const sec  = document.getElementById('edit-section').value.trim();
        const sy   = document.getElementById('edit-school_year').value.trim();
        const par  = document.getElementById('edit-parent_guardian').value.trim();
        const con  = document.getElementById('edit-contact_number').value.trim();
        const addr = document.getElementById('edit-address').value.trim();
        const stat = document.getElementById('edit-status').value.trim();
        const tin  = document.getElementById('edit-transferred_in').value.trim();
        const tout = document.getElementById('edit-transferred_out').value.trim();
        const ba   = document.getElementById('edit-balik_aral').value.trim();
        const rep  = document.getElementById('edit-repeater').value.trim();
        const enc  = document.getElementById('edit-encoded_by').value.trim();
        const rmk  = document.getElementById('edit-remarks').value.trim();
        hideAlert('edit-modal-alert');
        document.getElementById('edit-lrn-hint').style.display='none';
        if (lrn && !/^\d{11}$/.test(lrn)) {
            document.getElementById('edit-lrn-hint').style.display='block';
            showAlert('edit-modal-alert','LRN must be exactly 11 digits.'); return;
        }
        if (!id||!sn||!ln||!fn) { showAlert('edit-modal-alert','Required fields are missing.'); return; }
        if (!gr) { showAlert('edit-modal-alert','Grade Level is required.'); return; }
        fetch(window.location.pathname, {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({__action:'edit_record',id,student_number:sn,lrn,last_name:ln,first_name:fn,middle_name:mi,date_of_birth:dob,age,gender:gen,grade_level:gr,section:sec,school_year:sy,parent_guardian:par,contact_number:con,address:addr,status:stat,transferred_in:tin,transferred_out:tout,balik_aral:ba,repeater:rep,encoded_by:enc,remarks:rmk}).toString()
        }).then(r=>r.json()).then(d=>{
            if (d.success) {
                const idx=allRecords.findIndex(r=>r.record_id==id); if(idx!==-1) allRecords[idx]={...allRecords[idx],...d.record}; renderTable(); closeEditModal();
                showResult('success','Record Updated!','The student record has been updated.',[['🎓 Student No.',d.record.student_number],['🆔 LRN',d.record.lrn||'—'],['👤 Name',d.record.full_name||'—'],['📚 Grade',d.record.grade_level||'—']]);
            } else showAlert('edit-modal-alert', d.message||'Failed to update.');
        }).catch(()=>showAlert('edit-modal-alert','Network error.'));
    }

    /* ─ Delete Modal ─ */
    function openDelModal(id, name) { _pendingDeleteId=id; _pendingDeleteName=name; document.getElementById('del-name').textContent=name; document.getElementById('del-modal').classList.add('open'); }
    function closeDelModal() { document.getElementById('del-modal').classList.remove('open'); _pendingDeleteId=_pendingDeleteName=null; }
    function confirmDelete() {
        if (!_pendingDeleteId) return; const dn=_pendingDeleteName;
        fetch(window.location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`__action=delete_record&id=${_pendingDeleteId}` })
            .then(r=>r.json()).then(d=>{
                const delId = _pendingDeleteId; closeDelModal();
                if (d.success) { allRecords=allRecords.filter(r=>r.record_id!=delId); renderTable(); showResult('success','Deleted',`"${dn}" has been permanently removed.`); }
                else showResult('error','Failed',d.message||'Could not delete.');
            }).catch(()=>{ closeDelModal(); showResult('error','Network Error','Could not reach the server.'); });
    }

    /* ─ Alert helpers ─ */
    function showAlert(elId, msg) { const el=document.getElementById(elId); if(!el) return; el.innerHTML=`<i class="fa-solid fa-circle-exclamation"></i> ${msg}`; el.style.display='flex'; }
    function hideAlert(elId)      { const el=document.getElementById(elId); if(el) el.style.display='none'; }
    function showToast(msg, type='success') { const t=document.getElementById('toast'); t.textContent=msg; t.className=`toast ${type} show`; clearTimeout(t._t); t._t=setTimeout(()=>t.classList.remove('show'),3200); }

    /* ─ Export CSV ─ */
    function exportCSV() {
        const rows = [['Record ID','Student Number','LRN','Last Name','First Name','Middle Name','Birthdate','Age','Gender','Grade Level','Section','School Year','Address','Status','Transferred In','Transferred Out','Balik-Aral','Repeater','Parent/Guardian','Contact Number','Encoded By','Date Encoded','Remarks']];
        getFiltered().forEach(r => rows.push([r.record_id,r.student_number||'',r.lrn||'',r.last_name||'',r.first_name||'',r.middle_name||'',r.date_of_birth||'',r.age||'',r.gender||'',r.grade_level||'',r.section||'',r.school_year||'',r.address||'',r.status||'',r.transferred_in||'',r.transferred_out||'',r.balik_aral||'',r.repeater||'',r.parent_guardian||'',r.contact_number||'',r.encoded_by||'',r.date_encoded||'',r.remarks||'']));
        const csv = rows.map(r => r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
        const a = document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download='smes_student_records.csv'; a.click();
        showToast('Exported!','success');
    }

    function toggleAcc(id) { document.getElementById(id).classList.toggle('open'); }

    renderTable();
</script>
</body>
</html>