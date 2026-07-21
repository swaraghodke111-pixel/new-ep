<?php
// faculty/schedule_exam.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id  = (int)$_SESSION['user_id'];
$my_exams = get_exams_by_creator($user_id);
global $pdo;

$exam_id  = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
$exam     = $exam_id ? get_exam_by_id($exam_id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exam_id) {
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time'] ?? '';
    $duration   = (int)($_POST['duration'] ?? 60);

    if (empty($start_time) || empty($end_time)) {
        flash('error', 'Start and end times are required.');
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        flash('error', 'End time must be after start time.');
    } else {
        $pdo->prepare("UPDATE exams SET start_time=?, end_time=?, duration=?, status='scheduled' WHERE id=?")
            ->execute([$start_time, $end_time, $duration, $exam_id]);
        flash('success', 'Exam scheduled successfully!');
        redirect($_SERVER['PHP_SELF'] . '?exam_id=' . $exam_id);
    }
}

$page_title = 'Schedule Exam';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>📅 Schedule Exam</h2>
    <p>Set the date, time and duration for your exams</p>
</div>

<!-- Exam Selector -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:1;">
                <label class="form-label">Select Exam</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                    <option value="">— Choose an exam —</option>
                    <?php foreach ($my_exams as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $exam_id===$e['id']?'selected':'' ?>>
                            <?= h($e['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php $fs=get_flash('success'); $fe=get_flash('error');
if($fs):?><div class="alert alert-success">✅ <?=h($fs)?></div><?php endif;
if($fe):?><div class="alert alert-error">❌ <?=h($fe)?></div><?php endif;
?>

<?php if ($exam): ?>
<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <h3>⏰ Schedule: <?= h($exam['title']) ?></h3>
            <?= exam_status_badge($exam) ?>
        </div>
        <div class="card-body">
            <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:10px;padding:16px;margin-bottom:20px;font-size:0.85rem;">
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <span>❓ Questions: <strong><?= $exam['question_count'] ?></strong></span>
                    <span>📊 Total Marks: <strong><?= $exam['total_marks'] ?></strong></span>
                    <span>🔀 Randomize: <strong><?= $exam['randomize'] ? 'Yes' : 'No' ?></strong></span>
                </div>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date & Time *</label>
                        <input type="datetime-local" name="start_time" class="form-control"
                               value="<?= date('Y-m-d\TH:i', strtotime($exam['start_time'])) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date & Time *</label>
                        <input type="datetime-local" name="end_time" class="form-control"
                               value="<?= date('Y-m-d\TH:i', strtotime($exam['end_time'])) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration" class="form-control" value="<?= $exam['duration'] ?>" min="1" max="480">
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-warning">📅 Save Schedule</button>
                    <a href="add_questions.php?exam_id=<?= $exam_id ?>" class="btn btn-outline">← Back to Questions</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
