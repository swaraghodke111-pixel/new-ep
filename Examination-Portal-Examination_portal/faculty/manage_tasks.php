<?php
// faculty/manage_tasks.php — Faculty tasks management
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty', 'admin');

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
global $pdo;

$task_id = (int)($_GET['task_id'] ?? 0);
$sub_id  = (int)($_GET['sub_id'] ?? 0);

// Handle Create Task POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline    = $_POST['deadline'] ?? '';
    
    if (empty($title) || empty($deadline)) {
        $error = 'Title and deadline are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, deadline, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $deadline, $user_id])) {
            $success = 'Task created successfully!';
        } else {
            $error = 'Failed to create task.';
        }
    }
}

// Handle Grade Submission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission']) && $sub_id > 0) {
    $grade    = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');
    
    if (empty($grade)) {
        $error = 'Grade is required.';
    } else {
        $stmt = $pdo->prepare("UPDATE task_submissions SET grade = ?, feedback = ?, status = 'graded' WHERE id = ?");
        if ($stmt->execute([$grade, $feedback, $sub_id])) {
            $success = 'Submission graded successfully!';
            
            // Notify student
            $sub_stmt = $pdo->prepare("SELECT user_id, task_id FROM task_submissions WHERE id = ?");
            $sub_stmt->execute([$sub_id]);
            $sub = $sub_stmt->fetch();
            if ($sub) {
                send_notification($sub['user_id'], '🎯 Your task submission has been graded! Grade: ' . $grade);
            }
        } else {
            $error = 'Failed to submit grade.';
        }
    }
}

// Fetch all tasks
$tasks = $pdo->query("SELECT * FROM tasks ORDER BY deadline ASC")->fetchAll();

// Fetch submissions for selected task
$submissions = [];
if ($task_id > 0) {
    $stmt = $pdo->prepare("
        SELECT ts.*, u.name AS student_name, u.email AS student_email, t.title AS task_title
        FROM task_submissions ts
        JOIN users u ON ts.user_id = u.id
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.task_id = ?
        ORDER BY ts.submitted_at DESC
    ");
    $stmt->execute([$task_id]);
    $submissions = $stmt->fetchAll();
}

// Fetch selected submission for grading
$selected_sub = null;
if ($sub_id > 0) {
    $stmt = $pdo->prepare("
        SELECT ts.*, u.name AS student_name, u.email AS student_email, t.title AS task_title, t.description AS task_desc
        FROM task_submissions ts
        JOIN users u ON ts.user_id = u.id
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.id = ?
    ");
    $stmt->execute([$sub_id]);
    $selected_sub = $stmt->fetch();
}

$page_title = 'Manage Tasks';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>📋 Task & Assignment Management</h2>
    <p>Create tasks for students, view their answers, and grade their assignments.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Left Panel: Create Task & Task List -->
    <div>
        <!-- Create Task Form -->
        <div class="card mb-24">
            <div class="card-header">
                <h3>➕ Create New Task</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Task Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Database ER Diagram Design" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Task Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Detail the questions, inputs, and normalization details here..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Submission Deadline</label>
                        <input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    <button type="submit" name="create_task" class="btn btn-primary" style="width:100%;">Create & Assign Task</button>
                </form>
            </div>
        </div>

        <!-- Task List -->
        <div class="card">
            <div class="card-header">
                <h3>📑 Existing Tasks</h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if ($tasks): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Deadline</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td><strong><?= h($t['title']) ?></strong></td>
                                <td style="font-size:0.8rem;"><?= format_datetime($t['deadline']) ?></td>
                                <td>
                                    <a href="?task_id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Submissions</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">📭</span>
                        <h3>No tasks created yet</h3>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Panel: Submissions or Grading Forms -->
    <div>
        <?php if ($selected_sub): ?>
            <!-- Grading Form -->
            <div class="card">
                <div class="card-header">
                    <h3>🏆 Grade Student: <?= h($selected_sub['student_name']) ?></h3>
                </div>
                <div class="card-body">
                    <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); padding:16px; border-radius:10px; margin-bottom:20px;">
                        <h5><?= h($selected_sub['task_title']) ?></h5>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;"><?= nl2br(h($selected_sub['task_desc'])) ?></p>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <strong>Student's Submission Answer:</strong>
                        <div style="background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.08); padding:16px; border-radius:10px; margin-top:8px; font-family:Courier, monospace; font-size:0.9rem; white-space:pre-wrap; max-height:250px; overflow-y:auto;"><?= h($selected_sub['submission_text']) ?></div>
                    </div>

                    <form method="POST" action="?task_id=<?= $selected_sub['task_id'] ?>&sub_id=<?= $selected_sub['id'] ?>">
                        <input type="hidden" name="sub_id" value="<?= $selected_sub['id'] ?>">
                        <div class="form-group">
                            <label class="form-label">Grade</label>
                            <select name="grade" class="form-control" required>
                                <option value="A" <?= $selected_sub['grade']==='A'?'selected':'' ?>>Grade A (Excellent)</option>
                                <option value="B" <?= $selected_sub['grade']==='B'?'selected':'' ?>>Grade B (Very Good)</option>
                                <option value="C" <?= $selected_sub['grade']==='C'?'selected':'' ?>>Grade C (Good)</option>
                                <option value="D" <?= $selected_sub['grade']==='D'?'selected':'' ?>>Grade D (Satisfactory)</option>
                                <option value="F" <?= $selected_sub['grade']==='F'?'selected':'' ?>>Grade F (Fail)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Grading Feedback</label>
                            <textarea name="feedback" class="form-control" rows="4" placeholder="Provide details on strengths, weaknesses, or correction models here..." required><?= h($selected_sub['feedback'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="grade_submission" class="btn btn-primary">Submit Grade & Feedback</button>
                        <a href="?task_id=<?= $selected_sub['task_id'] ?>" class="btn btn-outline" style="text-align:center; display:block; margin-top:8px;">Cancel</a>
                    </form>
                </div>
            </div>
        <?php elseif ($task_id > 0): ?>
            <!-- Submissions list for selected task -->
            <div class="card">
                <div class="card-header">
                    <h3>👥 Submissions for Task</h3>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if ($submissions): ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Submitted At</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $s): ?>
                                <tr>
                                    <td>
                                        <div><strong><?= h($s['student_name']) ?></strong></div>
                                        <span style="font-size:0.75rem; color:var(--text-muted);"><?= h($s['student_email']) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem;"><?= format_datetime($s['submitted_at']) ?></td>
                                    <td>
                                        <?php
                                        if ($s['status'] === 'graded') {
                                            echo '<span class="badge badge-passed">Graded (' . h($s['grade']) . ')</span>';
                                        } else {
                                            echo '<span class="badge badge-failed">Pending Grade</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="?task_id=<?= $task_id ?>&sub_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">
                                            <?= $s['status'] === 'graded' ? 'Edit Grade' : 'Grade' ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="empty-icon">📭</span>
                            <h3>No submissions yet</h3>
                            <p>Once students submit work, it will appear here for grading.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Default placeholder -->
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <span class="empty-icon">👈</span>
                        <h3>Manage Submissions</h3>
                        <p>Click on the "Submissions" button next to any assignment to view student deliverables and perform grading evaluation.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
