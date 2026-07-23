<?php
// student/tasks.php — Student assigned tasks management
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
global $pdo;

$task_id = (int)($_GET['task_id'] ?? 0);

// Handle Submission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task']) && $task_id > 0) {
    $submission_text = trim($_POST['submission_text'] ?? '');
    
    // Check if task exists and isn't past deadline
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $error = 'Task not found.';
    } elseif (strtotime($task['deadline']) < time()) {
        $error = 'The deadline for this task has passed.';
    } elseif (empty($submission_text)) {
        $error = 'Please write something for your submission.';
    } else {
        // Insert or update submission
        $stmt = $pdo->prepare("
            INSERT INTO task_submissions (task_id, user_id, submission_text, status, submitted_at) 
            VALUES (?, ?, ?, 'pending', CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), status = 'pending', submitted_at = CURRENT_TIMESTAMP
        ");
        if ($stmt->execute([$task_id, $user_id, $submission_text])) {
            $success = 'Task submitted successfully!';
        } else {
            $error = 'Failed to record your submission.';
        }
    }
}

// Fetch all tasks with student's submission status and assigning faculty name
$stmt = $pdo->prepare("
    SELECT t.*, u.name AS faculty_name, ts.status AS sub_status, ts.grade, ts.feedback, ts.submitted_at
    FROM tasks t
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN task_submissions ts ON ts.task_id = t.id AND ts.user_id = ?
    ORDER BY t.deadline ASC
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

// If viewing a specific task details
$selected_task = null;
if ($task_id > 0) {
    foreach ($tasks as $t) {
        if ($t['id'] === $task_id) {
            $selected_task = $t;
            break;
        }
    }
}

$page_title = 'Assigned Tasks';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>📋 Assigned Tasks</h2>
    <p>View assignments, submit your deliverables, and review grading and feedback.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Tasks List -->
    <div class="card">
        <div class="card-header">
            <h3>📑 Task List</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($tasks): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Deadline</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $t): ?>
                        <tr style="cursor:pointer;" onclick="location.href='?task_id=<?= $t['id'] ?>'">
                            <td>
                                <div><strong><?= h($t['title']) ?></strong></div>
                                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                    👨‍🏫 Assigned by: <strong><?= h($t['faculty_name'] ?? 'Faculty') ?></strong>
                                </div>
                            </td>
                            <td style="font-size:0.8rem; <?= strtotime($t['deadline']) < time() && $t['sub_status']!=='graded' ? 'color:#ef4444;' : '' ?>">
                                <?= format_datetime($t['deadline']) ?>
                            </td>
                            <td>
                                <?php
                                if ($t['sub_status'] === 'graded') {
                                    echo '<span class="badge badge-passed">Grade: ' . h($t['grade']) . '</span>';
                                } elseif ($t['sub_status'] === 'pending') {
                                    echo '<span class="badge badge-draft">Submitted</span>';
                                } else {
                                    echo '<span class="badge badge-failed">Pending</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📭</span>
                    <h3>No tasks assigned</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Task Detail View & Submission Form -->
    <div class="card">
        <?php if ($selected_task): ?>
            <div class="card-header">
                <h3>🔍 Task Details</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 20px;">
                    <h4><?= h($selected_task['title']) ?></h4>
                    <div style="font-size:0.85rem; color:var(--purple-3); font-weight:600; margin-top:4px;">
                        👨‍🏫 Assigned by: <?= h($selected_task['faculty_name'] ?? 'Faculty') ?>
                    </div>
                    <p style="font-size:0.9rem; color:var(--text-muted); margin-top:8px;"><?= nl2br(h($selected_task['description'])) ?></p>
                </div>
                <div style="font-size:0.85rem; border-top:1px solid rgba(255,255,255,0.08); padding-top:16px; display:flex; justify-content:space-between;">
                    <div>📅 Deadline: <strong><?= format_datetime($selected_task['deadline']) ?></strong></div>
                    <div>
                        <?php if ($selected_task['submitted_at']): ?>
                            Status: <strong>Submitted on <?= format_datetime($selected_task['submitted_at']) ?></strong>
                        <?php else: ?>
                            Status: <strong style="color:#ef4444;">Not Submitted</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selected_task['sub_status'] === 'graded'): ?>
                    <div style="margin-top: 24px; padding: 16px; background: rgba(16,185,129,0.1); border: 1px solid #10b981; border-radius: 10px;">
                        <h4 style="color:#10b981; display:flex; align-items:center; gap:8px;">🏆 Grading Received</h4>
                        <div style="font-size:1.1rem; font-weight:700; margin-top:8px;">Grade: <?= h($selected_task['grade']) ?></div>
                        <div style="font-size:0.9rem; color:var(--text-muted); margin-top:8px;">
                            <strong>Feedback:</strong><br><?= nl2br(h($selected_task['feedback'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (strtotime($selected_task['deadline']) >= time()): ?>
                    <form method="POST" action="" style="margin-top:24px; border-top:1px solid rgba(255,255,255,0.08); padding-top:16px;">
                        <input type="hidden" name="task_id" value="<?= $selected_task['id'] ?>">
                        <div class="form-group">
                            <label class="form-label">Submit Your Answer</label>
                            <textarea name="submission_text" class="form-control" rows="6" placeholder="Paste your essay, code segment, or normalization model answers here..." required><?= h($selected_task['submission_text'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="submit_task" class="btn btn-primary">Submit Assignment</button>
                    </form>
                <?php else: ?>
                    <?php if ($selected_task['sub_status'] !== 'graded'): ?>
                        <div class="alert alert-error" style="margin-top: 24px;">🚫 Submissions are closed for this task (Deadline expired).</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-body">
                <div class="empty-state">
                    <span class="empty-icon">👈</span>
                    <h3>Select a task</h3>
                    <p>Click on any assignment in the left list to view details and submit responses.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
