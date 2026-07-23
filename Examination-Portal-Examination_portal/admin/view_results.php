<?php
// admin/view_results.php — View Exam Results & Coding Evaluation Submissions
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty','admin');

$user_id  = (int)$_SESSION['user_id'];
$my_exams = ($_SESSION['role'] === 'admin') ? get_all_exams() : get_exams_by_creator($user_id);
$exam_id  = (int)($_GET['exam_id'] ?? 0);
$exam     = $exam_id ? get_exam_by_id($exam_id) : null;
$results  = $exam_id ? get_exam_results($exam_id) : [];

// Fetch coding problems & coding submissions
global $pdo;
$coding_problems = $pdo->query("SELECT id, title FROM coding_problems ORDER BY title ASC")->fetchAll();
$selected_problem_id = (int)($_GET['coding_problem_id'] ?? 0);

$coding_sql = "
    SELECT cs.*, u.name AS student_name, u.email AS student_email, cp.title AS problem_title, cp.difficulty
    FROM coding_submissions cs
    JOIN users u ON cs.user_id = u.id
    JOIN coding_problems cp ON cs.problem_id = cp.id
";
$params = [];
if ($selected_problem_id > 0) {
    $coding_sql .= " WHERE cs.problem_id = ?";
    $params[] = $selected_problem_id;
}
$coding_sql .= " ORDER BY cs.submitted_at DESC";

$stmt = $pdo->prepare($coding_sql);
$stmt->execute($params);
$coding_submissions = $stmt->fetchAll();

$page_title = 'View Results';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>🏆 Exam Results & Coding Evaluations</h2>
    <p>View student scores, quiz performance, and programming evaluation submissions</p>
</div>

<!-- Exam Selector -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:1;">
                <label class="form-label">Select Quiz / MCQ Exam</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                    <option value="">— Choose a quiz exam —</option>
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

<div class="card mb-24">
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
    <div class="card mb-24"><div class="card-body">
        <div class="empty-state">
            <span class="empty-icon">📊</span>
            <h3>No quiz results yet</h3>
            <p>No students have submitted this exam yet.</p>
        </div>
    </div></div>
<?php endif; ?>

<!-- ── Student Coding Examination Submissions ────────────────────────────── -->
<div class="card mb-24" style="margin-top: 36px;">
    <div class="card-header flex-between" style="flex-wrap:wrap; gap:12px;">
        <div>
            <h3>💻 Student Coding Examination Submissions</h3>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">View student programming submissions, evaluation status, runtime metrics, and source code.</p>
        </div>
        <!-- Coding Problem Filter Form -->
        <form method="GET" style="display:flex; gap:8px; align-items:center;">
            <?php if ($exam_id > 0): ?>
                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
            <?php endif; ?>
            <select name="coding_problem_id" class="form-control" style="font-size:0.85rem; padding:6px 12px;" onchange="this.form.submit()">
                <option value="0">All Coding Challenges</option>
                <?php foreach ($coding_problems as $cp): ?>
                    <option value="<?= $cp['id'] ?>" <?= $selected_problem_id == $cp['id'] ? 'selected' : '' ?>>
                        <?= h($cp['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="card-body" style="padding:0;">
        <?php if ($coding_submissions): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Coding Problem</th>
                        <th>Language</th>
                        <th>Status / Verdict</th>
                        <th>Metrics</th>
                        <th>Submitted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coding_submissions as $idx => $cs): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <div style="font-weight:600;"><?= h($cs['student_name']) ?></div>
                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= h($cs['student_email']) ?></span>
                        </td>
                        <td>
                            <div><strong><?= h($cs['problem_title']) ?></strong></div>
                            <span class="badge" style="font-size:0.7rem; background:rgba(255,255,255,0.06); color:var(--text-muted);">
                                <?= ucfirst($cs['difficulty']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:rgba(99,102,241,0.15); color:var(--purple-3); text-transform:uppercase; font-size:0.75rem;">
                                💻 <?= h($cs['language']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($cs['status'] === 'Accepted'): ?>
                                <span class="badge badge-passed">✅ Accepted</span>
                            <?php else: ?>
                                <span class="badge badge-failed">❌ <?= h($cs['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem; color:var(--text-muted);">
                            ⏱ <?= $cs['runtime'] ? $cs['runtime'] . ' ms' : 'N/A' ?> | 💾 <?= $cs['memory'] ? $cs['memory'] . ' MB' : 'N/A' ?>
                        </td>
                        <td style="font-size:0.8rem;"><?= format_datetime($cs['submitted_at']) ?></td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm" onclick='openCodeModal(<?= json_encode($cs['student_name']) ?>, <?= json_encode($cs['problem_title']) ?>, <?= json_encode($cs['language']) ?>, <?= json_encode($cs['code']) ?>)'>
                                View Code 👁
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">💻</span>
                <h3>No coding submissions found</h3>
                <p>When students submit solutions in the coding workspace, their attempts will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Code View Modal -->
<div class="modal-backdrop" id="codeViewModal" onclick="if(event.target.id==='codeViewModal') this.classList.remove('show');">
    <div class="modal-container" style="max-width:750px;">
        <div class="modal-header">
            <h3 id="modalCodeTitle">Student Code Submission</h3>
            <button class="modal-close-btn" onclick="document.getElementById('codeViewModal').classList.remove('show');">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:12px; font-size:0.85rem; color:var(--text-muted);" id="modalCodeMeta"></div>
            <pre style="background:#1e1e1e; color:#d4d4d4; padding:16px; border-radius:10px; overflow-x:auto; font-family:Consolas, Monaco, monospace; font-size:0.9rem; max-height:400px; border:1px solid rgba(255,255,255,0.1);"><code id="modalCodeBody"></code></pre>
        </div>
    </div>
</div>

<script>
function openCodeModal(studentName, problemTitle, language, code) {
    document.getElementById('modalCodeTitle').innerText = '💻 Code Submission: ' + problemTitle;
    document.getElementById('modalCodeMeta').innerText = 'Student: ' + studentName + ' | Language: ' + language.toUpperCase();
    document.getElementById('modalCodeBody').textContent = code;
    document.getElementById('codeViewModal').classList.add('show');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
