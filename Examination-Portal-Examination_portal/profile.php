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
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($name) || empty($email)) {
            $error = 'Name and Email address cannot be empty.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            global $pdo;
            // Verify that new email is unique to this user
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            if ($check->fetch()) {
                $error = 'This email address is already registered to another account.';
            } else {
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
                    $email_changed = ($email !== strtolower($user['email']));
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, profile_pic = ? WHERE id = ?");
                    if ($stmt->execute([$name, $email, $profile_pic_path, $user_id])) {
                        $_SESSION['user_name']  = $name;
                        $_SESSION['user_email'] = $email;
                        $success = 'Profile details & registered email updated immediately in database!';

                        if ($email_changed) {
                            $subject = "✉️ Registered Email Address Updated — " . APP_NAME;
                            $body = "
                            <div style='font-family: Poppins, Arial, sans-serif; padding: 20px; color: #1e293b;'>
                                <h2 style='color: #ff6b00;'>Profile Email Updated Successfully</h2>
                                <p>Hello <strong>" . h($name) . "</strong>,</p>
                                <p>Your registered email address on the Online Examination Portal has been updated immediately in the database to <strong>" . h($email) . "</strong>.</p>
                                <p>All future OTP reset codes, login alerts, and portal notifications will now be routed directly to this updated email address.</p>
                            </div>";
                            send_smtp_email($email, $subject, $body);
                            log_email($user_id, 'email_update', $email, $subject, $body);
                        }

                        $user = get_user_by_id($user_id); // Reload updated row from DB
                    } else {
                        $error = 'Failed to update profile details.';
                    }
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
    <div class="card-body profile-banner-body" style="display:flex; align-items:center; gap:24px; padding:28px;">
        <div style="position:relative; flex-shrink:0;">
            <div style="width:110px; height:110px; border-radius:50%; overflow:hidden; border:3.5px solid #FF6B00; box-shadow:0 6px 18px rgba(255,107,0,0.25); background:var(--bg-card); display:flex; align-items:center; justify-content:center;">
                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/' . $user['profile_pic'])): ?>
                    <img src="<?= BASE_URL . '/' . h($user['profile_pic']) ?>" alt="<?= h($user['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <span style="font-size:2.8rem; font-weight:700; color:#FF6B00;"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <!-- Edit Profile Photo Button at bottom right corner -->
            <button type="button" class="profile-photo-edit-btn" title="Edit / Change Profile Photo" onclick="openEditProfileModal()">
                <i class="fa-solid fa-camera"></i>
            </button>
        </div>
        <div>
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px; flex-wrap:wrap;">
                <h3 style="font-size:1.5rem; color:var(--text); margin-bottom:0; font-weight:700;"><?= h($user['name']) ?></h3>
                <button type="button" onclick="openEditProfileModal()" class="btn btn-sm" style="background:#FF6B00; color:#fff; border-radius:20px; padding:6px 16px; font-size:0.82rem; gap:6px; font-weight:600;" title="Edit Profile Information">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Info
                </button>
            </div>
            <p style="margin-bottom:10px; color:var(--text-muted); font-size:0.92rem;"><?= h($user['email']) ?></p>
            <div>
                <span class="badge" style="background:rgba(255,107,0,0.15); color:#FF6B00; font-weight:700; font-size:0.8rem;">
                    👑 <?= strtoupper(h($user['role'])) ?> PORTAL
                </span>
                <?php if ($user['is_verified']): ?>
                    <span class="badge badge-passed" style="margin-left:6px;">✅ Verified Account</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Account Overview Cards (Clean Readonly Display) -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-header">
            <h3>👤 Account Overview</h3>
        </div>
        <div class="card-body">
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <span style="color:var(--text-muted); font-size:0.9rem;">Full Name:</span>
                    <strong style="color:var(--text); font-size:0.9rem;"><?= h($user['name']) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <span style="color:var(--text-muted); font-size:0.9rem;">Email Address:</span>
                    <strong style="color:var(--text); font-size:0.9rem;"><?= h($user['email']) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <span style="color:var(--text-muted); font-size:0.9rem;">Portal Role:</span>
                    <strong style="color:#FF6B00; font-size:0.9rem;"><?= ucfirst(h($user['role'])) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted); font-size:0.9rem;">Account Status:</span>
                    <strong style="color:var(--green); font-size:0.9rem;">Active & Verified</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>⚡ Quick Actions</h3>
        </div>
        <div class="card-body" style="display:flex; flex-direction:column; gap:12px; justify-content:center; height:100%;">
            <button type="button" onclick="openEditProfileModal()" class="btn btn-primary" style="background:#FF6B00; border-color:#FF6B00; padding:12px; font-weight:700;">
                ✏️ Edit Profile & Account Information
            </button>
            <button type="button" onclick="openEditProfileModal('password')" class="btn btn-outline" style="padding:12px; font-weight:600;">
                🔒 Change Password
            </button>
        </div>
    </div>
</div>

<!-- Dedicated Edit Profile Interface Modal -->
<div class="modal-backdrop" id="editProfileModal" onclick="closeEditProfileModalOnOutsideClick(event)">
    <div class="modal-container" style="max-width:620px;">
        <div class="modal-header" style="background:#252830; color:#ffffff; border-radius:12px 12px 0 0;">
            <h3 style="color:#ffffff; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-user-pen" style="color:#FF6B00;"></i> Edit Profile Information
            </h3>
            <button class="modal-close-btn" onclick="closeEditProfileModal()" style="color:#ffffff;">&times;</button>
        </div>
        <div class="modal-body" style="padding:28px;">
            
            <!-- Modal Tab Navigation -->
            <div style="display:flex; gap:12px; margin-bottom:24px; border-bottom:2px solid var(--border); padding-bottom:12px;">
                <button type="button" class="btn btn-sm" id="tab-btn-info" onclick="switchEditTab('info')" style="background:#FF6B00; color:#fff; font-weight:600; border-radius:8px;">
                    📝 Personal Details & Photo
                </button>
                <button type="button" class="btn btn-sm btn-outline" id="tab-btn-password" onclick="switchEditTab('password')" style="font-weight:600; border-radius:8px;">
                    🔒 Change Password
                </button>
            </div>

            <!-- Tab 1: Personal Details & Photo Form -->
            <div id="edit-tab-info">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group" style="margin-bottom:18px;">
                        <label class="form-label" style="font-weight:600;">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required style="height:44px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:18px;">
                        <label class="form-label" style="font-weight:600;">Profile Photo</label>
                        <input type="file" name="profile_pic" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding:10px;">
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Supported formats: JPG, PNG, WebP (Max 5MB)</div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:18px;">
                        <label class="form-label" style="font-weight:600;">Email Address (Editable - Instant DB Update)</label>
                        <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>" required style="height:44px;">
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Updating your email will immediately update the database to receive future OTPs.</div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:24px;">
                        <label class="form-label" style="font-weight:600;">User Role (Readonly)</label>
                        <input type="text" class="form-control" value="<?= ucfirst(h($user['role'])) ?> Portal" readonly style="background:var(--bg-dark); cursor:not-allowed; opacity:0.8;">
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" class="btn btn-outline" onclick="closeEditProfileModal()">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary" style="background:#FF6B00; border-color:#FF6B00; padding:10px 24px;">Save Profile Changes</button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Change Password Form -->
            <div id="edit-tab-password" style="display:none;">
                <form method="POST" action="">
                    <div class="form-group" style="margin-bottom:18px;">
                        <label class="form-label" style="font-weight:600;">Current Password</label>
                        <input type="password" name="old_password" class="form-control" placeholder="••••••••" required style="height:44px;">
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label class="form-label" style="font-weight:600;">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required style="height:44px;">
                    </div>
                    <div class="form-group" style="margin-bottom:24px;">
                        <label class="form-label" style="font-weight:600;">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required style="height:44px;">
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" class="btn btn-outline" onclick="closeEditProfileModal()">Cancel</button>
                        <button type="submit" name="change_password" class="btn btn-primary" style="background:#FF6B00; border-color:#FF6B00; padding:10px 24px;">Update Password</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function openEditProfileModal(tab = 'info') {
    switchEditTab(tab);
    document.getElementById('editProfileModal').classList.add('show');
}
function closeEditProfileModal() {
    document.getElementById('editProfileModal').classList.remove('show');
}
function closeEditProfileModalOnOutsideClick(e) {
    if (e.target.id === 'editProfileModal') {
        closeEditProfileModal();
    }
}
function switchEditTab(tab) {
    const tabInfo = document.getElementById('edit-tab-info');
    const tabPassword = document.getElementById('edit-tab-password');
    const btnInfo = document.getElementById('tab-btn-info');
    const btnPassword = document.getElementById('tab-btn-password');

    if (tab === 'password') {
        tabInfo.style.display = 'none';
        tabPassword.style.display = 'block';
        btnInfo.className = 'btn btn-sm btn-outline';
        btnInfo.style.background = 'transparent';
        btnInfo.style.color = 'var(--text)';
        btnPassword.className = 'btn btn-sm';
        btnPassword.style.background = '#FF6B00';
        btnPassword.style.color = '#fff';
    } else {
        tabInfo.style.display = 'block';
        tabPassword.style.display = 'none';
        btnInfo.className = 'btn btn-sm';
        btnInfo.style.background = '#FF6B00';
        btnInfo.style.color = '#fff';
        btnPassword.className = 'btn btn-sm btn-outline';
        btnPassword.style.background = 'transparent';
        btnPassword.style.color = 'var(--text)';
    }
}

// Automatically re-open modal if error occurred during POST submission
<?php if ($error): ?>
    document.addEventListener('DOMContentLoaded', function() {
        openEditProfileModal();
    });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
