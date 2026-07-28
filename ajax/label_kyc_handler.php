<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Standardize relative folder path lookup to htdocs/
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

// 1. Capture and sanitize text inputs [1]
$label_name = htmlspecialchars(strip_tags($_POST['label_name'] ?? ''));
$website = filter_input(INPUT_POST, 'website', FILTER_VALIDATE_URL);
$entity_type = htmlspecialchars(strip_tags($_POST['entity_type'] ?? 'corporate'));
$registration_number = htmlspecialchars(strip_tags($_POST['registration_number'] ?? ''));
$phone = htmlspecialchars(strip_tags($_POST['phone'] ?? ''));
$country = htmlspecialchars(strip_tags($_POST['country'] ?? 'India'));

if (empty($label_name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Label name and WhatsApp contact phone are required fields.']);
    exit();
}

$cert_relative_url = null;
$tax_relative_url = null;

// 2. Process conditional document uploads depending on Entity Type [1]
if ($entity_type === 'corporate') {
    if (empty($registration_number) || $registration_number === 'Individual') {
        echo json_encode(['success' => false, 'message' => 'Corporate Registration ID (GST/EIN) is required for registered corporate accounts.']);
        exit();
    }

    if (!isset($_FILES['cert_doc']) || $_FILES['cert_doc']['error'] !== UPLOAD_ERR_OK ||
        !isset($_FILES['tax_doc']) || $_FILES['tax_doc']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Missing or corrupt PDF/Image documentation files.']);
        exit();
    }

    $upload_dir = __DIR__ . '/../../uploads/kyc/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];

    // Process File 1: Certificate
    $cert_file = $_FILES['cert_doc'];
    $cert_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
    if (!in_array($cert_ext, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Certificate must strictly be formatted in PDF, JPG, or PNG.']);
        exit();
    }
    $safe_cert_name = uniqid('KYC_CERT_') . '.' . $cert_ext;
    $cert_destination = $upload_dir . $safe_cert_name;

    if (!move_uploaded_file($cert_file['tmp_name'], $cert_destination)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save Certificate file.']);
        exit();
    }
    $cert_relative_url = 'uploads/kyc/' . $safe_cert_name;

    // Process File 2: Tax Document
    $tax_file = $_FILES['tax_doc'];
    $tax_ext = strtolower(pathinfo($tax_file['name'], PATHINFO_EXTENSION));
    if (!in_array($tax_ext, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Tax Identification document must be formatted in PDF, JPG, or PNG.']);
        exit();
    }
    $safe_tax_name = uniqid('KYC_TAX_') . '.' . $tax_ext;
    $tax_destination = $upload_dir . $safe_tax_name;

    if (!move_uploaded_file($tax_file['tmp_name'], $tax_destination)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save Tax document file.']);
        exit();
    }
    $tax_relative_url = 'uploads/kyc/' . $safe_tax_name;

} else {
    // If Individual, bypass uploads and hardcode unique registration identifier [1]
    $registration_number = 'Individual';
}

$supabase = new SupabaseClient();

// 3. Map updates inside Supabase
$kyc_data = [
    'user_id'             => $_SESSION['user_id'],
    'name'                => $label_name,
    'website'             => $website ?: null,
    'entity_type'         => $entity_type,
    'registration_number' => $registration_number,
    'phone'               => $phone,
    'country'             => $country,
    'status'              => 'pending' // Re-triggers verification audits
];

// If corporate, save document URLs; if individual, they remain null/empty [1]
if ($entity_type === 'corporate') {
    $kyc_data['cert_doc'] = $cert_relative_url;
    $kyc_data['tax_doc']  = $tax_relative_url;
} else {
    $kyc_data['cert_doc'] = null;
    $kyc_data['tax_doc']  = null;
}

// Check if user has an existing label record to execute UPDATE vs INSERT [1]
$label_check = $supabase->select('labels', '*', ['user_id' => $_SESSION['user_id']]);

if ($label_check['status'] === 200 && !empty($label_check['data'])) {
    $db_result = $supabase->update('labels', $kyc_data, ['user_id' => $_SESSION['user_id']]);
} else {
    $db_result = $supabase->insert('labels', $kyc_data);
}

if ($db_result['status'] === 201 || $db_result['status'] === 200) {
    // Log transaction to global platform security audits
    $supabase->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'],
        'action'     => "Submitted KYC Document set for Record Label (" . $entity_type . "): " . $label_name,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'KYC registry and details saved successfully! Our compliance desk has been notified.']);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to write White Label parameters to Supabase. Database returned code: ' . $db_result['status']
    ]);
}
exit();