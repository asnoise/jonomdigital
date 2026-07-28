<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(dirname(__DIR__)) . '/config/supabase.php';
require_once dirname(dirname(__DIR__)) . '/includes/email_helper.php'; // Load EmailJS helper [2]

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Strictly verify the user session is active and holds appropriate roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['moderation', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Access denied.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload structure.']);
    exit();
}

// 2. Validate operational CSRF Token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$release_id = htmlspecialchars(strip_tags($input['release_id'] ?? ''));
$status = htmlspecialchars(strip_tags($input['status'] ?? '')); 
$rejection_reason = htmlspecialchars(strip_tags($input['rejection_reason'] ?? ''));

if (empty($release_id) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Required parameters missing.']);
    exit();
}

$supabase = new SupabaseClient();

// Query submitting user's contact details [1]
$rel_owner_query = $supabase->select('releases', 'user_id, title', ['id' => $release_id]);
$release_title = $rel_owner_query['data'][0]['title'] ?? 'Untitled Release';
$owner_id = $rel_owner_query['data'][0]['user_id'] ?? null;

$user_email = 'jonomdigital@gmail.com';
$user_name = 'Artist Partner';

if ($owner_id) {
    $owner_user_query = $supabase->select('users', 'email, full_name', ['id' => $owner_id]);
    $user_email = $owner_user_query['data'][0]['email'] ?? $user_email;
    $user_name = $owner_user_query['data'][0]['full_name'] ?? $user_name;
}

// =========================================================================
// 3. PROCESS APPROVED STATUS & SAVE DISTRIBUTION CODES
// =========================================================================
if ($status === 'approved') {
    $upc = htmlspecialchars(strip_tags($input['upc'] ?? ''));
    $smartlink = filter_var($input['smartlink'] ?? '', FILTER_VALIDATE_URL);
    $isrcs = $input['isrcs'] ?? []; 

    if (empty($upc) || !$smartlink || empty($isrcs)) {
        echo json_encode(['success' => false, 'message' => 'Failed to approve. UPC, valid landing Smartlink, and track ISRCs are required to verify distribution delivery.']);
        exit();
    }

    $db_release_update = $supabase->update('releases', [
        'status' => 'approved',
        'upc' => $upc,
        'smartlink' => $smartlink
    ], ['id' => $release_id]);

    if ($db_release_update['status'] !== 200) {
        echo json_encode(['success' => false, 'message' => 'Supabase transaction failure during distribution code updates.']);
        exit();
    }

    // Update each track ISRC code [1]
    foreach ($isrcs as $track_id => $isrc_value) {
        $clean_track_id = htmlspecialchars(strip_tags($track_id));
        $clean_isrc_value = strtoupper(htmlspecialchars(strip_tags(trim($isrc_value))));
        $supabase->update('tracks', ['isrc' => $clean_isrc_value], ['id' => $clean_track_id]);
    }

    // Log to audits
    $supabase->insert('audit_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => "Approved and delivered Release ID: " . $release_id . " with UPC: " . $upc,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    // TRIGGER EMAILJS NOTIFICATION: APPROVED [2]
    $email_subject = "Release Live on DSPs: " . $release_title;
    $email_title = "Your Release is Approved!";
    $email_body = "Congratulations, " . $user_name . "!\n\nYour catalog submission '" . $release_title . "' has been approved by our moderation team and delivered to global DSPs.\n\nUniversal Product Code (UPC): " . $upc . "\nYour landing Smartlink: " . $smartlink . "\n\nThank you for distributing with Jonom Digital India.";
    
    sendEmailNotification($user_email, $user_name, $email_subject, $email_title, $email_body);

    echo json_encode(['success' => true, 'message' => 'Release codes saved successfully! Catalog items delivered to DSPs. Recipient notified.']);
    exit();
}

// =========================================================================
// 4. PROCESS CORRECTIONS & FLAT REJECTIONS
// =========================================================================
$update_data = ['status' => $status];
if ($status === 'correction' || $status === 'rejected') {
    $update_data['rejection_reason'] = $rejection_reason;
}

$db_update = $supabase->update('releases', $update_data, ['id' => $release_id]);

if ($db_update['status'] === 200) {
    // Log event to global system audit records
    $supabase->insert('audit_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => "Updated Release ID: " . $release_id . " state to: " . $status,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    // TRIGGER EMAILJS NOTIFICATION: CORRECTION/REJECTION [1, 2]
    if ($status === 'correction') {
        $email_subject = "Correction Required on: " . $release_title;
        $email_title = "Release Modification Required";
        $email_body = "Hello " . $user_name . ",\n\nOur moderation team has audited your catalog submission '" . $release_title . "' and determined that corrections are required before distribution.\n\nReason for Correction:\n" . $rejection_reason . "\n\nPlease log into your dashboard, view the correction details, and resubmit your assets. Thank you.";
    } else {
        $email_subject = "Catalog Submission Rejected: " . $release_title;
        $email_title = "Release Audit Rejected";
        $email_body = "Hello " . $user_name . ",\n\nWe regret to inform you that your catalog submission '" . $release_title . "' has been rejected during moderation audits.\n\nReason for Rejection:\n" . $rejection_reason . "\n\nIf you have any queries, please open a support inquiry.";
    }

    sendEmailNotification($user_email, $user_name, $email_subject, $email_title, $email_body);

    echo json_encode(['success' => true, 'message' => 'Release modification logged successfully! Recipient notified.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to log release updates in Supabase.']);
}
exit();