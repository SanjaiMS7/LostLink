<?php
/**
 * Authentication and access control.
 * Every page includes this file; it also loads config, db and functions.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function is_user_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** Redirects to the login page when no user is logged in. */
function require_login(): void
{
    if (!is_user_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

/** Restricts a page to authenticated administrators. */
function require_admin(): void
{
    if (!is_admin_logged_in()) {
        set_flash('error', 'Administrator login required.');
        redirect('admin/login.php');
    }
}

/** Returns the logged-in user's row (cached), or logs out if it no longer exists. */
function current_user(PDO $pdo): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (is_user_logged_in()) {
            $stmt = $pdo->prepare('SELECT user_id, name, register_no, email, department, phone FROM users WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if (!$user) {
                unset($_SESSION['user_id'], $_SESSION['user_name']);
            }
        }
    }
    return $user;
}

function logout_all(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
