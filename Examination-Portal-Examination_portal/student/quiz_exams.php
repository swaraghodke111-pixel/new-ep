<?php
// student/quiz_exams.php — View & start Quiz exams created by Super Admin
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
global $pdo;

// Fetch published quiz exams created by Super Admin (role = admin)
$stmt = $pdo->query("
    SELECT e.*, u.name AS creator_name, u.role AS creator_role,
           (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) AS question_count
    FROM exams e
    JOIN users u ON u.id = e.created_by
    WHERE e.is_published = 1 AND u.role = 'admin'
    ORDER BY e.start_time ASC
");
$exams = $stmt->fetchAll();

$results = get_student_results($user_id);

// Map taken exams
$taken_map = [];
foreach ($results as $r) { $taken_map[$r['exam_id']] = $r; }

$page_title = 'Quiz Exams';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>📝 Quiz Exams</h2>
        <p>Browse and start quiz examinations created by the Super Admin</p>
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
        $starts_in = max(0, $start - $now);
    ?>
    <div class="exam-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div class="exam-card-title"><?= h($exam['title']) ?></div>
            <span id="badge-quiz-<?= $exam['id'] ?>"><?= exam_status_badge($exam) ?></span>
        </div>
        <div class="exam-card-desc">
            <?= h(mb_strimwidth($exam['description'] ?? 'No description provided.', 0, 120, '…')) ?>
        </div>
        <div class="exam-card-meta">
            <span class="exam-meta-item">⏱ <?= $exam['duration'] ?> min</span>
            <span class="exam-meta-item">❓ <?= $exam['question_count'] ?> Questions</span>
            <span class="exam-meta-item">👑 <?= h($exam['creator_name']) ?> (Super Admin)</span>
        </div>
        <div style="margin-bottom:16px;font-size:0.8rem;color:var(--text-muted);">
            <div>📅 Start: <?= format_datetime($exam['start_time']) ?></div>
            <div>🏁 End:   <?= format_datetime($exam['end_time']) ?></div>
        </div>

        <div id="btn-quiz-container-<?= $exam['id'] ?>">
        <?php if ($taken): ?>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge <?= $taken['passed'] ? 'badge-passed' : 'badge-failed' ?>">
                    <?= $taken['passed'] ? '✅ Passed' : '❌ Failed' ?> — <?= $taken['percentage'] ?>%
                </span>
                <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline btn-sm">View Result</a>
            </div>
        <?php elseif ($is_live): ?>
            <a href="take_exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-success" style="width:100%;justify-content:center;">
                🚀 Start Quiz Exam
            </a>
        <?php elseif ($is_soon): ?>
            <button id="soon-quiz-btn-<?= $exam['id'] ?>" class="btn btn-warning live-quiz-countdown-btn"
                    data-seconds="<?= $starts_in ?>"
                    data-exam-id="<?= $exam['id'] ?>"
                    style="width:100%;justify-content:center;font-weight:600;">
                ⏳ Starts in: <?= sprintf('%02d:%02d:%02d', floor($starts_in/3600), floor(($starts_in%3600)/60), $starts_in%60) ?>
            </button>
        <?php else: ?>
            <button class="btn btn-outline" disabled style="width:100%;justify-content:center;cursor:not-allowed;">
                🔒 Exam Ended
            </button>
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <span class="empty-icon">📭</span>
            <h3>No Super Admin Quiz Exams available</h3>
            <p>No quiz exams published by the Super Admin at the moment.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const countdownBtns = document.querySelectorAll('.live-quiz-countdown-btn');
    countdownBtns.forEach(function(btn) {
        let secondsLeft = parseInt(btn.getAttribute('data-seconds')) || 0;
        const examId = btn.getAttribute('data-exam-id');

        const timer = setInterval(function() {
            if (secondsLeft <= 0) {
                clearInterval(timer);
                // Auto-unlock Start Quiz Exam button
                const container = document.getElementById('btn-quiz-container-' + examId);
                const badge = document.getElementById('badge-quiz-' + examId);

                if (container) {
                    container.innerHTML = '<a href="take_exam.php?exam_id=' + examId + '" class="btn btn-success" style="width:100%;justify-content:center;">🚀 Start Quiz Exam</a>';
                }
                if (badge) {
                    badge.innerHTML = '<span class="badge badge-active">Active</span>';
                }
            } else {
                secondsLeft--;
                const hrs = Math.floor(secondsLeft / 3600);
                const mins = Math.floor((secondsLeft % 3600) / 60);
                const secs = secondsLeft % 60;
                const pad = (n) => n.toString().padStart(2, '0');
                btn.innerText = '⏳ Starts in: ' + (hrs > 0 ? pad(hrs) + ':' : '') + pad(mins) + ':' + pad(secs);
            }
        }, 1000);
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
