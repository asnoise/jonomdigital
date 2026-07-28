<?php
header('Content-Type: application/json');

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

// 1. Validate operational CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$entered_otp = trim($_POST['otp_code'] ?? '');

if (empty($entered_otp) || strlen($entered_otp) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit security code.']);
    exit();
}

// 2. Verify temporary user details exist [1]
if (!isset($_SESSION['temp_user']) || !isset($_SESSION['login_otp'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please restart your login.']);
    exit();
}

// 3. Evaluate code validity and expiration [1]
if (time() > $_SESSION['login_otp_expiry']) {
    echo json_encode(['success' => false, 'message' => 'Security code expired. Click back to request a new code.']);
    exit();
}

if ($entered_otp !== $_SESSION['login_otp']) {
    echo json_encode(['success' => false, 'message' => 'Incorrect verification code. Please try again.']);
    exit();
}

// 4. ACTIVATE FULL AUTHENTICATED SESSION ON MATCH [1]
$temp_user = $_SESSION['temp_user'];
session_regenerate_id(true); // Mitigates session fixation

$_SESSION['user_id']     = $temp_user['id'];
$_SESSION['email']       = $temp_user['email'];
$_SESSION['role']        = $temp_user['role'];
$_SESSION['full_name']   = $temp_user['full_name'];
$_SESSION['stage_name']  = $temp_user['stage_name'];
$_SESSION['avatar_path'] = $temp_user['avatar_path'];
$_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);

// Clear temporary session tokens
unset($_SESSION['temp_user']);
unset($_SESSION['login_otp']);
unset($_SESSION['login_otp_expiry']);

$supabase = new SupabaseClient();

// Log successful audit trail
$supabase->insert('audit_logs', [
    'user_id'    => $_SESSION['user_id'],
    'action'     => "User securely logged in after completing multi-factor OTP verification.",
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Determine Routing path based on verified role
$redirect = 'dashboard';
$backendRoles = ['support', 'moderation', 'financial', 'superadmin'];
if (in_array($_SESSION['role'], $backendRoles)) {
    $redirect = 'backend/dashboard';
}

echo json_encode([
    'success'  => true,
    'redirect' => $redirect
]);
exit();