<?php
/**
 * Database connection using PDO (prepared statements everywhere).
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif">Database connection failed.</h2>'
        . '<p style="font-family:sans-serif">Start MySQL in XAMPP, import <code>database.sql</code> '
        . 'and check the credentials in <code>includes/config.php</code>.</p>');
}
