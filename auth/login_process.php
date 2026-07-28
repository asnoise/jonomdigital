<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/email_helper.php'; // Load PHPMailer & OTP sender [2]

// Rate Limiting Protection (Max 5 attempts within 10 minutes)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if ($_SESSION['login_attempts'] >= 5) {
    $timePassed = time() - $_SESSION['last_attempt_time'];
    if ($timePassed < 600) {
        $minutesLeft = ceil((600 - $timePassed) / 60);
        echo json_encode(['success' => false, 'message' => "Too many attempts. Suspended for $minutesLeft minutes."]);
        exit();
    } else {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = time();
    }
}

// CSRF Protection validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF Token validation failed. Request rejected.']);
    exit();
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password format.']);
    exit();
}

$supabase = new SupabaseClient();
$response = $supabase->select('users', '*', ['email' => $email]);

if ($response['status'] !== 200 || empty($response['data'])) {
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt_time'] = time();
    echo json_encode(['success' => false, 'message' => 'Invalid email address or password.']);
    exit();
}

$user = $response['data'][0];

// Verify if account status is active
if (($user['status'] ?? '') === 'inactive') {
    echo json_encode(['success' => false, 'message' => 'Your account is suspended. Contact Support.']);
    exit();
}

// Secure Password Verification
if (password_verify($password, $user['password_hash'])) {
    // 1. GENERATE SECURE 6-DIGIT OTP [1]
    $otp = strval(rand(100000, 999999));

    // 2. STAGE CREDENTIALS TEMPORARILY IN SESSION ESCROW [1]
    $_SESSION['temp_user'] = [
        'id'          => $user['id'],
        'email'       => $user['email'],
        'role'        => $user['role'],
        'full_name'   => $user['full_name'],
        'stage_name'  => $user['stage_name'] ?? $user['full_name'],
        'avatar_path' => $user['avatar_path'] ?? null
    ];
    $_SESSION['login_otp'] = $otp;
    $_SESSION['login_otp_expiry'] = time() + 300; // Code expires in exactly 5 minutes [1]

    // 3. DISPATCH SECURE OTP EMAIL VIA GMAIL SMTP [1, 2]
    $purpose = "authorize secure login access to Jonom Digital portal";
    $email_sent = sendOtpEmail($user['email'], $user['full_name'], $otp, $purpose);

    if ($email_sent) {
        // Reset login fail logs
        $_SESSION['login_attempts'] = 0;

        echo json_encode([
            'success' => true,
            'step'    => 'otp_required',
            'message' => 'An identity verification code was dispatched to ' . substr($user['email'], 0, 4) . '***@gmail.com. Check your inbox [1, 2]!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'SMTP mailer handshake failure. Please try again later.'
        ]);
    }
    exit();
} else {
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt_time'] = time();
    echo json_encode(['success' => false, 'message' => 'Invalid email address or password.']);
    exit();
}