<?php
/**
 * process_add.php
 * ──────────────────────────────────────────────────────────────
 * Handles Admin "Add New User" from tbl_userlist.php modal.
 * Expects POST: username, full_name, email, associate_id,
 *               section, team, role, status, password
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

// ── Collect & sanitize inputs ──────────────────────────────────
$username     = trim($_POST['username']     ?? '');
$full_name    = trim($_POST['full_name']    ?? '');
$email        = trim($_POST['email']        ?? '');
$associate_id = trim($_POST['associate_id'] ?? '');
$section      = trim($_POST['section']      ?? '');
$team         = trim($_POST['team']         ?? '');
$role         = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
$status       = in_array($_POST['status'] ?? '', ['active', 'pending', 'inactive']) ? $_POST['status'] : 'active';
$password     = $_POST['password'] ?? '';

// ── Server-side validation ─────────────────────────────────────
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
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// ── Duplicate check ────────────────────────────────────────────
$chk = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? OR associate_id = ? LIMIT 1');
$chk->execute([$email, $username, $associate_id]);
if ($chk->fetch()) {
    echo json_encode(['success' => false, 'message' => 'An account with this email, username, or Associate ID already exists.']);
    exit;
}

// ── Avatar colour pool ─────────────────────────────────────────
$colors = ['#a8b9f8','#ffb347','#f4a2a2','#6ecf8b','#7bc8f6','#d4a5f5','#f9c784','#c2c2c2'];
$color  = $colors[array_rand($colors)];

// ── Hash the password ──────────────────────────────────────────
$hashed = password_hash($password, PASSWORD_DEFAULT);

// ── Insert ─────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare('
        INSERT INTO users
            (username, full_name, email, password, role, plan, billing, status,
             avatar_color, associate_id, section, team, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $username, $full_name, $email, $hashed,
        $role, 'Basic', 'Manual – Cash', $status,
        $color, $associate_id, $section, $team,
    ]);

    $newId = $pdo->lastInsertId();

    // Return the new user row so the JS table can add it live
    $newUser = $pdo->prepare('
        SELECT id, username, full_name, email, role, plan, billing, status,
               avatar_color, associate_id, section, team, created_at
        FROM users WHERE id = ?
    ');
    $newUser->execute([$newId]);
    $user = $newUser->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'user' => $user]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}