<?php
// includes/functions.php — Shared helper functions
require_once dirname(__DIR__) . '/config.php';

// ── Authentication Helpers ───────────────────────────────────────────────────

function login_user(array $user): void {
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email']= $user['email'];
    $_SESSION['user_role'] = $user['role'];
    session_regenerate_id(true);
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function get_role(): string {
    return $_SESSION['user_role'] ?? '';
}

function require_role(string ...$roles): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (!in_array(get_role(), $roles, true)) {
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
        exit;
    }
}

// ── User Functions ────────────────────────────────────────────────────────────

function get_user_by_id(int $id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_user_by_email(string $email): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function register_user(string $name, string $email, string $password, string $role = 'student'): int|false {
    global $pdo;
    $hash  = password_hash($password, PASSWORD_DEFAULT);
    
    // Securely generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $token_expires_at   = date('Y-m-d H:i:s', time() + 86400);
    
    // Set is_verified = 1 and email_verified = 1 by default for instant student access
    $stmt  = $pdo->prepare("INSERT INTO users (name, email, password, role, is_verified, email_verified, verification_token, token_expires_at) VALUES (?,?,?,?,1,1,?,?)");
    if ($stmt->execute([$name, $email, $hash, $role, $verification_token, $token_expires_at])) {
        $user_id = (int)$pdo->lastInsertId();
        return $user_id;
    }
    return false;
}

function count_users_by_role(string $role): int {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
    $stmt->execute([$role]);
    return (int) $stmt->fetchColumn();
}

// ── Exam Functions ────────────────────────────────────────────────────────────

function get_published_exams(): array {
    global $pdo;
    return $pdo->query("
        SELECT e.*, u.name AS creator_name,
               (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) AS question_count
        FROM exams e
        JOIN users u ON u.id = e.created_by
        WHERE e.is_published = 1
        ORDER BY e.start_time ASC
    ")->fetchAll();
}

function get_exam_by_id(int $id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT e.*, u.name AS creator_name,
               (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) AS question_count
        FROM exams e
        JOIN users u ON u.id = e.created_by
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_all_exams(): array {
    global $pdo;
    return $pdo->query("
        SELECT e.*, u.name AS creator_name,
               (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) AS question_count,
               (SELECT COUNT(*) FROM results WHERE exam_id=e.id) AS attempt_count
        FROM exams e
        JOIN users u ON u.id = e.created_by
        ORDER BY e.created_at DESC
    ")->fetchAll();
}

function get_exams_by_creator(int $user_id): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) AS question_count,
               (SELECT COUNT(*) FROM results WHERE exam_id=e.id) AS attempt_count
        FROM exams e
        WHERE e.created_by = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function create_exam(array $data): int {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO exams (title, description, duration, start_time, end_time, pass_marks, created_by, randomize)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $data['title'],
        $data['description'] ?? '',
        $data['duration'],
        $data['start_time'],
        $data['end_time'],
        $data['pass_marks'],
        $data['created_by'],
        $data['randomize'] ?? 1,
    ]);
    return (int) $pdo->lastInsertId();
}

// ── Question Functions ────────────────────────────────────────────────────────

function get_questions_by_exam(int $exam_id, bool $randomize = false): array {
    global $pdo;
    $order = $randomize ? 'RAND()' : 'id ASC';
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY $order");
    $stmt->execute([$exam_id]);
    return $stmt->fetchAll();
}

function add_question(array $data): int {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO questions (exam_id, question, opt1, opt2, opt3, opt4, answer, marks)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $data['exam_id'], $data['question'],
        $data['opt1'], $data['opt2'], $data['opt3'], $data['opt4'],
        $data['answer'], $data['marks'] ?? 1,
    ]);
    return (int) $pdo->lastInsertId();
}

function delete_question(int $id): void {
    global $pdo;
    $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$id]);
}

function update_question(array $data): void {
    global $pdo;
    $pdo->prepare("
        UPDATE questions SET question=?, opt1=?, opt2=?, opt3=?, opt4=?, answer=?, marks=?
        WHERE id=?
    ")->execute([
        $data['question'], $data['opt1'], $data['opt2'],
        $data['opt3'], $data['opt4'], $data['answer'],
        $data['marks'], $data['id'],
    ]);
}

