<?php
header('Content-Type: application/json');
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../config/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// REST security bounds check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['financial', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized call. Session ended.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input data.']);
    exit();
}

// Validate operational CSRF Token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$request_id = htmlspecialchars(strip_tags($input['request_id'] ?? ''));
$action = htmlspecialchars(strip_tags($input['action'] ?? '')); // approved, rejected

if (empty($request_id) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Required parameters missing.']);
    exit();
}

$supabase = new SupabaseClient();

// Query payout parameters details
$payout_query = $supabase->select('payout_requests', '*', ['id' => $request_id]);
if ($payout_query['status'] !== 200 || empty($payout_query['data'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID trace missing from transactional records.']);
    exit();
}

$payout_request_record = $payout_query['data'][0];
$requester_user_id = $payout_request_record['user_id'];
$amount_withdrawn = (float)$payout_request_record['amount'];

// Query target user wallet indices
$wallet_query = $supabase->select('wallets', '*', ['user_id' => $requester_user_id]);
$wallet = $wallet_query['data'][0] ?? null;

if (!$wallet) {
    echo json_encode(['success' => false, 'message' => 'Corresponding wallet for payout requester is missing.']);
    exit();
}

$new_pending = (float)$wallet['pending_balance'] - $amount_withdrawn;

if ($action === 'approved') {
    // Deduct pending balance on payout approval
    $db_wallet_update = $supabase->update('wallets', ['pending_balance' => $new_pending], ['user_id' => $requester_user_id]);
    
    // Insert debit trace record
    $supabase->insert('transactions', [
        'user_id' => $requester_user_id,
        'amount' => $amount_withdrawn,
        'type' => 'debit',
        'source' => 'Bank Payout Withdrawal Complete'
    ]);
    
    // Update payout requests tracker
    $supabase->update('payout_requests', ['status' => 'paid'], ['id' => $request_id]);
} elseif ($action === 'rejected') {
    // On payout rejection, return pending balance to the user's available balance
    $new_available = (float)$wallet['available_balance'] + $amount_withdrawn;
    $db_wallet_update = $supabase->update('wallets', [
        'pending_balance' => $new_pending,
        'available_balance' => $new_available
    ], ['user_id' => $requester_user_id]);
    
    // Update payout requests tracker
    $supabase->update('payout_requests', ['status' => 'rejected'], ['id' => $request_id]);
}

// Log action to audit logs
$supabase->insert('audit_logs', [
    'user_id' => $_SESSION['user_id'],
    'action' => "Processed payout request ID " . $request_id . " to state: " . $action,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode(['success' => true, 'message' => 'Payout state modification transaction complete!']);