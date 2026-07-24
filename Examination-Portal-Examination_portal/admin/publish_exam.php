<?php
// admin/publish_exam.php — Admin tool to toggle publishing of exams
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('admin', 'faculty');

global $pdo;

// Handle publish/unpublish POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id'], $_POST['action'])) {
    $exam_id = (int)$_POST['exam_id'];
    $action  = $_POST['action'];
    $is_published = ($action === 'publish') ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE exams SET is_published = ? WHERE id = ?");
    $stmt->execute([$is_published, $exam_id]);

    // Send notifications to students if published
    if ($is_published) {
        $exam = get_exam_by_id($exam_id);
        $exam_title = $exam['title'] ?? 'New Exam';
        
        // Fetch all students to notify
        $std_stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student'");
        $students = $std_stmt->fetchAll();
        
        foreach ($students as $std) {
            send_notification((int)$std['id'], "🚀 A new exam has been published: " . $exam_title . ". Check Available Exams!");

            $subject = "🚀 New Exam Published: " . $exam_title;
            $body = "
            <div style='font-family: Poppins, Arial, sans-serif; padding: 20px; color: #1e293b;'>
                <h2 style='color: #ff6b00;'>🚀 New Exam Published</h2>
                <p>Hello <strong>" . h($std['name']) . "</strong>,</p>
                <p>A new examination <strong>\"" . h($exam_title) . "\"</strong> is now published and available on the Online Examination Portal.</p>
                <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #ff6b00; border-radius: 6px; margin: 15px 0;'>
                    <p style='margin: 0;'><strong>Exam Title:</strong> " . h($exam_title) . "</p>
                    <p style='margin: 6px 0 0 0;'><strong>Duration:</strong> " . ($exam['duration_minutes'] ?? 'N/A') . " minutes</p>
                </div>
                <p><a href='" . BASE_URL . "/student/exams.php' style='display: inline-block; padding: 10px 20px; background: #ff6b00; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;'>Take Exam Now</a></p>
            </div>";
            send_async_email($std['id'], 'exam_published', $std['email'], $subject, $body);
        }
        flash('success', 'Exam published and student email notifications dispatched!');
    } else {
        flash('success', 'Exam unpublished successfully.');
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Fetch all exams
$stmt = $pdo->query("
    SELECT e.*, u.name AS creator_name, COUNT(q.id) AS question_count
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN questions q ON q.exam_id = e.id
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$exams = $stmt->fetchAll();

$page_title = 'Publish Exams';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>🚀 Publish / Unpublish Exams</h2>
    <p>Manage visibility of scheduled examinations. Only published exams are accessible by students.</p>
</div>

<?php $fs = get_flash('success'); if ($fs): ?>
    <div class="alert alert-success">✅ <?= h($fs) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>📋 All Examinations</h3>
        <span style="color:var(--text-muted);font-size:0.8rem;"><?= count($exams) ?> exams in database</span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if ($exams): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Exam Details</th>
                        <th>Created By</th>
                        <th>Questions</th>
                        <th>Window</th>
                        <th>Status</th>
                        <th>Visibility</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $e): ?>
                    <tr>
                        <td>
                            <div><strong><?= h($e['title']) ?></strong></div>
                            <span style="font-size:0.8rem;color:var(--text-muted);"><?= h(mb_strimwidth($e['description'], 0, 80, '…')) ?></span>
                        </td>
                        <td><?= h($e['creator_name'] ?? 'System') ?></td>
                        <td><?= $e['question_count'] ?> Qs (<?= $e['total_marks'] ?> marks)</td>
                        <td style="font-size:0.8rem;">
                            <div>🏁 <?= format_datetime($e['start_time']) ?></div>
                            <div>🛑 <?= format_datetime($e['end_time']) ?></div>
                            <div style="color:var(--text-muted);"><?= $e['duration'] ?> minutes</div>
                        </td>
                        <td><?= exam_status_badge($e) ?></td>
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="exam_id" value="<?= $e['id'] ?>">
                                <?php if ($e['is_published']): ?>
                                    <input type="hidden" name="action" value="unpublish">
                                    <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none;border-radius:6px;padding:6px 12px;font-weight:600;cursor:pointer;">
                                        Unpublish 🛑
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="publish">
                                    <button type="submit" class="btn btn-sm btn-primary" style="padding:6px 12px;cursor:pointer;">
                                        Publish 🚀
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">📭</span>
                <h3>No exams created yet</h3>
                <p>Exams must be created by Faculty or Admin before publishing.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
