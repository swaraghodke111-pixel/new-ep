<?php
// student/quiz_exams.php — View & start Quiz exams created by Super Admin
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
global $pdo;

// Fetch published quiz exams created by Super Admin (role = admin)
$stmt = $pdo->query("
    SELECT e.*, u.name AS creator_name, u.role AS creator_role,
           (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) AS question_count
    FROM exams e
    JOIN users u ON u.id = e.created_by
    WHERE e.is_published = 1 AND u.role = 'admin'
    ORDER BY e.created_at DESC
");
$exams = $stmt->fetchAll();

$results = get_student_results($user_id);

// Map taken exams
$taken_map = [];
foreach ($results as $r) { $taken_map[$r['exam_id']] = $r; }

$page_title = 'Quiz Exams';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.clickable-card {
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.clickable-card:hover {
    transform: translateY(-4px);
    border-color: var(--purple-1) !important;
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.25) !important;
}
</style>

<div class="page-header flex-between">
    <div>
        <h2>📝 Quiz Exams</h2>
        <p>Browse and start quiz examinations created by the Super Admin</p>
    </div>
</div>

<?php if ($exams): ?>
<div class="exam-grid">
    <?php foreach ($exams as $exam):
        $now   = time();
        $start = strtotime($exam['start_time']);
        $end   = strtotime($exam['end_time']);
        $taken = $taken_map[$exam['id']] ?? null;

        $is_upcoming = ($start > 0 && $now < $start);
        $is_expired  = ($end > 0 && $now >= $end);
        $is_active   = (!$is_upcoming && !$is_expired);
        $diff_days   = ($end > 0 && $end > $now) ? max(1, (int)ceil(($end - $now) / 86400)) : 0;

        // Determine destination URL
        if ($taken) {
            $card_url = 'results.php?exam_id=' . $exam['id'];
        } elseif ($is_active) {
            $card_url = 'take_exam.php?exam_id=' . $exam['id'];
        } else {
            $card_url = 'exam_details.php?exam_id=' . $exam['id'];
        }
    ?>
    <div class="exam-card clickable-card"
         onclick="if(!event.target.closest('a, button')) { window.location.href='<?= $card_url ?>'; }"
         tabindex="0"
         onkeydown="if(event.key==='Enter'||event.key===' '){ window.location.href='<?= $card_url ?>'; }">
        
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div class="exam-card-title"><?= h($exam['title']) ?></div>
            <?php if ($taken): ?>
                <span class="badge badge-passed">✅ Completed</span>
            <?php elseif ($is_expired): ?>
                <span class="badge badge-failed">🔴 Expired</span>
            <?php elseif ($is_upcoming): ?>
                <span class="badge" style="background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); font-weight:700;">🟡 Upcoming</span>
            <?php else: ?>
                <span class="badge badge-active">🟢 Active</span>
            <?php endif; ?>
        </div>

        <div class="exam-card-desc" style="margin-bottom:14px;">
            <?= h(mb_strimwidth($exam['description'] ?? 'No description provided.', 0, 120, '…')) ?>
        </div>

        <!-- Quiz Details Grid -->
        <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:14px; margin-bottom:16px; font-size:0.85rem; line-height:1.7;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px;">
                <div>📝 <strong>Quiz Name:</strong> <?= h($exam['title']) ?></div>
                <div>❓ <strong>Total Questions:</strong> <?= $exam['question_count'] ?></div>
                <div>⏱ <strong>Duration:</strong> <?= $exam['duration'] ?> mins</div>
                <div>🎯 <strong>Passing Marks:</strong> <?= $exam['pass_marks'] ?><?= (is_numeric($exam['pass_marks']) && $exam['pass_marks'] > 10) ? '%' : ' Marks' ?></div>
                <div>👑 <strong>Super Admin:</strong> <?= h($exam['creator_name']) ?></div>
                <div>⏳ <strong>Validity:</strong> 
                    <?php if ($is_upcoming): ?>
                        <span style="color:var(--amber); font-weight:600;">Starts <?= date('d M \a\t h:i A', $start) ?></span>
                    <?php elseif ($is_expired): ?>
                        <span style="color:var(--red); font-weight:600;">Expired</span>
                    <?php elseif ($diff_days > 1): ?>
                        <span style="color:var(--green); font-weight:600;">Valid for <?= $diff_days ?> Days</span>
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
            <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline" style="width:100%; justify-content:center;">
                📋 View Results
            </a>
        <?php elseif ($is_expired): ?>
            <a href="exam_details.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline" style="width:100%; justify-content:center; opacity:0.8;">
                🔒 View Details (Expired)
            </a>
        <?php elseif ($is_upcoming): ?>
            <a href="exam_details.php?exam_id=<?= $exam['id'] ?>" class="btn btn-warning" style="width:100%; justify-content:center; font-weight:600;">
                ⏳ Waiting Room & Countdown
            </a>
        <?php else: ?>
            <a href="take_exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-success" style="width:100%; justify-content:center; font-weight:700; padding:10px 16px;">
                🚀 Start Exam Now
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
            <h3>No Super Admin Quiz Exams available</h3>
            <p>No quiz exams published by the Super Admin at the moment.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