// ── Answer / Result Functions ─────────────────────────────────────────────────

function start_attempt(int $user_id, int $exam_id): void {
    global $pdo;
    $pdo->prepare("
        INSERT IGNORE INTO exam_attempts (user_id, exam_id) VALUES (?,?)
    ")->execute([$user_id, $exam_id]);
}

function get_attempt(int $user_id, int $exam_id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM exam_attempts WHERE user_id=? AND exam_id=?");
    $stmt->execute([$user_id, $exam_id]);
    return $stmt->fetch() ?: null;
}

function save_answer(int $user_id, int $exam_id, int $question_id, string $answer): int {
    global $pdo;
    // Check correct
    $q = $pdo->prepare("SELECT answer FROM questions WHERE id=?");
    $q->execute([$question_id]);
    $correct_ans = $q->fetchColumn();
    $is_correct = (strtolower(trim($answer)) === strtolower(trim($correct_ans))) ? 1 : 0;

    // Fetch existing attempt count
    $chk = $pdo->prepare("SELECT attempt_count FROM answers WHERE user_id=? AND exam_id=? AND question_id=?");
    $chk->execute([$user_id, $exam_id, $question_id]);
    $existing_count = (int)($chk->fetchColumn() ?: 0);
    $new_count = min(3, $existing_count + 1);

    $pdo->prepare("
        INSERT INTO answers (user_id, exam_id, question_id, answer, is_correct, attempt_count)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE answer=VALUES(answer), is_correct=VALUES(is_correct), attempt_count=VALUES(attempt_count)
    ")->execute([$user_id, $exam_id, $question_id, $answer, $is_correct, $new_count]);

    return $new_count;
}

function submit_exam(int $user_id, int $exam_id, bool $auto = false): array {
    global $pdo;
    $exam = get_exam_by_id($exam_id);
    $questions = get_questions_by_exam($exam_id);

    // Calculate score
    $score = 0;
    $total = 0;
    foreach ($questions as $q) {
        $total += $q['marks'];
        $stmt = $pdo->prepare("SELECT is_correct, answer FROM answers WHERE user_id=? AND exam_id=? AND question_id=?");
        $stmt->execute([$user_id, $exam_id, $q['id']]);
        $ans = $stmt->fetch();
        if ($ans && $ans['is_correct']) {
            $score += $q['marks'];
        }
    }

    $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
    
    $pass_req = (float)($exam['pass_marks'] ?? 40);
    if ($pass_req <= $total && $total > 0) {
        $passed = ($score >= $pass_req);
    } else {
        $passed = ($percentage >= $pass_req);
    }

    // Calculate time taken
    $attempt = get_attempt($user_id, $exam_id);
    $time_taken = $attempt ? (time() - strtotime($attempt['start_time'])) : 0;

    // Insert or update result
    $pdo->prepare("
        INSERT INTO results (user_id, exam_id, score, total, percentage, passed, time_taken, auto_submitted)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE score=VALUES(score), total=VALUES(total), percentage=VALUES(percentage),
        passed=VALUES(passed), submitted_at=CURRENT_TIMESTAMP, time_taken=VALUES(time_taken), auto_submitted=VALUES(auto_submitted)
    ")->execute([$user_id, $exam_id, $score, $total, $percentage, $passed ? 1 : 0, $time_taken, $auto ? 1 : 0]);

    // Mark attempt as submitted
    $pdo->prepare("UPDATE exam_attempts SET status='submitted' WHERE user_id=? AND exam_id=?")->execute([$user_id, $exam_id]);

    // Update exam total_marks
    $pdo->prepare("UPDATE exams SET total_marks=? WHERE id=?")->execute([$total, $exam_id]);

    return ['score' => $score, 'total' => $total, 'percentage' => $percentage, 'passed' => $passed];
}

function get_result(int $user_id, int $exam_id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM results WHERE user_id=? AND exam_id=?");
    $stmt->execute([$user_id, $exam_id]);
    return $stmt->fetch() ?: null;
}

function get_student_results(int $user_id): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, e.title, e.duration
        FROM results r
        JOIN exams e ON e.id = r.exam_id
        WHERE r.user_id = ?
        ORDER BY r.submitted_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_exam_results(int $exam_id): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, u.name, u.email
        FROM results r
        JOIN users u ON u.id = r.user_id
        WHERE r.exam_id = ?
        ORDER BY r.score DESC
    ");
    $stmt->execute([$exam_id]);
    return $stmt->fetchAll();
}

function get_all_results(): array {
    global $pdo;
    return $pdo->query("
        SELECT r.*, u.name AS student_name, u.email AS student_email, e.title AS exam_title
        FROM results r
        JOIN users u ON u.id = r.user_id
        JOIN exams e ON e.id = r.exam_id
        ORDER BY r.submitted_at DESC
    ")->fetchAll();
}

// ── Notification Functions ────────────────────────────────────────────────────

function send_notification(int $user_id, string $message): void {
    global $pdo;
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)")->execute([$user_id, $message]);
}

function get_notifications(int $user_id, int $limit = 10): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function get_unread_count(int $user_id): int {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

function mark_notifications_read(int $user_id): void {
    global $pdo;
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user_id]);
}

// ── Utility Functions ─────────────────────────────────────────────────────────

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function get_flash(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function flash_messages(): void {
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $key => $msg) {
            $class = ($key === 'error' || $key === 'danger') ? 'alert-error' : (($key === 'success') ? 'alert-success' : 'alert-info');
            $icon  = ($key === 'error' || $key === 'danger') ? 'fa-circle-xmark' : 'fa-circle-check';
            echo '<div class="alert ' . $class . '"><i class="fa-solid ' . $icon . '"></i> ' . $msg . '</div>';
        }
        unset($_SESSION['flash']);
    }
}

