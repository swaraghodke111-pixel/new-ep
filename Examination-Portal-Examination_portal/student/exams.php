<?php
// student/exams.php — View & start available exams
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$exams   = get_published_exams();
$results = get_student_results($user_id);

// Map taken exams
$taken_map = [];
foreach ($results as $r) { $taken_map[$r['exam_id']] = $r; }

$page_title = 'Available Exams';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>📝 Available Exams</h2>
        <p>Browse and start your scheduled examinations</p>
    </div>
</div>

<?php if ($exams): ?>
<div class="exam-grid">
    <?php foreach ($exams as $exam):
        $now     = time();
        $end     = strtotime($exam['end_time']);
        $taken   = $taken_map[$exam['id']] ?? null;
        $is_expired = ($end > 0 && $now >= $end);

        // Calculate validity in days
        $diff_days = ($end > 0 && $end > $now) ? max(1, (int)ceil(($end - $now) / 86400)) : 0;
    ?>
    <div class="exam-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div class="exam-card-title"><?= h($exam['title']) ?></div>
            <?php if ($is_expired): ?>
                <span class="badge badge-failed">Exam Expired</span>
            <?php elseif ($taken): ?>
                <span class="badge badge-passed">Completed</span>
            <?php else: ?>
                <span class="badge badge-active">🟢 Active</span>
            <?php endif; ?>
        </div>

        <div class="exam-card-desc" style="margin-bottom:14px;">
            <?= h(mb_strimwidth($exam['description'] ?? 'No description provided.', 0, 120, '…')) ?>
        </div>

        <!-- Quiz Details Grid (Pre-exam Information) -->
        <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:14px; margin-bottom:16px; font-size:0.85rem; line-height:1.7;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px;">
                <div>📝 <strong>Quiz Name:</strong> <?= h($exam['title']) ?></div>
                <div>❓ <strong>Total Questions:</strong> <?= $exam['question_count'] ?></div>
                <div>⏱ <strong>Duration:</strong> <?= $exam['duration'] ?> mins</div>
                <div>🎯 <strong>Passing Marks:</strong> <?= $exam['pass_marks'] ?><?= (is_numeric($exam['pass_marks']) && $exam['pass_marks'] > 10) ? '%' : ' Marks' ?></div>
                <div>👤 <strong>Attempts Allowed:</strong> 1 Attempt</div>
                <div>⏳ <strong>Validity:</strong> 
                    <?php if ($is_expired): ?>
                        <span style="color:var(--red); font-weight:600;">Expired</span>
                    <?php elseif ($diff_days > 1): ?>
                        <span style="color:var(--green); font-weight:600;">Valid for <?= $diff_days ?> Days</span> (until <?= date('d M Y', $end) ?>)
                    <?php elseif ($diff_days == 1): ?>
                        <span style="color:var(--amber); font-weight:600;">Expires Today</span>
                    <?php else: ?>
                        <span style="color:var(--green); font-weight:600;">Open Access</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Section -->
        <div>
        <?php if ($taken): ?>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="btn btn-outline" disabled style="flex:1; justify-content:center; cursor:not-allowed; opacity:0.65;">
                    🔒 Exam Completed
                </button>
                <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline btn-sm">View Result</a>
            </div>
        <?php elseif ($is_expired): ?>
            <button class="btn btn-outline" disabled style="width:100%; justify-content:center; cursor:not-allowed; opacity:0.65;">
                🔒 Exam Expired
            </button>
        <?php else: ?>
            <a href="take_exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-success" style="width:100%; justify-content:center; font-weight:700; padding:10px 16px;">
                🚀 Start Exam
            </a>
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <span class="empty-icon">📭</span>
            <h3>No exams available</h3>
            <p>No published exams at the moment. Please check back later.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
