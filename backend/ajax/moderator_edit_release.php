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

// =========================================================================
// ROLE-BASED ACCESS CONTROL (Restricted to Moderation & Superadmin) [1]
// =========================================================================
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['moderation', 'superadmin'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Access denied.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload structure.']);
    exit();
}

// Validate operational CSRF Token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$release_id = htmlspecialchars(strip_tags($input['release_id'] ?? ''));
$title = htmlspecialchars(strip_tags($input['title'] ?? ''));
$main_artist = htmlspecialchars(strip_tags($input['main_artist'] ?? ''));
$upc = htmlspecialchars(strip_tags($input['upc'] ?? ''));
$smartlink = filter_var($input['smartlink'] ?? '', FILTER_VALIDATE_URL);
$set_default_artwork = htmlspecialchars(strip_tags($input['set_default_artwork'] ?? '0'));
$status = htmlspecialchars(strip_tags($input['status'] ?? 'pending'));
$isrcs = $input['isrcs'] ?? []; // Associative array [track_id => ISRC_string] [1]

if (empty($release_id) || empty($title) || empty($main_artist) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'All mandatory parameters (Title, Singer, and Status) must be populated.']);
    exit();
}

$supabase = new SupabaseClient();

// Compile metadata update block [1]
$update_data = [
    'title' => $title,
    'main_artist' => $main_artist,
    'status' => $status
];

if (!empty($upc)) {
    $update_data['upc'] = $upc;
}
if ($smartlink) {
    $update_data['smartlink'] = $smartlink;
}

// Override to default artwork path if requested [1]
if ($set_default_artwork === '1') {
    $update_data['artwork_filepath'] = 'assets/images/artwork.png';
}

// Execute releases update in PostgreSQL
$db_release_update = $supabase->update('releases', $update_data, ['id' => $release_id]);

if ($db_release_update['status'] !== 200) {
    echo json_encode(['success' => false, 'message' => 'Supabase database update failed during metadata override.']);
    exit();
}

// Update track ISRCs if provided [1]
if (!empty($isrcs) && is_array($isrcs)) {
    foreach ($isrcs as $track_id => $isrc_val) {
        $clean_track_id = htmlspecialchars(strip_tags($track_id));
        $clean_isrc_val = strtoupper(htmlspecialchars(strip_tags(trim($isrc_val))));
        $supabase->update('tracks', ['isrc' => $clean_isrc_val], ['id' => $clean_track_id]);
    }
}

// Fetch user email and trigger Email notification [1, 2]
$rel_owner_query = $supabase->select('releases', 'user_id', ['id' => $release_id]);
$owner_id = $rel_owner_query['data'][0]['user_id'] ?? null;

$user_email = 'jonomdigitalindia@gmail.com'; // Fallback
$user_name = 'Artist Partner';

if ($owner_id) {
    $owner_user_query = $supabase->select('users', 'email, full_name', ['id' => $owner_id]);
    $user_email = $owner_user_query['data'][0]['email'] ?? $user_email;
    $user_name = $owner_user_query['data'][0]['full_name'] ?? $user_name;
}

// Compile custom context depending on administrative state changes [1, 2]
if ($status === 'approved') {
    $email_subject = "Release Live on DSPs: " . $title;
    $email_title = "Your Release has been Approved & Delivered!";
    $email_body = "Congratulations, " . $user_name . "!\n\nOur moderation team has verified and approved your release '" . $title . "' for distribution.\n\nUniversal Product Code (UPC): " . ($upc ?: 'Allocated') . "\nSmartlink Streaming Portal: " . ($smartlink ?: 'Generated') . "\n\nThank you for partner distributing with Jonom Digital Indian Distributor.";
} elseif ($status === 'taken_down') {
    $email_subject = "Catalog Takedown Processed: " . $title;
    $email_title = "Release Taken Down";
    $email_body = "Hello " . $user_name . ",\n\nWe are writing to notify you that your catalog submission '" . $title . "' has been taken down from global DSPs.\n\nReason: Metadata override or regulatory breach.\n\nIf you have any questions, please open a support inquiry.";
} else {
    $email_subject = "Release Status Updated: " . $title;
    $email_title = "Release Catalog Updated";
    $email_body = "Hello " . $user_name . ",\n\nOur moderation team has updated your release '" . $title . "' status in the system database to: " . strtoupper($status) . ".\n\nPlease log into your dashboard to view the changes.";
}

sendEmailNotification($user_email, $user_name, $email_subject, $email_title, $email_body);

// Log transaction to global platform security audits [1]
$supabase->insert('audit_logs', [
    'user_id'    => $_SESSION['user_id'],
    'action'     => "Administratively updated Release ID: " . $release_id . " to status: " . $status,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode(['success' => true, 'message' => 'Release modified and saved successfully! Recipient notified.']);
exit();