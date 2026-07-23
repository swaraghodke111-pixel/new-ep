<?php
// faculty/schedule_exam.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id  = (int)$_SESSION['user_id'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['user_role'] ?? '') === 'admin');
$my_exams = $is_admin ? get_all_exams() : get_exams_by_creator($user_id);
global $pdo;

$exam_id  = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
$exam     = $exam_id ? get_exam_by_id($exam_id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exam_id) {
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $duration   = (int)($_POST['duration'] ?? 60);

    if (empty($start_date) || empty($end_date)) {
        flash('error', 'Start and end dates are required.');
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        flash('error', 'End date must be on or after start date.');
    } else {
        $start_time = $start_date . ' 00:00:00';
        $end_time   = $end_date . ' 23:59:59';
        $pdo->prepare("UPDATE exams SET start_time=?, end_time=?, duration=?, status='scheduled' WHERE id=?")
            ->execute([$start_time, $end_time, $duration, $exam_id]);
        flash('success', 'Exam scheduled successfully!');
        redirect($_SERVER['PHP_SELF'] . '?exam_id=' . $exam_id);
    }
}

$page_title = 'Schedule Exam';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* Chakravyuh Animation Styles */
.chakravyuh-btn-container {
    position: relative;
    display: inline-block;
    border-radius: 50px;
    padding: 3px;
}
.chakra-ring {
    position: absolute;
    inset: 0;
    border-radius: 50px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
/* Outer ring - rotates clockwise */
.chakra-ring.ring-1 {
    border: 2px dashed #A67C52;
    margin: -3px;
    animation: spinClockwise 8s linear infinite;
}
/* Inner ring - rotates counter-clockwise */
.chakra-ring.ring-2 {
    border: 1.5px dotted #8C6239;
    margin: -1px;
    animation: spinCounterClockwise 5s linear infinite;
}
.chakravyuh-btn-container:hover .chakra-ring {
    opacity: 1;
}
.chakravyuh-trigger {
    position: relative;
    border-radius: 50px;
    padding: 10px 24px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    height: 42px;
    border: none;
    cursor: pointer;
    z-index: 2;
    background: #A67C52;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s;
}
.chakravyuh-trigger:hover {
    background: #7A5C48;
    box-shadow: 0 0 15px rgba(166, 124, 82, 0.4);
}
/* Spiral scale-in dropdown */
@keyframes chakravyuhIn {
    0% {
        transform: scale(0) rotate(-360deg);
        opacity: 0;
    }
    100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}
.chakravyuh-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 50px;
    background: #0f0f20;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    z-index: 100;
    min-width: 160px;
    overflow: hidden;
    transform-origin: top right;
}
.show-menu {
    display: block !important;
    animation: chakravyuhIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes spinClockwise {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes spinCounterClockwise {
    from { transform: rotate(360deg); }
    to { transform: rotate(0deg); }
}
</style>

<div class="page-header flex-between">
    <div>
        <h2>📅 Schedule Exam</h2>
        <p>Set the date, time and duration for your exams</p>
    </div>
    <div class="chakravyuh-btn-container">
        <div class="chakra-ring ring-1"></div>
        <div class="chakra-ring ring-2"></div>
        <button onclick="toggleAddExamMenu(event)" class="btn btn-primary chakravyuh-trigger">
            <span>+ Add Exam</span>
        </button>
        <div id="add-exam-menu" class="chakravyuh-menu">
            <a href="create_exam.php" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; color: #fff; text-decoration: none; font-size: 0.9rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
                <span>📝</span> Quiz Exam
            </a>
            <a href="manage_coding_problems.php" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; color: #fff; text-decoration: none; font-size: 0.9rem; border-top: 1px solid var(--border); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
                <span>💻</span> Coding Exam
            </a>
        </div>
    </div>
</div>
<script>
function toggleAddExamMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('add-exam-menu');
    menu.classList.toggle('show-menu');
}
document.addEventListener('click', function() {
    const menu = document.getElementById('add-exam-menu');
    if (menu) menu.classList.remove('show-menu');
});
</script>

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
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control"
                               value="<?= date('Y-m-d', strtotime($exam['start_time'] ?? 'now')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" class="form-control"
                               value="<?= date('Y-m-d', strtotime($exam['end_time'] ?? '+7 days')) ?>" required>
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
