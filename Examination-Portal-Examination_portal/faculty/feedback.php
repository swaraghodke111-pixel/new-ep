<?php
// faculty/feedback.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id    = (int)$_SESSION['user_id'];
$student_id = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
global $pdo;

// My exams
$my_exams = get_exams_by_creator($user_id);

// Students who attempted
$students_stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN results r ON r.user_id=u.id
    JOIN exams e ON e.id=r.exam_id
    WHERE u.role='student' AND e.created_by=?
    ORDER BY u.name ASC
");
$students_stmt->execute([$user_id]);
$students = $students_stmt->fetchAll();

// Submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student_id) {
    $exam_id_fb = (int)$_POST['exam_id_fb'];
    $message    = trim($_POST['message'] ?? '');
    if ($message && $exam_id_fb) {
        $pdo->prepare("INSERT INTO feedback (faculty_id,student_id,exam_id,message) VALUES (?,?,?,?)")
            ->execute([$user_id, $student_id, $exam_id_fb, $message]);
        send_notification($student_id, 'New feedback from your faculty: ' . mb_strimwidth($message, 0, 80, '…'));
        flash('success', 'Feedback sent to student!');
        redirect($_SERVER['PHP_SELF'] . '?student_id=' . $student_id);
    }
}

// Load past feedbacks
$past_fb = [];
if ($student_id) {
    $stmt = $pdo->prepare("
        SELECT f.*, e.title AS exam_title
        FROM feedback f
        JOIN exams e ON e.id=f.exam_id
        WHERE f.faculty_id=? AND f.student_id=?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id, $student_id]);
    $past_fb = $stmt->fetchAll();
}

$selected_student = $student_id ? get_user_by_id($student_id) : null;
$fs = get_flash('success');

$page_title = 'Provide Feedback';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>💬 Provide Feedback</h2>
    <p>Send personalized feedback to students based on their exam performance</p>
</div>

<div class="grid-2" style="align-items:start;">
    <div>
        <!-- Student Selector -->
        <div class="card mb-24">
            <div class="card-header"><h3>👨‍🎓 Select Student</h3></div>
            <div class="card-body" style="max-height:350px;overflow-y:auto;padding:12px;">
                <?php foreach ($students as $s): ?>
                <a href="?student_id=<?= $s['id'] ?>" class="nav-link <?= $student_id==$s['id']?'active':'' ?>" style="margin-bottom:4px;">
                    <span class="user-avatar" style="width:28px;height:28px;font-size:0.75rem;"><?= strtoupper(substr($s['name'],0,1)) ?></span>
                    <div>
                        <div style="font-size:0.85rem;font-weight:600;"><?= h($s['name']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= h($s['email']) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (!$students): ?>
                    <div class="empty-state" style="padding:20px;"><p>No students have taken your exams yet.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>

        <?php if ($selected_student): ?>
        <!-- Feedback Form -->
        <div class="card mb-24">
            <div class="card-header">
                <h3>✉️ Send Feedback to <?= h($selected_student['name']) ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="student_id" value="<?= $student_id ?>">
                    <div class="form-group">
                        <label class="form-label">Related Exam</label>
                        <select name="exam_id_fb" class="form-control" required>
                            <option value="">— Select exam —</option>
                            <?php foreach ($my_exams as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= h($e['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Feedback Message</label>
                        <textarea name="message" class="form-control" rows="4" required
                                  placeholder="Write your feedback, suggestions or encouragement here..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Feedback →</button>
                </form>
            </div>
        </div>

        <!-- Past Feedbacks -->
        <?php if ($past_fb): ?>
        <div class="card">
            <div class="card-header"><h3>📝 Past Feedbacks</h3></div>
            <div class="card-body" style="padding:0;">
                <?php foreach ($past_fb as $fb): ?>
                <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
                    <div style="font-size:0.78rem;color:var(--purple-3);margin-bottom:6px;font-weight:600;">
                        <?= h($fb['exam_title']) ?> · <?= format_datetime($fb['created_at']) ?>
                    </div>
                    <p style="font-size:0.875rem;line-height:1.6;"><?= nl2br(h($fb['message'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
            <div class="card"><div class="card-body">
                <div class="empty-state">
                    <span class="empty-icon">💬</span>
                    <h3>Select a student</h3>
                    <p>Choose a student from the left panel to provide feedback.</p>
                </div>
            </div></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
