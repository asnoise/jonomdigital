<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/ [1]
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/email_helper.php'; // Load EmailJS helper [2]

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

$request_type = htmlspecialchars(strip_tags($_POST['request_type'] ?? ''));
$subject = htmlspecialchars(strip_tags($_POST['subject'] ?? ''));
$message = htmlspecialchars(strip_tags($_POST['message'] ?? ''));

if (empty($request_type) || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'All standard fields must be populated.']);
    exit();
}

// 1. Process Dynamic Options and Build Compiled Explanation Block
$extra_data = [];

if ($request_type === 'YouTube') {
    $extra_data['Artist Name'] = $_POST['yt_artist_name'] ?? '';
    $extra_data['YouTube Channel ID'] = $_POST['yt_channel_id'] ?? '';
    $extra_data['Artist Topic Channel URL'] = $_POST['yt_topic_url'] ?? '';
} elseif ($request_type === 'Facebook') {
    $extra_data['Track Name'] = $_POST['fb_track_name'] ?? '';
    $extra_data['Artist Name'] = $_POST['fb_artist_name'] ?? '';
    $extra_data['Facebook Profile URL'] = $_POST['fb_profile_url'] ?? '';
    $extra_data['Instagram Profile URL'] = $_POST['insta_profile_url'] ?? '';
} elseif ($request_type === 'Copyright') {
    $extra_data['Asset Name'] = $_POST['copyright_asset'] ?? '';
    $extra_data['Video URL(s)'] = $_POST['copyright_video_urls'] ?? '';
    $extra_data['Platform Name'] = $_POST['copyright_platform'] ?? '';
} elseif ($request_type === 'Spotify') {
    $sub_type = $_POST['spotify_sub_type'] ?? '';
    $extra_data['Relocation Request Sub-Type'] = $sub_type;
    if ($sub_type === 'My Release is on Another Artist Page') {
        $extra_data['Correct Artist URL'] = $_POST['spotify_correct_url'] ?? '';
        $extra_data['Album / Single URL(s)'] = $_POST['spotify_album_urls'] ?? '';
        $extra_data['Incorrect Artist URL'] = $_POST['spotify_incorrect_url'] ?? '';
    } elseif ($sub_type === "Another Artist's Release is on My Page") {
        $extra_data['My Artist URL'] = $_POST['spotify_my_url'] ?? '';
        $extra_data['Album / Single URL(s)'] = $_POST['spotify_album_urls'] ?? '';
    } elseif ($sub_type === 'Move Release to a New Artist Page') {
        $extra_data['Current Artist URL'] = $_POST['spotify_current_url'] ?? '';
        $extra_data['Album / Single URL(s)'] = $_POST['spotify_album_urls'] ?? '';
        $extra_data['New Artist Name'] = $_POST['spotify_new_artist'] ?? '';
    }
} elseif ($request_type === 'Playlist') {
    $extra_data['Release UPC / Details'] = $_POST['playlist_release_detail'] ?? '';
} elseif ($request_type === 'Release') {
    $sub_type = $_POST['release_sub_type'] ?? '';
    $extra_data['Release Issue Sub-Type'] = $sub_type;
    if ($sub_type === "Can't Find Release on DSP") {
        $extra_data['Store Name(s)'] = $_POST['release_store_names'] ?? '';
        $extra_data['Release UPC / Track Details'] = $_POST['release_upc_details'] ?? '';
    } elseif ($sub_type === "Release Approval") {
        $extra_data['Release UPC / Details'] = $_POST['release_upc_details'] ?? '';
    } elseif ($sub_type === "Release Takedown") {
        $extra_data['Store(s) to remove from'] = $_POST['release_takedown_stores'] ?? '';
        $extra_data['Release UPC / Details'] = $_POST['release_upc_details'] ?? '';
    }
}

// Format the dynamic meta fields into a clean message block
$compiled_message = "=== Support Request Fields ===\n";
foreach ($extra_data as $key => $val) {
    $compiled_message .= htmlspecialchars(strip_tags($key)) . ": " . htmlspecialchars(strip_tags($val)) . "\n";
}
$compiled_message .= "\n=== User Statement ===\n" . $message;

$supabase = new SupabaseClient();
$attached_file_cdn_url = null;

// 2. Handle Ticket File Attachment uploads to Supabase tickets bucket
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'wav'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_exts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Upload JPG, PNG, PDF, or WAV only.']);
        exit();
    }

    if ($file['size'] > 10485760) {
        echo json_encode(['success' => false, 'message' => 'Attachment file exceeds 10MB size limit.']);
        exit();
    }

    $safe_file_name = uniqid('ATTACH_') . '.' . $file_ext;

    // Stream straight to Supabase tickets bucket
    $attachment_upload_res = $supabase->upload('tickets', $safe_file_name, $file['tmp_name'], $file['type']);

    if ($attachment_upload_res['status'] !== 200 && $attachment_upload_res['status'] !== 201) {
        echo json_encode(['success' => false, 'message' => 'Failed to stream attachment document to Supabase Storage.']);
        exit();
    }

    $attached_file_cdn_url = SUPABASE_URL . '/storage/v1/object/public/tickets/' . $safe_file_name;
}

// 3. Write structured support case to Supabase PostgreSQL
$ticket_data = [
    'user_id'   => $_SESSION['user_id'],
    'category'  => $request_type,
    'subject'   => $subject,
    'message'   => $compiled_message,
    'status'    => 'new',
    'file_path' => $attached_file_cdn_url // Saves dynamic CDN URL
];

$db_insert = $supabase->insert('tickets', $ticket_data);

if ($db_insert['status'] === 201 || $db_insert['status'] === 200) {
    $new_ticket_id = $db_insert['data'][0]['id'] ?? 'NEW';
    $case_id_string = strtoupper(substr($new_ticket_id, 0, 8));

    // Log transaction to global platform audits
    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Opened Support Case #" . $case_id_string . " under " . $request_type,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    // TRIGGER EMAILJS NOTIFICATION: SUPPORT TICKET CREATED [2]
    $user_email = $_SESSION['email'] ?? 'jonomdigital@gmail.com';
    $user_name = $_SESSION['full_name'] ?? 'Artist Partner';

    $email_subject = "Support Case Opened: #" . $case_id_string;
    $email_title = "Support Case Opened";
    $email_body = "Hello " . $user_name . ",\n\nWe have successfully received your support request regarding '" . $subject . "'.\n\nYour Case ID is: #" . $case_id_string . "\nOur Support team has been notified and is currently auditing your request. We will update you as soon as possible. Thank you.";

    sendEmailNotification($user_email, $user_name, $email_subject, $email_title, $email_body);

    echo json_encode([
        'success' => true, 
        'message' => 'Support ticket ' . $case_id_string . ' created successfully! Receipt sent.'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save support request to database. Status: ' . $db_insert['status']
    ]);
}
exit();