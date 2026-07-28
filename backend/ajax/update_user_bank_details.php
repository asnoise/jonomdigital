<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(dirname(__DIR__)) . '/config/supabase.php';
require_once dirname(dirname(__DIR__)) . '/includes/email_helper.php'; // Load SMTP helper [2]

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Strictly verify the session is active and the user is Financial Team or Superadmin [1.1.1]
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['financial', 'superadmin'])) {
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

// 3. Capture and sanitize administrative inputs
$target_user_id = htmlspecialchars(strip_tags($_POST['target_user_id'] ?? ''));
$bank_name = htmlspecialchars(strip_tags($_POST['bank_name'] ?? ''));
$bank_account = htmlspecialchars(strip_tags($_POST['bank_account'] ?? ''));
$upi_id = htmlspecialchars(strip_tags($_POST['upi_id'] ?? ''));

if (empty($target_user_id) || empty($bank_name) || empty($bank_account) || empty($upi_id)) {
    echo json_encode(['success' => false, 'message' => 'All banking parameters must be correctly filled.']);
    exit();
}

$supabase = new SupabaseClient();

// 4. Update banking details inside Supabase users table [1]
$db_update = $supabase->update('users', [
    'bank_name'       => $bank_name,
    'bank_account_id' => $bank_account,
    'upi_id'          => $upi_id
], ['id' => $target_user_id]);

if ($db_update['status'] === 200) {
    // 5. FETCH USER EMAIL AND TRIGGER EMAIL SECURITY ALERT [1, 2]
    $user_query = $supabase->select('users', 'email, full_name', ['id' => $target_user_id]);
    if (!empty($user_query['data'])) {
        $user_email = $user_query['data'][0]['email'];
        $user_name = $user_query['data'][0]['full_name'];

        // TRIGGER SMTP NOTIFICATION [2]
        $subject = "Security Alert: Banking Details Modified";
        $title = "Your Banking Details Have Been Updated";
        $body = "Hello " . $user_name . ",\n\nThis is an automated security alert to notify you that your settlement banking credentials and UPI ID have been administratively updated by our Financial Team [1].\n\nNew Bank / IFSC: " . $bank_name . "\nNew Account: " . $bank_account . "\nNew UPI ID: " . $upi_id . "\n\nIf you did not request this modification, please contact support immediately at jonomdigital@gmail.com [1].";

        sendEmailNotification($user_email, $user_name, $subject, $title, $body);
    }

    // Log transaction to global platform security audits [1]
    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Administratively modified banking details for user ID: " . $target_user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Settlement credentials updated successfully! Recipient notified [1, 2].']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update credentials. Database returned code: ' . $db_update['status']
    ]);
}
exit();