<?php
// student/results.php — Student results history
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$results = get_student_results($user_id);

// Detailed result view for one exam
$detail = null;
$detail_answers = [];
if (isset($_GET['exam_id'])) {
    $exam_id = (int)$_GET['exam_id'];
    $detail  = get_result($user_id, $exam_id);
    if ($detail) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT a.*, q.question, q.opt1, q.opt2, q.opt3, q.opt4, q.answer AS correct_answer, q.marks
            FROM answers a
            JOIN questions q ON q.id = a.question_id
            WHERE a.user_id = ? AND a.exam_id = ?
            ORDER BY q.id ASC
        ");
        $stmt->execute([$user_id, $exam_id]);
        $detail_answers = $stmt->fetchAll();
        $detail_exam    = get_exam_by_id($exam_id);
    }
}

$page_title = 'My Results';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>📊 My Results</h2>
    <p>Track your examination performance and review answers</p>
</div>

<?php if ($detail && $detail_exam): ?>
<!-- ── Detailed Result View ──────────────────────────────────────────────── -->
<div style="margin-bottom:16px;">
    <a href="results.php" class="btn btn-outline btn-sm">← Back to Results</a>
</div>

<div class="result-hero">
    <span class="result-emoji"><?= $detail['passed'] ? '🏆' : '😔' ?></span>
    <div class="result-score"><?= $detail['score'] ?> / <?= $detail['total'] ?></div>
    <div class="result-percent"><?= $detail['percentage'] ?>% Score</div>
    <div class="result-status <?= $detail['passed'] ? 'pass' : 'fail' ?>">
        <?= $detail['passed'] ? '✅ PASSED' : '❌ FAILED' ?>
    </div>
    <div style="margin-top:16px;color:var(--text-muted);font-size:0.85rem;">
        📅 Submitted: <?= format_datetime($detail['submitted_at']) ?>
        <?php if ($detail['auto_submitted']): ?> | ⏰ Auto-submitted<?php endif; ?>
        <?php if ($detail['time_taken']): ?>
            | ⏱ Time taken: <?= gmdate('H:i:s', $detail['time_taken']) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Answer Review -->
