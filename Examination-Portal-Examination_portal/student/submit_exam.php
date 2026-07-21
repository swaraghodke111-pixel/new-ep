<?php
// student/submit_exam.php — Result display after submission
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

if (!$exam_id) redirect(BASE_URL . '/student/exams.php');

$result = get_result($user_id, $exam_id);
$exam   = get_exam_by_id($exam_id);

if (!$result || !$exam) {
    flash('error', 'Result not found.');
    redirect(BASE_URL . '/student/exams.php');
}

$page_title = 'Exam Submitted';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header text-center">
    <h2>Exam Submitted Successfully</h2>
    <p><?= h($exam['title']) ?></p>
</div>

<!-- Result Hero -->
<div class="result-hero" style="max-width:600px;margin:0 auto 28px;">
    <span class="result-emoji"><?= $result['passed'] ? '🏆' : '📚' ?></span>
    <div class="result-score"><?= $result['score'] ?><span style="font-size:1.5rem;color:var(--text-muted);font-weight:400;"> / <?= $result['total'] ?></span></div>
    <div class="result-percent"><?= $result['percentage'] ?>% Score</div>
    <div class="result-status <?= $result['passed'] ? 'pass' : 'fail' ?>" style="margin:16px auto;display:inline-flex;">
        <?= $result['passed'] ? '✅ PASSED' : '❌ FAILED' ?>
    </div>

    <div style="margin-top:16px;">
        <div class="progress-bar-wrap" style="height:12px;max-width:400px;margin:0 auto;">
            <div class="progress-bar-fill <?= $result['passed'] ? 'green' : 'red' ?>" style="width:<?= $result['percentage'] ?>%;transition:width 1.5s ease;"></div>
        </div>
    </div>

    <?php if ($result['auto_submitted']): ?>
        <div class="alert alert-warning" style="margin-top:20px;max-width:400px;margin-left:auto;margin-right:auto;">
            ⏰ This exam was auto-submitted when time expired.
        </div>
    <?php endif; ?>
</div>

<!-- Stats Row -->
<div class="stats-grid" style="max-width:700px;margin:0 auto 28px;">
    <div class="stat-card green">
        <span class="stat-icon">✅</span>
        <div>
            <div class="stat-label">Score</div>
            <div class="stat-value"><?= $result['score'] ?></div>
        </div>
    </div>
    <div class="stat-card purple">
        <span class="stat-icon">📋</span>
        <div>
            <div class="stat-label">Total Marks</div>
            <div class="stat-value"><?= $result['total'] ?></div>
        </div>
    </div>
    <div class="stat-card <?= $result['passed'] ? 'teal' : 'red' ?>">
        <span class="stat-icon"><?= $result['passed'] ? '🎯' : '💪' ?></span>
        <div>
            <div class="stat-label">Percentage</div>
            <div class="stat-value"><?= $result['percentage'] ?>%</div>
        </div>
    </div>
    <?php if ($result['time_taken']): ?>
    <div class="stat-card amber">
        <span class="stat-icon">⏱</span>
        <div>
            <div class="stat-label">Time Taken</div>
            <div class="stat-value" style="font-size:1.2rem;"><?= gmdate('i:s', $result['time_taken']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Actions -->
<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;max-width:700px;margin:0 auto;">
    <a href="results.php?exam_id=<?= $exam_id ?>" class="btn btn-primary">📋 Review Answers</a>
    <a href="exams.php" class="btn btn-outline">← Back to Exams</a>
    <a href="dashboard.php" class="btn btn-outline">🏠 Dashboard</a>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