function format_datetime(string $dt): string {
    return date('d M Y, h:i A', strtotime($dt));
}

function time_remaining(string $start_time, int $duration_minutes): int {
    $end = strtotime($start_time) + ($duration_minutes * 60);
    return max(0, $end - time());
}

function exam_status_badge(array $exam): string {
    $now = time();
    $start = strtotime($exam['start_time']);
    $end   = strtotime($exam['end_time']);

    if (!$exam['is_published'])       return '<span class="badge badge-draft">Draft</span>';
    if ($now < $start)                return '<span class="badge badge-scheduled">Scheduled</span>';
    if ($now >= $start && $now < $end) return '<span class="badge badge-active">Active</span>';
    return '<span class="badge badge-completed">Completed</span>';
}

function get_dashboard_url(): string {
    $role = get_role();
    return match($role) {
        'admin'   => BASE_URL . '/admin/dashboard.php',
        'faculty' => BASE_URL . '/faculty/dashboard.php',
        default   => BASE_URL . '/student/dashboard.php',
    };
}

function send_smtp_verification_email(int $user_id, string $email, string $token): void {
    $verify_url = BASE_URL . "/api/auth/verify.php?token=" . $token;
    $subject = "Verify Your Email Address - " . APP_NAME;
    
    $body = "<html>
<head>
    <title>Email Verification</title>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f0c29; color: #fff; padding: 40px; }
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 15px; max-width: 500px; margin: 0 auto; text-align: center; }
        .btn { display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 20px; }
        .footer { margin-top: 20px; font-size: 0.8rem; color: rgba(255,255,255,0.5); }
    </style>
</head>
<body>
    <div class='card'>
        <h2>Welcome to " . APP_NAME . "!</h2>
        <p>Please verify your email address to activate your account. This link will expire in 24 hours.</p>
        <a href='" . $verify_url . "' class='btn'>Verify Email</a>
        <p class='footer'>Or copy & paste: " . $verify_url . "</p>
    </div>
</body>
</html>";

    log_email($user_id, 'verification', $email, $subject, $body);
}

function get_help_queries(?int $user_id = null): array {
    global $pdo;
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT * FROM help_queries WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->query("SELECT * FROM help_queries ORDER BY created_at DESC");
    }
    return $stmt->fetchAll() ?: [];
}