<div class="card">
    <div class="card-header"><h3>📋 Answer Review — <?= h($detail_exam['title']) ?></h3></div>
    <div class="card-body">
        <?php foreach ($detail_answers as $i => $a): ?>
        <div style="background:var(--bg-card);border:1px solid <?= $a['is_correct'] ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.25)' ?>;border-radius:12px;padding:20px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
                <span style="color:var(--purple-3);font-size:0.8rem;font-weight:600;">QUESTION <?= $i + 1 ?></span>
                <span class="badge <?= $a['is_correct'] ? 'badge-passed' : 'badge-failed' ?>">
                    <?= $a['is_correct'] ? '✅ Correct' : '❌ Wrong' ?> (<?= $a['marks'] ?> mark<?= $a['marks'] > 1 ? 's' : '' ?>)
                </span>
            </div>
            <p style="font-weight:500;margin-bottom:14px;"><?= h($a['question']) ?></p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <?php
                $opts = ['A' => $a['opt1'], 'B' => $a['opt2'], 'C' => $a['opt3'], 'D' => $a['opt4']];
                foreach ($opts as $letter => $opt):
                    $is_correct_opt = ($opt === $a['correct_answer']);
                    $is_selected    = ($opt === $a['answer']);
                    $bg = 'rgba(255,255,255,0.04)';
                    $border = 'rgba(255,255,255,0.08)';
                    if ($is_correct_opt) { $bg = 'rgba(16,185,129,0.12)'; $border = 'rgba(16,185,129,0.4)'; }
                    elseif ($is_selected && !$is_correct_opt) { $bg = 'rgba(239,68,68,0.1)'; $border = 'rgba(239,68,68,0.3)'; }
                ?>
                    <div style="background:<?= $bg ?>;border:1.5px solid <?= $border ?>;border-radius:8px;padding:10px 14px;font-size:0.85rem;display:flex;align-items:center;gap:8px;">
                        <span style="font-weight:700;color:var(--text-muted);"><?= $letter ?>.</span>
                        <?= h($opt) ?>
                        <?php if ($is_correct_opt): ?><span style="margin-left:auto;color:var(--green);">✓</span><?php endif; ?>
                        <?php if ($is_selected && !$is_correct_opt): ?><span style="margin-left:auto;color:var(--red);">✗</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$a['answer']): ?>
                <div style="margin-top:10px;color:var(--text-muted);font-size:0.8rem;">⚠️ Not answered — Correct: <?= h($a['correct_answer']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<!-- ── Results Summary & Performance Analytics ────────────────────────────── -->
<?php if ($results): 
    $total_given = count($results);
    $total_obtained = 0;
    $total_possible = 0;
    $passed_count = 0;
    $failed_count = 0;
    $sum_percentage = 0;

    foreach ($results as $r) {
        $total_obtained += (int)$r['score'];
        $total_possible += (int)$r['total'];
        $sum_percentage += (float)$r['percentage'];
        if ($r['passed']) {
            $passed_count++;
        } else {
            $failed_count++;
        }
    }

    $overall_avg_percentage = $total_given > 0 ? round($sum_percentage / $total_given, 1) : 0;
    $total_missed = max(0, $total_possible - $total_obtained);

    // Line graph data in chronological order (oldest to newest)
    $line_labels = [];
    $line_scores = [];
    $line_passmarks = [];
    foreach (array_reverse($results) as $r) {
        $line_labels[]    = mb_strimwidth($r['title'], 0, 25, '…');
        $line_scores[]    = (float)$r['percentage'];
        $line_passmarks[] = (float)($r['pass_percentage'] ?? 40);
    }
?>

<!-- Summary Stats Grid -->
<div class="stats-grid mb-24">
    <div class="stat-card purple">
        <span class="stat-icon">📋</span>
        <div>
            <div class="stat-label">Exams Completed</div>
            <div class="stat-value"><?= $total_given ?></div>
        </div>
    </div>
    <div class="stat-card green">
        <span class="stat-icon">🎯</span>
        <div>
            <div class="stat-label">Total Score</div>
            <div class="stat-value"><?= $total_obtained ?> <span style="font-size:1rem;color:var(--text-muted);">/ <?= $total_possible ?></span></div>
        </div>
    </div>
    <div class="stat-card teal">
        <span class="stat-icon">📊</span>
        <div>
            <div class="stat-label">Overall Average</div>
            <div class="stat-value"><?= $overall_avg_percentage ?>%</div>
        </div>
    </div>
    <div class="stat-card amber">
        <span class="stat-icon">🏆</span>
        <div>
            <div class="stat-label">Passed / Failed</div>
            <div class="stat-value"><?= $passed_count ?> <span style="font-size:0.9rem;color:var(--text-muted);">Pass</span> / <?= $failed_count ?> <span style="font-size:0.9rem;color:var(--red);">Fail</span></div>
        </div>
    </div>
</div>

<!-- Line Graph Card -->
<div class="card mb-24" style="margin-top:24px;">
    <div class="card-header">
        <h3>📈 Student Performance & Score Progress Trend</h3>
    </div>
    <div class="card-body">
        <div style="height: 280px; position: relative;">
            <canvas id="progressLineChart"></canvas>
        </div>
    </div>
</div>

<!-- Pie Charts Row -->
<div class="grid-2 mb-24" style="margin-top:24px;">
    <!-- Pie Chart Card -->
    <div class="card">
        <div class="card-header">
            <h3>🥧 Overall Average Percentage Breakdown</h3>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;">
            <div style="width:240px;height:240px;position:relative;">
                <canvas id="overallPieChart"></canvas>
            </div>
            <div style="margin-top:16px;text-align:center;font-weight:600;font-size:0.95rem;color:var(--text-main);">
                Overall Score: <span style="color:var(--purple-3);"><?= $overall_avg_percentage ?>%</span>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">(<?= $total_obtained ?> Marks Scored out of <?= $total_possible ?> Total)</div>
            </div>
        </div>
    </div>

    <!-- Exam Pass/Fail Ratio Donut Chart -->
    <div class="card">
        <div class="card-header">
            <h3>🍩 Pass vs. Fail Performance Ratio</h3>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;">
            <div style="width:240px;height:240px;position:relative;">
                <canvas id="passFailChart"></canvas>
            </div>
            <div style="margin-top:16px;text-align:center;font-weight:600;font-size:0.95rem;color:var(--text-main);">
                Success Rate: <span style="color:var(--green);"><?= $total_given > 0 ? round(($passed_count / $total_given) * 100, 1) : 0 ?>%</span>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;"><?= $passed_count ?> Passed &bull; <?= $failed_count ?> Failed</div>
            </div>
        </div>
    </div>
</div>

<!-- Results History Table -->
<div class="card">
    <div class="card-header"><h3>🏆 Examination History</h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= h($r['title']) ?></strong></td>
                    <td><?= $r['score'] ?> / <?= $r['total'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="progress-bar-wrap" style="width:80px;">
                                <div class="progress-bar-fill <?= $r['passed'] ? 'green' : 'red' ?>" style="width:<?= min(100,$r['percentage']) ?>%"></div>
                            </div>
                            <span style="font-weight:600;"><?= $r['percentage'] ?>%</span>
                        </div>
                    </td>
                    <td><?= $r['passed'] ? '<span class="badge badge-passed">✅ Passed</span>' : '<span class="badge badge-failed">❌ Failed</span>' ?></td>
                    <td style="font-size:0.8rem;"><?= format_datetime($r['submitted_at']) ?></td>
                    <td><a href="results.php?exam_id=<?= $r['exam_id'] ?>" class="btn btn-outline btn-sm">Review</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Line Graph for Student Progress Trend
    const lineCtx = document.getElementById('progressLineChart').getContext('2d');
    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($line_labels) ?>,
            datasets: [
                {
                    label: 'Score Percentage (%)',
                    data: <?= json_encode($line_scores) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 8
                },
                {
                    label: 'Required Pass Mark (%)',
                    data: <?= json_encode($line_passmarks) ?>,
                    borderColor: '#ef4444',
                    borderDash: [6, 6],
                    borderWidth: 2,
                    fill: false,
                    tension: 0,
                    pointRadius: 4,
                    pointBackgroundColor: '#ef4444'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#94a3b8' }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#cbd5e1', font: { size: 12 } }
                }
            }
        }
    });

    // 2. Pie Chart for Overall Marks / Average Percentage
    const pieCtx = document.getElementById('overallPieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: ['Marks Obtained', 'Marks Missed'],
            datasets: [{
                data: [<?= $total_obtained ?>, <?= $total_missed ?>],
                backgroundColor: ['#6366f1', 'rgba(255,255,255,0.08)'],
                borderColor: ['#4f46e5', 'rgba(255,255,255,0.15)'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#cbd5e1', font: { size: 12 } }
                }
            }
        }
    });

    // 3. Pass vs Fail Ratio Chart
    const passCtx = document.getElementById('passFailChart').getContext('2d');
    new Chart(passCtx, {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{
                data: [<?= $passed_count ?>, <?= $failed_count ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderColor: ['#059669', '#dc2626'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#cbd5e1', font: { size: 12 } }
                }
            }
        }
    });
});
</script>

<?php else: ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <span class="empty-icon">📊</span>
        <h3>No results yet</h3>
        <p>Take an exam to see your results here.</p>
        <a href="exams.php" class="btn btn-primary" style="margin-top:16px;">Browse Exams</a>
    </div>
</div></div>
<?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
