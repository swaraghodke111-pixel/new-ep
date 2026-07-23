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
            $profile_pic_path = $user['profile_pic'] ?? '';
            
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $target_rel = 'uploads/user_' . $user_id . '_' . time() . '.' . $ext;
                    $target_abs = __DIR__ . '/' . $target_rel;
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_abs)) {
                        $profile_pic_path = $target_rel;
                    }
                } else {
                    $error = 'Invalid image file type. Only JPG, PNG, and WebP are allowed.';
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, profile_pic = ? WHERE id = ?");
                if ($stmt->execute([$name, $profile_pic_path, $user_id])) {
                    $_SESSION['user_name'] = $name;
                    $success = 'Profile updated successfully!';
                    $user = get_user_by_id($user_id); // Reload
                } else {
                    $error = 'Failed to update profile details.';
                }
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
    <p>View your credentials, manage your profile photo, and update your account details.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<!-- Profile Avatar Banner Card -->
<div class="card mb-24">
    <div class="card-body" style="display:flex; align-items:center; gap:24px; padding:24px;">
        <div style="width:110px; height:110px; border-radius:50%; overflow:hidden; border:3px solid #A67C52; box-shadow:0 6px 18px rgba(166,124,82,0.2); background:var(--bg-card); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/' . $user['profile_pic'])): ?>
                <img src="<?= BASE_URL . '/' . h($user['profile_pic']) ?>" alt="<?= h($user['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <span style="font-size:2.8rem; font-weight:700; color:var(--purple-3);"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h3 style="margin-bottom:4px; font-size:1.4rem; color:var(--text-main);"><?= h($user['name']) ?></h3>
            <p style="margin-bottom:8px; color:var(--text-muted); font-size:0.9rem;"><?= h($user['email']) ?></p>
            <div>
                <span class="badge" style="background:rgba(166,124,82,0.15); color:#A67C52; font-weight:700; font-size:0.8rem;">
                    👑 <?= strtoupper(h($user['role'])) ?> PORTAL
                </span>
                <?php if ($user['is_verified']): ?>
                    <span class="badge badge-passed" style="margin-left:6px;">✅ Verified</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Account Information & Photo</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Profile Photo</label>
                    <input type="file" name="profile_pic" class="form-control" accept="image/jpeg,image/png,image/webp" style="font-size:0.85rem;">
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Allowed formats: JPG, PNG, WebP (Max 5MB)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" value="<?= h($user['email']) ?>" readonly style="background:rgba(255,255,255,0.04); cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">User Role</label>
                    <input type="text" class="form-control" value="<?= ucfirst(h($user['role'])) ?>" readonly style="background:rgba(255,255,255,0.04); cursor:not-allowed;">
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top:16px;">Save Changes</button>
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
