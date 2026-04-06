<?php
/**
 * logout.php  — with audit trail integration
 * CHANGES: added audit_logout() call before destroying session
 */
session_start();

if (isset($_SESSION['user_id'])) {
    include('dbconnection/config.php');
    include('audit_helper.php');    // ← AUDIT
    audit_logout($pdo);             // log before destroying session
}

session_unset();
session_destroy();

$reason = isset($_GET['reason']) ? '?reason=' . htmlspecialchars($_GET['reason']) : '';
header('Location: login.php' . $reason);
exit;