<?php
/**
 * process_upload_avatar.php
 * ─────────────────────────
 * Place this file inside your  Crud/  folder.
 * Called by user_account.php via fetch('Crud/process_upload_avatar.php')
 */
session_start();

header('Content-Type: application/json');

// ── Auth check ───────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

// config.php is one level up from Crud/
$configPath = __DIR__ . '/../dbconnection/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Config not found: ' . $configPath]);
    exit;
}
include $configPath;

$uid = (int) ($_POST['id'] ?? 0);

// Only the owner can change their own avatar
if ($uid === 0 || $uid !== (int) $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── RESET: remove avatar, revert to initials ─────────────────
if (!empty($_POST['reset'])) {
    $row = $pdo->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
    $row->execute([$uid]);
    $cur = $row->fetch(PDO::FETCH_ASSOC);

    if (!empty($cur['avatar'])) {
        $filePath = __DIR__ . '/../' . ltrim($cur['avatar'], '/');
        if (file_exists($filePath)) @unlink($filePath);
    }

    $pdo->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$uid]);
    echo json_encode(['success' => true]);
    exit;
}

// ── UPLOAD ───────────────────────────────────────────────────
if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['avatar']['error'] ?? -1;
    $errMap  = [
        1 => 'File too large (server limit).',
        2 => 'File too large (form limit).',
        3 => 'File only partially uploaded.',
        4 => 'No file was uploaded.',
        6 => 'Missing temporary folder.',
        7 => 'Failed to write file to disk.',
    ];
    echo json_encode([
        'success' => false,
        'message' => $errMap[$errCode] ?? "Upload error (code $errCode)."
    ]);
    exit;
}

$file     = $_FILES['avatar'];
$maxBytes = 800 * 1024; // 800 KB

// Validate size
if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 800K limit.']);
    exit;
}

// Validate MIME via finfo (not just extension)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowed  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

if (!array_key_exists($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WEBP allowed.']);
    exit;
}

// Build upload directory — avatars folder is at Application/uploads/avatars/
$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Cannot create upload folder. Check permissions on: ' . $uploadDir]);
        exit;
    }
}

// Check folder is writable
if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'message' => 'Upload folder is not writable: ' . $uploadDir]);
    exit;
}

$ext      = $allowed[$mimeType];
$filename = 'avatar_' . $uid . '_' . time() . '.' . $ext;
$savePath = $uploadDir . $filename;
$webPath  = 'uploads/avatars/' . $filename; // relative path stored in DB

// Delete old avatar file if it exists
$oldRow = $pdo->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
$oldRow->execute([$uid]);
$cur = $oldRow->fetch(PDO::FETCH_ASSOC);
if (!empty($cur['avatar'])) {
    $oldFile = __DIR__ . '/../' . ltrim($cur['avatar'], '/');
    if (file_exists($oldFile)) @unlink($oldFile);
}

// Move the uploaded temp file to final location
if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    echo json_encode(['success' => false, 'message' => 'Could not save file. Check permissions on: ' . $uploadDir]);
    exit;
}

// Save web path to DB
$pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$webPath, $uid]);

echo json_encode(['success' => true, 'avatar' => $webPath]);