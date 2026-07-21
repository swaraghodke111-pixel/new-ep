<?php
// faculty/dashboard.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty', 'admin');

$user_id = (int)$_SESSION['user_id'];
global $pdo;

// Fetch stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE created_by = ?");
$stmt->execute([$user_id]);
$total_exams = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE created_by = ? AND is_published = 1 AND start_time <= NOW() AND end_time > NOW()");
$stmt->execute([$user_id]);
$active_exams_count = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM results r JOIN exams e ON r.exam_id = e.id WHERE e.created_by = ?");
$stmt->execute([$user_id]);
$total_attempts = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(r.percentage) FROM results r JOIN exams e ON r.exam_id = e.id WHERE e.created_by = ?");
$stmt->execute([$user_id]);
$avg_score = round((float)$stmt->fetchColumn(), 1);

// Recent attempts
$stmt = $pdo->prepare("
    SELECT r.*, u.name AS student_name, e.title AS exam_title
    FROM results r
    JOIN users u ON r.user_id = u.id
    JOIN exams e ON r.exam_id = e.id
    WHERE e.created_by = ?
    ORDER BY r.submitted_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_attempts = $stmt->fetchAll();

// My exams
$stmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) AS question_count,
           (SELECT COUNT(*) FROM results WHERE exam_id = e.id) AS attempt_count
    FROM exams e
    WHERE e.created_by = ?
    ORDER BY e.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$my_exams = $stmt->fetchAll();

$page_title = 'Faculty Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>👋 Welcome back, <?= h($_SESSION['user_name']) ?>!</h2>
    <p>Monitor your exams, view student submissions, and manage assessments.</p>
</div>

<?php $flash_e = get_flash('error'); if ($flash_e): ?>
    <div class="alert alert-error">❌ <?= h($flash_e) ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card purple">
        <span class="stat-icon">📋</span>
        <div>
            <div class="stat-label">Total Exams Created</div>
            <div class="stat-value"><?= $total_exams ?></div>
        </div>
    </div>
    <div class="stat-card teal">
        <span class="stat-icon">⚡</span>
        <div>
            <div class="stat-label">Active Exams</div>
            <div class="stat-value"><?= $active_exams_count ?></div>
        </div>
    </div>
    <div class="stat-card green">
        <span class="stat-icon">👨‍🎓</span>
        <div>
            <div class="stat-label">Student Attempts</div>
            <div class="stat-value"><?= $total_attempts ?></div>
        </div>
    </div>
    <div class="stat-card amber">
        <span class="stat-icon">📊</span>
        <div>
            <div class="stat-label">Average Score</div>
            <div class="stat-value"><?= $avg_score ?>%</div>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top: 24px;">
    <!-- Recent Attempts -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Recent Submissions</h3>
            <a href="view_results.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($recent_attempts): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attempts as $r): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= h($r['student_name']) ?></div>
                            </td>
                            <td><?= h($r['exam_title']) ?></td>
                            <td><?= $r['score'] ?>/<?= $r['total'] ?></td>
                            <td>
                                <span style="font-weight:600; color: <?= $r['passed'] ? 'var(--green)' : 'var(--red)' ?>;">
                                    <?= $r['percentage'] ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📭</span>
                    <h3>No attempts yet</h3>
                    <p>Student attempts will show here once they complete your exams.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Exams Overview -->
    <div class="card">
        <div class="card-header">
            <h3>📅 My Exams</h3>
            <a href="create_exam.php" class="btn btn-outline btn-sm">New Exam</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($my_exams): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_exams as $e): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= h($e['title']) ?></div>
                            </td>
                            <td><?= $e['question_count'] ?> Qs</td>
                            <td><?= $e['attempt_count'] ?> attempts</td>
                            <td><?= exam_status_badge($e) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🗓️</span>
                    <h3>No exams created</h3>
                    <p>Get started by creating your first exam.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
