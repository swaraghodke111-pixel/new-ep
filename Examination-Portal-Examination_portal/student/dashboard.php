<?php
// student/dashboard.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id   = (int)$_SESSION['user_id'];
$results   = get_student_results($user_id);
$exams     = get_published_exams();
$notifs    = get_notifications($user_id, 5);

// Stats
$total_exams    = count($exams);
$taken          = count($results);
$avg_score      = $taken > 0 ? round(array_sum(array_column($results, 'percentage')) / $taken, 1) : 0;
$passed         = count(array_filter($results, fn($r) => $r['passed']));

// Active exams right now
$active_exams = array_filter($exams, function($e) use ($user_id, $results) {
    $now   = time();
    $start = strtotime($e['start_time']);
    $end   = strtotime($e['end_time']);
    $taken = array_filter($results, fn($r) => $r['exam_id'] == $e['id']);
    return $now >= $start && $now < $end && empty($taken);
});

$page_title = 'Student Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>👋 Welcome back, <?= h($_SESSION['user_name']) ?>!</h2>
    <p>Here's your exam overview and recent activity.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card purple" onclick="location.href='exams.php'" style="cursor: pointer;">
        <span class="stat-icon">📋</span>
        <div>
            <div class="stat-label">Available Exams</div>
            <div class="stat-value"><?= $total_exams ?></div>
        </div>
    </div>
    <div class="stat-card green" onclick="location.href='results.php'" style="cursor: pointer;">
        <span class="stat-icon">🏆</span>
        <div>
            <div class="stat-label">Passed</div>
            <div class="stat-value"><?= $passed ?></div>
        </div>
    </div>
    <div class="stat-card amber" onclick="location.href='results.php'" style="cursor: pointer;">
        <span class="stat-icon">📊</span>
        <div>
            <div class="stat-label">Avg Score</div>
            <div class="stat-value"><?= $avg_score ?>%</div>
        </div>
    </div>
</div>

<!-- Active Exams Alert -->
<?php if (!empty($active_exams)): ?>
    <div class="alert alert-warning" style="margin-bottom:24px;">
        🔴 <strong><?= count($active_exams) ?> exam(s) are currently active!</strong>
        <a href="exams.php" class="link" style="margin-left:8px;">Take exam →</a>
    </div>
<?php endif; ?>

<div class="grid-2">
    <!-- Recent Results -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Recent Results</h3>
            <a href="results.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($results): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>%</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($results, 0, 5) as $r): ?>
                        <tr>
                            <td><?= h($r['title']) ?></td>
                            <td><?= $r['score'] ?>/<?= $r['total'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div class="progress-bar-wrap" style="width:60px;">
                                        <div class="progress-bar-fill <?= $r['passed'] ? 'green' : 'red' ?>" style="width:<?= min(100,$r['percentage']) ?>%"></div>
                                    </div>
                                    <?= $r['percentage'] ?>%
                                </div>
                            </td>
                            <td><?= $r['passed'] ? '<span class="badge badge-passed">✅ Passed</span>' : '<span class="badge badge-failed">❌ Failed</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📭</span>
                    <h3>No results yet</h3>
                    <p>Take your first exam to see results here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Exams -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Upcoming Exams</h3>
            <a href="exams.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php
            $upcoming = array_filter($exams, fn($e) => strtotime($e['start_time']) > time());
            if ($upcoming):
            ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Exam</th><th>Questions</th><th>Starts</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($upcoming, 0, 5) as $e): ?>
                        <tr>
                            <td><?= h($e['title']) ?></td>
                            <td><?= $e['question_count'] ?> Qs</td>
                            <td style="font-size:0.8rem;"><?= format_datetime($e['start_time']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🗓️</span>
                    <h3>No upcoming exams</h3>
                    <p>Check back later for scheduled exams.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($results): ?>
    <?php
    $chart_labels = [];
    $chart_scores = [];
    $chart_passmark = [];
    foreach (array_reverse(array_slice($results, 0, 8)) as $r) {
        $chart_labels[] = h($r['title']);
        $chart_scores[] = (int)$r['percentage'];
        $chart_passmark[] = (int)$r['pass_percentage'];
    }
    ?>
    <div class="card" style="margin-top:24px;">
        <div class="card-header">
            <h3>📈 Performance Trends & Comparison Graphs</h3>
        </div>
        <div class="card-body">
            <div style="height: 250px; position: relative;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Your Percentage (%)',
                        data: <?= json_encode($chart_scores) ?>,
                        backgroundColor: 'rgba(147, 51, 234, 0.6)',
                        borderColor: 'rgba(147, 51, 234, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Required Pass Mark (%)',
                        data: <?= json_encode($chart_passmark) ?>,
                        backgroundColor: 'rgba(239, 68, 68, 0.2)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1,
                        type: 'line'
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
                    legend: { labels: { color: '#cbd5e1' } }
                }
            }
        });
    });
    </script>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
