<?php
header('Content-Type: application/json');

// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/config/supabase.php [1.1.1]
require_once dirname(dirname(__DIR__)) . '/config/supabase.php';
require_once dirname(dirname(__DIR__)) . '/includes/email_helper.php'; // Load PHPMailer SMTP helper [2]

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Strictly verify session is active and user is Financial Team or Superadmin [1.1.1]
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['financial', 'superadmin'])) {
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

$settlement_period = htmlspecialchars(strip_tags($_POST['settlement_period'] ?? ''));

if (empty($settlement_period)) {
    echo json_encode(['success' => false, 'message' => 'Please select the target settlement month.']);
    exit();
}

// 3. Validate Uploaded CSV File
if (!isset($_FILES['royalty_csv']) || $_FILES['royalty_csv']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Missing or corrupt CSV file.']);
    exit();
}

$csv_file = $_FILES['royalty_csv'];
$file_content = file_get_contents($csv_file['tmp_name']);

if (!$file_content) {
    echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file contents.']);
    exit();
}

// =========================================================================
// STAGE 1: ENCODING CONVERTER & UNIX LINE ENDINGS NORMALIZER
// =========================================================================
if (substr($file_content, 0, 2) === "\xFF\xFE" || substr($file_content, 0, 2) === "\xFE\xFF") {
    $file_content = mb_convert_encoding($file_content, 'UTF-8', 'UTF-16');
} elseif (substr($file_content, 0, 3) === "\xEF\xBB\xBF") {
    $file_content = substr($file_content, 3);
}

$file_content = str_replace("\r", "\n", str_replace("\r\n", "\n", $file_content));
$lines = explode("\n", $file_content);
$total_lines = count($lines);

// =========================================================================
// STAGE 2: DELIMITER DETECTOR (Semicolon/Tab/Comma) [1]
// =========================================================================
$first_line = trim($lines[0] ?? '');
$comma_count = substr_count($first_line, ',');
$semicolon_count = substr_count($first_line, ';');
$tab_count = substr_count($first_line, "\t");

$delimiter = ',';
$max = $comma_count;

if ($semicolon_count > $max) {
    $delimiter = ';';
    $max = $semicolon_count;
}
if ($tab_count > $max) {
    $delimiter = "\t";
    $max = $tab_count;
}

$first_line_headers = str_getcsv($first_line, $delimiter);

// =========================================================================
// STAGE 3: HARDCODED COLUMN INDICES (A=0, K=10, P=15, R=17) [1]
// =========================================================================
$email_idx = 0;     // Column A: Email Id
$platform_idx = 10; // Column K: Platforms
$stream_idx = 15;   // Column P: Stream
$rupee_idx = 17;    // Column R: Earning (₹)

$user_credits = [];
$first_data_row = [];
$processed_count = 0;
$supabase = new SupabaseClient();

// Read rows starting from Index 1 (skipping header row) [1]
for ($i = 1; $i < $total_lines; $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) {
        continue;
    }

    $row = str_getcsv($line, $delimiter);
    
    if (empty($first_data_row)) {
        $first_data_row = $row;
    }

    $email = strtolower(trim($row[$email_idx] ?? '')); 
    $track_title = trim($row[$1] ?? 'Untitled');       // Column B: Track Title
    $platform = trim($row[$platform_idx] ?? 'DSP');    
    $streams_raw = trim($row[$stream_idx] ?? '0');     
    $rupee_earnings_raw = trim($row[$rupee_idx] ?? '0.00'); 
    $usd_earnings_raw = trim($row[16] ?? '0.00');      // Column Q: Earnings($)

    if (empty($email) || empty($rupee_earnings_raw)) {
        continue;
    }

    // Clean currency characters and extract numeric floats
    $rupee_earnings = (float)preg_replace('/[^0-9.]/', '', $rupee_earnings_raw);
    $usd_earnings = (float)preg_replace('/[^0-9.]/', '', $usd_earnings_raw);
    $streams = (int)preg_replace('/[^0-9]/', '', $streams_raw);

    if ($rupee_earnings <= 0.00) {
        continue;
    }

    // Look up target user ID dynamically [1]
    $user_query = $supabase->select('users', 'id', ['email' => $email]);
    if ($user_query['status'] !== 200 || empty($user_query['data'])) {
        continue; // Skip if user account is missing from database
    }
    $target_user_id = $user_query['data'][0]['id'];

    // =========================================================================
    // WRITE GRANULAR STREAM LOG TO THE ROYALTY REPORTS ANALYTICS TABLE [1]
    // =========================================================================
    $supabase->insert('royalty_reports', [
        'user_id'           => $target_user_id,
        'track_title'       => $track_title,
        'track_artist'      => htmlspecialchars(strip_tags(trim($row[2] ?? ''))), // Column C
        'album_title'       => htmlspecialchars(strip_tags(trim($row[3] ?? ''))), // Column D
        'label_name'        => htmlspecialchars(strip_tags(trim($row[5] ?? ''))), // Column F
        'isrc'              => htmlspecialchars(strip_tags(trim($row[6] ?? ''))), // Column G
        'upc'               => htmlspecialchars(strip_tags(trim($row[7] ?? ''))), // Column H
        'settlement_month'  => $settlement_period,
        'platform'          => $platform,
        'currency'          => htmlspecialchars(strip_tags(trim($row[11] ?? ''))), // Column L
        'territory'         => htmlspecialchars(strip_tags(trim($row[12] ?? ''))), // Column M
        'downloads'         => (int)preg_replace('/[^0-9]/', '', $row[13] ?? '0'),  // Column N
        'creations'         => (int)preg_replace('/[^0-9]/', '', $row[14] ?? '0'),  // Column O
        'streams'           => $streams,
        'original_earnings' => $usd_earnings,
        'rupee_earnings'    => $rupee_earnings
    ]);

    // Accumulate sums for aggregate wallet credits [1]
    if (!isset($user_credits[$email])) {
        $user_credits[$email] = [
            'rupee_sum' => 0.00,
            'streams_sum' => 0,
            'platforms' => []
        ];
    }

    $user_credits[$email]['rupee_sum'] += $rupee_earnings;
    $user_credits[$email]['streams_sum'] += $streams;
    
    if (!empty($platform) && !in_array($platform, $user_credits[$email]['platforms'])) {
        $user_credits[$email]['platforms'][] = $platform;
    }
}

