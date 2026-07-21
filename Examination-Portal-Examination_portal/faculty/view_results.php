<?php
// faculty/view_results.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id  = (int)$_SESSION['user_id'];
$my_exams = get_exams_by_creator($user_id);
$exam_id  = (int)($_GET['exam_id'] ?? 0);
$exam     = $exam_id ? get_exam_by_id($exam_id) : null;
$results  = $exam_id ? get_exam_results($exam_id) : [];

$page_title = 'View Results';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>🏆 Exam Results</h2>
    <p>View student scores and performance for your exams</p>
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
                        <option value="<?= $e['id'] ?>" <?= $exam_id==$e['id']?'selected':'' ?>>
                            <?= h($e['title']) ?> (<?= $e['attempt_count'] ?> attempts)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($exam && $results): ?>
<!-- Summary Stats -->
<?php
$avg_pct  = round(array_sum(array_column($results,'percentage')) / count($results), 1);
$pass_cnt = count(array_filter($results, fn($r) => $r['passed']));
$top      = $results[0] ?? null;
?>
<div class="stats-grid mb-24" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card teal"><span class="stat-icon">👥</span><div><div class="stat-label">Attempts</div><div class="stat-value"><?= count($results) ?></div></div></div>
    <div class="stat-card green"><span class="stat-icon">✅</span><div><div class="stat-label">Passed</div><div class="stat-value"><?= $pass_cnt ?></div></div></div>
    <div class="stat-card red"><span class="stat-icon">❌</span><div><div class="stat-label">Failed</div><div class="stat-value"><?= count($results)-$pass_cnt ?></div></div></div>
    <div class="stat-card purple"><span class="stat-icon">📊</span><div><div class="stat-label">Avg Score</div><div class="stat-value"><?= $avg_pct ?>%</div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <h3>📋 Results — <?= h($exam['title']) ?></h3>
        <span style="color:var(--text-muted);font-size:0.8rem;">Sorted by score (highest first)</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $i => $r): ?>
                <tr>
                    <td>
                        <?php if ($i === 0): ?><span style="font-size:1.2rem;">🥇</span>
                        <?php elseif($i===1): ?><span style="font-size:1.2rem;">🥈</span>
                        <?php elseif($i===2): ?><span style="font-size:1.2rem;">🥉</span>
                        <?php else: ?><?= $i+1 ?><?php endif; ?>
                    </td>
                    <td><strong><?= h($r['name']) ?></strong></td>
                    <td style="color:var(--text-muted);"><?= h($r['email']) ?></td>
                    <td><?= $r['score'] ?> / <?= $r['total'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="progress-bar-wrap" style="width:80px;">
                                <div class="progress-bar-fill <?= $r['passed']?'green':'red' ?>" style="width:<?= $r['percentage'] ?>%"></div>
                            </div>
                            <span style="font-weight:600;"><?= $r['percentage'] ?>%</span>
                        </div>
                    </td>
                    <td><?= $r['passed'] ? '<span class="badge badge-passed">✅ Passed</span>' : '<span class="badge badge-failed">❌ Failed</span>' ?></td>
                    <td style="font-size:0.8rem;"><?= format_datetime($r['submitted_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($exam): ?>
    <div class="card"><div class="card-body">
        <div class="empty-state">
            <span class="empty-icon">📊</span>
            <h3>No results yet</h3>
            <p>No students have submitted this exam yet.</p>
        </div>
    </div></div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
