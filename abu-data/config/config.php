<?php
// config/config.php - Database configuration for MySQL

$host = 'localhost';
$dbname = 'abu_datasiswa';
$user = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/audit_logger.php';

function getDB() {
    global $pdo;
    return $pdo;
}
?>