function reply_to_help_query(int $query_id, int $admin_id, string $reply_text): bool {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE help_queries
        SET admin_reply = ?, replied_by = ?, replied_at = NOW(), status = 'replied'
        WHERE id = ?
    ");
    $ok = $stmt->execute([$reply_text, $admin_id, $query_id]);

    if ($ok) {
        // Find query owner to send notification
        $q = $pdo->prepare("SELECT user_id, user_name, query_text FROM help_queries WHERE id = ?");
        $q->execute([$query_id]);
        $row = $q->fetch();
        if ($row && $row['user_id']) {
            $notif = "💬 Super Admin replied to your query: \"" . mb_strimwidth($reply_text, 0, 120, "…") . "\"";
            send_notification((int)$row['user_id'], $notif);
        }
    }
    return $ok;
}

function send_async_email(int $user_id, string $type, string $recipient, string $subject, string $body_html): bool {
    global $pdo;
    try {
        // 1. Log email to database immediately
        $stmt = $pdo->prepare("INSERT INTO email_logs (user_id, email_type, recipient, subject, body) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $recipient, $subject, $body_html]);
        $log_id = (int)$pdo->lastInsertId();

        // 2. Spawn non-blocking background worker for instant 0ms response time
        $worker_path = __DIR__ . '/async_mailer.php';
        if (function_exists('exec')) {
            $cmd = "php " . escapeshellarg($worker_path) . " " . $log_id . " > /dev/null 2>&1 &";
            exec($cmd);
            return true;
        } else {
            // Fallback to synchronous delivery if exec is restricted
            return send_smtp_email($recipient, $subject, $body_html);
        }
    } catch (Throwable $e) {
        error_log("Async email error: " . $e->getMessage());
        return send_smtp_email($recipient, $subject, $body_html);
    }
}

function log_email(int $user_id, string $type, string $recipient, string $subject, string $body): void {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO email_logs (user_id, email_type, recipient, subject, body) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $recipient, $subject, $body]);
}

function send_smtp_email(string $to, string $subject, string $body_html): bool {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $from_name = SMTP_FROM_NAME;

    // Fast socket connection with 6s timeout
    $socket = @fsockopen($host, $port, $errno, $errstr, 6);
    if (!$socket) {
        error_log("SMTP Socket connection failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 6);

    $read = function() use ($socket) {
        $res = "";
        while ($str = fgets($socket, 512)) {
            $res .= $str;
            if (substr($str, 3, 1) === " ") break;
        }
        return $res;
    };

    $read();
    fwrite($socket, "EHLO " . gethostname() . "\r\n");
    $read();

    fwrite($socket, "STARTTLS\r\n");
    $read();

    @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);

    fwrite($socket, "EHLO " . gethostname() . "\r\n");
    $read();

    fwrite($socket, "AUTH LOGIN\r\n");
    $read();

    fwrite($socket, base64_encode($username) . "\r\n");
    $read();

    fwrite($socket, base64_encode($password) . "\r\n");
    $auth_res = $read();

    if (strpos($auth_res, "235") === false) {
        fclose($socket);
        error_log("SMTP Auth failed: " . trim($auth_res));
        return false;
    }

    fwrite($socket, "MAIL FROM: <$username>\r\n");
    $read();

    fwrite($socket, "RCPT TO: <$to>\r\n");
    $read();

    fwrite($socket, "DATA\r\n");
    $read();

    // Anti-Spam RFC 5322 Headers & Multipart MIME to ensure delivery to Inbox
    $boundary = "----=_Part_" . md5(uniqid(microtime(), true));
    $plain_text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body_html)));

    $domain = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
    $message_id = "<" . md5(uniqid(microtime(), true)) . "@" . $domain . ">";

    $headers  = "From: $from_name <$username>\r\n";
    $headers .= "Reply-To: $from_name <$username>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date(DATE_RFC2822) . "\r\n";
    $headers .= "Message-ID: $message_id\r\n";
    $headers .= "X-Mailer: Online Examination Portal Mailer v2.0\r\n";
    $headers .= "X-Auto-Response-Suppress: All\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $body_content  = "--$boundary\r\n";
    $body_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body_content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body_content .= $plain_text . "\r\n\r\n";

    $body_content .= "--$boundary\r\n";
    $body_content .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body_content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body_content .= $body_html . "\r\n\r\n";
    $body_content .= "--$boundary--";

    fwrite($socket, $headers . "\r\n" . $body_content . "\r\n.\r\n");
    $data_res = $read();

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return (strpos($data_res, "250") !== false);
}

/**
 * Automatically checks for upcoming tasks and exams due in ~24 hours
 * and sends reminder emails to students.
 */
