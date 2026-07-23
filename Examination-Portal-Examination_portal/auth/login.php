<?php
// auth/login.php — Role-based Login Selection & Authenticator
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Silent AJAX endpoint to look up database user roles
if (isset($_GET['get_role_by_email'])) {
    header('Content-Type: application/json');
    $email = trim($_GET['get_role_by_email']);
    $user = get_user_by_email($email);
    echo json_encode(['role' => $user ? $user['role'] : '']);
    exit;
}

if (is_logged_in()) { redirect(get_dashboard_url()); exit; }

$error = '';
$pre_selected_role = '';
$lockout_remaining = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $selected_role = trim($_POST['selected_role'] ?? '');
    $pre_selected_role = $selected_role;

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }

    $email_key = md5($email ?: 'guest');
    $attempts_data = $_SESSION['login_attempts'][$email_key] ?? ['count' => 0, 'lockout_until' => 0];
    $now = time();

    // Check if account/session is currently locked out
    if ($now < $attempts_data['lockout_until']) {
        $lockout_remaining = $attempts_data['lockout_until'] - $now;
        $error = "🚫 Too many failed login attempts! Account login is frozen for " . $lockout_remaining . " seconds. Please wait before trying again.";
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $user = get_user_by_email($email);
        if ($user && password_verify($password, $user['password'])) {
            // Success: Reset attempt counters
            unset($_SESSION['login_attempts'][$email_key]);

            // Auto-verify account if not marked verified
            if (!$user['is_verified'] || !$user['email_verified']) {
                global $pdo;
                $pdo->prepare("UPDATE users SET is_verified = 1, email_verified = 1 WHERE id = ?")->execute([$user['id']]);
                $user['is_verified'] = 1;
                $user['email_verified'] = 1;
            }

            login_user($user);
            send_notification($user['id'], 'Welcome back, ' . $user['name'] . '! You have logged in successfully.');
            redirect(get_dashboard_url());
            exit;
        } else {
            // Failed attempt logic
            // Reset count if previous lockout window passed
            if ($now >= $attempts_data['lockout_until'] && $attempts_data['lockout_until'] > 0) {
                $attempts_data['count'] = 0;
                $attempts_data['lockout_until'] = 0;
            }

            $attempts_data['count']++;

            if ($attempts_data['count'] >= 3) {
                $attempts_data['lockout_until'] = $now + 30; // 30 seconds freeze
                $attempts_data['count'] = 0; // Reset count for next cycle
                $lockout_remaining = 30;
                $error = "🚫 Too many failed login attempts (3/3)! Account login is frozen for 30 seconds.";
            } else {
                $remaining_attempts = 3 - $attempts_data['count'];
                $error = "Invalid email or password. Attempt " . $attempts_data['count'] . " of 3. (" . $remaining_attempts . " attempt" . ($remaining_attempts > 1 ? "s" : "") . " remaining before 30s lockout)";
            }

            $_SESSION['login_attempts'][$email_key] = $attempts_data;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Online Examination Portal</title>
    <meta name="description" content="Login to the Online Examination Portal — students, faculty, and admin access.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', -apple-system, sans-serif;
            background-color: #F5F0E6;
            color: #2E2E2E;
        }
        .role-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px 16px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: var(--shadow);
        }
        .role-card:hover {
            transform: translateY(-4px);
            border-color: #A67C52;
            box-shadow: 0 8px 24px rgba(92, 64, 51, 0.08);
        }
        .role-card.selected-active {
            border-color: #A67C52 !important;
            background: #FFF8F0 !important;
            box-shadow: 0 8px 24px rgba(166, 124, 82, 0.15) !important;
        }
        .role-icon {
            font-size: 2.2rem;
            margin-bottom: 12px;
        }
        .role-card h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #5C4033;
        }
        .role-card p {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<div class="auth-page">
    
    <div class="auth-card" style="width: 100%; max-width: 600px; padding: 40px;">
        <div class="auth-logo">
            <span class="logo-icon"><i class="fa-solid fa-graduation-cap" style="color: #A67C52;"></i></span>
            <h1>Examination Portal</h1>
            <p>Select your portal and sign in to your account</p>
        </div>

        <!-- Horizontal Role Selection Grid -->
        <div class="role-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px;">
            <!-- Student Card -->
            <div class="role-card" id="card-student" onclick="selectRoleCard('student')">
                <div class="role-icon"><i class="fa-solid fa-user-graduate" style="color: #A67C52;"></i></div>
                <h2>Student</h2>
                <p>Access assessments, tasks, and results.</p>
            </div>
            
            <!-- Admin/Faculty Card -->
            <div class="role-card" id="card-admin-faculty" onclick="selectRoleCard('admin_faculty')">
                <div class="role-icon"><i class="fa-solid fa-user-shield" style="color: #A67C52;"></i></div>
                <h2>Admin/Faculty (SUPER ADMIN)</h2>
                <p>Configure portal and manage users.</p>
            </div>
        </div>

        <!-- Selected Role Card -->
        <div class="selected-role-card" id="selectedRoleCard">
            Selected Role: <span id="selectedRoleName" style="color: #A67C52; font-weight: 700;">Student</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i> <?= h($error) ?></div>
        <?php endif; ?>
        <?php $flash_s = get_flash('success'); if ($flash_s): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= h($flash_s) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <input type="hidden" name="selected_role" id="selected-role" value="student">
            
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="you@example.com" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">
                    Password
                    <a href="forgot_password.php" class="link" style="float:right; font-size:0.8rem; color: #A67C52;">Forgot password?</a>
                </label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="••••••••" required>
                    <button type="button" onclick="togglePwd()" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1rem;" id="pwd-toggle">👁</button>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="remember" value="1">
                    Remember Me
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="login-btn" style="background:#A67C52; border-color:#A67C52;">Sign In →</button>
        </form>

        <div class="divider" id="signup-divider" style="display: flex;"><span>New here?</span></div>

        <a href="register.php" class="btn btn-outline btn-block" id="signup-link" style="justify-content:center; display: flex;">
            Create an Account
        </a>
    </div>

    <!-- Site Footer -->
    <footer class="site-footer" style="margin-top: 20px; border-radius: 12px; max-width: 600px;">
        <a onclick="openAboutModal()">About Us</a>
        <a onclick="openHelpModal()">Help</a>
    </footer>
</div>

<!-- About Us Modal -->
<div class="modal-backdrop" id="aboutModal" onclick="closeAboutModalOnOutsideClick(event)">
    <div class="modal-container">
        <div class="modal-header">
            <h3>About Us</h3>
            <button class="modal-close-btn" onclick="closeAboutModal()">&times;</button>
        </div>
        <div class="modal-body">
            <h4>Portal Overview</h4>
            <p>The Online Examination Portal is an enterprise-grade platform designed to conduct secure, timed, and role-based examinations and coding evaluations.</p>
            
            <h4>Purpose</h4>
            <p>To provide a robust academic assessment framework allowing students, faculty, and administrators to seamlessly schedule, execute, and monitor quizzes and programming challenges.</p>
            
            <h4>Institute Information</h4>
            <p>Powered by the Advanced Institute of Technology, Academic Assessment Division.</p>
            
            <h4>Project Objectives</h4>
            <p>Streamline exam scheduling, verify coding submissions automatically, and support instant feedback and results reporting.</p>
            
            <h4>Contact Information</h4>
            <p>Email: info@examportal.edu<br>Phone: +1 (555) 019-2834</p>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal-backdrop" id="helpModal" onclick="closeHelpModalOnOutsideClick(event)">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Help & Support</h3>
            <button class="modal-close-btn" onclick="closeHelpModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Ask Super Admin for Help Box -->
            <div style="background: rgba(166, 124, 82, 0.08); border: 1.5px solid #A67C52; padding: 18px; border-radius: 12px; margin-bottom: 24px;">
                <h4 style="margin-bottom: 6px; color: #7A5C48; display: flex; align-items: center; gap: 8px;">
                    💬 Ask Super Admin for Help
                </h4>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px;">
                    Have a question or issue? Type your query below to send a direct help request notification to the Super Admin.
                </p>
                <form id="help-request-form" onsubmit="submitHelpRequest(event)">
                    <?php if (!is_logged_in()): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <input type="text" id="help-user-name" class="form-control" placeholder="Your Name" required style="font-size: 0.85rem;">
                            <input type="email" id="help-user-email" class="form-control" placeholder="Your Email Address" required style="font-size: 0.85rem;">
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="help-query-input" class="form-control" placeholder="Type your help question or query..." required style="flex: 1; font-size: 0.85rem;">
                        <button type="submit" class="btn btn-primary" id="help-submit-btn" style="background: #A67C52; border-color: #A67C52; white-space: nowrap; font-size: 0.85rem;">
                            Send Request 🚀
                        </button>
                    </div>
                </form>
                <div id="help-form-status" style="margin-top: 10px; display: none; font-size: 0.85rem; padding: 8px 12px; border-radius: 8px;"></div>
            </div>

            <h4>Login Help</h4>
            <p>Select your role card (Student or Admin/Faculty), fill in your registered email and password, and click Sign In.</p>
            
            <h4>Password Reset Guide</h4>
            <p>Click "Forgot password?" on the login page, enter your registered email address, and use the demo link generated to set a new password.</p>
            
            <h4>Examination Instructions</h4>
            <p>Ensure you have a stable internet connection. Do not refresh or navigate away from the active quiz page, as the timer runs continuously in real-time and cannot be paused.</p>
            
            <h4>Coding Examination Help</h4>
            <p>Select your programming language from the dropdown menu, write your code, and click "Run Code" to compile. Click "Submit Solution" to submit against all tests.</p>
            
            <h4>Quiz Instructions</h4>
            <p>Select your answers for MCQs. Use the navigator grid to track which questions have been answered. Click "Submit Exam" when done.</p>
            
            <h4>Technical Support</h4>
            <p>Email: support@examportal.edu<br>Phone: +1 (555) 019-2835</p>
        </div>
    </div>
</div>

<script>
let currentSelectedCard = 'student';

function selectRoleCard(role) {
    document.getElementById('card-student').classList.remove('selected-active');
    document.getElementById('card-admin-faculty').classList.remove('selected-active');
    
    const signupDivider = document.getElementById('signup-divider');
    const signupLink = document.getElementById('signup-link');
    
    if (role === 'student') {
        document.getElementById('card-student').classList.add('selected-active');
        document.getElementById('selectedRoleName').innerText = 'Student';
        currentSelectedCard = 'student';
        if (signupDivider) signupDivider.style.display = 'flex';
        if (signupLink) signupLink.style.display = 'flex';
    } else {
        document.getElementById('card-admin-faculty').classList.add('selected-active');
        document.getElementById('selectedRoleName').innerText = 'Admin/Faculty (SUPER ADMIN)';
        currentSelectedCard = 'admin_faculty';
        if (signupDivider) signupDivider.style.display = 'none';
        if (signupLink) signupLink.style.display = 'none';
    }
}

function togglePwd() {
    const p = document.getElementById('password');
    const b = document.getElementById('pwd-toggle');
    if (p.type === 'password') { p.type = 'text'; b.textContent = '🙈'; }
    else { p.type = 'password'; b.textContent = '👁'; }
}

// Client-side submit interceptor to set role dynamically
document.getElementById('login-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const submitBtn = document.getElementById('login-btn');
    
    if (!email || !password) {
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.innerText = 'Signing In...';
    
    if (currentSelectedCard === 'student') {
        document.getElementById('selected-role').value = 'student';
        this.submit();
    } else {
        // Query the role of this email via AJAX helper
        try {
            const response = await fetch(`login.php?get_role_by_email=${encodeURIComponent(email)}`);
            const data = await response.json();
            
            if (data.role === 'admin' || data.role === 'faculty') {
                document.getElementById('selected-role').value = data.role;
            } else {
                document.getElementById('selected-role').value = 'admin'; // Failback
            }
        } catch (err) {
            document.getElementById('selected-role').value = 'admin';
        }
        this.submit();
    }
});

// Restore state on post error
window.addEventListener('DOMContentLoaded', () => {
    const preSelected = '<?= h($pre_selected_role) ?>';
    selectRoleCard(preSelected === 'student' || preSelected === '' ? 'student' : 'admin_faculty');
});

/* Modal control JS */
function openAboutModal() {
    document.getElementById('aboutModal').classList.add('show');
}
function closeAboutModal() {
    document.getElementById('aboutModal').classList.remove('show');
}
function closeAboutModalOnOutsideClick(e) {
    if (e.target.id === 'aboutModal') {
        closeAboutModal();
    }
}
function openHelpModal() {
    document.getElementById('helpModal').classList.add('show');
}
function closeHelpModal() {
    document.getElementById('helpModal').classList.remove('show');
}
function closeHelpModalOnOutsideClick(e) {
    if (e.target.id === 'helpModal') {
        closeHelpModal();
    }
}

async function submitHelpRequest(e) {
    e.preventDefault();
    const queryInput = document.getElementById('help-query-input');
    const nameInput = document.getElementById('help-user-name');
    const emailInput = document.getElementById('help-user-email');
    const submitBtn = document.getElementById('help-submit-btn');
    const statusDiv = document.getElementById('help-form-status');

    if (!queryInput || !queryInput.value.trim()) return;

    submitBtn.disabled = true;
    submitBtn.innerText = 'Sending...';

    const formData = new FormData();
    formData.append('query', queryInput.value.trim());
    if (nameInput) formData.append('name', nameInput.value.trim());
    if (emailInput) formData.append('email', emailInput.value.trim());

    try {
        const response = await fetch('<?= BASE_URL ?>/includes/send_help.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        statusDiv.style.display = 'block';
        if (data.success) {
            statusDiv.className = 'alert alert-success';
            statusDiv.style.background = 'rgba(16,185,129,0.15)';
            statusDiv.style.color = 'var(--green)';
            statusDiv.style.border = '1px solid #10b981';
            statusDiv.innerHTML = '✅ ' + data.message;
            queryInput.value = '';
            if (nameInput) nameInput.value = '';
            if (emailInput) emailInput.value = '';
        } else {
            statusDiv.className = 'alert alert-error';
            statusDiv.style.background = 'rgba(239,68,68,0.15)';
            statusDiv.style.color = 'var(--red)';
            statusDiv.style.border = '1px solid #ef4444';
            statusDiv.innerHTML = '❌ ' + (data.message || 'Failed to send request.');
        }
    } catch (err) {
        statusDiv.style.display = 'block';
        statusDiv.className = 'alert alert-error';
        statusDiv.style.background = 'rgba(239,68,68,0.15)';
        statusDiv.style.color = 'var(--red)';
        statusDiv.style.border = '1px solid #ef4444';
        statusDiv.innerHTML = '❌ An error occurred while sending your request.';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Send Request 🚀';
    }
}

// ── 30-Second Lockout Countdown Timer ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    let secondsLeft = <?= (int)$lockout_remaining ?>;
    const loginBtn = document.getElementById('login-btn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (secondsLeft > 0 && loginBtn) {
        if (emailInput) emailInput.disabled = true;
        if (passwordInput) passwordInput.disabled = true;
        loginBtn.disabled = true;

        const interval = setInterval(function() {
            if (secondsLeft <= 0) {
                clearInterval(interval);
                loginBtn.disabled = false;
                if (emailInput) emailInput.disabled = false;
                if (passwordInput) passwordInput.disabled = false;
                loginBtn.innerText = 'Sign In →';
                loginBtn.style.background = '#A67C52';
            } else {
                loginBtn.innerText = '⏳ Frozen: ' + secondsLeft + 's remaining...';
                loginBtn.style.background = '#64748b';
                secondsLeft--;
            }
        }, 1000);
    }
});
</script>
</body>
</html>
