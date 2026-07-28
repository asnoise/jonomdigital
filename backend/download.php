<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// 1. Load validation context and absolute database/Supabase pathing [1.1.1]
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/config/supabase.php';

// Strictly restrict access to administrative accounts [1.1.1]
checkAccess(['moderation', 'superadmin']);

$file_key = $_GET['file'] ?? '';

if (empty($file_key)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Missing file reference.');
}

// 2. Prevent Directory Traversal Path Injection Attacks
$file_key = str_replace(['../', '..\\'], '', $file_key);

// Determine target storage parameters [2]
$parts = explode('/', $file_key, 2);
$bucket = $parts[0] ?? '';
$path = $parts[1] ?? '';

if (empty($bucket) || empty($path) || !in_array($bucket, ['audio', 'posters', 'tickets'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access to requested storage bucket is restricted.');
}

// 3. Request the binary file from Supabase Storage using Service Role privileges [2]
$supabase = new SupabaseClient();
$target_url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . ltrim($path, '/');

$ch = curl_init();
$headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY
];

curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 180); // Extends timeout for heavy WAV tracks
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ProFreeHost compatibility
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$file_binary = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$file_binary) {
    header('HTTP/1.1 404 Not Found');
    exit('Requested file could not be retrieved from secure cloud storage.');
}

// 4. Force browser to trigger a secure file download stream [1]
$filename = basename($path);
header('Content-Description: File Transfer');
if ($bucket === 'audio') {
    header('Content-Type: audio/wav');
} else {
    header('Content-Type: application/octet-stream');
}
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($file_binary));

echo $file_binary;
exit();