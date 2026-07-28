<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure the security guard validates
define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to go up exactly one level (htdocs/)
require_once dirname(__DIR__) . '/config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_only_cookies', 1);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Session expired.']);
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

// 1. Capture and sanitize input datasets
$stage_name = htmlspecialchars(strip_tags($_POST['stage_name'] ?? ''));
$legal_name = htmlspecialchars(strip_tags($_POST['legal_name'] ?? ''));
$artist_email = filter_input(INPUT_POST, 'artist_email', FILTER_VALIDATE_EMAIL);
$iprs_id = htmlspecialchars(strip_tags($_POST['iprs_id'] ?? ''));

$spotify_url = filter_input(INPUT_POST, 'spotify_url', FILTER_VALIDATE_URL);
$apple_url = filter_input(INPUT_POST, 'apple_url', FILTER_VALIDATE_URL);
$facebook_url = filter_input(INPUT_POST, 'facebook_url', FILTER_VALIDATE_URL);
$instagram_url = filter_input(INPUT_POST, 'instagram_url', FILTER_VALIDATE_URL);

if (empty($stage_name) || empty($legal_name) || !$artist_email) {
    echo json_encode(['success' => false, 'message' => 'All required metrics must be correctly filled.']);
    exit();
}

$supabase = new SupabaseClient();

// 2. Prepare database dataset mapping
$artist_data = [
    'user_id' => $_SESSION['user_id'],
    'stage_name' => $stage_name,
    'legal_name' => $legal_name,
    'artist_email' => $artist_email,
    'iprs_id' => !empty($iprs_id) ? $iprs_id : null,
    'spotify_url' => $spotify_url ?: null,
    'apple_url' => $apple_url ?: null,
    'facebook_url' => $facebook_url ?: null,
    'instagram_url' => $instagram_url ?: null,
    'status' => 'active'
];

$db_insert = $supabase->insert('artists', $artist_data);

if ($db_insert['status'] === 201 || $db_insert['status'] === 200) {
    // Log transaction to global platform security audits
    $supabase->insert('audit_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => "Registered artist: " . $stage_name . " associated under Email: " . $artist_email,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Artist registered successfully!']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Supabase database query failed. Database returned code: ' . $db_insert['status']
    ]);
}
exit();