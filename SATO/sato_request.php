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

        case 'add_record':
            if (!$isAdminAjax) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $pn = trim($_POST['printer_name'] ?? '');
            $sn = trim($_POST['section_name'] ?? '');
            $pic = trim($_POST['pic'] ?? '') ?: null;
            $dd = trim($_POST['date_deployed'] ?? '') ?: null;
            $ser = trim($_POST['serial_number'] ?? '') ?: null;
            $mdl = trim($_POST['model'] ?? '') ?: null;
            $ps = trim($_POST['print_speed'] ?? '') ?: null;
            $pd = trim($_POST['print_darkness'] ?? '') ?: null;
            $ov = trim($_POST['offset_val'] ?? '') ?: null;
            $hs = trim($_POST['h_settings'] ?? '') ?: null;
            $vs = trim($_POST['v_settings'] ?? '') ?: null;
            $ip = trim($_POST['ip_address'] ?? '') ?: null;
            $st = trim($_POST['status'] ?? 'Active');
            if (!$pn || !$sn) {
                echo json_encode(['success' => false, 'message' => 'Printer Name and Section are required.']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO sato_printers (printer_name,section_name,pic,date_deployed,serial_number,model,print_speed,print_darkness,offset_val,h_settings,v_settings,ip_address,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$pn, $sn, $pic, $dd, $ser, $mdl, $ps, $pd, $ov, $hs, $vs, $ip, $st]);
            $newId = (int) $pdo->lastInsertId();
            echo json_encode(['success' => true, 'record' => ['id' => $newId, 'printer_name' => $pn, 'section_name' => $sn, 'pic' => $pic, 'date_deployed' => $dd, 'serial_number' => $ser, 'model' => $mdl, 'print_speed' => $ps, 'print_darkness' => $pd, 'offset_val' => $ov, 'h_settings' => $hs, 'v_settings' => $vs, 'ip_address' => $ip, 'status' => $st]]);
            exit;

        case 'edit_record':
            if (!$isAdminAjax) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            $id = (int) ($_POST['id'] ?? 0);
            $pn = trim($_POST['printer_name'] ?? '');
            $sn = trim($_POST['section_name'] ?? '');
            $pic = trim($_POST['pic'] ?? '') ?: null;
            $dd = trim($_POST['date_deployed'] ?? '') ?: null;
            $ser = trim($_POST['serial_number'] ?? '') ?: null;
            $mdl = trim($_POST['model'] ?? '') ?: null;
            $ps = trim($_POST['print_speed'] ?? '') ?: null;
            $pd = trim($_POST['print_darkness'] ?? '') ?: null;
            $ov = trim($_POST['offset_val'] ?? '') ?: null;
            $hs = trim($_POST['h_settings'] ?? '') ?: null;
            $vs = trim($_POST['v_settings'] ?? '') ?: null;
            $ip = trim($_POST['ip_address'] ?? '') ?: null;
            $st = trim($_POST['status'] ?? 'Active');
            if (!$id || !$pn || !$sn) {
                echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE sato_printers SET printer_name=?,section_name=?,pic=?,date_deployed=?,serial_number=?,model=?,print_speed=?,print_darkness=?,offset_val=?,h_settings=?,v_settings=?,ip_address=?,status=? WHERE id=?');
            $stmt->execute([$pn, $sn, $pic, $dd, $ser, $mdl, $ps, $pd, $ov, $hs, $vs, $ip, $st, $id]);
            echo json_encode(['success' => true, 'record' => ['id' => $id, 'printer_name' => $pn, 'section_name' => $sn, 'pic' => $pic, 'date_deployed' => $dd, 'serial_number' => $ser, 'model' => $mdl, 'print_speed' => $ps, 'print_darkness' => $pd, 'offset_val' => $ov, 'h_settings' => $hs, 'v_settings' => $vs, 'ip_address' => $ip, 'status' => $st]]);
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
            $pdo->prepare('DELETE FROM sato_printers WHERE id=?')->execute([$id]);
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

$allRecords = $pdo->query('SELECT id,printer_name,section_name,pic,date_deployed,serial_number,model,print_speed,print_darkness,offset_val,h_settings,v_settings,ip_address,status,created_at FROM sato_printers ORDER BY id DESC')->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────
$totalRecords = count($allRecords);
$activeRecords = count(array_filter($allRecords, fn($r) => strtolower($r['status'] ?? '') === 'active'));
$inactiveRecords = count(array_filter($allRecords, fn($r) => strtolower($r['status'] ?? '') !== 'active'));
$todayRecords = count(array_filter($allRecords, fn($r) => substr($r['created_at'] ?? '', 0, 10) === date('Y-m-d')));
$thisMonth = count(array_filter($allRecords, fn($r) => strpos($r['date_deployed'] ?? '', date('Y-m')) === 0));

// Unique models count
$models = array_unique(array_filter(array_column($allRecords, 'model')));
$modelCount = count($models);

// ── Users for PIC dropdown ────────────────────────────────────────────
$associates = [];
try {
    $associates = $pdo->query("SELECT id,full_name,username,role,associate_id FROM users WHERE status='active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    try {
        $associates = $pdo->query("SELECT id,full_name,username,role,associate_id FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e2) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sato Printer List – System</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/userlist.css">
    <style>
        /* ── accent: orange/amber #f59e0b ── */
        :root {
            --acc: #f59e0b;
            --acc2: #d97706;
            --acc-bg: #f59e0b12;
            --acc-dark: #92400e;
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

        .stat-card.active-filter.fc-red {
            border-color: #ff3e1d;
            box-shadow: 0 0 0 3px #ff3e1d22;
        }

        .stat-card.active-filter.fc-blue {
            border-color: #696cff;
            box-shadow: 0 0 0 3px #696cff22;
        }

        .stat-card.active-filter.fc-purple {
            border-color: #9c27b0;
            box-shadow: 0 0 0 3px #9c27b022;
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

        .stat-icon.amber {
            background: var(--acc-bg);
            color: var(--acc);
        }

        .stat-icon.green {
            background: #28c76f12;
            color: #28c76f;
        }

        .stat-icon.red {
            background: #ff3e1d12;
            color: #ff3e1d;
        }

        .stat-icon.blue {
            background: #696cff12;
            color: #696cff;
        }

        .stat-icon.purple {
            background: #9c27b012;
            color: #9c27b0;
        }

        .stat-icon.teal {
            background: #00bcd412;
            color: #00bcd4;
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

        .action-btn.hist-btn:hover {
            background: #e0f2fe;
            color: #0284c7;
        }

        /* Status badge */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .badge-status.active {
            background: #28c76f18;
            color: #28c76f;
        }

        .badge-status.inactive {
            background: #ff3e1d12;
            color: #ff3e1d;
        }

        .badge-status.maintenance {
            background: #ff9f4318;
            color: #ff9f43;
        }

        /* Printer name badge */
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

        /* Settings chips */
        .setting-chip {
            display: inline-block;
            background: #f0f1f5;
            color: #4a5568;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            min-width: 28px;
            text-align: center;
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

        /* ── Modals ── */
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
            max-width: 620px;
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
            padding: 24px 28px 10px;
        }

        .form-section {
            font-size: 11px;
            font-weight: 700;
            color: var(--acc2);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin: 18px 0 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #fef3c7;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-section:first-child {
            margin-top: 0;
        }

        .modal-label {
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
            display: block;
        }

        .modal-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13.5px;
            color: #2d3a4a;
            background: #f8fafc;
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
            font-family: inherit;
        }

        .modal-input:focus {
            outline: none;
            border-color: var(--acc);
            box-shadow: 0 0 0 3px var(--acc-bg);
            background: #fff;
        }

        .modal-grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .modal-grid3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .modal-field {
            margin-bottom: 14px;
        }

        .modal-alert {
            display: none;
            background: #fff0ee;
            border: 1px solid #ffd0c8;
            color: #c0392b;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
            gap: 8px;
            align-items: center;
        }

        .ce-modal-footer {
            padding: 14px 28px 22px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #f0f1f5;
        }

        .btn-modal-cancel {
            background: #f0f1f5;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-modal-submit {
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .btn-modal-cancel:hover,
        .btn-modal-submit:hover {
            opacity: .88;
        }

        /* ── View Modal ── */
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
            max-width: 660px;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            animation: vmIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .view-modal-header {
            background: linear-gradient(135deg, var(--acc), var(--acc2));
            padding: 22px 28px 18px;
            border-radius: 16px 16px 0 0;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #fff;
        }

        .view-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .view-name {
            font-size: 17px;
            font-weight: 700;
        }

        .view-sub {
            font-size: 12px;
            opacity: .8;
            margin-top: 2px;
        }

        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            padding: 0 28px 10px;
        }

        .view-field {
            padding: 13px 0;
            border-bottom: 1px solid #f0f1f5;
        }

        .view-field.full {
            grid-column: 1 / -1;
        }

        .view-field label {
            font-size: 11px;
            font-weight: 700;
            color: var(--acc2);
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 4px;
        }

        .view-field span {
            font-size: 13.5px;
            color: #2d3a4a;
            font-weight: 500;
        }

        .view-modal-footer {
            padding: 14px 28px 22px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #f0f1f5;
        }

        .btn-close-view {
            background: #f0f1f5;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #5a6070;
            cursor: pointer;
        }

        .btn-edit-view {
            background: linear-gradient(135deg, #ff9f43, #f7b731);
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .btn-close-view:hover,
        .btn-edit-view:hover {
            opacity: .88;
        }

        /* Settings grid in view */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px;
        }

        .settings-item {
            text-align: center;
        }

        .settings-item .s-label {
            font-size: 10px;
            color: #9aa3b0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .settings-item .s-val {
            font-size: 18px;
            font-weight: 700;
            color: var(--acc2);
            margin-top: 3px;
        }

        /* ── Delete Modal ── */
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
            padding: 40px 32px 30px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .2);
            animation: vmIn .35s cubic-bezier(.34, 1.56, .64, 1);
        }

        .del-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #ff3e1d12;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #ff3e1d;
            margin: 0 auto 18px;
        }

        .del-title {
            font-size: 20px;
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

        /* ── Result Modal ── */
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
            min-width: 120px;
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

        /* IP address style */
        .ip-chip {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #5a6070;
            background: #f0f1f5;
            padding: 2px 8px;
            border-radius: 5px;
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
                        <li><a class="nav-sub-link active" href="sato_request.php">List of Printers</a></li>
                        <li><a class="nav-sub-link" href="printerhistory.php">Printer History</a></li>
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
                <a href="#">Sato Printer</a><span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                <span style="color:var(--text-mid);">List of Printers</span>
            </div>
            <?php if (!$isAdmin): ?>
                <div class="access-notice"><i class="fa-solid fa-circle-info"></i> You are logged in as a
                    <strong>User</strong>. Management actions are restricted to Admins only.
                </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="stat-grid">
                <div class="stat-card" id="sc-total" onclick="filterByCard('total')" data-tip="Show all">
                    <div>
                        <div class="stat-label">Total</div>
                        <div class="stat-value" id="stat-total"><?= $totalRecords ?></div>
                        <div class="stat-sub">All Printers</div>
                    </div>
                    <div class="stat-icon amber"><i class="fa-solid fa-print"></i></div>
                </div>
                <div class="stat-card fc-green" id="sc-active" onclick="filterByCard('active')"
                    data-tip="Filter: Active">
                    <div>
                        <div class="stat-label">Active</div>
                        <div class="stat-value" id="stat-active"><?= $activeRecords ?></div>
                        <div class="stat-sub">Operational</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-card fc-red" id="sc-inactive" onclick="filterByCard('inactive')"
                    data-tip="Filter: Inactive">
                    <div>
                        <div class="stat-label">Inactive</div>
                        <div class="stat-value" id="stat-inactive"><?= $inactiveRecords ?></div>
                        <div class="stat-sub">Offline / Retired</div>
                    </div>
                    <div class="stat-icon red"><i class="fa-solid fa-circle-xmark"></i></div>
                </div>
                <div class="stat-card fc-blue" id="sc-today" onclick="filterByCard('today')"
                    data-tip="Filter: Added Today">
                    <div>
                        <div class="stat-label">Today</div>
                        <div class="stat-value" id="stat-today"><?= $todayRecords ?></div>
                        <div class="stat-sub">Added today</div>
                    </div>
                    <div class="stat-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                </div>
                <div class="stat-card fc-purple" id="sc-month" onclick="filterByCard('month')"
                    data-tip="Filter: Deployed This Month">
                    <div>
                        <div class="stat-label">This Month</div>
                        <div class="stat-value" id="stat-month"><?= $thisMonth ?></div>
                        <div class="stat-sub">Deployed <?= date('M Y') ?></div>
                    </div>
                    <div class="stat-icon purple"><i class="fa-solid fa-calendar-week"></i></div>
                </div>
                <div class="stat-card" id="sc-models" onclick="filterByCard('total')" data-tip="Unique models">
                    <div>
                        <div class="stat-label">Models</div>
                        <div class="stat-value" id="stat-models"><?= $modelCount ?></div>
                        <div class="stat-sub">Unique models</div>
                    </div>
                    <div class="stat-icon teal"><i class="fa-solid fa-tags"></i></div>
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
                            placeholder="Search Printer" oninput="renderTable()"></div>
                    <button class="btn btn-outline" onclick="exportCSV()"><i class="fa fa-download"></i> Export</button>
                    <?php if ($isAdmin): ?><button class="btn btn-primary"
                            style="background:linear-gradient(135deg,var(--acc),var(--acc2));" onclick="openAddModal()"><i
                                class="fa fa-plus"></i> Add Printer</button><?php endif; ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="cb" id="select-all" onchange="toggleAll(this)"></th>
                            <th>Printer Name</th>
                            <th>Section</th>
                            <th>PIC</th>
                            <th>Model</th>
                            <th>Serial No.</th>
                            <th>IP Address</th>
                            <th>Speed</th>
                            <th>Darkness</th>
                            <th>Status</th>
                            <th>Date Deployed</th>
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
        <!-- ════ ADD MODAL ════ -->
        <div class="ce-modal-overlay" id="add-modal">
            <div class="ce-modal">
                <div class="ce-modal-header" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <div class="ce-modal-header-icon"><i class="fa-solid fa-print"></i></div>
                    <div>
                        <h3>Add Sato Printer</h3>
                        <p>Fill in the printer details below.</p>
                    </div>
                </div>
                <div class="ce-modal-body">
                    <div id="modal-alert" class="modal-alert"></div>

                    <div class="form-section"><i class="fa-solid fa-print"></i> Printer Info</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Printer Name <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="add-printer_name" type="text" placeholder="e.g. BX65"></div>
                        <div><label class="modal-label">Section <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="add-section_name" type="text" placeholder="e.g. Plant 2 NNP"></div>
                    </div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Model</label><input class="modal-input" id="add-model" type="text"
                                placeholder="e.g. CL4NX plus"></div>
                        <div><label class="modal-label">Serial Number</label><input class="modal-input"
                                id="add-serial_number" type="text" placeholder="e.g. GG103295"></div>
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
                        <div><label class="modal-label">Date Deployed</label><input class="modal-input"
                                id="add-date_deployed" type="date"></div>
                    </div>

                    <div class="form-section"><i class="fa-solid fa-sliders"></i> Print Settings</div>
                    <div class="modal-grid3">
                        <div><label class="modal-label">Print Speed</label><input class="modal-input" id="add-print_speed"
                                type="number" min="1" max="20" placeholder="4" style="text-align:center;"></div>
                        <div><label class="modal-label">Print Darkness</label><input class="modal-input"
                                id="add-print_darkness" type="number" min="0" max="30" placeholder="10"
                                style="text-align:center;"></div>
                        <div><label class="modal-label">Offset Value</label><input class="modal-input" id="add-offset_val"
                                type="number" placeholder="0" style="text-align:center;"></div>
                    </div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">H Settings</label><input class="modal-input" id="add-h_settings"
                                type="number" placeholder="0" style="text-align:center;"></div>
                        <div><label class="modal-label">V Settings</label><input class="modal-input" id="add-v_settings"
                                type="number" placeholder="0" style="text-align:center;"></div>
                    </div>

                    <div class="form-section"><i class="fa-solid fa-network-wired"></i> Network &amp; Status</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">IP Address</label><input class="modal-input" id="add-ip_address"
                                type="text" placeholder="e.g. 10.243.19.20" style="font-family:monospace;"></div>
                        <div>
                            <label class="modal-label">Status</label>
                            <select class="modal-input" id="add-status">
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="ce-modal-footer">
                    <button class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
                    <button class="btn-modal-submit" style="background:linear-gradient(135deg,#f59e0b,#d97706);"
                        onclick="submitAddRecord()"><i class="fa fa-plus"></i> Add Printer</button>
                </div>
            </div>
        </div>

        <!-- ════ EDIT MODAL ════ -->
        <div class="ce-modal-overlay" id="edit-modal">
            <div class="ce-modal">
                <div class="ce-modal-header" style="background:linear-gradient(135deg,#ff9f43,#f7b731);">
                    <div class="ce-modal-header-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div>
                        <h3>Edit Printer</h3>
                        <p>Update the printer's information below.</p>
                    </div>
                </div>
                <div class="ce-modal-body">
                    <div id="edit-modal-alert" class="modal-alert"></div>
                    <input type="hidden" id="edit-record_id">

                    <div class="form-section"><i class="fa-solid fa-print"></i> Printer Info</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Printer Name <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="edit-printer_name" type="text"></div>
                        <div><label class="modal-label">Section <span style="color:#ff3e1d;">*</span></label><input
                                class="modal-input" id="edit-section_name" type="text"></div>
                    </div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">Model</label><input class="modal-input" id="edit-model" type="text">
                        </div>
                        <div><label class="modal-label">Serial Number</label><input class="modal-input"
                                id="edit-serial_number" type="text"></div>
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
                        <div><label class="modal-label">Date Deployed</label><input class="modal-input"
                                id="edit-date_deployed" type="date"></div>
                    </div>

                    <div class="form-section"><i class="fa-solid fa-sliders"></i> Print Settings</div>
                    <div class="modal-grid3">
                        <div><label class="modal-label">Print Speed</label><input class="modal-input" id="edit-print_speed"
                                type="number" min="1" max="20" style="text-align:center;"></div>
                        <div><label class="modal-label">Print Darkness</label><input class="modal-input"
                                id="edit-print_darkness" type="number" min="0" max="30" style="text-align:center;"></div>
                        <div><label class="modal-label">Offset Value</label><input class="modal-input" id="edit-offset_val"
                                type="number" style="text-align:center;"></div>
                    </div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">H Settings</label><input class="modal-input" id="edit-h_settings"
                                type="number" style="text-align:center;"></div>
                        <div><label class="modal-label">V Settings</label><input class="modal-input" id="edit-v_settings"
                                type="number" style="text-align:center;"></div>
                    </div>

                    <div class="form-section"><i class="fa-solid fa-network-wired"></i> Network &amp; Status</div>
                    <div class="modal-grid2">
                        <div><label class="modal-label">IP Address</label><input class="modal-input" id="edit-ip_address"
                                type="text" style="font-family:monospace;"></div>
                        <div>
                            <label class="modal-label">Status</label>
                            <select class="modal-input" id="edit-status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
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

    <!-- ════ VIEW MODAL ════ -->
    <div class="view-modal-overlay" id="view-modal">
        <div class="view-modal">
            <div class="view-modal-header">
                <div class="view-icon-wrap"><i class="fa-solid fa-print"></i></div>
                <div>
                    <div class="view-name" id="view-printer_name">—</div>
                    <div class="view-sub" id="view-section_sub">—</div>
                </div>
            </div>
            <div style="padding:20px 28px 0;">
                <!-- Settings overview -->
                <div class="settings-grid">
                    <div class="settings-item">
                        <div class="s-label">Speed</div>
                        <div class="s-val" id="vset-speed">—</div>
                    </div>
                    <div class="settings-item">
                        <div class="s-label">Darkness</div>
                        <div class="s-val" id="vset-dark">—</div>
                    </div>
                    <div class="settings-item">
                        <div class="s-label">Offset</div>
                        <div class="s-val" id="vset-offset">—</div>
                    </div>
                    <div class="settings-item">
                        <div class="s-label">H Setting</div>
                        <div class="s-val" id="vset-h">—</div>
                    </div>
                    <div class="settings-item">
                        <div class="s-label">V Setting</div>
                        <div class="s-val" id="vset-v">—</div>
                    </div>
                    <div class="settings-item">
                        <div class="s-label">Status</div>
                        <div class="s-val" id="vset-status" style="font-size:13px;">—</div>
                    </div>
                </div>
            </div>
            <div class="view-grid">
                <div class="view-field"><label>Model</label><span id="view-model"></span></div>
                <div class="view-field"><label>Serial Number</label><span id="view-serial_number"></span></div>
                <div class="view-field"><label>IP Address</label><span id="view-ip_address"
                        style="font-family:monospace;"></span></div>
                <div class="view-field"><label>PIC</label><span id="view-pic"></span></div>
                <div class="view-field"><label>Date Deployed</label><span id="view-date_deployed"></span></div>
                <div class="view-field"><label>Date Added</label><span id="view-created_at"></span></div>
            </div>
            <div class="view-modal-footer">
                <button class="btn-close-view" onclick="closeViewModal()">Close</button>
                <?php if ($isAdmin): ?><button class="btn-edit-view" onclick="switchToEdit()"><i class="fa fa-pen"></i>
                        Edit</button><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div class="del-modal-overlay" id="del-modal">
        <div class="del-modal">
            <div class="del-icon-wrap"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="del-title">Delete Printer?</div>
            <div class="del-sub">You are about to delete <strong id="del-name"></strong>.<br>This action cannot be
                undone.</div>
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
            <div class="result-msg" id="result-msg"></div>
            <div class="result-detail" id="result-detail" style="display:none;"></div>
            <button class="btn-result-ok" id="result-ok-btn" onclick="closeResultModal()">OK</button>
        </div>
    </div>
    <div class="toast" id="toast"></div>

    <script>
        const ALL_RECORDS = <?= json_encode(array_values($allRecords)) ?>;
        const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        const TODAY = '<?= date('Y-m-d') ?>';
        const THIS_MONTH = '<?= date('Y-m') ?>';

        let allRecords = [...ALL_RECORDS], curPage = 1, perPage = 10;
        let _pendingDeleteId = null, _pendingDeleteName = null;
        let _currentViewRecord = null, _filterType = null, _activeCardFilter = null;

        /* ── Filter ── */
        function getFiltered() {
            const q = (document.getElementById('table-search')?.value || '').toLowerCase();
            return allRecords.filter(r => {
                const ms = !q || [r.printer_name, r.section_name, r.pic, r.model, r.serial_number, r.ip_address, r.status].some(f => (f || '').toLowerCase().includes(q));
                let mf = true;
                if (_filterType === 'active') mf = (r.status || '').toLowerCase() === 'active';
                if (_filterType === 'inactive') mf = (r.status || '').toLowerCase() !== 'active';
                if (_filterType === 'today') mf = (r.created_at || '').startsWith(TODAY);
                if (_filterType === 'month') mf = (r.date_deployed || '').startsWith(THIS_MONTH);
                return ms && mf;
            });
        }

        function statusBadge(s) {
            const sl = (s || 'active').toLowerCase();
            if (sl === 'active') return `<span class="badge-status active"><i class="fa-solid fa-circle" style="font-size:7px;"></i> Active</span>`;
            if (sl === 'maintenance') return `<span class="badge-status maintenance"><i class="fa-solid fa-wrench" style="font-size:9px;"></i> Maintenance</span>`;
            return `<span class="badge-status inactive"><i class="fa-solid fa-circle" style="font-size:7px;"></i> Inactive</span>`;
        }

        /* ── Table ── */
        function renderTable() {
            const f = getFiltered(), total = f.length, tp = Math.ceil(total / perPage) || 1;
            if (curPage > tp) curPage = tp;
            const start = (curPage - 1) * perPage, paged = f.slice(start, start + perPage);
            document.getElementById('recordTableBody').innerHTML = paged.map(r => `
<tr>
  <td><input type="checkbox" class="cb row-cb" data-id="${r.id}"></td>
  <td><span class="printer-badge"><i class="fa-solid fa-print" style="font-size:10px;"></i> ${r.printer_name || '—'}</span></td>
  <td style="font-size:13px;color:var(--text-mid);">${r.section_name || '—'}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.pic || '—'}</td>
  <td style="font-size:13px;font-weight:600;color:#2d3a4a;">${r.model || '—'}</td>
  <td style="font-size:12px;font-family:monospace;color:#5a6070;">${r.serial_number || '—'}</td>
  <td><span class="ip-chip">${r.ip_address || '—'}</span></td>
  <td style="text-align:center;"><span class="setting-chip">${r.print_speed || '—'}</span></td>
  <td style="text-align:center;"><span class="setting-chip">${r.print_darkness || '—'}</span></td>
  <td>${statusBadge(r.status)}</td>
  <td style="font-size:13px;color:var(--text-mid);">${r.date_deployed || '—'}</td>
  <td><div class="action-cell">
    <button class="action-btn view-btn" title="View" onclick='openViewModal(${r.id})'><i class="fa fa-eye"></i></button>
    <a class="action-btn hist-btn" title="Printer History" href='printerhistory.php?printer=${encodeURIComponent(r.printer_name || "")}'><i class="fa fa-clock-rotate-left"></i></a>
    ${IS_ADMIN ? `<button class="action-btn edit-btn" title="Edit" onclick='openEditModal(${r.id})'><i class="fa fa-pen"></i></button>
    <button class="action-btn del" title="Delete" onclick='openDelModal(${r.id},"${(r.printer_name || "Printer #" + r.id).replace(/"/g, "")}")'><i class="fa fa-trash"></i></button>` : ''}
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
            for (let i = s; i <= e; i++) html += `<button class="page-btn ${i === curPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
            html += `<button class="page-btn ${curPage === tp ? 'disabled' : ''}" onclick="goPage(${curPage + 1})"><i class="fa fa-angle-right"></i></button>`;
            html += `<button class="page-btn ${curPage === tp ? 'disabled' : ''}" onclick="goPage(${tp})"><i class="fa fa-angles-right"></i></button>`;
            c.innerHTML = html;
        }
        function goPage(p) { const t = Math.ceil(getFiltered().length / perPage) || 1; if (p < 1 || p > t) return; curPage = p; renderTable(); }
        function perPageChanged() { perPage = parseInt(document.getElementById('per-page').value); curPage = 1; renderTable(); }

        function updateStats() {
            document.getElementById('stat-total').textContent = allRecords.length;
            document.getElementById('stat-active').textContent = allRecords.filter(r => (r.status || '').toLowerCase() === 'active').length;
            document.getElementById('stat-inactive').textContent = allRecords.filter(r => (r.status || '').toLowerCase() !== 'active').length;
            document.getElementById('stat-today').textContent = allRecords.filter(r => (r.created_at || '').startsWith(TODAY)).length;
            document.getElementById('stat-month').textContent = allRecords.filter(r => (r.date_deployed || '').startsWith(THIS_MONTH)).length;
            const models = [...new Set(allRecords.map(r => r.model).filter(Boolean))];
            document.getElementById('stat-models').textContent = models.length;
        }

        function filterByCard(type) {
            if (_activeCardFilter === type || type === 'total') { _filterType = null; _activeCardFilter = null; _highlightCard(null); curPage = 1; renderTable(); return; }
            _filterType = type; _activeCardFilter = type; _highlightCard(type); curPage = 1; renderTable();
        }
        function _highlightCard(type) {
            const map = { active: 'sc-active', inactive: 'sc-inactive', today: 'sc-today', month: 'sc-month' };
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
            if (type && map[type]) document.getElementById(map[type])?.classList.add('active-filter');
        }
        function syncSearch() { document.getElementById('table-search').value = document.getElementById('topbar-search').value; curPage = 1; renderTable(); }
        function toggleAll(cb) { document.querySelectorAll('.row-cb').forEach(r => r.checked = cb.checked); }

        /* ── Result ── */
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

        /* ── View ── */
        function openViewModal(id) {
            const r = allRecords.find(x => x.id == id); if (!r) return; _currentViewRecord = r;
            document.getElementById('view-printer_name').textContent = r.printer_name || '—';
            document.getElementById('view-section_sub').textContent = r.section_name || '—';
            document.getElementById('vset-speed').textContent = r.print_speed ?? '—';
            document.getElementById('vset-dark').textContent = r.print_darkness ?? '—';
            document.getElementById('vset-offset').textContent = r.offset_val ?? '—';
            document.getElementById('vset-h').textContent = r.h_settings ?? '—';
            document.getElementById('vset-v').textContent = r.v_settings ?? '—';
            document.getElementById('vset-status').innerHTML = statusBadge(r.status);
            document.getElementById('view-model').textContent = r.model || '—';
            document.getElementById('view-serial_number').textContent = r.serial_number || '—';
            document.getElementById('view-ip_address').textContent = r.ip_address || '—';
            document.getElementById('view-pic').textContent = r.pic || '—';
            document.getElementById('view-date_deployed').textContent = r.date_deployed || '—';
            document.getElementById('view-created_at').textContent = r.created_at ? r.created_at.substring(0, 10) : '—';
            document.getElementById('view-modal').classList.add('open');
        }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('open'); _currentViewRecord = null; }
        function switchToEdit() { if (!_currentViewRecord) return; closeViewModal(); openEditModal(_currentViewRecord.id); }

        /* ── Add ── */
        const addFields = ['printer_name', 'section_name', 'pic', 'date_deployed', 'serial_number', 'model', 'print_speed', 'print_darkness', 'offset_val', 'h_settings', 'v_settings', 'ip_address'];
        function openAddModal() {
            addFields.forEach(f => { const el = document.getElementById('add-' + f); if (el) el.value = ''; });
            document.getElementById('add-status').value = 'Active';
            hideAlert('modal-alert'); document.getElementById('add-modal').classList.add('open');
        }
        function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); }
        document.getElementById('add-modal')?.addEventListener('click', function (e) { if (e.target === this) closeAddModal(); });

        function submitAddRecord() {
            const flds = [...addFields, 'status'];
            const data = {}; flds.forEach(f => { const el = document.getElementById('add-' + f); data[f] = el ? el.value.trim() : ''; });
            hideAlert('modal-alert');
            if (!data.printer_name || !data.section_name) { showAlert('modal-alert', 'Printer Name and Section are required.'); return; }
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ __action: 'add_record', ...data }).toString() })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        allRecords.unshift(d.record); curPage = 1; renderTable(); closeAddModal();
                        showResult('success', 'Printer Added!', 'Sato printer has been saved.', [
                            ['🖨️ Printer', d.record.printer_name],
                            ['📍 Section', d.record.section_name || '—'],
                            ['🏷️ Model', d.record.model || '—'],
                            ['🌐 IP Address', d.record.ip_address || '—'],
                        ]);
                    } else showAlert('modal-alert', d.message || 'Failed to add.');
                }).catch(() => showAlert('modal-alert', 'Network error.'));
        }

        /* ── Edit ── */
        const editFields = ['printer_name', 'section_name', 'pic', 'date_deployed', 'serial_number', 'model', 'print_speed', 'print_darkness', 'offset_val', 'h_settings', 'v_settings', 'ip_address', 'status'];
        function openEditModal(id) {
            const r = allRecords.find(x => x.id == id); if (!r) return;
            document.getElementById('edit-record_id').value = r.id;
            editFields.forEach(f => { const el = document.getElementById('edit-' + f); if (el) el.value = r[f] || ''; });
            hideAlert('edit-modal-alert'); document.getElementById('edit-modal').classList.add('open');
        }
        function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); }
        document.getElementById('edit-modal')?.addEventListener('click', function (e) { if (e.target === this) closeEditModal(); });

        function submitEditRecord() {
            const id = document.getElementById('edit-record_id').value;
            const data = {}; editFields.forEach(f => { const el = document.getElementById('edit-' + f); data[f] = el ? el.value.trim() : ''; });
            hideAlert('edit-modal-alert');
            if (!data.printer_name || !data.section_name) { showAlert('edit-modal-alert', 'Printer Name and Section are required.'); return; }
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ __action: 'edit_record', id, ...data }).toString() })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        const idx = allRecords.findIndex(r => r.id == id); if (idx !== -1) allRecords[idx] = { ...allRecords[idx], ...d.record }; renderTable(); closeEditModal();
                        showResult('success', 'Printer Updated!', 'Sato printer has been updated.', [['🖨️ Printer', d.record.printer_name], ['🏷️ Model', d.record.model || '—']]);
                    } else showAlert('edit-modal-alert', d.message || 'Failed to update.');
                }).catch(() => showAlert('edit-modal-alert', 'Network error.'));
        }

        /* ── Delete ── */
        function openDelModal(id, name) { _pendingDeleteId = id; _pendingDeleteName = name; document.getElementById('del-name').textContent = name; document.getElementById('del-modal').classList.add('open'); }
        function closeDelModal() { document.getElementById('del-modal').classList.remove('open'); _pendingDeleteId = _pendingDeleteName = null; }
        function confirmDelete() {
            if (!_pendingDeleteId) return; const dn = _pendingDeleteName;
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `__action=delete_record&id=${_pendingDeleteId}` })
                .then(r => r.json()).then(d => { closeDelModal(); if (d.success) { allRecords = allRecords.filter(r => r.id != _pendingDeleteId); renderTable(); showResult('success', 'Deleted', `"${dn}" has been permanently removed.`); } else showResult('error', 'Failed', d.message || 'Could not delete.'); })
                .catch(() => { closeDelModal(); showResult('error', 'Network Error', 'Could not reach the server.'); });
        }

        /* ── Helpers ── */
        function showAlert(elId, msg) { const el = document.getElementById(elId); if (!el) return; el.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${msg}`; el.style.display = 'flex'; }
        function hideAlert(elId) { const el = document.getElementById(elId); if (el) el.style.display = 'none'; }
        function showToast(msg, type = 'success') { const t = document.getElementById('toast'); t.textContent = msg; t.className = `toast ${type} show`; clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 3200); }

        function exportCSV() {
            const rows = [['ID', 'Printer Name', 'Section', 'PIC', 'Model', 'Serial No.', 'IP Address', 'Print Speed', 'Print Darkness', 'Offset', 'H Settings', 'V Settings', 'Status', 'Date Deployed', 'Created At']];
            getFiltered().forEach(r => rows.push([r.id, r.printer_name || '', r.section_name || '', r.pic || '', r.model || '', r.serial_number || '', r.ip_address || '', r.print_speed || '', r.print_darkness || '', r.offset_val || '', r.h_settings || '', r.v_settings || '', r.status || '', r.date_deployed || '', r.created_at || '']));
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' })); a.download = 'sato_printers.csv'; a.click();
            showToast('Exported!', 'success');
        }
        function toggleAcc(id) {
            const clicked = document.getElementById(id);
            const isOpen  = clicked.classList.contains('open');
            document.querySelectorAll('.nav-accordion').forEach(el => el.classList.remove('open'));
            if (!isOpen) clicked.classList.add('open');
        }
        renderTable();
    </script>
</body>

</html>