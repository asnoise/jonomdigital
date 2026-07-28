<?php
header('Content-Type: application/json');
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../config/supabase.php';

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

// CSRF Validation Check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failure.']);
    exit();
}

$requested_amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

if (!$requested_amount || $requested_amount < 50.00) {
    echo json_encode(['success' => false, 'message' => 'Strict parameter check failed. Minimum withdrawal value is $50.00.']);
    exit();
}

$supabase = new SupabaseClient();

// Query available balance securely
$wallet_query = $supabase->select('wallets', '*', ['user_id' => $_SESSION['user_id']]);
if ($wallet_query['status'] !== 200 || empty($wallet_query['data'])) {
    echo json_encode(['success' => false, 'message' => 'Associated ledger structure missing.']);
    exit();
}

$wallet = $wallet_query['data'][0];
$available_balance = (float)$wallet['available_balance'];

if ($requested_amount > $available_balance) {
    echo json_encode(['success' => false, 'message' => 'Deficient wallet balance. Withdrawal amount exceeds your available funds.']);
    exit();
}

// Begin transaction mechanics by moving balance from available_balance to pending_balance
$new_available = $available_balance - $requested_amount;
$new_pending = (float)$wallet['pending_balance'] + $requested_amount;

$wallet_update = $supabase->update('wallets', [
    'available_balance' => $new_available,
    'pending_balance' => $new_pending
], ['user_id' => $_SESSION['user_id']]);

if ($wallet_update['status'] !== 200) {
    echo json_encode(['success' => false, 'message' => 'Failed to freeze balance on server backend database.']);
    exit();
}

// Log withdrawal to payout settlement tracking queue
$payout_response = $supabase->insert('payout_requests', [
    'user_id' => $_SESSION['user_id'],
    'amount' => $requested_amount,
    'status' => 'pending' // Initial status
]);

// Write event to global audit records
$supabase->insert('audit_logs', [
    'user_id' => $_SESSION['user_id'],
    'action' => "Created payout request value: $" . $requested_amount,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode(['success' => true, 'message' => 'Withdrawal request posted successfully. Processing transfer validation queue.']);