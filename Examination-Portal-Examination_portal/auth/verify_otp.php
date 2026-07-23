<?php
// auth/verify_otp.php — Verify 6-Digit OTP & Reset Password
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (is_logged_in()) { redirect(get_dashboard_url()); exit; }

$email   = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp              = trim($_POST['otp'] ?? '');
    $new_password     = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($otp) || empty($new_password)) {
        $error = 'Please enter your email, OTP code, and new password.';
    } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error = 'OTP code must be a 6-digit numeric code.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        $user = get_user_by_email($email);

        if (!$user) {
            $error = '❌ Invalid registered email address.';
        } elseif (empty($user['reset_token']) || $user['reset_token'] !== $otp) {
            $error = '❌ Invalid OTP code. Please check your email and try again.';
        } elseif (empty($user['reset_expires']) || strtotime($user['reset_expires']) < time()) {
            $error = '⏰ This OTP code has expired. Please request a new OTP.';
        } else {
            // Update password & clear token
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            global $pdo;
            $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
                ->execute([$new_hash, $user['id']]);

            flash('success', '✅ Password updated successfully! Please sign in with your new password.');
            redirect(BASE_URL . '/auth/login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP & Reset Password — Examination Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', -apple-system, sans-serif;
            background-color: #F5F0E6;
            color: #2E2E2E;
        }
        .otp-input-field {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 8px;
            text-align: center;
            color: #7A5C48;
        }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="max-width: 460px;">
        <div class="auth-logo">
            <span class="logo-icon"><i class="fa-solid fa-shield-halved" style="color: #A67C52;"></i></span>
            <h1>Verify OTP Code</h1>
            <p>Enter the 6-digit OTP sent to your registered email</p>
        </div>

        <?php flash_messages(); ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i> <?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Registered Email</label>
                <input type="email" name="email" class="form-control" value="<?= h($email) ?>" required readonly style="background:rgba(0,0,0,0.04);">
            </div>

            <div class="form-group">
                <label class="form-label">6-Digit OTP Code</label>
                <input type="text" name="otp" class="form-control otp-input-field" placeholder="123456" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="off">
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="background:#A67C52; border-color:#A67C52; font-weight:600; padding:12px;">
                Reset Password ✅
            </button>
        </form>

        <div style="margin-top:20px; text-align:center; font-size:0.85rem;">
            <span>Didn't receive code? </span>
            <a href="forgot_password.php" style="color:#A67C52; font-weight:600; text-decoration:underline;">Resend OTP</a>
        </div>

        <div class="divider"><span>Or</span></div>
        <a href="login.php" class="btn btn-outline btn-block" style="justify-content:center;">← Back to Login</a>
    </div>
</div>
</body>
</html>
