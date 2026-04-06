<?php
/**
 * process_delete.php
 * ──────────────────────────────────────────────────────────────
 * Handles user deletion from tbl_userlist.php.
 * POST fields: id
 * Returns JSON: { success: bool, message?: string }
 *
 * Guards:
 *  - Must be admin
 *  - Cannot delete yourself
 */

session_start();
header('Content-Type: application/json');

// ── Auth guard ─────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

include('../dbconnection/config.php');

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

// ── Cannot delete yourself ─────────────────────────────────────
if ($id === (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
    exit;
}

// ── Verify user exists ─────────────────────────────────────────
$chk = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
$chk->execute([$id]);
if (!$chk->fetch()) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

// ── Delete ─────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}