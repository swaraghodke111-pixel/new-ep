<?php
// student/exam_details.php — Exam Details & Waiting Room page
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

if (!$exam_id) {
    flash('error', 'Invalid exam specified.');
    redirect(BASE_URL . '/student/exams.php');
}

$exam = get_exam_by_id($exam_id);
if (!$exam || !$exam['is_published']) {
    flash('error', 'Exam not found or not published.');
    redirect(BASE_URL . '/student/exams.php');
}

$now   = time();
$start = strtotime($exam['start_time']);
$end   = strtotime($exam['end_time']);
$taken = get_result($user_id, $exam_id);

$is_upcoming = ($start > 0 && $now < $start);
$is_expired  = ($end > 0 && $now >= $end);
$is_active   = (!$is_upcoming && !$is_expired);
$starts_in   = max(0, $start - $now);

$page_title = 'Exam Details: ' . $exam['title'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="margin-bottom:16px;">
    <a href="exams.php" class="btn btn-outline btn-sm">← Back to Available Exams</a>
</div>

<div class="card" style="max-width:800px; margin:0 auto 28px;">
    <div class="card-header flex-between">
        <div>
            <h2>🎓 <?= h($exam['title']) ?></h2>
            <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                👨‍🏫 Faculty/Creator: <strong><?= h($exam['creator_name']) ?></strong>
            </div>
        </div>
        <div>
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
    </div>

    <div class="card-body">
        <div style="margin-bottom:20px; font-size:0.95rem; color:var(--text-muted);">
            <?= h($exam['description'] ?? 'No description provided.') ?>
        </div>

        <!-- Exam Parameters Grid -->
        <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; margin-bottom:24px;">
            <h4 style="margin-bottom:14px; font-size:0.95rem; color:var(--purple-3);">📋 Exam Specifications</h4>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px 24px; font-size:0.9rem;">
                <div>📝 <strong>Subject/Exam:</strong> <?= h($exam['title']) ?></div>
                <div>👤 <strong>Faculty:</strong> <?= h($exam['creator_name']) ?></div>
                <div>❓ <strong>Total Questions:</strong> <?= $exam['question_count'] ?></div>
                <div>📊 <strong>Total Marks:</strong> <?= $exam['total_marks'] ?></div>
                <div>⏱ <strong>Duration:</strong> <?= $exam['duration'] ?> minutes</div>
                <div>🎯 <strong>Passing Marks:</strong> <?= $exam['pass_marks'] ?><?= (is_numeric($exam['pass_marks']) && $exam['pass_marks'] > 10) ? '%' : ' Marks' ?></div>
                <div>📅 <strong>Exam Date:</strong> <?= ($start > 0) ? date('d M Y', $start) : 'Open Access' ?></div>
                <div>🕒 <strong>Scheduled Window:</strong> 
                    <?= ($start > 0 && $end > 0) ? date('h:i A', $start) . ' – ' . date('h:i A', $end) : 'Flexible Window' ?>
                </div>
            </div>
        </div>

        <!-- Instructions Section -->
        <div style="background:rgba(99,102,241,0.05); border:1px solid rgba(99,102,241,0.2); border-radius:12px; padding:20px; margin-bottom:24px;">
            <h4 style="margin-bottom:10px; font-size:0.95rem; color:var(--purple-3);">📌 Examination Rules & Guidelines</h4>
            <ul style="margin:0; padding-left:20px; font-size:0.85rem; line-height:1.7; color:var(--text-muted);">
                <li>Ensure a stable internet connection before starting the examination.</li>
                <li>Each question permits up to <strong>3 attempts</strong>. On the 3rd attempt, that question is locked and auto-submitted.</li>
                <li>The real-time countdown timer starts immediately upon clicking <strong>Start Exam</strong>.</li>
                <li>When the timer expires, your exam will automatically be submitted.</li>
                <li>Do not refresh or leave the browser window while the exam is in progress.</li>
            </ul>
        </div>

        <!-- Action / Waiting Room Button Section -->
        <div id="action-box" style="text-align:center;">
            <?php if ($taken): ?>
                <div style="display:flex; gap:12px; justify-content:center;">
                    <button class="btn btn-outline" disabled style="cursor:not-allowed; opacity:0.65;">🔒 Exam Completed</button>
                    <a href="results.php?exam_id=<?= $exam_id ?>" class="btn btn-primary">📋 View Results</a>
                </div>
            <?php elseif ($is_expired): ?>
                <div class="alert alert-danger" style="margin-bottom:16px;">
                    🛑 This exam has expired. New attempts are no longer permitted.
                </div>
                <button class="btn btn-outline" disabled style="width:100%; justify-content:center; cursor:not-allowed; opacity:0.65; padding:12px;">
                    🔒 Exam Expired
                </button>
            <?php elseif ($is_upcoming): ?>
                <div style="background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); border-radius:12px; padding:16px; margin-bottom:16px;">
                    <div style="font-weight:700; color:var(--amber); font-size:1.1rem; margin-bottom:4px;" id="live-timer-text">
                        ⏳ Exam Starts In: <?= sprintf('%02d:%02d:%02d', floor($starts_in/3600), floor(($starts_in%3600)/60), $starts_in%60) ?>
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Scheduled Start: <?= date('d M Y \a\t h:i A', $start) ?></div>
                </div>

                <div id="btn-wrapper">
                    <button id="waiting-start-btn" class="btn btn-success" disabled
                            style="width:100%; justify-content:center; font-weight:700; padding:12px; opacity:0.6; cursor:not-allowed;">
                        ⏳ Start Exam (Locked Until Scheduled Start)
                    </button>
                </div>
            <?php else: ?>
                <a href="take_exam.php?exam_id=<?= $exam_id ?>" class="btn btn-success" style="width:100%; justify-content:center; font-weight:700; padding:14px; font-size:1.05rem;">
                    🚀 Start Exam Now
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($is_upcoming): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let secondsRemaining = <?= $starts_in ?>;
    const timerDisplay = document.getElementById('live-timer-text');
    const btnWrapper = document.getElementById('btn-wrapper');

    const interval = setInterval(function() {
        if (secondsRemaining <= 0) {
            clearInterval(interval);
            if (timerDisplay) {
                timerDisplay.style.color = 'var(--green)';
                timerDisplay.innerText = '🟢 Exam is Now Active!';
            }
            if (btnWrapper) {
                btnWrapper.innerHTML = '<a href="take_exam.php?exam_id=<?= $exam_id ?>" class="btn btn-success" style="width:100%; justify-content:center; font-weight:700; padding:14px; font-size:1.05rem;">🚀 Start Exam Now</a>';
            }
        } else {
            secondsRemaining--;
            const hrs  = Math.floor(secondsRemaining / 3600);
            const mins = Math.floor((secondsRemaining % 3600) / 60);
            const secs = secondsRemaining % 60;
            const pad  = (n) => n.toString().padStart(2, '0');
            if (timerDisplay) {
                timerDisplay.innerText = '⏳ Exam Starts In: ' + (hrs > 0 ? pad(hrs) + ':' : '') + pad(mins) + ':' + pad(secs);
            }
        }
    }, 1000);
});
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
