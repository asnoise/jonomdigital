<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(dirname(__DIR__)) . '/config/supabase.php';
require_once dirname(dirname(__DIR__)) . '/includes/email_helper.php'; // Load Email SMTP helper [2]

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Strictly verify the user has an active session and the Superadmin role [1.1.1]
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

$action = htmlspecialchars(strip_tags($_POST['action'] ?? 'create'));
$full_name = htmlspecialchars(strip_tags($_POST['full_name'] ?? ''));
$stage_name = htmlspecialchars(strip_tags($_POST['stage_name'] ?? ''));
$role = htmlspecialchars(strip_tags($_POST['role'] ?? 'artist'));
$status = htmlspecialchars(strip_tags($_POST['status'] ?? 'active'));
$record_label = htmlspecialchars(strip_tags($_POST['record_label'] ?? ''));

$supabase = new SupabaseClient();

// --- ACTION 1: CREATE USER (WITH AUTOMATED CORRESPONDING DATABASE PROFILE TRIGGERS) ---
if ($action === 'create') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'All mandatory parameters must be correctly filled.']);
        exit();
    }

    if (($role === 'artist' || $role === 'label') && (empty($stage_name) || empty($record_label))) {
        echo json_encode(['success' => false, 'message' => 'Stage Name and Primary Record Label are required for Artist/Label profiles.']);
        exit();
    }

    // Check if the email address is already registered
    $email_check = $supabase->select('users', '*', ['email' => $email]);
    if ($email_check['status'] === 200 && !empty($email_check['data'])) {
        echo json_encode(['success' => false, 'message' => 'Email address is already in use by another account.']);
        exit();
    }

    // A: AUTOMATIC SECURE PASSWORD GENERATION
    $allowed_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#%';
    $plain_password = substr(str_shuffle($allowed_chars), 0, 12);
    $password_hash = password_hash($plain_password, PASSWORD_BCRYPT);

    $user_data = [
        'email'         => $email,
        'password_hash' => $password_hash,
        'full_name'     => $full_name,
        'stage_name'    => !empty($stage_name) ? $stage_name : $full_name, // Sync stage name
        'role'          => $role,
        'status'        => $status
    ];

    $db_insert = $supabase->insert('users', $user_data);

    if ($db_insert['status'] === 201 || $db_insert['status'] === 200) {
        $created_user_id = $db_insert['data'][0]['id'];

        // B: AUTOMATIC COMPLIANCE PROVISIONING [1]
        if ($role === 'artist' || $role === 'label') {
            
            // 1. Auto-Insert the Primary Record Label into labels table [1]
            $label_data = [
                'user_id'             => $created_user_id,
                'name'                => $record_label,
                'entity_type'         => 'individual', 
                'registration_number' => 'Individual',
                'phone'               => 'Registered',
                'country'             => 'India',
                'status'              => 'verified' 
            ];
            $supabase->insert('labels', $label_data);

            // 2. If registration is an Artist, auto-insert into managed artists list [1]
            if ($role === 'artist') {
                $artist_profile_data = [
                    'user_id'      => $created_user_id,
                    'stage_name'   => $stage_name,
                    'legal_name'   => $full_name,
                    'artist_email' => $email,
                    'status'       => 'active'
                ];
                $supabase->insert('artists', $artist_profile_data);
            }
        }

        // TRIGGER AUTOMATED EMAILJS/SMTP WELCOME NOTIFICATION [1, 2]
        // Maps their stage name as the greeting username [1, 2]
        $greeting_name = !empty($stage_name) ? $stage_name : $full_name;
        sendWelcomeEmail($email, $greeting_name, $plain_password);

        // Log action to global system audits [1]
        $supabase->insert('audit_logs', [
            'user_id'    => $_SESSION['user_id'],
            'action'     => "Superadmin registered account: " . $email . " (" . $role . ") and auto-sent SMTP welcome credentials.",
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);

        echo json_encode([
            'success'        => true, 
            'message'        => 'Account provisioned successfully!',
            'email'          => $email,
            'plain_password' => $plain_password
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase user creation failed. DB Status: ' . $db_insert['status']]);
    }
    exit();
}

// --- ACTION 2: EDIT / UPDATE USER ---
if ($action === 'edit') {
    $user_id = htmlspecialchars(strip_tags($_POST['user_id'] ?? ''));

    if (empty($user_id) || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Required update targets are missing.']);
        exit();
    }

    if ($user_id === $_SESSION['user_id'] && $status === 'inactive') {
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own administrative session.']);
        exit();
    }

    $update_data = [
        'full_name'  => $full_name,
        'stage_name' => !empty($stage_name) ? $stage_name : null,
        'status'     => $status
    ];

    $db_update = $supabase->update('users', $update_data, ['id' => $user_id]);

    if ($db_update['status'] === 200) {
        $supabase->insert('audit_logs', [
            'user_id'    => $_SESSION['user_id'],
            'action'     => "Modified details for profile ID: " . $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);

        echo json_encode(['success' => true, 'message' => 'User profile updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase update operation failed. Status: ' . $db_update['status']]);
    }
    exit();
}