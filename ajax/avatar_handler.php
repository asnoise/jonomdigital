<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(__DIR__) . '/config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_only_cookies', 1);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Access denied.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Access Method.']);
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Photo file is missing or contains errors.']);
    exit();
}

$avatar_file = $_FILES['avatar'];
$allowed_image_types = ['image/jpeg', 'image/png'];

if (!in_array($avatar_file['type'], $allowed_image_types)) {
    echo json_encode(['success' => false, 'message' => 'Avatar must strictly be formatted in JPEG or PNG.']);
    exit();
}

$image_dimensions = getimagesize($avatar_file['tmp_name']);
if (!$image_dimensions || $image_dimensions[0] !== 300 || $image_dimensions[1] !== 300) {
    echo json_encode(['success' => false, 'message' => 'Image dimensions must be exactly 300 x 300px.']);
    exit();
}

// CORRECTED: Write upload folders inside htdocs/ to bypass permission blocks [1]
$upload_dir = dirname(__DIR__) . '/uploads/profiles/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$file_ext = pathinfo($avatar_file['name'], PATHINFO_EXTENSION);
$safe_file_name = uniqid('AVATAR_') . '.' . $file_ext;
$destination_path = $upload_dir . $safe_file_name;

if (!move_uploaded_file($avatar_file['tmp_name'], $destination_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save avatar image file to server disk.']);
    exit();
}

$supabase = new SupabaseClient();

$avatar_url = 'uploads/profiles/' . $safe_file_name;

// Update users table in Supabase
$db_update = $supabase->update('users', ['avatar_path' => $avatar_url], ['id' => $_SESSION['user_id']]);

if ($db_update['status'] === 200) {
    // SYNCHRONIZE ACTIVE SESSION IMMEDIATELY [1]
    $_SESSION['avatar_path'] = $avatar_url;

    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Uploaded profile photo avatar path: " . $avatar_url,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Profile photo uploaded successfully!', 'avatar_path' => $avatar_url]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update user avatar inside Supabase. Code: ' . $db_update['status']
    ]);
}
exit();