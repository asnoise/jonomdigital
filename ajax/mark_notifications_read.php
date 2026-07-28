<?php
header('Content-Type: application/json');

// Define security guard context
define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(__DIR__) . '/config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Session expired.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Access Method.']);
    exit();
}

// Validate operational CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$supabase = new SupabaseClient();

// Update read flags in database for the logged-in user [1]
$db_update = $supabase->update('notifications', ['is_read' => true], ['user_id' => $_SESSION['user_id']]);

if ($db_update['status'] === 200) {
    echo json_encode(['success' => true, 'message' => 'Notifications cleared.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear notifications. Code: ' . $db_update['status']]);
}
exit();