<?php
// auth/register.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (is_logged_in()) { redirect(get_dashboard_url()); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $role     = 'student';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (get_user_by_email($email)) {
        $error = 'An account with this email already exists.';
    } else {
        $user_id = register_user($name, $email, $password, $role);
        if ($user_id) {
            $token = bin2hex(random_bytes(32));
            $token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            global $pdo;
            $stmt = $pdo->prepare("UPDATE users SET verification_token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $token_expires_at, $user_id]);
            
            $verify_url = BASE_URL . '/api/auth/verify.php?token=' . $token;
            $success = 'Account created! Please complete activation by clicking: <a href="' . h($verify_url) . '" style="color:#FF6B00; text-decoration:underline; font-weight:600;">Verify Email Address</a>';
            
            send_notification($user_id, 'Welcome to Exam Portal, ' . $name . '! Your account has been created successfully.');

            // Send Welcome Email with Account Registration & Login details
            $subject = "🎉 Welcome to Online Examination Portal - Account Details";
            $body = "
            <div style='font-family: Poppins, Arial, sans-serif; background-color: #f8fafc; padding: 25px;'>
                <div style='max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06);'>
                    <div style='text-align: center; margin-bottom: 24px;'>
                        <div style='display: inline-block; width: 56px; height: 56px; line-height: 56px; border-radius: 16px; background: rgba(255, 107, 0, 0.1); color: #ff6b00; font-size: 1.8rem; text-align: center;'>🎓</div>
                        <h2 style='color: #1e293b; margin: 12px 0 4px 0; font-size: 1.4rem;'>Online Examination Portal</h2>
                        <p style='color: #ff6b00; font-weight: 600; font-size: 0.95rem; margin: 0;'>🎉 Account Created Successfully</p>
                    </div>
                    
                    <p style='color: #1e293b; font-size: 0.95rem;'>Hello <strong>" . h($name) . "</strong>,</p>
                    <p style='color: #475569; font-size: 0.9rem; line-height: 1.6;'>Welcome to the Online Examination Portal! Here are your account registration & login details:</p>
                    
                    <div style='background: #f8fafc; border: 1.5px solid #e2e8f0; border-left: 4px solid #ff6b00; padding: 18px; border-radius: 10px; margin: 20px 0;'>
                        <table style='width: 100%; border-collapse: collapse; font-size: 0.9rem; color: #1e293b;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b; width: 140px;'><strong>Full Name:</strong></td>
                                <td style='padding: 6px 0; font-weight: 600;'>" . h($name) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Registered Email:</strong></td>
                                <td style='padding: 6px 0; font-weight: 600; color: #ff6b00;'>" . h($email) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Portal Role:</strong></td>
                                <td style='padding: 6px 0; font-weight: 600; color: #10b981;'>" . ucfirst(h($role)) . " Portal</td>
                            </tr>
                        </table>
                    </div>

                    <div style='text-align: center; margin-top: 24px; margin-bottom: 20px;'>
                        <a href='" . BASE_URL . "/auth/login.php' style='display: inline-block; padding: 12px 28px; background: #ff6b00; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(255, 107, 0, 0.3);'>Click Here to Login →</a>
                    </div>
                </div>
            </div>";
            send_async_email($user_id, 'registration_welcome', $email, $subject, $body);
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Online Examination Portal</title>
    <meta name="description" content="Create a new account on the Online Examination Portal.">
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
        .register-wrapper {
            display: flex;
            width: 100%;
            max-width: 1020px;
            background: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            min-height: 620px;
        }
        .register-hero {
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
            line-height: 1.6;
        }
        .hero-illustration {
            margin-top: auto;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .register-form-container {
            flex: 1;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
        }
        .register-header-text {
            margin-bottom: 24px;
        }
        .register-header-text h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #1E293B;
            margin-bottom: 4px;
        }
        .register-header-text p {
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
        .btn-register-primary {
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
            margin-top: 8px;
        }
        .btn-register-primary:hover {
            background: #E05D00 !important;
            box-shadow: 0 8px 20px rgba(255, 107, 0, 0.3) !important;
        }
        @media (max-width: 900px) {
            .register-wrapper {
                flex-direction: column;
                max-width: 480px;
            }
            .register-hero {
                padding: 32px 20px;
            }
            .hero-illustration {
                display: none;
            }
            .register-form-container {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="register-wrapper">
        <!-- Left Hero Banner -->
        <div class="register-hero">
            <div class="hero-accent-top"></div>
            <div class="hero-accent-bottom"></div>
            
            <div class="hero-content">
                <div class="hero-logo-emblem">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <h1 class="hero-title">ONLINE <span class="highlight-orange">EXAM PORTAL</span></h1>
                <p class="hero-subtitle">Create your account to access secure online examinations, quizzes & coding evaluations</p>

                <div class="hero-illustration">
                    <svg width="280" height="190" viewBox="0 0 350 240" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="260" cy="50" r="14" stroke="#475569" stroke-width="2" fill="none"/>
                        <line x1="260" y1="50" x2="260" y2="42" stroke="#FF6B00" stroke-width="2" stroke-linecap="round"/>
                        <line x1="260" y1="50" x2="267" y2="50" stroke="#FF6B00" stroke-width="2" stroke-linecap="round"/>
                        
                        <rect x="90" y="170" width="180" height="6" rx="3" fill="#475569"/>
                        <rect x="110" y="176" width="5" height="40" fill="#334155"/>
                        <rect x="250" y="176" width="5" height="40" fill="#334155"/>
                        
                        <rect x="45" y="155" width="40" height="10" rx="3" fill="#94A3B8"/>
                        <rect x="38" y="110" width="10" height="55" rx="3" fill="#94A3B8"/>
                        <line x1="45" y1="165" x2="40" y2="216" stroke="#64748B" stroke-width="4" stroke-linecap="round"/>
                        <line x1="80" y1="165" x2="85" y2="216" stroke="#64748B" stroke-width="4" stroke-linecap="round"/>
                        
                        <circle cx="102" cy="90" r="14" fill="#FED7AA"/>
                        <path d="M80 108 C80 102 90 100 102 100 C114 100 124 102 124 108 L118 155 L86 155 Z" fill="#FF6B00"/>
                        <rect x="86" y="155" width="16" height="55" rx="4" fill="#1E293B"/>
                        <rect x="104" y="155" width="16" height="55" rx="4" fill="#0F172A"/>
                        
                        <path d="M150 170 L165 130 L210 130 L195 170 Z" fill="#E2E8F0"/>
                        <rect x="142" y="168" width="65" height="3" rx="1.5" fill="#94A3B8"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="register-form-container">
            <div class="register-header-text">
                <h2>Create <span class="highlight-orange">Account</span></h2>
                <p>Join the Online Examination Portal today</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:16px;"><i class="fa-solid fa-circle-xmark"></i> <?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="name">Full Name</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-user field-icon"></i>
                        <input type="text" id="name" name="name" class="form-control"
                               placeholder="John Doe" value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-envelope field-icon"></i>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="you@example.com" value="<?= h($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock field-icon"></i>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 chars" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm">Confirm Password</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-shield-halved field-icon"></i>
                            <input type="password" id="confirm" name="confirm" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-register-primary btn-block">CREATE ACCOUNT</button>
            </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 24px; font-size: 0.85rem; color: #64748B;">
                Already have an account? <a href="login.php" style="color: #FF6B00; font-weight: 700; text-decoration: none;">Sign In</a>
            </div>
        </div>
    </div>

    <!-- Site Footer -->
    <footer class="site-footer" style="margin-top: 20px; border-radius: 12px; max-width: 500px;">
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
</script>
</body>
</html>
