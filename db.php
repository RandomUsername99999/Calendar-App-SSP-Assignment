<?php
$host = 'localhost';
$db   = 'calendar';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // connect to database server without dbname first to create it if not exists
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
    
    // connect with dbname
    $dsn_with_db = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn_with_db, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
