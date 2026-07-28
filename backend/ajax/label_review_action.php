<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(dirname(__DIR__)) . '/config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify Superadmin role [1.1.1]
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
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

$label_id = htmlspecialchars(strip_tags($input['label_id'] ?? ''));
$status = htmlspecialchars(strip_tags($input['status'] ?? '')); // verified, rejected

if (empty($label_id) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Target metrics missing.']);
    exit();
}

$supabase = new SupabaseClient();

// 3. Update Record Label application status in Supabase [1]
$db_label_update = $supabase->update('labels', ['status' => $status], ['id' => $label_id]);

if ($db_label_update['status'] === 200) {
    // 4. AUTOMATED ACCESS UPGRADE CHAIN [1]
    if ($status === 'verified') {
        // Query the linked user_id from the labels application
        $label_info = $supabase->select('labels', 'user_id, name', ['id' => $label_id]);
        
        if (!empty($label_info['data'])) {
            $linked_user_id = $label_info['data'][0]['user_id'];
            $label_name = $label_info['data'][0]['name'];

            // Dynamically upgrade the linked user's role to 'label' inside the users table
            $supabase->update('users', ['role' => 'label'], ['id' => $linked_user_id]);

            // Log event to global system audit records [1]
            $supabase->insert('audit_logs', [
                'user_id'    => $_SESSION['user_id'],
                'action'     => "Approved Record Label: " . $label_name . " and programmatically upgraded User ID: " . $linked_user_id . " to 'label' role.",
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
        }
    } else {
        // If rejected, log rejection event
        $supabase->insert('audit_logs', [
            'user_id'    => $_SESSION['user_id'],
            'action'     => "Rejected Record Label verification ID: " . $label_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Label status saved successfully! Linked user roles upgraded automatically [1].']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Supabase database update failed. Database returned code: ' . $db_label_update['status']
    ]);
}
exit();