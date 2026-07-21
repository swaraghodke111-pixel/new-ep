<?php
// admin/reports.php — View & export student performance reports
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Fetch all exams for filter dropdown
$exams_stmt = $pdo->query("SELECT id, title FROM exams ORDER BY created_at DESC");
$exams_list = $exams_stmt->fetchAll();

$filter_exam_id = (int)($_GET['exam_id'] ?? 0);

// If CSV export is requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Exam_Portal_Report_" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, ['Attempt ID', 'Student Name', 'Student Email', 'Exam Title', 'Score Obtained', 'Total Score', 'Percentage', 'Result', 'Time Taken (seconds)', 'Submitted At']);

    // Fetch data
    $query = "
        SELECT r.*, u.name AS student_name, u.email AS student_email, e.title AS exam_title
        FROM results r
        JOIN users u ON r.user_id = u.id
        JOIN exams e ON r.exam_id = e.id
    ";
    $params = [];
    if ($filter_exam_id > 0) {
        $query .= " WHERE r.exam_id = ?";
        $params[] = $filter_exam_id;
    }
    $query .= " ORDER BY r.submitted_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            $row['student_name'],
            $row['student_email'],
            $row['exam_title'],
            $row['score'],
            $row['total'],
            $row['percentage'] . '%',
            $row['passed'] ? 'Passed' : 'Failed',
            $row['time_taken'] ?? 'N/A',
            $row['submitted_at']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch results based on filter
$results_query = "
    SELECT r.*, u.name AS student_name, u.email AS student_email, e.title AS exam_title
    FROM results r
    JOIN users u ON r.user_id = u.id
    JOIN exams e ON r.exam_id = e.id
";
$params = [];
if ($filter_exam_id > 0) {
    $results_query .= " WHERE r.exam_id = ?";
    $params[] = $filter_exam_id;
}
$results_query .= " ORDER BY r.submitted_at DESC";

$stmt = $pdo->prepare($results_query);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Calculations for report overview
$total_attempts = count($results);
$pass_count = 0;
$avg_percentage = 0;

if ($total_attempts > 0) {
    foreach ($results as $res) {
        if ($res['passed']) $pass_count++;
        $avg_percentage += $res['percentage'];
    }
    $avg_percentage = round($avg_percentage / $total_attempts, 2);
    $pass_rate = round(($pass_count / $total_attempts) * 100, 2);
} else {
    $pass_rate = 0;
}

$page_title = 'System Reports';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>📄 Examination Reports</h2>
    <p>View stats, track student metrics, and download performance reports.</p>
</div>

<!-- Filters & Actions -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group mb-0" style="flex:1; min-width:200px;">
                <label class="form-label">Filter by Exam</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                    <option value="0">All Exams</option>
                    <?php foreach ($exams_list as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filter_exam_id === $e['id'] ? 'selected' : '' ?>>
                            <?= h($e['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <a href="?exam_id=<?= $filter_exam_id ?>&export=csv" class="btn btn-primary" style="text-decoration:none; padding:12px 20px;">
                    📥 Download CSV Report
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid mb-24">
    <div class="stat-card purple">
        <span class="stat-icon">🎓</span>
        <div>
            <div class="stat-label">Total Attempts</div>
            <div class="stat-value"><?= $total_attempts ?></div>
        </div>
    </div>
    <div class="stat-card green">
        <span class="stat-icon">🎯</span>
        <div>
            <div class="stat-label">Pass Rate</div>
            <div class="stat-value"><?= $pass_rate ?>%</div>
        </div>
    </div>
    <div class="stat-card amber">
        <span class="stat-icon">📈</span>
        <div>
            <div class="stat-label">Avg Percentage</div>
            <div class="stat-value"><?= $avg_percentage ?>%</div>
        </div>
    </div>
</div>

<!-- Report Table -->
<div class="card">
    <div class="card-header">
        <h3>📊 Student Performance Records</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if ($results): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Result</th>
                        <th>Time Taken</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td>
                            <div><strong><?= h($r['student_name']) ?></strong></div>
                            <span style="font-size:0.75rem;color:var(--text-muted);"><?= h($r['student_email']) ?></span>
                        </td>
                        <td><?= h($r['exam_title']) ?></td>
                        <td><?= $r['score'] ?>/<?= $r['total'] ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="progress-bar-wrap" style="width:60px;">
                                    <div class="progress-bar-fill <?= $r['passed'] ? 'green' : 'red' ?>" style="width:<?= min(100, $r['percentage']) ?>%"></div>
                                </div>
                                <?= $r['percentage'] ?>%
                            </div>
                        </td>
                        <td>
                            <?= $r['passed'] ? '<span class="badge badge-passed">✅ Passed</span>' : '<span class="badge badge-failed">❌ Failed</span>' ?>
                        </td>
                        <td>
                            <?php
                            if ($r['time_taken']) {
                                $mins = floor($r['time_taken'] / 60);
                                $secs = $r['time_taken'] % 60;
                                echo "$mins m $secs s";
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td style="font-size:0.8rem;"><?= format_datetime($r['submitted_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">📊</span>
                <h3>No records found</h3>
                <p>No student results match the filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
