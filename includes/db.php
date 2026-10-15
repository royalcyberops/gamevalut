<?php
// includes/db.php
// Central PDO connection for GameVault (XAMPP defaults).
// Used by every page via: require_once __DIR__ . '/db.php';

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';            // XAMPP default: empty password
$DB_NAME = 'gamevault_db';

try {
    $conn = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // queries throw, like MYSQLI_REPORT_STRICT did
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements; native int/float values
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch() returns assoc rows, like fetch_assoc()
        ]
    );
} catch (PDOException $e) {
    // Never show raw SQL errors to visitors (viva point).
    // In a college project we show a friendly message.
    http_response_code(500);
    die('Database connection failed. Please start MySQL in XAMPP and import database.sql.');
}
