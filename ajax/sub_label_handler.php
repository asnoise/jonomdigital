<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(__DIR__) . '/config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
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

// Validate operational CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

// 1. Capture and sanitize sub-brand details [1]
$sub_label_name = htmlspecialchars(strip_tags($_POST['sub_label_name'] ?? ''));
$website = filter_input(INPUT_POST, 'website', FILTER_VALIDATE_URL);
$parent_label_id = htmlspecialchars(strip_tags($_POST['parent_label_id'] ?? ''));
$country = htmlspecialchars(strip_tags($_POST['country'] ?? 'India'));

if (empty($sub_label_name) || empty($parent_label_id)) {
    echo json_encode(['success' => false, 'message' => 'Mandatory sub-label parameters are missing.']);
    exit();
}

$supabase = new SupabaseClient();

// Verify that the submitting user actually owns the specified parent label [1]
$parent_check = $supabase->select('labels', 'id, status', [
    'id' => $parent_label_id,
    'user_id' => $_SESSION['user_id']
]);

if ($parent_check['status'] !== 200 || empty($parent_check['data'])) {
    echo json_encode(['success' => false, 'message' => 'Owner verification failed. Access denied.']);
    exit();
}

$parent_status = $parent_check['data'][0]['status'];
if ($parent_status !== 'verified') {
    echo json_encode(['success' => false, 'message' => 'Your parent label must be fully verified by administrators before registering sub-labels [1].']);
    exit();
}

// Check for unique sub-label naming conflicts in database
$duplicate_check = $supabase->select('labels', 'id', ['name' => $sub_label_name]);
if ($duplicate_check['status'] === 200 && !empty($duplicate_check['data'])) {
    echo json_encode(['success' => false, 'message' => 'This sub-label brand is already registered in the system database.']);
    exit();
}

// 2. Prepare database dataset mapping
// Sub-labels are auto-approved ('verified') directly under parent validation chains [1]
$sub_label_data = [
    'user_id'             => $_SESSION['user_id'],
    'parent_label_id'     => $parent_label_id,
    'name'                => $sub_label_name,
    'website'             => $website ?: null,
    'entity_type'         => 'individual',
    'registration_number' => 'Sub-Label',
    'phone'               => 'Inherited',
    'country'             => $country,
    'is_sub_label'        => true,
    'status'              => 'verified' 
];

$db_result = $supabase->insert('labels', $sub_label_data);

if ($db_result['status'] === 201 || $db_result['status'] === 200) {
    // Log transaction to global platform security audits [1]
    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Registered child Sub-Label: " . $sub_label_name . " linked to Parent Label ID: " . $parent_label_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Sub-Label brand configured and activated successfully! You can select it during release submissions [1].']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save sub-label parameters to Supabase. Database returned code: ' . $db_result['status']
    ]);
}
exit();