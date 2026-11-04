<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';

echo "<h2>LostLink Database Diagnostic</h2>";

echo "<b>PHP Version:</b> " . PHP_VERSION . "<br>";

echo "<b>PDO Extension:</b> ";

if (extension_loaded('pdo')) {
    echo "Enabled<br>";
} else {
    echo "Disabled<br>";
}

echo "<b>PDO MySQL Driver:</b> ";

if (extension_loaded('pdo_mysql')) {
    echo "Enabled<br>";
} else {
    echo "Disabled<br>";
}

echo "<b>Available PDO Drivers:</b> ";

print_r(PDO::getAvailableDrivers());

echo "<hr>";

try {

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    echo "<h3 style='color:green;'>Database Connection Successful!</h3>";

    echo "Connected to database: " . DB_NAME . "<br>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll();

    echo "<h4>Tables Found:</h4>";

    echo "<pre>";
    print_r($tables);
    echo "</pre>";

} catch (PDOException $e) {

    echo "<h3 style='color:red;'>Database Connection Failed!</h3>";

    echo "<b>Actual Error:</b><br>";

    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";

    echo "<b>Error Code:</b> " . htmlspecialchars($e->getCode());
}
?>
