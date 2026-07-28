<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(dirname(__DIR__)) . '/config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Strictly verify the session is active and the user is Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Access denied.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Access Method.']);
    exit();
}

// 2. Validate operational CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$announcement_text = htmlspecialchars(strip_tags($_POST['announcement_text'] ?? ''));

$supabase = new SupabaseClient();

// 3. Update the global site settings table inside Supabase [1]
$db_update = $supabase->update('site_settings', ['value' => $announcement_text], ['key' => 'announcement_banner']);

if ($db_update['status'] === 200) {
    // Log transaction to global platform security audits [1]
    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Superadmin updated global announcement notice: " . $announcement_text,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Global announcement updated successfully! All active users will see the update [1].']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save announcement. Database returned code: ' . $db_update['status']
    ]);
}
exit();