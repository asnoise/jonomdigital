<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/email_helper.php'; // Load PHPMailer SMTP helper [2]

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

// Capture and sanitize input values
$full_name = htmlspecialchars(strip_tags($_POST['full_name'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$bank_name = htmlspecialchars(strip_tags($_POST['bank_name'] ?? ''));
$bank_account = htmlspecialchars(strip_tags($_POST['bank_account'] ?? ''));
$upi_id = htmlspecialchars(strip_tags($_POST['upi_id'] ?? ''));

$entered_otp = trim($_POST['otp'] ?? '');

if (empty($full_name) || !$email || empty($bank_name) || empty($bank_account) || empty($upi_id)) {
    echo json_encode(['success' => false, 'message' => 'All mandatory parameters must be populated.']);
    exit();
}

$supabase = new SupabaseClient();

// =========================================================================
// STAGE 2: PROCESS ACTIVE STEP-UP OTP CHALLENGE MATCH [1]
// =========================================================================
if (!empty($entered_otp)) {
    // Check if security code is valid on server session
    if (!isset($_SESSION['settings_otp']) || !isset($_SESSION['temp_settings_data'])) {
        echo json_encode(['success' => false, 'message' => 'Security session expired. Please resubmit your form.']);
        exit();
    }

    if (time() > $_SESSION['settings_otp_expiry']) {
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please resubmit your form to receive a new code.']);
        exit();
    }

    if ($entered_otp !== $_SESSION['settings_otp']) {
        echo json_encode(['success' => false, 'message' => 'Incorrect verification code. Please check your email and try again.']);
        exit();
    }

    // Recover cached parameters on successful match [1]
    $cached_data = $_SESSION['temp_settings_data'];

    // 1. Validate email uniqueness
    $email_check = $supabase->select('users', '*', ['email' => $cached_data['email']]);
    if ($email_check['status'] === 200 && !empty($email_check['data'])) {
        $existing_user = $email_check['data'][0];
        if ($existing_user['id'] !== $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'This email address is already in use by another account.']);
            exit();
        }
    }

    // 2. Compile SQL update dataset [1]
    $update_data = [
        'full_name'       => $cached_data['full_name'],
        'email'           => $cached_data['email'],
        'bank_name'       => $cached_data['bank_name'],
        'bank_account_id' => $cached_data['bank_account'],
        'upi_id'          => $cached_data['upi_id']
    ];

    if (!empty($cached_data['password'])) {
        $update_data['password_hash'] = password_hash($cached_data['password'], PASSWORD_BCRYPT);
    }

    $db_update = $supabase->update('users', $update_data, ['id' => $_SESSION['user_id']]);

    if ($db_update['status'] === 200) {
        // Sync active session variables
        $_SESSION['full_name'] = $cached_data['full_name'];
        $_SESSION['email']    = $cached_data['email'];

        // Clean temporary session variables [1]
        unset($_SESSION['settings_otp']);
        unset($_SESSION['settings_otp_expiry']);
        unset($_SESSION['temp_settings_data']);

        // Log transaction to global platform security audits [1]
        $supabase->insert('audit_logs', [
            'user_id'    => $_SESSION['user_id'],
            'action'     => "Saved updated bank routing and profile details after completing step-up OTP verification.",
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);

        echo json_encode(['success' => true, 'message' => 'Profile parameters saved successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase update failure. Code: ' . $db_update['status']]);
    }
    exit();
}

// =========================================================================
// STAGE 1: DYNAMIC SENSITIVE CHANGES INTERCEPTOR -> DISPATCHES OTP [1]
// =========================================================================

// Fetch current database values to verify if sensitive fields actually changed [1]
$curr_user_query = $supabase->select('users', 'email, bank_name, bank_account_id, upi_id', ['id' => $_SESSION['user_id']]);
$curr_user = $curr_user_query['data'][0] ?? null;

if (!$curr_user) {
    echo json_encode(['success' => false, 'message' => 'User profile not found.']);
    exit();
}

$sensitive_changed = false;

// If bank name, account number, UPI ID, or Email is changed, we force OTP authorization [1]
if ($email !== $curr_user['email'] ||
    $bank_name !== ($curr_user['bank_name'] ?? '') ||
    $bank_account !== ($curr_user['bank_account_id'] ?? '') ||
    $upi_id !== ($curr_user['upi_id'] ?? '') ||
    !empty($password)) {
    $sensitive_changed = true;
}

if ($sensitive_changed) {
    // 1. Generate 6-digit OTP [1]
    $otp = strval(rand(100000, 999999));

    // 2. Cache sensitive changes temporarily inside Session [1]
    $_SESSION['temp_settings_data'] = [
        'full_name'    => $full_name,
        'email'        => $email,
        'bank_name'    => $bank_name,
        'bank_account' => $bank_account,
        'upi_id'       => $upi_id,
        'password'     => $password
    ];
    $_SESSION['settings_otp'] = $otp;
    $_SESSION['settings_otp_expiry'] = time() + 300; // 5-minute code expiration [1]

    // 3. Dispatch security OTP email via Gmail SMTP [1, 2]
    $purpose = "authorize updates to your sensitive bank/UPI settlement profiles";
    $email_sent = sendOtpEmail($_SESSION['email'], $_SESSION['full_name'], $otp, $purpose);

    if ($email_sent) {
        echo json_encode([
            'success'      => false,
            'otp_required' => true,
            'message'      => 'Step-up security challenge required. Verification code sent successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'SMTP mailer handshake failure. Please try again later.'
        ]);
    }
    exit();
} else {
    // No sensitive changes made (e.g. only legal name updated) -> Save to DB immediately [1]
    $db_update = $supabase->update('users', ['full_name' => $full_name], ['id' => $_SESSION['user_id']]);
    if ($db_update['status'] === 200) {
        $_SESSION['full_name'] = $full_name;
        echo json_encode(['success' => true, 'message' => 'Profile name saved successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase update failure.']);
    }
    exit();
}