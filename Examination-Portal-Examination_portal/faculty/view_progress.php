<?php
// faculty/view_progress.php — Student progress tracking
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id  = (int)$_SESSION['user_id'];
global $pdo;

// Students who attempted exams by this faculty
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email,
           COUNT(r.id) AS exams_taken,
           ROUND(AVG(r.percentage),1) AS avg_percentage,
           SUM(r.passed) AS passed_count,
           MAX(r.submitted_at) AS last_activity
    FROM users u
    JOIN results r ON r.user_id = u.id
    JOIN exams e ON e.id = r.exam_id
    WHERE u.role='student' AND e.created_by = ?
    GROUP BY u.id, u.name, u.email
    ORDER BY avg_percentage DESC
");
$stmt->execute([$user_id]);
$students = $stmt->fetchAll();

$page_title = 'Student Progress';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>📈 Student Progress</h2>
    <p>Track how your students are performing across all your exams</p>
</div>

<?php if ($students): ?>
<div class="card">
    <div class="card-header">
        <h3>👨‍🎓 Student Performance Overview</h3>
        <span style="color:var(--text-muted);font-size:0.8rem;"><?= count($students) ?> students tracked</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Exams Taken</th>
                    <th>Passed</th>
                    <th>Avg Performance</th>
                    <th>Last Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:600;"><?= h($s['name']) ?></div>
                        <div style="font-size:0.78rem;color:var(--text-muted);"><?= h($s['email']) ?></div>
                    </td>
                    <td><?= $s['exams_taken'] ?></td>
                    <td>
                        <span class="badge badge-passed"><?= $s['passed_count'] ?></span>
                        <span style="color:var(--text-muted);font-size:0.8rem;"> / <?= $s['exams_taken'] ?></span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="progress-bar-wrap" style="width:100px;">
                                <div class="progress-bar-fill <?= $s['avg_percentage']>=50?'green':'red' ?>"
                                     style="width:<?= min(100,$s['avg_percentage']) ?>%"></div>
                            </div>
                            <span style="font-weight:700;color:<?= $s['avg_percentage']>=50?'var(--green)':'var(--red)' ?>;">
                                <?= $s['avg_percentage'] ?>%
                            </span>
                        </div>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= format_datetime($s['last_activity']) ?></td>
                    <td><a href="feedback.php?student_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">💬 Feedback</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <span class="empty-icon">📊</span>
        <h3>No student data yet</h3>
        <p>Progress will appear once students start attempting your exams.</p>
    </div>
</div></div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
