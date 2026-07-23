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

// Store in help_queries table
$user_id = is_logged_in() ? (int)$_SESSION['user_id'] : null;
$ins = $pdo->prepare("
    INSERT INTO help_queries (user_id, user_name, user_email, query_text)
    VALUES (?, ?, ?, ?)
");
$ins->execute([$user_id, $user_name ?: 'Portal User', $user_email ?: 'user@portal.com', $message]);

// Find all Super Admins (role = admin) to notify
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
    'message' => 'Your help request has been sent to the Super Admin! They will receive your query and reply shortly.'
]);
