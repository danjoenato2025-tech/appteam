<?php
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

include('../dbconnection/config.php');

$uid = (int) $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'No file uploaded or upload error.';
    echo json_encode($response); exit;
}

$file     = $_FILES['avatar'];
$maxSize  = 800 * 1024; // 800KB
$allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];

if ($file['size'] > $maxSize)            { $response['message'] = 'File exceeds 800KB limit.'; echo json_encode($response); exit; }
if (!in_array($file['type'], $allowed))  { $response['message'] = 'Only JPG, PNG, GIF, WEBP allowed.'; echo json_encode($response); exit; }

$uploadDir = '../uploads/avatars/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Delete old avatar file if exists
$old = $pdo->prepare('SELECT avatar FROM users WHERE id=? LIMIT 1');
$old->execute([$uid]);
$oldAvatar = $old->fetchColumn();
if ($oldAvatar && file_exists('../' . $oldAvatar)) unlink('../' . $oldAvatar);

$filename  = 'avatar_' . $uid . '_' . time() . '.' . $extMap[$file['type']];
$destPath  = $uploadDir . $filename;
$dbPath    = 'uploads/avatars/' . $filename;

if (move_uploaded_file($file['tmp_name'], $destPath)) {
    $pdo->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$dbPath, $uid]);
    $response = ['success' => true, 'avatar_url' => $dbPath];
} else {
    $response['message'] = 'Failed to save file. Check folder permissions.';
}

echo json_encode($response);