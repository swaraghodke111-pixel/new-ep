<?php
// api/auth/verify.php — Endpoint for email verification link clicks
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

if (empty($token)) {
    $error = 'Invalid verification link. Token is missing.';
} else {
    // Find the user with matching verification token
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'The verification link is invalid or has already been used.';
    } else {
        $expires = strtotime($user['token_expires_at']);
        if ($expires < time()) {
            $error = 'This verification link has expired. Please register again to get a new link.';
        } else {
            // Valid token, verify user and clear token fields
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = ?");
            if ($update->execute([$user['id']])) {
                // Send success notification to the user
                send_notification($user['id'], '🎉 Your email address has been verified successfully. Welcome to the portal!');
                flash('success', 'Your email address has been verified! You can now log in.');
                redirect(BASE_URL . '/auth/login.php');
                exit;
            } else {
                $error = 'Failed to verify account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification — Online Examination Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 48px;
            width: 100%;
            max-width: 500px;
            color: #fff;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            text-align: center;
        }
        .icon {
            font-size: 3.5rem;
            margin-bottom: 24px;
        }
        h2 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 12px;
        }
        p {
            color: rgba(255,255,255,0.7);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            border-radius: 10px;
            color: #fff;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99,102,241,0.4);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            margin-top: 12px;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
            box-shadow: none;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">❌</div>
    <h2>Verification Failed</h2>
    <p><?= htmlspecialchars($error) ?></p>
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn">Go to Login</a>
    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-secondary">Create a New Account</a>
</div>
</body>
</html>
