<?php
// logout.php
define('SECURE_ACCESS', true);

// Enable verbose error logging for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once __DIR__ . '/config/supabase.php';

// Clear persistent database token if user ID exists in session
if (!empty($_SESSION['user_id'])) {
    try {
        $supabase = new SupabaseClient();
        $supabase->update('users', [
            'remember_token' => null,
            'remember_expires_at' => null
        ], ['id' => $_SESSION['user_id']]);
    } catch (Exception $e) {
        // Fallback silently if database connection fails during logout
    }
}

// Delete the 30-day Remember Me cookie
if (isset($_COOKIE['jonom_remember_token'])) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('jonom_remember_token', '', time() - 3600, '/', '', $isSecure, true);
}

// Clear all application session data and destroy session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect back to the login portal index page cleanly
header('Location: index.php');
exit();