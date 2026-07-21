<?php
// profile.php — Profile management page for all users
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_role('student', 'admin', 'faculty');

$user_id = (int)$_SESSION['user_id'];
$user = get_user_by_id($user_id);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $error = 'Name cannot be empty.';
        } else {
            global $pdo;
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            if ($stmt->execute([$name, $user_id])) {
                $_SESSION['user_name'] = $name;
                $success = 'Profile updated successfully!';
                $user = get_user_by_id($user_id); // Reload
            } else {
                $error = 'Failed to update profile details.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        
        if (empty($old_pass) || empty($new_pass) || empty($confirm)) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($old_pass, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new_pass !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            global $pdo;
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$new_hash, $user_id])) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to update password.';
            }
        }
    }
}

$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>👤 My Profile</h2>
    <p>View your credentials and update your account details.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Account Information</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" value="<?= h($user['email']) ?>" readonly style="background:rgba(255,255,255,0.04); cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">User Role</label>
                    <input type="text" class="form-control" value="<?= ucfirst(h($user['role'])) ?>" readonly style="background:rgba(255,255,255,0.04); cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Verification Status</label>
                    <div style="margin-top:6px;">
                        <?= $user['is_verified'] ? '<span class="badge badge-passed">✅ Verified Account</span>' : '<span class="badge badge-failed">❌ Unverified</span>' ?>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top:16px;">Update Details</button>
            </form>
        </div>
    </div>

    <!-- Password Change -->
    <div class="card">
        <div class="card-header">
            <h3>🔒 Change Password</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="old_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-outline" style="margin-top:16px; width:100%; text-align:center; justify-content:center;">Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
