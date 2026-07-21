<?php
// student/exams.php — View & start available exams
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$exams   = get_published_exams();
$results = get_student_results($user_id);

// Map taken exams
$taken_map = [];
foreach ($results as $r) { $taken_map[$r['exam_id']] = $r; }

$page_title = 'Available Exams';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>📝 Available Exams</h2>
        <p>Browse and start your scheduled examinations</p>
    </div>
</div>

<?php if ($exams): ?>
<div class="exam-grid">
    <?php foreach ($exams as $exam):
        $now     = time();
        $start   = strtotime($exam['start_time']);
        $end     = strtotime($exam['end_time']);
        $taken   = $taken_map[$exam['id']] ?? null;
        $is_live = ($now >= $start && $now < $end);
        $is_past = ($now >= $end);
        $is_soon = (!$is_live && !$is_past);
    ?>
    <div class="exam-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div class="exam-card-title"><?= h($exam['title']) ?></div>
            <?= exam_status_badge($exam) ?>
        </div>
        <div class="exam-card-desc">
            <?= h(mb_strimwidth($exam['description'] ?? 'No description provided.', 0, 120, '…')) ?>
        </div>
        <div class="exam-card-meta">
            <span class="exam-meta-item">⏱ <?= $exam['duration'] ?> min</span>
            <span class="exam-meta-item">❓ <?= $exam['question_count'] ?> Questions</span>
            <span class="exam-meta-item">👤 <?= h($exam['creator_name']) ?></span>
        </div>
        <div style="margin-bottom:16px;font-size:0.8rem;color:var(--text-muted);">
            <div>📅 Start: <?= format_datetime($exam['start_time']) ?></div>
            <div>🏁 End:   <?= format_datetime($exam['end_time']) ?></div>
        </div>

        <?php if ($taken): ?>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge <?= $taken['passed'] ? 'badge-passed' : 'badge-failed' ?>">
                    <?= $taken['passed'] ? '✅ Passed' : '❌ Failed' ?> — <?= $taken['percentage'] ?>%
                </span>
                <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline btn-sm">View Result</a>
            </div>
        <?php elseif ($is_live): ?>
            <a href="take_exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-success" style="width:100%;justify-content:center;">
                🚀 Start Exam Now
            </a>
        <?php elseif ($is_soon): ?>
            <button class="btn btn-outline" disabled style="width:100%;justify-content:center;cursor:not-allowed;">
                ⏳ Starts <?= format_datetime($exam['start_time']) ?>
            </button>
        <?php else: ?>
            <button class="btn btn-outline" disabled style="width:100%;justify-content:center;cursor:not-allowed;">
                🔒 Exam Ended
            </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <span class="empty-icon">📭</span>
            <h3>No exams available</h3>
            <p>No published exams at the moment. Please check back later.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
