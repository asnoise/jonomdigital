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

// 1. Strictly verify session is active and user is Moderator or Superadmin [1.1.1]
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['moderation', 'superadmin'])) {
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

// 3. Capture and sanitize administrative inputs [1]
$target_user_id = htmlspecialchars(strip_tags($_POST['target_user_id'] ?? ''));
$title = htmlspecialchars(strip_tags($_POST['title'] ?? ''));
$main_artist = htmlspecialchars(strip_tags($_POST['main_artist'] ?? ''));
$upc = htmlspecialchars(strip_tags($_POST['upc'] ?? ''));
$isrc = strtoupper(htmlspecialchars(strip_tags(trim($_POST['isrc'] ?? ''))));
$status = htmlspecialchars(strip_tags($_POST['status'] ?? 'approved'));

if (empty($target_user_id) || empty($title) || empty($main_artist) || empty($upc) || empty($isrc)) {
    echo json_encode(['success' => false, 'message' => 'All mandatory parameters must be correctly populated.']);
    exit();
}

$supabase = new SupabaseClient();

// 4. Register the new release administratively (Corrected: Removed non-existent record_label key) [1]
$release_data = [
    'user_id'               => $target_user_id,
    'title'                 => $title,
    'main_artist'           => $main_artist,
    'release_format'        => 'single', // Default single
    'genre'                 => 'Devotional/Bhajan', // Default fallback
    'metadata_language'     => 'English',
    'original_release_date' => date('Y-m-d'),
    'go_live_date'          => date('Y-m-d'),
    'artwork_filepath'      => 'assets/images/artwork.png', // Default Artwork specified [1]
    'upc'                   => $upc,
    'smartlink'             => 'https://smartlink.jonomdigital.com/' . uniqid(), // Auto-generate fallback link [1]
    'status'                => $status
];

$db_response = $supabase->insert('releases', $release_data);

if ($db_response['status'] !== 201 && $db_response['status'] !== 200) {
    echo json_encode(['success' => false, 'message' => 'Supabase DB write operation failure. Code: ' . $db_response['status']]);
    exit();
}

$created_release = $db_response['data'][0] ?? null;
if (!$created_release) {
    $latest_rel_query = $supabase->select('releases', 'id', ['user_id' => $target_user_id]);
    if (!empty($latest_rel_query['data'])) {
        $created_release = $latest_rel_query['data'][0];
    }
}

$release_id = $created_release['id'] ?? null;

if (empty($release_id)) {
    echo json_encode(['success' => false, 'message' => 'Unable to determine the ID of the provisioned release.']);
    exit();
}

// 5. Auto-Provision one single track linked to this release [1]
$track_data = [
    'release_id'     => $release_id,
    'title'          => $title,
    'composer'       => $main_artist,
    'lyricist'       => 'Traditional',
    'explicit'       => false,
    'audio_filepath' => 'audio/default.wav', // Default audio placeholder on Supabase storage [1, 2]
    'isrc'           => $isrc,
    'track_sequence' => 1
];

$db_track_res = $supabase->insert('tracks', $track_data);

if ($db_track_res['status'] !== 201 && $db_track_res['status'] !== 200) {
    echo json_encode(['success' => false, 'message' => 'Failed to auto-provision track node inside Supabase.']);
    exit();
}

// 6. Fetch user details and trigger EmailJS/PHPMailer confirmation [1, 2]
$owner_user_query = $supabase->select('users', 'email, full_name', ['id' => $target_user_id]);
if (!empty($owner_user_query['data'])) {
    $user_email = $owner_user_query['data'][0]['email'];
    $user_name = $owner_user_query['data'][0]['full_name'];

    // TRIGGER SMTP NOTIFICATION
    $email_subject = "New Release Configured: " . $title;
    $email_title = "Your Release has been Provisioned!";
    $email_body = "Hello " . $user_name . ",\n\nOur moderation team has administratively registered and approved your release '" . $title . "' in our system.\n\nUniversal Product Code (UPC): " . $upc . "\nTrack ISRC: " . $isrc . "\n\nThese details are now synchronized with your partner dashboard catalog.";

    sendEmailNotification($user_email, $user_name, $email_subject, $email_title, $email_body);
}

// Log action to audit logs [1]
$supabase->insert('audit_logs', [
    'user_id'    => $_SESSION['user_id'],
    'action'     => "Administratively created and approved Release ID: " . $release_id . " with UPC: " . $upc . " for User ID: " . $target_user_id,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode(['success' => true, 'message' => 'Admin release provisioned successfully! User notified via SMTP [1, 2].']);
exit();