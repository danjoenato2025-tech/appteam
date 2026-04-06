<?php
/**
 * process_view.php  (lives in: /Crud/)
 * ─────────────────────────────────────────────────────────────
 * Handles : Fetch Single User Details  (All logged-in users)
 * Called by: index.php or any page via fetch GET/POST
 * Usage    : process_view.php?id=5
 * Returns  : JSON { success, user } | { success, message }
 *
 * NOTE: The view modal in index.php reads from the JS allUsers
 * array for speed (no extra network call). Use this endpoint
 * when you need server-fresh data, extra fields, or audit logs.
 * ─────────────────────────────────────────────────────────────
 */

session_start();
header('Content-Type: application/json');

// ── Auth guard ───────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit;
}

include('../dbconnection/config.php');

// ── Accept GET or POST ───────────────────────────────────────
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']); exit;
}

// ── Fetch ────────────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT id, username, full_name, email, role, plan, billing, status,
           avatar_color, associate_id, section, team, created_at
    FROM users WHERE id=? LIMIT 1
');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']); exit;
}

echo json_encode(['success' => true, 'user' => $user]);