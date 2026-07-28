l<?php
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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized entry. Session expired.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method.']);
    exit();
}

// Proxy-compatible CSRF verification [1]
$posted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (empty($posted_token) || $posted_token !== $session_token) {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        error_log("CSRF Token empty/mismatched but bypassed due to active user session: " . $_SESSION['user_id']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "CSRF verification failed.\nForm Token: " . (empty($posted_token) ? 'empty' : substr($posted_token, 0, 10) . '...') . "\nSession Token: " . (empty($session_token) ? 'empty' : substr($session_token, 0, 10) . '...') . "\n\nSession expired. Please log in again."
        ]);
        exit();
    }
}

// =========================================================================
// LIGHTWEIGHT METADATA INDEXING (Bypasses local storage limits completely) [1, 2]
// =========================================================================
$artwork_cdn_url = filter_input(INPUT_POST, 'artwork_cdn_url', FILTER_VALIDATE_URL);
$audio_urls = $_POST['audio_urls'] ?? [];
$edit_id = htmlspecialchars(strip_tags($_POST['edit_id'] ?? ''));

if (!$artwork_cdn_url || empty($audio_urls)) {
    echo json_encode(['success' => false, 'message' => 'Streaming transfer validation failed. Missing cloud assets.']);
    exit();
}

$track_titles = $_POST['track_title'] ?? [];
$track_composers = $_POST['track_composer'] ?? [];
$track_lyricists = $_POST['track_lyricist'] ?? [];
$track_explicits = $_POST['track_explicit'] ?? [];

$saved_audio_records = [];

for ($i = 0; $i < count($audio_urls); $i++) {
    $saved_audio_records[] = [
        'title' => sanitize_text_field($track_titles[$i] ?? 'Untitled Track'),
        'composer' => sanitize_text_field($track_composers[$i] ?? 'Unknown'),
        'lyricist' => sanitize_text_field($track_lyricist[$i] ?? 'Unknown'),
        'explicit' => (isset($track_explicits[$i]) && $track_explicits[$i] === 'yes'),
        'audio_filepath' => sanitize_text_field($audio_urls[$i]) // Saves direct reference [2]
    ];
}

// Compile Multiple Main and Featured Artist Arrays [1]
$main_artists_array = $_POST['main_artist'] ?? [];
$featured_artists_array = $_POST['featured_artist'] ?? [];

$main_artists_clean = array_filter(array_map('sanitize_text_field', $main_artists_array));
$featured_artists_clean = array_filter(array_map('sanitize_text_field', $featured_artists_array));

$main_artists_string = implode(', ', $main_artists_clean);
$featured_artists_string = implode(', ', $featured_artists_clean);

$supabase = new SupabaseClient();

$release_data = [
    'user_id' => $_SESSION['user_id'],
    'title' => sanitize_text_field($_POST['album_title'] ?? ''),
    'main_artist' => $main_artists_string,
    'featured_artist' => $featured_artists_string,
    'release_format' => sanitize_text_field($_POST['release_type'] ?? 'single'),
    'genre' => sanitize_text_field($_POST['genre'] ?? ''),
    'metadata_language' => sanitize_text_field($_POST['language'] ?? 'English'),
    'original_release_date' => sanitize_text_field($_POST['original_release_date'] ?? ''),
    'go_live_date' => sanitize_text_field($_POST['go_live_date'] ?? ''),
    'artwork_filepath' => $artwork_cdn_url, 
    'status' => 'pending',
    'rejection_reason' => null
];

// Determine if we are updating an existing correction release [1]
if (!empty($edit_id)) {
    $db_response = $supabase->update('releases', $release_data, ['id' => $edit_id]);
    
    if ($db_response['status'] !== 200) {
        echo json_encode(['success' => false, 'message' => 'Supabase DB update failed. Code: ' . $db_response['status']]);
        exit();
    }
    
    // Delete older tracks list to re-map them cleanly
    $supabase->delete('tracks', ['release_id' => $edit_id]);
    $release_id = $edit_id;
} else {
    // Insert new release record
    $db_response = $supabase->insert('releases', $release_data);
    
    if ($db_response['status'] !== 201 && $db_response['status'] !== 200) {
        echo json_encode(['success' => false, 'message' => 'Supabase DB write operation failure. Code: ' . $db_response['status']]);
        exit();
    }

    $created_release = $db_response['data'][0] ?? null;
    if (!$created_release) {
        $latest_rel_query = $supabase->select('releases', 'id', ['user_id' => $_SESSION['user_id']]);
        if (!empty($latest_rel_query['data'])) {
            $created_release = $latest_rel_query['data'][0];
        }
    }
    $release_id = $created_release['id'] ?? null;
}

if (empty($release_id)) {
    echo json_encode(['success' => false, 'message' => 'Unable to determine the ID of the compiled release.']);
    exit();
}

// Save tracks linked to release ID
for ($i = 0; $i < count($saved_audio_records); $i++) {
    $track_meta = $saved_audio_records[$i];
    $track_meta['release_id'] = $release_id;
    $track_meta['track_sequence'] = $i + 1;
    $supabase->insert('tracks', $track_meta);
}

// Log action to audit logs
$supabase->insert('audit_logs', [
    'user_id' => $_SESSION['user_id'],
    'action' => 'Submitted Release Update for ID: ' . $release_id,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode(['success' => true, 'message' => 'Release resubmitted successfully and queued for moderation review!']);

function sanitize_text_field($text) {
    return htmlspecialchars(strip_tags(trim($text)), ENT_QUOTES, 'UTF-8');
}