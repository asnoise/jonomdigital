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

// 1. Strictly verify the session is active and the user is Financial Team or Superadmin [1.1.1]
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['financial', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Access denied.']);
    exit();
}

$email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid search email.']);
    exit();
}

$supabase = new SupabaseClient();

// 2. Query target user details [1]
$user_query = $supabase->select('users', 'id, full_name, bank_name, bank_account_id, upi_id', ['email' => $email]);

if ($user_query['status'] === 200 && !empty($user_query['data'])) {
    echo json_encode([
        'success' => true,
        'user' => $user_query['data'][0]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No active user account found with that email.']);
}
exit();