<?php
// includes/header.php — Common HTML head + top bar
$page_title = $page_title ?? APP_NAME;
$unread_count = is_logged_in() ? get_unread_count((int)$_SESSION['user_id']) : 0;
$role = get_role();

// Automatically check and send 1-day prior deadline reminders to students
check_and_send_deadline_reminders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Online Examination Portal — Secure, timer-based MCQ exams for students and admins.">
    <title><?= h($page_title) ?> | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- SweetAlert2 library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function() {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        })();
    </script>
</head>
<body>
<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>

<!-- SIDEBAR OVERLAY FOR MOBILE -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR NAV -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon"><i class="fa-solid fa-graduation-cap" style="color: #FF6B00;"></i></span>
        <div>
            <div class="brand-name">ExamPortal</div>
            <div class="brand-role"><?= ucfirst($role) ?> Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'student'): ?>
            <a href="<?= BASE_URL ?>/student/dashboard.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='dashboard.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-house"></i></span> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/student/exams.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='exams.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-file-signature"></i></span> Available Exams
            </a>
            <a href="<?= BASE_URL ?>/student/quiz_exams.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='quiz_exams.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-pen-to-square"></i></span> Quiz Exams
            </a>
            <a href="<?= BASE_URL ?>/student/coding_problems.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='coding_problems.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-code"></i></span> Coding Exams
            </a>
            <a href="<?= BASE_URL ?>/student/tasks.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='tasks.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span> Assigned Tasks
            </a>
            <a href="<?= BASE_URL ?>/student/results.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='results.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-column"></i></span> My Results
            </a>

        <?php elseif ($role === 'faculty'): ?>
            <a href="<?= BASE_URL ?>/faculty/dashboard.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='dashboard.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-house"></i></span> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/faculty/add_questions.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='add_questions.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-circle-question"></i></span> Add Questions
            </a>
            <a href="<?= BASE_URL ?>/faculty/schedule_exam.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='schedule_exam.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Schedule Exam
            </a>
            <a href="<?= BASE_URL ?>/faculty/manage_tasks.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='manage_tasks.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span> Manage Tasks
            </a>
            <a href="<?= BASE_URL ?>/faculty/view_results.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='view_results.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-trophy"></i></span> View Results
            </a>
            <a href="<?= BASE_URL ?>/faculty/view_progress.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='view_progress.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span> Student Progress
            </a>
            <a href="<?= BASE_URL ?>/faculty/feedback.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='feedback.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-comments"></i></span> Feedback
            </a>
        <?php elseif ($role === 'admin'): ?>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='dashboard.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-house"></i></span> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/admin/manage_users.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='manage_users.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span> Student List
            </a>
            <a href="<?= BASE_URL ?>/admin/faculty.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='faculty.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-chalkboard-user"></i></span> Faculty Management
            </a>
            <a href="<?= BASE_URL ?>/admin/schedule_exam.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='schedule_exam.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span> Schedule Exam
            </a>
            <a href="<?= BASE_URL ?>/admin/publish_exam.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='publish_exam.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-upload"></i></span> Publish Exam
            </a>
            <a href="<?= BASE_URL ?>/admin/add_questions.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='add_questions.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-circle-question"></i></span> Add Questions
            </a>
            <a href="<?= BASE_URL ?>/admin/view_results.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='view_results.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-trophy"></i></span> View Results
            </a>
            <a href="<?= BASE_URL ?>/admin/settings.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])==='settings.php')?'active':'' ?>">
                <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span> System Settings
            </a>

        <?php endif; ?>
    </nav>

    <?php
    $current_user_pic = '';
    if (is_logged_in()) {
        $u_data = get_user_by_id((int)$_SESSION['user_id']);
        if (!empty($u_data['profile_pic']) && file_exists(dirname(__DIR__) . '/' . $u_data['profile_pic'])) {
            $current_user_pic = BASE_URL . '/' . $u_data['profile_pic'];
        }
    }
    ?>
    <div class="sidebar-footer">
        <div class="user-info" onclick="location.href='<?= BASE_URL ?>/profile.php'" style="cursor:pointer;" title="View Profile">
            <div class="user-avatar" style="overflow:hidden; display:flex; align-items:center; justify-content:center;">
                <?php if ($current_user_pic): ?>
                    <img src="<?= $current_user_pic ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="user-name"><?= h($_SESSION['user_name'] ?? '') ?></div>
                <div class="user-email"><?= h($_SESSION['user_email'] ?? '') ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
    </div>
</div>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <!-- TOP BAR -->
    <div class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title"><?= h($page_title) ?></div>
        
        <div class="topbar-actions">


            <!-- Notifications -->
            <div class="notif-btn" onclick="toggleNotifications()" title="Notifications">
                <i class="fa-regular fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </div>

            <!-- Dark Mode Toggle Button (Left side of Profile) -->
            <button class="theme-toggle-btn" onclick="toggleTheme()" id="themeToggleBtn" title="Toggle Dark/Light Mode">
                <i class="fa-solid fa-moon"></i>
            </button>

            <!-- Profile Dropdown -->
            <div class="profile-dropdown-container">
                <div class="profile-toggle" onclick="toggleProfileDropdown(event)">
                    <div class="profile-avatar" style="overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <?php if ($current_user_pic): ?>
                            <img src="<?= $current_user_pic ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                        <?php else: ?>
                            <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <span class="profile-name-text"><?= h($_SESSION['user_name'] ?? '') ?></span>
                    <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; color: var(--text-muted);"></i>
                </div>
                <div class="profile-dropdown-menu" id="profileDropdown">
                    <a href="<?= BASE_URL ?>/profile.php" class="profile-dropdown-item"><i class="fa-regular fa-user"></i> My Profile</a>
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="profile-dropdown-item logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                </div>
            </div>

            <div class="topbar-user">
                <span class="role-chip <?= $role ?>"><?= ucfirst($role) ?></span>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION DROPDOWN -->
    <div class="notif-panel" id="notifPanel" style="display:none;">
        <div class="notif-header"><i class="fa-solid fa-bell"></i> Notifications</div>
        <?php
        if (is_logged_in()) {
            $notifs = get_notifications((int)$_SESSION['user_id'], 6);
            mark_notifications_read((int)$_SESSION['user_id']);
            if ($notifs):
                foreach ($notifs as $n): ?>
                    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div><?= h($n['message']) ?></div>
                        <div class="notif-time"><?= format_datetime($n['created_at']) ?></div>
                    </div>
                <?php endforeach;
            else: ?>
                <div class="notif-empty">No notifications yet</div>
            <?php endif;
        }
        ?>
    </div>

    <!-- PAGE CONTENT START -->
    <div class="page-content">

    <!-- Flash Messages via SweetAlert2 -->
    <?php
    $flash_success = get_flash('success');
    $flash_error   = get_flash('error');
    if ($flash_success): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: <?= json_encode($flash_success) ?>,
                    confirmButtonColor: '#2563EB',
                    timer: 4500
                });
            });
        </script>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: <?= json_encode($flash_error) ?>,
                    confirmButtonColor: '#2563EB'
                });
            });
        </script>
    <?php endif; ?>
