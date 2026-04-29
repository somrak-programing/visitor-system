<?php
// Secure Session Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// Note: session.cookie_secure = 1 should be used in production with HTTPS
session_start();
$host = 'localhost';
$db   = 'visitor_system';
$user = 'root';
$pass = ''; // Default XAMPP has no password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]);
    exit;
}
?>