fclose($file_handle);

// =========================================================================
// STAGE 3: DATABASE WALLET COMMITS & EMAIL NOTIFICATIONS [1, 2]
// =========================================================================
foreach ($user_credits as $user_email => $data) {
    $user_query = $supabase->select('users', 'id, full_name', ['email' => $user_email]);
    $target_user_id = $user_query['data'][0]['id'];
    $user_name = $user_query['data'][0]['full_name'];

    $wallet_query = $supabase->select('wallets', '*', ['user_id' => $target_user_id]);
    $wallet = $wallet_query['data'][0] ?? null;

    if ($wallet) {
        $credited_rupees = $data['rupee_sum'];
        
        $new_available = (float)$wallet['available_balance'] + $credited_rupees;
        $new_lifetime = (float)$wallet['lifetime_earnings'] + $credited_rupees;

        $supabase->update('wallets', [
            'available_balance' => $new_available,
            'lifetime_earnings' => $new_lifetime
        ], ['user_id' => $target_user_id]);

        $source_description = "Royalty Distribution - " . $settlement_period;
        $supabase->insert('transactions', [
            'user_id' => $target_user_id,
            'amount' => $credited_rupees,
            'type' => 'credit',
            'source' => $source_description
        ]);

        $platforms_list_string = implode(', ', $data['platforms']);

        // TRIGGER SMTP NOTIFICATION [2]
        $subject = "Royalty Statement Credited: " . $settlement_period;
        $title = "Your Monthly Royalties are Credited!";
        $body = "Hello " . $user_name . ",\n\nWe are pleased to notify you that your monthly royalty statement has been processed.\n\nStatement Month: " . $settlement_period . "\nAmount Credited: ₹" . number_format($credited_rupees, 2) . "\nTotal Streams: " . number_format($data['streams_sum']) . "\n\nParsed Platforms:\n" . $platforms_list_string . "\n\nThese details have been updated on your dynamic dashboard.";

        sendEmailNotification($user_email, $user_name, $subject, $title, $body);

        // Insert real-time Notification inside Supabase [1]
        $notif_title = "Revenue Report Processed";
        $notif_message = "Your royalty statement for " . $settlement_period . " is processed! ₹" . number_format($credited_rupees, 2) . " has been credited to your wallet.";
        $supabase->insert('notifications', [
            'user_id' => $target_user_id,
            'title'   => $notif_title,
            'message' => $notif_message,
            'type'    => 'revenue'
        ]);

        $processed_count++;
    }
}

if ($processed_count === 0) {
    echo json_encode([
        'success' => false,
        'message' => "Import processed 0 wallets.\n\n[Diagnostics - Server read details]:\n- Detected Delimiter: '" . $delimiter . "'\n- First Row Headers: " . json_encode(array_slice($first_line_headers, 0, 4)) . "...\n- First Data Row: " . json_encode(array_slice($first_data_row, 0, 4)) . "...\n\n[Fix]: Ensure the emails in Column A match your active users exactly [1]."
    ]);
    exit();
}

// Log action to audits
$supabase->insert('audit_logs', [
    'user_id'    => $_SESSION['user_id'],
    'action'     => "Imported royalty statement CSV sheet with Email matching for period: " . $settlement_period . ". Processed: " . $processed_count,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode([
    'success' => true, 
    'message' => 'Royalty statement processed successfully!', 
    'processed_rows' => $processed_count
]);
exit();