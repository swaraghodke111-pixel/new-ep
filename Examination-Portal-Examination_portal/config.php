<?php
// config.php — Database & SMTP configuration for Online Examination Portal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'exam_portal');
define('BASE_URL', 'http://localhost:8001');

// SMTP Configuration (Gmail SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'badakrohit@gmail.com');
define('SMTP_PASS', 'trkxotmhbbnkcofq');
define('SMTP_FROM', 'badakrohit@gmail.com');
define('SMTP_FROM_NAME', 'Online Examination Portal');

// Application settings
define('APP_NAME', 'Online Examination Portal');
define('ADMIN_EMAIL', 'balajichaughule@gmail.com');

try {
    // First connect without DB to check if it exists
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $db_check = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if (!$db_check->fetch()) {
        if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
            header('Location: ' . BASE_URL . '/install.php');
            exit;
        }
    } else {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
} catch (PDOException $e) {
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: ' . BASE_URL . '/install.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>
