<?php
// includes/restore_session.php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Check if user session is already active
if (empty($_SESSION['user_id']) && !empty($_COOKIE['jonom_remember_token'])) {
    require_once __DIR__ . '/../config/supabase.php';
    $supabase = new SupabaseClient();

    $tokenPlain = $_COOKIE['jonom_remember_token'];
    $tokenHash = hash('sha256', $tokenPlain);

    // Query user matching the unexpired remember token
    $res = $supabase->select('users', '*', [
        'remember_token' => $tokenHash
    ]);

    if (!empty($res['data'][0])) {
        $user = $res['data'][0];
        $expiresAt = $user['remember_expires_at'] ?? null;

        // Verify token expiration timestamp
        if ($expiresAt && strtotime($expiresAt) > time()) {
            // Automatically regenerate and restore PHP session variables
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'] ?? 'artist';
        } else {
            // Expired token cleanup cookie
            setcookie('jonom_remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }
}