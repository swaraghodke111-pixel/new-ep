<?php
// student/coding_problems.php — Student programming challenges list
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
global $pdo;

// Fetch all coding problems and the user's best submission status for each
$stmt = $pdo->prepare("
    SELECT cp.*, 
           (SELECT status FROM coding_submissions WHERE problem_id = cp.id AND user_id = ? ORDER BY id DESC LIMIT 1) AS last_status
    FROM coding_problems cp
    ORDER BY cp.id ASC
");
$stmt->execute([$user_id]);
$problems = $stmt->fetchAll();

$page_title = 'Coding Examinations';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>💻 Coding Examinations</h2>
    <p>Select a programming challenge to open the workspace compiler environment and run or submit your code.</p>
</div>

<div class="card">
    <div class="card-header">
        <h3>🚀 Programming Problems</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if ($problems): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Problem Statement</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($problems as $p): ?>
                    <tr>
                        <td>
                            <strong><?= h($p['title']) ?></strong>
                            <p style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">
                                Limit: <?= $p['time_limit'] ?>ms / <?= $p['memory_limit'] ?>MB
                            </p>
                        </td>
                        <td>
                            <?php
                            if ($p['difficulty'] === 'easy') {
                                echo '<span class="badge badge-passed">Easy</span>';
                            } elseif ($p['difficulty'] === 'medium') {
                                echo '<span class="badge" style="background:rgba(251,191,36,0.1); color:#fbbf24; border:1px solid #fbbf24;">Medium</span>';
                            } else {
                                echo '<span class="badge badge-failed">Hard</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($p['last_status'] === 'Accepted') {
                                echo '<span class="badge badge-passed">✅ Solved</span>';
                            } elseif (!empty($p['last_status'])) {
                                echo '<span class="badge badge-failed">❌ ' . h($p['last_status']) . '</span>';
                            } else {
                                echo '<span class="badge badge-draft">Not Attempted</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="coding_exam.php?problem_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Enter IDE →</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">💻</span>
                <h3>No coding problems assigned yet</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
