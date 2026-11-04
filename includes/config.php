<?php
/**
 * LostLink configuration.
 * Change the values below only if your XAMPP setup is different.
 */

// ---- Database (XAMPP defaults: user "root", empty password) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'lostlink');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- URL path of the project (folder name inside htdocs) ----
define('BASE_URL', '/LostLink');

// ---- File uploads ----
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2 MB

define('APP_NAME', 'LostLink');
define('ITEMS_PER_PAGE', 12);

date_default_timezone_set('Asia/Kolkata');

// ---- Secure session start ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
