<?php
// admin/schedule_exam.php — Super Admin Exam Scheduling & Master Control
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty', 'admin');

global $pdo;
$user_id  = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin' || ($_SESSION['user_role'] ?? '') === 'admin');

// Super Admin sees ALL exams system-wide (created by any Faculty or Admin)
$my_exams = $is_admin ? get_all_exams() : get_exams_by_creator($user_id);

$exam_id  = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
$exam     = $exam_id ? get_exam_by_id($exam_id) : null;

// Handle Delete Exam action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_exam') {
    $delete_id = (int)($_POST['delete_exam_id'] ?? 0);
    if ($delete_id) {
        $pdo->prepare("DELETE FROM questions WHERE exam_id=?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM answers WHERE exam_id=?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM results WHERE exam_id=?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM exam_attempts WHERE exam_id=?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM exams WHERE id=?")->execute([$delete_id]);
        flash('success', 'Exam deleted successfully.');
        redirect($_SERVER['PHP_SELF']);
    }
}

// Handle Schedule Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exam_id && (!isset($_POST['action']) || $_POST['action'] === 'schedule')) {
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
        flash('success', 'Exam schedule updated successfully!');
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
.chakra-ring.ring-1 {
    border: 2px dashed #A67C52;
    margin: -3px;
    animation: spinClockwise 8s linear infinite;
}
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
@keyframes chakravyuhIn {
    0% { transform: scale(0) rotate(-360deg); opacity: 0; }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
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
        <h2>📅 Master Exam Schedule</h2>
        <p>View, configure, and schedule examinations created by Faculty and Super Admin</p>
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

<?php $fs=get_flash('success'); $fe=get_flash('error');
if($fs):?><div class="alert alert-success">✅ <?=h($fs)?></div><?php endif;
if($fe):?><div class="alert alert-error">❌ <?=h($fe)?></div><?php endif;
?>

<!-- Exam Selector -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:1;">
                <label class="form-label">Select Exam to Configure Schedule</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                    <option value="">— Choose an exam —</option>
                    <?php foreach ($my_exams as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $exam_id===$e['id']?'selected':'' ?>>
                            <?= h($e['title']) ?> — Created by: <?= h($e['creator_name'] ?? 'Faculty') ?> (<?= $e['question_count'] ?> Qs)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($exam): ?>
<div style="max-width:650px;" class="mb-24">
    <div class="card">
        <div class="card-header">
            <div>
                <h3>⏰ Schedule: <?= h($exam['title']) ?></h3>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">👨‍🏫 Created by: <strong><?= h($exam['creator_name'] ?? 'Faculty') ?></strong></div>
            </div>
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
                <input type="hidden" name="action" value="schedule">
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
                    <a href="add_questions.php?exam_id=<?= $exam_id ?>" class="btn btn-outline">← Edit Questions</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- System-Wide Scheduled & Created Exams Overview Table -->
<div class="card mb-24">
    <div class="card-header flex-between">
        <div>
            <h3>📋 All System-Wide Scheduled & Created Exams</h3>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">Showing all exams created by Faculty members and Super Admin</p>
        </div>
        <span class="badge" style="background:rgba(166,124,82,0.15); color:#A67C52; font-weight:700;">
            Total: <?= count($my_exams) ?> Exams
        </span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if ($my_exams): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Exam Details</th>
                        <th>Created By</th>
                        <th>Questions & Marks</th>
                        <th>Schedule Window</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_exams as $idx => $e): ?>
                    <tr <?= ($exam_id === $e['id']) ? 'style="background:rgba(166,124,82,0.08);"' : '' ?>>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <div><strong><?= h($e['title']) ?></strong></div>
                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= h(mb_strimwidth($e['description'] ?? '', 0, 70, '…')) ?></span>
                        </td>
                        <td>
                            <span class="badge" style="background:rgba(99,102,241,0.12); color:var(--purple-3); font-weight:600;">
                                👨‍🏫 <?= h($e['creator_name'] ?? 'Faculty') ?>
                            </span>
                        </td>
                        <td style="font-size:0.85rem;">
                            <div>❓ <strong><?= $e['question_count'] ?></strong> Qs</div>
                            <div style="color:var(--text-muted);">📊 <?= $e['total_marks'] ?> marks</div>
                        </td>
                        <td style="font-size:0.8rem;">
                            <div>🏁 Start: <strong><?= format_datetime($e['start_time']) ?></strong></div>
                            <div>🛑 End: <strong><?= format_datetime($e['end_time']) ?></strong></div>
                            <div style="color:var(--text-muted);">⏱ Duration: <?= $e['duration'] ?> mins</div>
                        </td>
                        <td>
                            <?= exam_status_badge($e) ?>
                            <?php if ($e['is_published']): ?>
                                <div style="margin-top:4px;"><span class="badge badge-passed" style="font-size:0.7rem;">🚀 Published</span></div>
                            <?php else: ?>
                                <div style="margin-top:4px;"><span class="badge" style="font-size:0.7rem; background:rgba(255,255,255,0.06); color:var(--text-muted);">Draft</span></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <a href="schedule_exam.php?exam_id=<?= $e['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.8rem; padding:4px 8px;">
                                    📅 Schedule
                                </a>
                                <form method="POST" action="schedule_exam.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete exam \'<?= addslashes($e['title']) ?>\'?')">
                                    <input type="hidden" name="action" value="delete_exam">
                                    <input type="hidden" name="delete_exam_id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="font-size:0.8rem; padding:4px 8px;">
                                        🗑 Delete
                                    </button>
                                </form>
                            </div>
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
                <p>When faculty or admin create exams, they will appear here for scheduling.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
