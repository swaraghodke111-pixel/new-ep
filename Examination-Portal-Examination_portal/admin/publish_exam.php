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
        $std_stmt = $pdo->query("SELECT id FROM users WHERE role = 'student'");
        $students = $std_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($students as $student_id) {
            send_notification($student_id, "🚀 A new exam has been published: " . $exam_title . ". Check Available Exams!");
        }
        flash('success', 'Exam published and students notified!');
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