function check_and_send_deadline_reminders(): void {
    global $pdo;
    static $has_run = false;
    if ($has_run || !is_object($pdo)) return;
    $has_run = true;

    try {
        // 1. Check Tasks due in next 24 to 30 hours
        $task_stmt = $pdo->query("
            SELECT t.*
            FROM tasks t
            WHERE t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 HOUR)
        ");
        $tasks = $task_stmt ? $task_stmt->fetchAll() : [];

        // 2. Check Exams scheduled in next 24 to 30 hours
        $exam_stmt = $pdo->query("
            SELECT * FROM exams
            WHERE is_published = 1
              AND scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 HOUR)
        ");
        $exams = $exam_stmt ? $exam_stmt->fetchAll() : [];

        if (!$tasks && !$exams) return;

        // Fetch students
        $students = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student'")->fetchAll();
        if (!$students) return;

        foreach ($tasks as $task) {
            $subject = "⏰ Reminder: Task \"" . $task['title'] . "\" Deadline is Tomorrow!";
            foreach ($students as $student) {
                $check = $pdo->prepare("SELECT id FROM email_logs WHERE user_id = ? AND email_type = 'deadline_reminder' AND subject = ?");
                $check->execute([$student['id'], $subject]);
                if (!$check->fetch()) {
                    $body = "
                    <div style='font-family: Poppins, Arial, sans-serif; padding: 20px; color: #1e293b;'>
                        <h2 style='color: #ff6b00;'>⏰ Upcoming Task Deadline Reminder</h2>
                        <p>Hello <strong>" . h($student['name']) . "</strong>,</p>
                        <p>This is a friendly reminder that the task <strong>\"" . h($task['title']) . "\"</strong> is due within 24 hours.</p>
                        <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #ff6b00; border-radius: 6px; margin: 15px 0;'>
                            <p style='margin: 0;'><strong>Task Title:</strong> " . h($task['title']) . "</p>
                            <p style='margin: 6px 0 0 0;'><strong>Deadline:</strong> " . date('F j, Y, g:i a', strtotime($task['deadline'])) . "</p>
                        </div>
                        <p><a href='" . BASE_URL . "/student/tasks.php' style='display: inline-block; padding: 10px 20px; background: #ff6b00; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;'>View & Submit Task</a></p>
                    </div>";
                    if (send_smtp_email($student['email'], $subject, $body)) {
                        log_email($student['id'], 'deadline_reminder', $student['email'], $subject, $body);
                    }
                }
            }
        }

        foreach ($exams as $exam) {
            $subject = "⏰ Reminder: Exam \"" . $exam['title'] . "\" is Scheduled Tomorrow!";
            foreach ($students as $student) {
                $check = $pdo->prepare("SELECT id FROM email_logs WHERE user_id = ? AND email_type = 'deadline_reminder' AND subject = ?");
                $check->execute([$student['id'], $subject]);
                if (!$check->fetch()) {
                    $body = "
                    <div style='font-family: Poppins, Arial, sans-serif; padding: 20px; color: #1e293b;'>
                        <h2 style='color: #ff6b00;'>⏰ Upcoming Exam Reminder</h2>
                        <p>Hello <strong>" . h($student['name']) . "</strong>,</p>
                        <p>This is a reminder that your exam <strong>\"" . h($exam['title']) . "\"</strong> is scheduled within 24 hours.</p>
                        <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #ff6b00; border-radius: 6px; margin: 15px 0;'>
                            <p style='margin: 0;'><strong>Exam Title:</strong> " . h($exam['title']) . "</p>
                            <p style='margin: 6px 0 0 0;'><strong>Scheduled Date:</strong> " . date('F j, Y, g:i a', strtotime($exam['scheduled_at'])) . "</p>
                        </div>
                        <p><a href='" . BASE_URL . "/student/exams.php' style='display: inline-block; padding: 10px 20px; background: #ff6b00; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;'>Go to Exams Portal</a></p>
                    </div>";
                    if (send_smtp_email($student['email'], $subject, $body)) {
                        log_email($student['id'], 'deadline_reminder', $student['email'], $subject, $body);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log("Deadline reminder exception: " . $e->getMessage());
    }
}
?>
