<?php
// faculty/dashboard.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty', 'admin');

global $pdo;

// Fetch stats (same as admin dashboard)
$total_students = count_users_by_role('student');
$total_faculty  = count_users_by_role('faculty');

$total_exams = (int)$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$total_attempts = (int)$pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();

// Recent attempts
$recent_attempts = $pdo->query("
    SELECT r.*, u.name AS student_name, e.title AS exam_title
    FROM results r
    JOIN users u ON r.user_id = u.id
    JOIN exams e ON r.exam_id = e.id
    ORDER BY r.submitted_at DESC
    LIMIT 5
")->fetchAll();

// Recent users
$recent_users = $pdo->query("
    SELECT id, name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

$page_title = 'Faculty Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>⚙️ System Overview</h2>
    <p>Portal overview panel. Monitor system usage, users and examinations.</p>
</div>

<?php $flash_e = get_flash('error'); if ($flash_e): ?>
    <div class="alert alert-error">❌ <?= h($flash_e) ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card purple" onclick="location.href='../admin/manage_users.php'" style="cursor: pointer;">
        <span class="stat-icon">👥</span>
        <div>
            <div class="stat-label">Total Students</div>
            <div class="stat-value"><?= $total_students ?></div>
        </div>
    </div>
    <div class="stat-card teal" onclick="location.href='../admin/faculty.php'" style="cursor: pointer;">
        <span class="stat-icon">👨‍🏫</span>
        <div>
            <div class="stat-label">Total Faculty</div>
            <div class="stat-value"><?= $total_faculty ?></div>
        </div>
    </div>
    <div class="stat-card green" onclick="location.href='../admin/publish_exam.php'" style="cursor: pointer;">
        <span class="stat-icon">📋</span>
        <div>
            <div class="stat-label">Total Exams</div>
            <div class="stat-value"><?= $total_exams ?></div>
        </div>
    </div>
    <div class="stat-card amber" onclick="location.href='../admin/view_results.php'" style="cursor: pointer;">
        <span class="stat-icon">🏆</span>
        <div>
            <div class="stat-label">Total Attempts</div>
            <div class="stat-value"><?= $total_attempts ?></div>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top: 24px;">
    <!-- Recent Submissions System Wide -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Recent System-wide Submissions</h3>
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
                    <p>Student attempts will show here once exams are taken.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recently Registered Users -->
    <div class="card">
        <div class="card-header">
            <h3>👤 Newly Registered Users</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($recent_users): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= h($u['name']) ?></div>
                            </td>
                            <td><?= h($u['email']) ?></td>
                            <td>
                                <span class="badge" style="background: <?= $u['role'] === 'admin' ? 'rgba(239, 68, 68, 0.15)' : ($u['role'] === 'faculty' ? 'rgba(99, 102, 241, 0.15)' : 'rgba(16, 185, 129, 0.15)') ?>; color: <?= $u['role'] === 'admin' ? 'var(--red)' : ($u['role'] === 'faculty' ? 'var(--purple-3)' : 'var(--green)') ?>;">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td style="font-size:0.8rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">👥</span>
                    <h3>No users registered</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
