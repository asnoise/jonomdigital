<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to go up exactly one level (htdocs/) [1.1.1]
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/email_helper.php'; // Load PHPMailer SMTP helper [2]

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Access Method.']);
    exit();
}

// 1. Validate operational CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$email = filter_input(INPUT_POST, 'reset_email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}

$supabase = new SupabaseClient();

// 2. Query if user exists in our central identity database
$user_query = $supabase->select('users', '*', ['email' => $email]);

if ($user_query['status'] !== 200 || empty($user_query['data'])) {
    // Return a friendly success response even if the user is not found to prevent account harvesting
    echo json_encode([
        'success' => true, 
        'message' => 'If this email is registered in our system, a temporary password has been sent successfully to your inbox.'
    ]);
    exit();
}

$user = $user_query['data'][0];
$user_name = $user['full_name'];
$user_id = $user['id'];

// 3. SECURE PASS GENERATION (Temporary 10-character alphanumeric string)
$allowed_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$temp_password = substr(str_shuffle($allowed_chars), 0, 10);
$password_hash = password_hash($temp_password, PASSWORD_BCRYPT);

// 4. Update the user password in PostgreSQL
$db_update = $supabase->update('users', ['password_hash' => $password_hash], ['id' => $user_id]);

if ($db_update['status'] === 200) {
    // 5. TRIGGER SMTP WELCOME/RESET TEMPLATE [2]
    $subject = "Temporary Password - Jonom Digital India";
    $title = "Password Reset Request";
    $body = "Hello " . $user_name . ",\n\nWe have received a password reset request for your account.\n\nYour new temporary credentials are:\n\nEmail ID: " . $email . "\nTemporary Password: " . $temp_password . "\n\nFor security reasons, we highly recommend logging in immediately and changing your password under Account Settings.";

    $email_sent = sendEmailNotification($email, $user_name, $subject, $title, $body);

    if ($email_sent) {
        // Log transaction to global platform security audits [1]
        $supabase->insert('audit_logs', [
            'user_id'    => $user_id,
            'action'     => "Triggered system password reset for Email: " . $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'A new temporary password has been successfully delivered to your inbox!'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'SMTP mailer handshake failure. Please try again later.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save updated password in the database.']);
}
exit();