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

// 1. Strictly verify the user is logged in as Support or Superadmin [1.1.1]
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['support', 'superadmin'])) {
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

$ticket_id = htmlspecialchars(strip_tags($_POST['ticket_id'] ?? ''));
$status = htmlspecialchars(strip_tags($_POST['status'] ?? 'new'));
$staff_reply = htmlspecialchars(strip_tags($_POST['staff_reply'] ?? ''));

if (empty($ticket_id) || empty($staff_reply)) {
    echo json_encode(['success' => false, 'message' => 'All response parameters must be populated.']);
    exit();
}

$supabase = new SupabaseClient();

// 3. Update the ticket inside Supabase [1]
$db_update = $supabase->update('tickets', [
    'status' => $status,
    'staff_reply' => $staff_reply
], ['id' => $ticket_id]);

if ($db_update['status'] === 200) {
    // 4. AUTOMATED EMAILJS/PHPMailer NOTIFICATION [1, 2]
    // Fetch target submitting user's contact information
    $ticket_info_query = $supabase->select('tickets', 'user_id, subject', ['id' => $ticket_id]);
    $ticket_subject = $ticket_info_query['data'][0]['subject'] ?? 'Support Inquiry';
    $target_user_id = $ticket_info_query['data'][0]['user_id'] ?? null;

    if ($target_user_id) {
        $user_query = $supabase->select('users', 'email, full_name', ['id' => $target_user_id]);
        if (!empty($user_query['data'])) {
            $user_obj = $user_query['data'][0];
            $user_email = $user_obj['email'];
            $user_name = $user_obj['full_name'];
            $short_id = strtoupper(substr($ticket_id, 0, 8));

            // TRIGGER SMTP NOTIFICATION [2]
            $subject = "Support Case Updated: #" . $short_id;
            $title = "Your Support Case has a Response";
            $body = "Hello " . $user_name . ",\n\nOur Support team has updated your active support case regarding '" . $ticket_subject . "'.\n\nCase ID: #" . $short_id . "\nNew Status: " . strtoupper(str_replace('_', ' ', $status)) . "\n\nSupport Staff Response:\n" . $staff_reply . "\n\nYou can view and reply to this ticket directly from your customer dashboard.";

            sendEmailNotification($user_email, $user_name, $subject, $title, $body);
        }
    }

    // Log transaction to global platform security audits [1]
    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Answered Support Case ID: " . $ticket_id . " and updated status to: " . $status,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Response delivered and status saved successfully! Recipient notified.']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save staff response in Supabase. Database returned code: ' . $db_update['status']
    ]);
}
exit();