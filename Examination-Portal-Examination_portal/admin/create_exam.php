<?php
// faculty/create_exam.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id = (int)$_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $duration    = (int)($_POST['duration'] ?? 60);
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $pass_marks  = (int)($_POST['pass_marks'] ?? 0);
    $randomize   = isset($_POST['randomize']) ? 1 : 0;

    if (empty($title) || empty($start_time) || empty($end_time)) {
        $error = 'Title, start time and end time are required.';
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = 'End time must be after start time.';
    } elseif ($duration < 1) {
        $error = 'Duration must be at least 1 minute.';
    } else {
        $exam_id = create_exam([
            'title'       => $title,
            'description' => $desc,
            'duration'    => $duration,
            'start_time'  => $start_time,
            'end_time'    => $end_time,
            'pass_marks'  => $pass_marks,
            'created_by'  => $user_id,
            'randomize'   => $randomize,
        ]);
        flash('success', 'Exam "' . $title . '" created! Now add questions.');
        redirect(BASE_URL . '/' . get_role() . '/add_questions.php?exam_id=' . $exam_id);
    }
}

$page_title = 'Create Exam';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>➕ Create New Exam</h2>
    <p>Fill in the details to create a new examination</p>
</div>

<div style="max-width:700px;">
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>📋 Exam Details</h3></div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="title">Exam Title *</label>
                    <input type="text" id="title" name="title" class="form-control"
                           placeholder="e.g. Mid-Semester Mathematics Exam"
                           value="<?= h($_POST['title'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Brief description or instructions for students..."><?= h($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="duration">Duration (minutes) *</label>
                        <input type="number" id="duration" name="duration" class="form-control"
                               min="1" max="480" value="<?= h($_POST['duration'] ?? 60) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pass_marks">Pass Marks</label>
                        <input type="number" id="pass_marks" name="pass_marks" class="form-control"
                               min="0" placeholder="0 = no minimum" value="<?= h($_POST['pass_marks'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="start_time">Start Date & Time *</label>
                        <input type="datetime-local" id="start_time" name="start_time" class="form-control"
                               value="<?= h($_POST['start_time'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="end_time">End Date & Time *</label>
                        <input type="datetime-local" id="end_time" name="end_time" class="form-control"
                               value="<?= h($_POST['end_time'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="randomize" value="1" <?= (!isset($_POST['randomize']) || $_POST['randomize']) ? 'checked' : '' ?>>
                        🔀 Randomize question order for each student
                    </label>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary">Create Exam & Add Questions →</button>
                    <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
