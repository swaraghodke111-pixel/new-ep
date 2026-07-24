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

<!-- Student Vertical Bar Graph Section -->
<div class="card" style="margin-top:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3>📊 Student Performance Vertical Bar Graph</h3>
        <span class="badge" style="background:rgba(255,107,0,0.15); color:#FF6B00; font-weight:700;">
            Overall Progress: <?= $taken > 0 ? round(($passed / $taken) * 100) : 0 ?>% Pass Rate
        </span>
    </div>
    <div class="card-body">
        
        <?php if (!empty($results)): ?>
            <!-- Summary Metrics Cards -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:28px;">
                <div style="background:var(--bg-card); border:1px solid var(--border); padding:14px 16px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
                    <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:6px;">
                        <span style="color:var(--text-muted); font-weight:500;">Pass Rate</span>
                        <strong style="color:#FF6B00; font-size:0.95rem;"><?= $taken > 0 ? round(($passed / $taken) * 100) : 0 ?>%</strong>
                    </div>
                    <div style="height:8px; background:var(--border); border-radius:4px; overflow:hidden;">
                        <div style="width:<?= $taken > 0 ? round(($passed / $taken) * 100) : 0 ?>%; height:100%; background:#FF6B00; border-radius:4px;"></div>
                    </div>
                </div>

                <div style="background:var(--bg-card); border:1px solid var(--border); padding:14px 16px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
                    <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:6px;">
                        <span style="color:var(--text-muted); font-weight:500;">Average Score</span>
                        <strong style="color:#10B981; font-size:0.95rem;"><?= $avg_score ?>%</strong>
                    </div>
                    <div style="height:8px; background:var(--border); border-radius:4px; overflow:hidden;">
                        <div style="width:<?= min(100, max(0, $avg_score)) ?>%; height:100%; background:#10B981; border-radius:4px;"></div>
                    </div>
                </div>

                <div style="background:var(--bg-card); border:1px solid var(--border); padding:14px 16px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
                    <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:6px;">
                        <span style="color:var(--text-muted); font-weight:500;">Completion Rate</span>
                        <strong style="color:#3B82F6; font-size:0.95rem;"><?= $total_exams > 0 ? round(($taken / $total_exams) * 100) : 0 ?>%</strong>
                    </div>
                    <div style="height:8px; background:var(--border); border-radius:4px; overflow:hidden;">
                        <div style="width:<?= $total_exams > 0 ? round(($taken / $total_exams) * 100) : 0 ?>%; height:100%; background:#3B82F6; border-radius:4px;"></div>
                    </div>
                </div>
            </div>

            <!-- Vertical Bar Graph Section -->
            <h4 style="font-size:0.95rem; margin-bottom:20px; color:var(--text-muted); font-weight:600;">📈 Individual Exam Scores (Vertical Bar Graph)</h4>
            
            <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px 20px 20px 20px; box-shadow:0 4px 16px rgba(0,0,0,0.03);">
                <div style="display:flex; height:270px; position:relative; gap:16px;">
                    
                    <!-- Y-Axis Scale (0% to 100%) -->
                    <div style="display:flex; flex-direction:column; justify-content:space-between; height:210px; font-size:0.75rem; color:var(--text-muted); font-weight:600; text-align:right; min-width:42px;">
                        <span>100%</span>
                        <span>75%</span>
                        <span>50%</span>
                        <span>25%</span>
                        <span>0%</span>
                    </div>

                    <!-- Graph Plot Area with Baseline & Grid -->
                    <div style="flex:1; display:flex; flex-direction:column; height:100%;">
                        <!-- Bars Container -->
                        <div style="height:210px; border-left:2px solid var(--border); border-bottom:2px solid var(--border); display:flex; align-items:flex-end; justify-content:space-around; gap:12px; padding:0 16px; position:relative; background:linear-gradient(to bottom, transparent 99%, var(--border) 100%); background-size: 100% 25%;">
                            
                            <?php foreach (array_slice($results, 0, 7) as $res): 
                                $score_pct = (float)$res['percentage'];
                                $bar_color = $res['passed'] ? '#FF6B00' : '#EF4444';
                                $bar_height = max(5, min(100, $score_pct));
                            ?>
                                <div style="display:flex; flex-direction:column; align-items:center; flex:1; max-width:68px; height:100%; justify-content:flex-end; position:relative;">
                                    <!-- Percentage Pill above Bar -->
                                    <span style="font-size:0.75rem; font-weight:700; color:<?= $bar_color ?>; margin-bottom:8px; background:rgba(0,0,0,0.04); padding:3px 8px; border-radius:6px; white-space:nowrap;">
                                        <?= number_format($score_pct, 0) ?>%
                                    </span>

                                    <!-- Vertical Pillar -->
                                    <div style="width:100%; max-width:48px; height:<?= $bar_height ?>%; background:linear-gradient(180deg, <?= $bar_color ?>, rgba(255,107,0,0.55)); border-radius:8px 8px 0 0; transition:height 0.8s ease; box-shadow:0 4px 12px rgba(0,0,0,0.08);" title="<?= h($res['title']) ?>: <?= number_format($score_pct, 1) ?>%"></div>
                                </div>
                            <?php endforeach; ?>

                        </div>

                        <!-- X-Axis Labels Below Baseline -->
                        <div style="display:flex; justify-content:space-around; gap:12px; padding:12px 16px 0 16px;">
                            <?php foreach (array_slice($results, 0, 7) as $res): ?>
                                <div style="flex:1; max-width:68px; text-align:center; font-size:0.78rem; font-weight:600; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= h($res['title']) ?>">
                                    <?= h($res['title']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state" style="padding:24px;">
                <span class="empty-icon">📊</span>
                <h3>No Performance Data Yet</h3>
                <p>Take your first exam to view your vertical bar graph here!</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
