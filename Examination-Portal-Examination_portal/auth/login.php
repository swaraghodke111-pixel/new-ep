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

            // ── SEND LOGIN DETAILS EMAIL NOTIFICATION ─────────────────────────────────
            $subject = "🔐 Account Login Details & Alert - " . APP_NAME;
            $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser';
            $login_time = date('F j, Y, g:i a');
            $dashboard_link = BASE_URL . '/' . (
                $user['role'] === 'admin' ? 'admin/dashboard.php' :
                ($user['role'] === 'faculty' ? 'faculty/dashboard.php' : 'student/dashboard.php')
            );

            $body = "
            <div style='font-family: Poppins, Arial, sans-serif; background-color: #f8fafc; padding: 25px;'>
                <div style='max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06);'>
                    <div style='text-align: center; margin-bottom: 24px;'>
                        <div style='display: inline-block; width: 56px; height: 56px; line-height: 56px; border-radius: 16px; background: rgba(255, 107, 0, 0.1); color: #ff6b00; font-size: 1.8rem; text-align: center;'>🎓</div>
                        <h2 style='color: #1e293b; margin: 12px 0 4px 0; font-size: 1.4rem;'>Online Examination Portal</h2>
                        <p style='color: #10b981; font-weight: 600; font-size: 0.95rem; margin: 0;'>✅ Account Login Successful</p>
                    </div>
                    
                    <p style='color: #1e293b; font-size: 0.95rem;'>Hello <strong>" . h($user['name']) . "</strong>,</p>
                    <p style='color: #475569; font-size: 0.9rem; line-height: 1.6;'>You have successfully logged into your account on the Online Examination Portal. Here are your account login details:</p>
                    
                    <div style='background: #f8fafc; border: 1.5px solid #e2e8f0; border-left: 4px solid #ff6b00; padding: 18px; border-radius: 10px; margin: 20px 0;'>
                        <table style='width: 100%; border-collapse: collapse; font-size: 0.9rem; color: #1e293b;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b; width: 140px;'><strong>Account Holder:</strong></td>
                                <td style='padding: 6px 0; font-weight: 600;'>" . h($user['name']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Registered Email:</strong></td>
                                <td style='padding: 6px 0; font-weight: 600; color: #ff6b00;'>" . h($user['email']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Portal Role:</strong></td>
                                <td style='padding: 6px 0; font-weight: 600; color: #6366f1;'>" . ucfirst(h($user['role'])) . " Portal</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Login Time:</strong></td>
                                <td style='padding: 6px 0;'>" . $login_time . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>IP Address:</strong></td>
                                <td style='padding: 6px 0; font-family: monospace;'>" . h($ip_addr) . "</td>
                            </tr>
                        </table>
                    </div>

                    <div style='text-align: center; margin-top: 24px; margin-bottom: 20px;'>
                        <a href='" . $dashboard_link . "' style='display: inline-block; padding: 12px 28px; background: #ff6b00; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(255, 107, 0, 0.3);'>Go to Your Dashboard →</a>
                    </div>

                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                    <p style='color: #94a3b8; font-size: 0.8rem; text-align: center; margin: 0;'>
                        If you did not perform this login, please <a href='" . BASE_URL . "/auth/forgot_password.php' style='color: #ef4444; text-decoration: underline;'>reset your password immediately</a>.
                    </p>
                </div>
            </div>";

            // Non-blocking async email dispatch for instant <30ms login response
            send_async_email($user['id'], 'login_alert', $user['email'], $subject, $body);

            redirect(get_dashboard_url());
            exit;
        } else {
            // Failed attempt logic
            if ($now >= $attempts_data['lockout_until'] && $attempts_data['lockout_until'] > 0) {
                $attempts_data['count'] = 0;
                $attempts_data['lockout_until'] = 0;
            }

            $attempts_data['count']++;

            if ($attempts_data['count'] >= 3) {
                $attempts_data['lockout_until'] = $now + 30; // 30 seconds freeze
                $attempts_data['count'] = 0; // Reset count for next cycle
                $lockout_remaining = 30;
                $error = "🚫 Too many failed login attempts (3/3)! Account login is frozen for 30 seconds. Please wait before trying again.";

                // Send Security Alert Email when user enters wrong password 3 times
                if ($user) {
                    $subject = "⚠️ Security Alert: 3 Failed Login Attempts Detected";
                    $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $time_now = date('F j, Y, g:i a');

                    $body = "
                    <div style='font-family: Poppins, Arial, sans-serif; padding: 20px; color: #1e293b;'>
                        <h2 style='color: #ef4444;'>⚠️ Security Warning: 3 Incorrect Password Attempts</h2>
                        <p>Hello <strong>" . h($user['name']) . "</strong>,</p>
                        <p>There were <strong>3 consecutive incorrect password attempts</strong> entered for your account.</p>
                        <div style='background: #fef2f2; padding: 15px; border-left: 4px solid #ef4444; border-radius: 6px; margin: 15px 0;'>
                            <p style='margin: 0;'><strong>Account Email:</strong> " . h($user['email']) . "</p>
                            <p style='margin: 6px 0 0 0;'><strong>Status:</strong> Login frozen for 30 seconds</p>
                            <p style='margin: 6px 0 0 0;'><strong>Timestamp:</strong> " . $time_now . "</p>
                            <p style='margin: 6px 0 0 0;'><strong>IP Address:</strong> " . h($ip_addr) . "</p>
                        </div>
                        <p>If you forgot your password, click below to reset it using your 6-digit OTP code:</p>
                        <p><a href='" . BASE_URL . "/auth/forgot_password.php' style='display: inline-block; padding: 10px 20px; background: #ef4444; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;'>Reset Password Now</a></p>
                    </div>";
                    send_async_email($user['id'], 'failed_login_alert', $user['email'], $subject, $body);
                }
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
            background-color: #ECEFF4;
            color: #1E293B;
        }
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1020px;
            background: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            min-height: 620px;
        }
        .login-hero {
            flex: 1.1;
            background: #252830; /* Dark Charcoal Slate */
            position: relative;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #FFFFFF;
            overflow: hidden;
        }
        .hero-accent-top {
            position: absolute;
            top: -60px;
            left: -60px;
            width: 200px;
            height: 200px;
            background: #FF6B00;
            border-radius: 50%;
            opacity: 0.95;
        }
        .hero-accent-bottom {
            position: absolute;
            bottom: -70px;
            right: -40px;
            width: 190px;
            height: 190px;
            background: #FF6B00;
            border-radius: 50%;
            opacity: 0.95;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
        }
        .hero-logo-emblem {
            width: 84px;
            height: 84px;
            border: 3.5px solid #FF6B00;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            color: #FF6B00;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(255, 107, 0, 0.25);
        }
        .hero-title {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 1px;
            color: #FFFFFF;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .highlight-orange {
            color: #FF6B00 !important;
        }
        .hero-subtitle {
            color: #94A3B8;
            font-size: 0.92rem;
            font-weight: 400;
            margin-bottom: 32px;
        }
        .hero-illustration {
            margin-top: auto;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .login-form-container {
            flex: 1;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
        }
        .login-header-text {
            margin-bottom: 24px;
        }
        .login-header-text h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #1E293B;
            margin-bottom: 4px;
        }
        .login-header-text p {
            color: #64748B;
            font-size: 0.9rem;
        }
        .input-with-icon {
            position: relative;
        }
        .input-with-icon i.field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.05rem;
        }
        .input-with-icon .form-control {
            padding-left: 48px;
            height: 48px;
            border-radius: 12px;
            border: 1.5px solid #E2E8F0;
            font-size: 0.92rem;
        }
        .input-with-icon .form-control:focus {
            border-color: #FF6B00;
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.15);
        }
        .role-card {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            padding: 14px 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .role-card:hover {
            border-color: #FF6B00;
            transform: translateY(-2px);
        }
        .role-card.selected-active {
            border-color: #FF6B00 !important;
            background: rgba(255, 107, 0, 0.05) !important;
            box-shadow: 0 4px 14px rgba(255, 107, 0, 0.12) !important;
        }
        .role-icon {
            font-size: 1.6rem;
            margin-bottom: 6px;
            color: #FF6B00;
        }
        .role-card h2 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 2px;
            color: #1E293B;
        }
        .role-card p {
            color: #64748B;
            font-size: 0.75rem;
            margin-bottom: 0;
        }
        .selected-role-card {
            background: rgba(255, 107, 0, 0.05);
            border: 1.5px dashed #FF6B00;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            margin: 16px 0;
            font-weight: 600;
            color: #1E293B;
            font-size: 0.85rem;
        }
        .btn-login-primary {
            background: #FF6B00 !important;
            border-color: #FF6B00 !important;
            color: #FFFFFF !important;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            height: 48px;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .btn-login-primary:hover {
            background: #E05D00 !important;
            box-shadow: 0 8px 20px rgba(255, 107, 0, 0.3) !important;
        }
        @media (max-width: 900px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 480px;
            }
            .login-hero {
                padding: 32px 20px;
            }
            .hero-illustration {
                display: none;
            }
            .login-form-container {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
<div class="auth-page">
    
    <div class="login-wrapper">
        <!-- Left Hero Banner -->
        <div class="login-hero">
            <div class="hero-accent-top"></div>
            <div class="hero-accent-bottom"></div>
            
            <div class="hero-content">
                <div class="hero-logo-emblem">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <h1 class="hero-title">ONLINE <span class="highlight-orange">EXAM PORTAL</span></h1>
                <p class="hero-subtitle">Your Gateway to Secure Online Examinations</p>

                <div class="hero-illustration">
                    <svg width="280" height="190" viewBox="0 0 350 240" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Clock on Wall -->
                        <circle cx="260" cy="50" r="14" stroke="#475569" stroke-width="2" fill="none"/>
                        <line x1="260" y1="50" x2="260" y2="42" stroke="#FF6B00" stroke-width="2" stroke-linecap="round"/>
                        <line x1="260" y1="50" x2="267" y2="50" stroke="#FF6B00" stroke-width="2" stroke-linecap="round"/>
                        
                        <!-- Table -->
                        <rect x="90" y="170" width="180" height="6" rx="3" fill="#475569"/>
                        <rect x="110" y="176" width="5" height="40" fill="#334155"/>
                        <rect x="250" y="176" width="5" height="40" fill="#334155"/>
                        
                        <!-- Chair -->
                        <rect x="45" y="155" width="40" height="10" rx="3" fill="#94A3B8"/>
                        <rect x="38" y="110" width="10" height="55" rx="3" fill="#94A3B8"/>
                        <line x1="45" y1="165" x2="40" y2="216" stroke="#64748B" stroke-width="4" stroke-linecap="round"/>
                        <line x1="80" y1="165" x2="85" y2="216" stroke="#64748B" stroke-width="4" stroke-linecap="round"/>
                        
                        <!-- Student Avatar -->
                        <circle cx="102" cy="90" r="14" fill="#FED7AA"/>
                        <path d="M80 108 C80 102 90 100 102 100 C114 100 124 102 124 108 L118 155 L86 155 Z" fill="#FF6B00"/>
                        <rect x="86" y="155" width="16" height="55" rx="4" fill="#1E293B"/>
                        <rect x="104" y="155" width="16" height="55" rx="4" fill="#0F172A"/>
                        
                        <!-- Laptop -->
                        <path d="M150 170 L165 130 L210 130 L195 170 Z" fill="#E2E8F0"/>
                        <rect x="142" y="168" width="65" height="3" rx="1.5" fill="#94A3B8"/>
                        
                        <!-- Plant -->
                        <path d="M228 170 L232 154 L244 154 L248 170 Z" fill="#E2E8F0"/>
                        <path d="M236 146 Q228 130 236 122 Q244 130 236 146 Z" fill="#64748B"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="login-form-container">
            <div class="login-header-text">
                <h2>Welcome <span class="highlight-orange">Back!</span></h2>
                <p>Login to continue your session</p>
            </div>

            <!-- Role Selection Grid -->
            <div class="role-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 14px;">
                <!-- Student Card -->
                <div class="role-card" id="card-student" onclick="selectRoleCard('student')">
                    <div class="role-icon"><i class="fa-solid fa-user-graduate"></i></div>
                    <h2>Student</h2>
                    <p>Assessments & results</p>
                </div>
                
                <!-- Admin/Faculty Card -->
                <div class="role-card" id="card-admin-faculty" onclick="selectRoleCard('admin_faculty')">
                    <div class="role-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <h2>Admin / Faculty</h2>
                    <p>Super Admin control</p>
                </div>
            </div>

            <!-- Selected Role Indicator -->
            <div class="selected-role-card" id="selectedRoleCard">
                Role: <span id="selectedRoleName" style="color: #FF6B00; font-weight: 700;">Student</span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:14px;"><i class="fa-solid fa-circle-xmark"></i> <?= h($error) ?></div>
            <?php endif; ?>
            <?php $flash_s = get_flash('success'); if ($flash_s): ?>
                <div class="alert alert-success" style="margin-bottom:14px;"><i class="fa-solid fa-circle-check"></i> <?= h($flash_s) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="login-form">
                <input type="hidden" name="selected_role" id="selected-role" value="student">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="email">Username / Email</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-user field-icon"></i>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="balajichaughule@gmail.com" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="password">
                        Password
                        <a href="forgot_password.php" class="link" style="float:right; font-size:0.8rem; color: #FF6B00;">Forgot Password?</a>
                    </label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="••••••••" required style="padding-right: 44px;">
                        <button type="button" onclick="togglePwd()" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1rem;" id="pwd-toggle">👁</button>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-check">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn btn-login-primary btn-block" id="login-btn">LOGIN</button>
            </form>

            <div style="text-align: center; margin-top: 24px; font-size: 0.85rem; color: #64748B;" id="signup-link">
                Don't have an account? <a href="register.php" style="color: #FF6B00; font-weight: 700; text-decoration: none;">Create an Account</a>
            </div>
        </div>
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
