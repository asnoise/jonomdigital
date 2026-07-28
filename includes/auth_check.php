<?php
// Prevent direct file access if not included by an authenticated application script
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// =========================================================================
// ANTI-CACHING SECURITY HEADERS (Solves Mobile Stale CSRF Token Bugs) [1.1.1, 1.1.5]
// =========================================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Historic expiration date

// Automatically load the database configuration to get access to SITE_URL [1]
$config_path = dirname(__DIR__) . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);
    }
}

// Securely initialize session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// =========================================================================
// REMEMBER ME SESSION RESTORATION LOGIC
// =========================================================================
if (empty($_SESSION['user_id']) && !empty($_COOKIE['jonom_remember_token'])) {
    $supabase_config_path = dirname(__DIR__) . '/config/supabase.php';
    if (file_exists($supabase_config_path)) {
        require_once $supabase_config_path;
        if (class_exists('SupabaseClient')) {
            $supabase = new SupabaseClient();

            $tokenPlain = $_COOKIE['jonom_remember_token'];
            $tokenHash = hash('sha256', $tokenPlain);

            // Query user matching unexpired remember token
            $res = $supabase->select('users', '*', [
                'remember_token' => $tokenHash
            ]);

            if (!empty($res['data'][0])) {
                $user = $res['data'][0];
                $expiresAt = $user['remember_expires_at'] ?? null;

                // Verify token validity and expiration timestamp
                if ($expiresAt && strtotime($expiresAt) > time()) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'] ?? 'artist';
                    // Re-bind session fingerprint for restored session
                    $_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
                } else {
                    // Token expired; strip stale cookie
                    setcookie('jonom_remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                }
            }
        }
    }
}

// Global CSRF Token Initialization
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validates session variables and role permissions.
 *
 * @param array $allowedRoles List of user roles allowed to access the page (e.g., ['artist', 'label'])
 */
function checkAccess(array $allowedRoles = []) {
    // 1. Authenticated validation
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }

    // 2. Session Hijacking Prevention
    $current_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
    if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== $current_fingerprint) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: ' . SITE_URL . '/index.php?error=session_invalidated');
        exit();
    }

    // 3. Role validation check
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        header('HTTP/1.1 403 Forbidden');
        echo "<div style='background:#121214; color:#fff; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif;'>";
        echo "<h1 style='color:#e74c3c; margin-bottom:10px;'>Access Denied</h1>";
        echo "<p style='color:#a7a7a7;'>You do not have the required permissions to access this administrative deck.</p>";
        echo "<a href='" . SITE_URL . "/index.php' style='color:#1db954; text-decoration:none; margin-top:20px; font-weight:bold;'>Return to Login Screen</a>";
        echo "</div>";
        exit();
    }
}