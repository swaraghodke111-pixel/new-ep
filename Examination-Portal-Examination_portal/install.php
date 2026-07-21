<?php
// install.php — One-time database installer
if (session_status() === PHP_SESSION_NONE) session_start();

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'exam_portal');

$error = '';
$success = '';
$installed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id`                INT(11) NOT NULL AUTO_INCREMENT,
            `name`              VARCHAR(100) NOT NULL,
            `email`             VARCHAR(150) NOT NULL UNIQUE,
            `password`          VARCHAR(255) NOT NULL,
            `role`              ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
            `email_verified`    TINYINT(1) NOT NULL DEFAULT 0,
            `verify_token`      VARCHAR(64) DEFAULT NULL,
            `is_verified`       TINYINT(1) NOT NULL DEFAULT 0,
            `verification_token` VARCHAR(64) DEFAULT NULL,
            `token_expires_at`  DATETIME DEFAULT NULL,
            `reset_token`       VARCHAR(64) DEFAULT NULL,
            `reset_expires`     DATETIME DEFAULT NULL,
            `profile_pic`       VARCHAR(255) DEFAULT NULL,
            `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── EXAMS TABLE ───────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `exams` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `title`         VARCHAR(200) NOT NULL,
            `description`   TEXT DEFAULT NULL,
            `duration`      INT(11) NOT NULL DEFAULT 60 COMMENT 'minutes',
            `start_time`    DATETIME NOT NULL,
            `end_time`      DATETIME NOT NULL,
            `total_marks`   INT(11) NOT NULL DEFAULT 0,
            `pass_marks`    INT(11) NOT NULL DEFAULT 0,
            `created_by`    INT(11) NOT NULL,
            `is_published`  TINYINT(1) NOT NULL DEFAULT 0,
            `randomize`     TINYINT(1) NOT NULL DEFAULT 1,
            `status`        ENUM('draft','scheduled','active','completed') NOT NULL DEFAULT 'draft',
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── QUESTIONS TABLE ───────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `questions` (
            `id`        INT(11) NOT NULL AUTO_INCREMENT,
            `exam_id`   INT(11) NOT NULL,
            `question`  TEXT NOT NULL,
            `opt1`      VARCHAR(500) NOT NULL,
            `opt2`      VARCHAR(500) NOT NULL,
            `opt3`      VARCHAR(500) NOT NULL,
            `opt4`      VARCHAR(500) NOT NULL,
            `answer`    VARCHAR(500) NOT NULL,
            `marks`     INT(11) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── ANSWERS TABLE ─────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `answers` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`       INT(11) NOT NULL,
            `exam_id`       INT(11) NOT NULL,
            `question_id`   INT(11) NOT NULL,
            `answer`        VARCHAR(500) DEFAULT NULL,
            `is_correct`    TINYINT(1) DEFAULT NULL,
            `answered_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_answer` (`user_id`,`exam_id`,`question_id`),
            FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`exam_id`)     REFERENCES `exams`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── RESULTS TABLE ─────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `results` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`       INT(11) NOT NULL,
            `exam_id`       INT(11) NOT NULL,
            `score`         INT(11) NOT NULL DEFAULT 0,
            `total`         INT(11) NOT NULL DEFAULT 0,
            `percentage`    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `passed`        TINYINT(1) NOT NULL DEFAULT 0,
            `submitted_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `time_taken`    INT(11) DEFAULT NULL COMMENT 'seconds',
            `auto_submitted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_result` (`user_id`,`exam_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── NOTIFICATIONS TABLE ───────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id`         INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`    INT(11) NOT NULL,
            `message`    TEXT NOT NULL,
            `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── FEEDBACK TABLE ────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `feedback` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `faculty_id`  INT(11) NOT NULL,
            `student_id`  INT(11) NOT NULL,
            `exam_id`     INT(11) NOT NULL,
            `message`     TEXT NOT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`exam_id`)    REFERENCES `exams`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── EXAM ATTEMPTS TABLE ───────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `exam_attempts` (
            `id`         INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`    INT(11) NOT NULL,
            `exam_id`    INT(11) NOT NULL,
            `start_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status`     ENUM('in_progress','submitted','expired') NOT NULL DEFAULT 'in_progress',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_attempt` (`user_id`,`exam_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── EMAIL LOGS TABLE ──────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
            `id`           INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`      INT(11) NOT NULL,
            `email_type`   VARCHAR(50) NOT NULL,
            `recipient`    VARCHAR(150) NOT NULL,
            `subject`      VARCHAR(255) NOT NULL,
            `body`         TEXT NOT NULL,
            `sent_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── TASKS TABLE ───────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `tasks` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `title`       VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `deadline`    DATETIME NOT NULL,
            `created_by`  INT(11) NOT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── TASK SUBMISSIONS TABLE ────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `task_submissions` (
            `id`             INT(11) NOT NULL AUTO_INCREMENT,
            `task_id`        INT(11) NOT NULL,
            `user_id`        INT(11) NOT NULL,
            `submission_text` TEXT DEFAULT NULL,
            `file_path`      VARCHAR(255) DEFAULT NULL,
            `status`         VARCHAR(50) NOT NULL DEFAULT 'pending',
            `grade`          VARCHAR(10) DEFAULT NULL,
            `feedback`       TEXT DEFAULT NULL,
            `submitted_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_task_sub` (`task_id`, `user_id`),
            FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── CODING PROBLEMS TABLE ─────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `coding_problems` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `title`         VARCHAR(255) NOT NULL,
            `description`   TEXT NOT NULL,
            `input_format`  TEXT NOT NULL,
            `output_format` TEXT NOT NULL,
            `sample_input`  TEXT NOT NULL,
            `sample_output` TEXT NOT NULL,
            `time_limit`    INT(11) NOT NULL DEFAULT 1000 COMMENT 'milliseconds',
            `memory_limit`  INT(11) NOT NULL DEFAULT 256 COMMENT 'MB',
            `difficulty`    ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
            `created_by`    INT(11) NOT NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── CODING SUBMISSIONS TABLE ──────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `coding_submissions` (
            `id`           INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`      INT(11) NOT NULL,
            `problem_id`   INT(11) NOT NULL,
            `language`     VARCHAR(50) NOT NULL,
            `code`         TEXT NOT NULL,
            `status`       VARCHAR(50) NOT NULL DEFAULT 'Pending',
            `runtime`      INT(11) DEFAULT NULL COMMENT 'milliseconds',
            `memory`       INT(11) DEFAULT NULL COMMENT 'MB',
            `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`problem_id`) REFERENCES `coding_problems`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── SYSTEM SETTINGS TABLE ─────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── ACADEMIC FOUNDATION TABLES ───────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `name`        VARCHAR(100) NOT NULL UNIQUE,
            `code`        VARCHAR(20) NOT NULL UNIQUE,
            `description` TEXT DEFAULT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `programs` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `department_id` INT(11) NOT NULL,
            `name`          VARCHAR(150) NOT NULL,
            `code`          VARCHAR(20) NOT NULL UNIQUE,
            `description`   TEXT DEFAULT NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
            INDEX `idx_program_dept` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `academic_years` (
            `id`         INT(11) NOT NULL AUTO_INCREMENT,
            `name`       VARCHAR(50) NOT NULL UNIQUE,
            `start_date` DATE NOT NULL,
            `end_date`   DATE NOT NULL,
            `status`     ENUM('active','inactive') DEFAULT 'active',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `semesters` (
            `id`               INT(11) NOT NULL AUTO_INCREMENT,
            `academic_year_id` INT(11) NOT NULL,
            `name`             VARCHAR(50) NOT NULL,
            `start_date`       DATE DEFAULT NULL,
            `end_date`         DATE DEFAULT NULL,
            `status`           ENUM('active','inactive') DEFAULT 'active',
            `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
            INDEX `idx_semester_ay` (`academic_year_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `sections` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `program_id`  INT(11) NOT NULL,
            `semester_id` INT(11) NOT NULL,
            `name`        VARCHAR(50) NOT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`program_id`)  REFERENCES `programs`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_section` (`program_id`, `semester_id`, `name`),
            INDEX `idx_section_program` (`program_id`),
            INDEX `idx_section_semester` (`semester_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `department_id` INT(11) NOT NULL,
            `name`          VARCHAR(150) NOT NULL,
            `code`          VARCHAR(20) NOT NULL UNIQUE,
            `description`   TEXT DEFAULT NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
            INDEX `idx_subject_dept` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `faculty_profiles` (
            `id`            INT(11) NOT NULL AUTO_INCREMENT,
            `user_id`       INT(11) NOT NULL UNIQUE,
            `faculty_id`    VARCHAR(50) NOT NULL UNIQUE,
            `department_id` INT(11) DEFAULT NULL,
            `designation`   VARCHAR(100) NOT NULL,
            `phone`         VARCHAR(20) DEFAULT NULL,
            `office_location` VARCHAR(100) DEFAULT NULL,
            `status`        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
            `joining_date`  DATE NOT NULL,
            `qualification` VARCHAR(255) NOT NULL,
            `bio`           TEXT DEFAULT NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
            INDEX `idx_faculty_dept` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $student_pass = password_hash('Admin@123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT IGNORE INTO `users` (`id`,`name`,`email`,`password`,`role`,`email_verified`,`is_verified`) VALUES
            (1, 'Super Admin','admin@exam.com','$student_pass','admin',1,1),
            (2, 'Demo Student','student@exam.com','$student_pass','student',1,1)
        ");

        // Seed default Tasks
        $pdo->exec("INSERT IGNORE INTO `tasks` (`id`, `title`, `description`, `deadline`, `created_by`) VALUES
            (1, 'Database Design Assignment', 'Create an ER diagram and normalize the tables for an e-commerce platform up to 3NF.', DATE_ADD(NOW(), INTERVAL 5 DAY), 1),
            (2, 'RESTful API Homework', 'Implement a secure RESTful API in PHP with CRUD operations and user role validation.', DATE_ADD(NOW(), INTERVAL 2 DAY), 1)
        ");

        // Seed default Coding Problems
        $pdo->exec("INSERT IGNORE INTO `coding_problems` (`id`, `title`, `description`, `input_format`, `output_format`, `sample_input`, `sample_output`, `difficulty`, `created_by`) VALUES
            (1, 'Two Sum', 'Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target.', 'First line: space-separated integers for array.\nSecond line: target integer.', 'Space-separated indices.', '2 7 11 15\n9', '0 1', 'easy', 1),
            (2, 'Fibonacci Number', 'Write a function to compute the N-th Fibonacci number.', 'A single integer N.', 'N-th Fibonacci number.', '5', '5', 'easy', 1),
            (3, 'Reverse Words in a String', 'Given an input string s, reverse the order of the words.', 'A string s containing words.', 'Reversed string.', 'the sky is blue', 'blue is sky the', 'medium', 1)
        ");

        // Seed default System Settings
        $pdo->exec("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
            ('allow_registration', '1'),
            ('maintenance_mode', '0'),
            ('default_exam_duration', '60'),
            ('smtp_server_simulation', '1')
        ");

        $installed = true;
        $success = 'Database installed successfully! Default credentials: admin@exam.com / Admin@123';

    } catch (PDOException $e) {
        $error = 'Installation failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — Online Examination Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F5F0E6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #2E2E2E;
            padding: 24px;
        }
        .card {
            background: #FFF8F0;
            border: 1px solid #D8CBB8;
            border-radius: 12px;
            padding: 48px;
            width: 100%;
            max-width: 520px;
            color: #2E2E2E;
            box-shadow: 0 4px 20px rgba(92, 64, 51, 0.05);
            margin-bottom: 24px;
        }
        .logo { text-align: center; margin-bottom: 32px; }
        .logo h1 { font-size: 1.8rem; font-weight: 700; color: #5C4033; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .logo p { color: #4a4a4a; margin-top: 6px; }
        .badge { display: inline-block; background: rgba(166,124,82,0.1); border: 1px solid #A67C52; color: #A67C52; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; margin-top: 8px; font-weight: 600; }
        .section { margin: 24px 0; }
        .section h3 { color: #5C4033; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; font-weight: 700; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #D8CBB8; font-size: 0.9rem; }
        .info-row span:last-child { color: #10b981; font-weight: 600; }
        .btn { width: 100%; padding: 14px; background: #A67C52; border: none; border-radius: 10px; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 24px; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(166,124,82,0.25); background: #7A5C48; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .alert { padding: 14px 18px; border-radius: 10px; margin: 16px 0; font-size: 0.9rem; }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #059669; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #dc2626; }
        .creds-box { background: #fffdf8; border: 1px solid #D8CBB8; border-radius: 10px; padding: 16px; margin-top: 16px; }
        .creds-box p { font-size: 0.85rem; margin: 4px 0; }
        .creds-box span { color: #A67C52; font-weight: 600; }
        .goto-btn { display: block; text-align: center; margin-top: 16px; padding: 12px; background: #10b981; border-radius: 10px; color: #fff; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .goto-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.25); background: #059669; }
        .warning { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); color: #d97706; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; margin-top: 12px; }
        
        /* Sticky Footer */
        .site-footer {
            background-color: #E5E7EB;
            color: #000000;
            padding: 16px 24px;
            text-align: center;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            justify-content: center;
            gap: 24px;
            width: 100%;
            max-width: 520px;
        }
        .site-footer a {
            color: #000000;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        .site-footer a:hover {
            text-decoration: underline;
        }
        
        /* Redesigned Modal Styling */
        .modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-backdrop.show {
            display: flex;
        }
        .modal-container {
            background: #FFF8F0;
            border: 1px solid #D8CBB8;
            border-radius: 12px;
            width: 90%;
            max-width: 550px;
            max-height: 85vh;
            overflow-y: auto;
            color: #2E2E2E;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #D8CBB8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #5C4033;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: #2E2E2E;
        }
        .modal-close-btn:hover {
            color: #ef4444;
        }
        .modal-body {
            padding: 24px;
            font-size: 0.9rem;
            line-height: 1.6;
            text-align: left;
        }
        .modal-body h4 {
            margin-top: 16px;
            margin-bottom: 8px;
            color: #5C4033;
            font-weight: 600;
        }
        .modal-body p {
            margin-bottom: 12px;
            color: #2E2E2E;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1><i class="fa-solid fa-graduation-cap" style="color: #A67C52;"></i> Exam Portal</h1>
        <p>Online Examination System</p>
        <span class="badge">PHP + XAMPP + MySQL</span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($installed): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <div class="creds-box">
            <p>👨‍🎓 Student: <span>student@exam.com</span> / <span>Admin@123</span></p>
        </div>
        <div class="warning">⚠️ Delete or rename install.php after installation for security.</div>
        <a href="index.php" class="goto-btn">🚀 Go to Portal →</a>
    <?php else: ?>
        <div class="section">
            <h3>System Requirements</h3>
            <div class="info-row"><span>PHP Version</span><span><?= phpversion() ?></span></div>
            <div class="info-row"><span>PDO Extension</span><span><?= extension_loaded('pdo') ? '✅ Loaded' : '❌ Missing' ?></span></div>
            <div class="info-row"><span>PDO MySQL</span><span><?= extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Missing' ?></span></div>
            <div class="info-row"><span>Server</span><span><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?></span></div>
        </div>
        <div class="section">
            <h3>Database Configuration</h3>
            <div class="info-row"><span>Host</span><span><?= DB_HOST ?></span></div>
            <div class="info-row"><span>User</span><span><?= DB_USER ?></span></div>
            <div class="info-row"><span>Database</span><span><?= DB_NAME ?></span></div>
        </div>
        <form method="POST">
            <button type="submit" name="install" class="btn">🔧 Install Database & Tables</button>
        </form>
    <?php endif; ?>
</div>

<!-- Site Footer -->
<footer class="site-footer">
    <a onclick="openAboutModal()">About Us</a>
    <a onclick="openHelpModal()">Help</a>
</footer>

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
