<?php
// includes/send_help.php — AJAX endpoint for submitting help requests to Super Admin
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$message = trim($_POST['query'] ?? $_POST['message'] ?? '');
$user_name = trim($_POST['name'] ?? '');
$user_email = trim($_POST['email'] ?? '');

if (is_logged_in()) {
    $user_name = $_SESSION['user_name'] ?? $user_name;
    $user_email = $_SESSION['user_email'] ?? $user_email;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your question or help request.']);
    exit;
}

global $pdo;

// Find all Super Admins (role = admin)
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'admin'");
$admins = $stmt->fetchAll();

if (empty($admins)) {
    $admins = [['id' => 1, 'name' => 'Super Admin']];
}

$sender_display = $user_name ? $user_name : ($user_email ? $user_email : 'Portal User');
if ($user_email && $user_name) {
    $sender_display .= " ($user_email)";
}

$notif_text = "📩 Help Request from " . $sender_display . ": \"" . mb_strimwidth($message, 0, 150, "…") . "\"";

foreach ($admins as $admin) {
    send_notification((int)$admin['id'], $notif_text);
}

echo json_encode([
    'success' => true,
    'message' => 'Your help request has been sent to the Super Admin! They will receive a notification shortly.'
]);
