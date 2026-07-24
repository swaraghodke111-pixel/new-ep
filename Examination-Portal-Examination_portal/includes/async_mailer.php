<?php
// includes/async_mailer.php — Non-blocking background SMTP email worker
if (php_sapi_name() !== 'cli' && empty($_GET['internal_key'])) {
    die("Access denied.");
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$log_id = (int)($argv[1] ?? $_GET['log_id'] ?? 0);
if ($log_id <= 0) {
    exit(0);
}

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ?");
$stmt->execute([$log_id]);
$email_log = $stmt->fetch();

if ($email_log) {
    // Send email using SMTP in the background
    send_smtp_email($email_log['recipient'], $email_log['subject'], $email_log['body']);
}
?>
