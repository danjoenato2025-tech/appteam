<?php
/**
 * process_edit.php
 * ──────────────────────────────────────────────────────────────
 * Handles two cases:
 *
 * 1. NORMAL EDIT  — from Edit User modal
 *    POST fields: id, username, full_name, email, associate_id,
 *                 section, team, role, status
 *
 * 2. APPROVAL ACTION — from Approval Modal (bell notification)
 *    POST fields: id, status, _approval=1
 *    (only updates the status column — approve/reject)
 *
 * Returns JSON: { success: bool, user: {...} | message: string }
 */

session_start();
header('Content-Type: application/json');

// ── Auth guard: only logged-in admins ──────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

include('../dbconnection/config.php');

// Accept id sent as int, string, or trimmed string
$id         = (int)trim($_POST['id'] ?? '0');
$isApproval = !empty($_POST['_approval']);   // true = approval-only update

if ($id <= 0) {
    // Debug: echo back what was received to help diagnose
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID.',
        'received_id' => $_POST['id'] ?? 'not sent',
        'parsed_id'   => $id,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// CASE 1 — APPROVAL / REJECTION  (status-only update)
// ══════════════════════════════════════════════════════════════
if ($isApproval) {
    $status = in_array($_POST['status'] ?? '', ['active', 'pending', 'inactive'])
        ? $_POST['status']
        : 'active';

    try {
        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);

        // Return updated user row
        $sel = $pdo->prepare('
            SELECT id, username, full_name, email, role, plan, billing, status,
                   avatar_color, associate_id, section, team, created_at
            FROM users WHERE id = ?
        ');
        $sel->execute([$id]);
        $user = $sel->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'user' => $user]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
// CASE 2 — FULL EDIT
// ══════════════════════════════════════════════════════════════
$username     = trim($_POST['username']     ?? '');
$full_name    = trim($_POST['full_name']    ?? '');
$email        = trim($_POST['email']        ?? '');
$associate_id = trim($_POST['associate_id'] ?? '');
$section      = trim($_POST['section']      ?? '');
$team         = trim($_POST['team']         ?? '');
$role         = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
$status       = in_array($_POST['status'] ?? '', ['active', 'pending', 'inactive']) ? $_POST['status'] : 'active';

// ── Validation ─────────────────────────────────────────────────
if (!$username || !$full_name || !$email || !$associate_id || !$section || !$team) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}
if (!preg_match('/^\d{7}$/', $associate_id)) {
    echo json_encode(['success' => false, 'message' => 'Associate ID must be exactly 7 digits.']);
    exit;
}

// ── Duplicate check (exclude self) ────────────────────────────
$chk = $pdo->prepare('
    SELECT id FROM users
    WHERE (email = ? OR username = ? OR associate_id = ?)
      AND id != ?
    LIMIT 1
');
$chk->execute([$email, $username, $associate_id, $id]);
if ($chk->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Another account already uses this email, username, or Associate ID.']);
    exit;
}

// ── Update ─────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare('
        UPDATE users
        SET username     = ?,
            full_name    = ?,
            email        = ?,
            associate_id = ?,
            section      = ?,
            team         = ?,
            role         = ?,
            status       = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $username, $full_name, $email,
        $associate_id, $section, $team,
        $role, $status, $id,
    ]);

    // Return updated user row
    $sel = $pdo->prepare('
        SELECT id, username, full_name, email, role, plan, billing, status,
               avatar_color, associate_id, section, team, created_at
        FROM users WHERE id = ?
    ');
    $sel->execute([$id]);
    $user = $sel->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'user' => $user]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}