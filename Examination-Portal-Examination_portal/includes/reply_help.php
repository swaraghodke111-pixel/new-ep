<?php
// includes/reply_help.php — Endpoint for Super Admin to reply to user help queries
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || ($_SESSION['user_role'] ?? $_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only Super Admin can reply to help queries.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$query_id   = (int)($_POST['query_id'] ?? 0);
$reply_text = trim($_POST['reply_text'] ?? '');
$admin_id   = (int)$_SESSION['user_id'];

if (!$query_id || empty($reply_text)) {
    echo json_encode(['success' => false, 'message' => 'Query ID and reply content are required.']);
    exit;
}

$ok = reply_to_help_query($query_id, $admin_id, $reply_text);

if ($ok) {
    echo json_encode([
        'success' => true,
        'message' => 'Reply sent successfully to the user!'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record reply in database.']);
}
