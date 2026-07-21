<?php
// student/take_exam.php — Timer-based exam taking page
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

if (!$exam_id) redirect(BASE_URL . '/student/exams.php');

$exam = get_exam_by_id($exam_id);
if (!$exam || !$exam['is_published']) {
    flash('error', 'Exam not found or not published.');
    redirect(BASE_URL . '/student/exams.php');
}

$now   = time();
$start = strtotime($exam['start_time']);
$end   = strtotime($exam['end_time']);

// Validate exam window
if ($now < $start) {
    flash('error', 'This exam has not started yet.');
    redirect(BASE_URL . '/student/exams.php');
}
if ($now >= $end) {
    flash('error', 'This exam has ended.');
    redirect(BASE_URL . '/student/exams.php');
}

// Check already submitted
$existing = get_result($user_id, $exam_id);
if ($existing) {
    redirect(BASE_URL . '/student/submit_exam.php?exam_id=' . $exam_id . '&done=1');
}

// ── AJAX: Save individual answer ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_answer') {
    header('Content-Type: application/json');
    $q_id   = (int)($_POST['question_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');
    if ($q_id && $answer) {
        start_attempt($user_id, $exam_id);
        save_answer($user_id, $exam_id, $q_id, $answer);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// ── POST: Full submit ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $auto = isset($_POST['auto_submit']);

    // Save all answers from POST
    start_attempt($user_id, $exam_id);
    foreach ($_POST as $key => $val) {
        if (strpos($key, 'q_') === 0) {
            $q_id = (int)substr($key, 2);
            save_answer($user_id, $exam_id, $q_id, trim($val));
        }
    }

    $result = submit_exam($user_id, $exam_id, $auto);
    send_notification($user_id, 'You scored ' . $result['score'] . '/' . $result['total'] . ' (' . $result['percentage'] . '%) on "' . $exam['title'] . '".');
    redirect(BASE_URL . '/student/submit_exam.php?exam_id=' . $exam_id);
}

// ── Load questions (randomized if set) ───────────────────────────────────────
start_attempt($user_id, $exam_id);
$questions = get_questions_by_exam($exam_id, (bool)$exam['randomize']);
$total_q   = count($questions);

if ($total_q === 0) {
    flash('error', 'This exam has no questions yet.');
    redirect(BASE_URL . '/student/exams.php');
}

// Time remaining
$attempt    = get_attempt($user_id, $exam_id);
$elapsed    = $attempt ? (time() - strtotime($attempt['start_time'])) : 0;
$remaining  = max(0, ($exam['duration'] * 60) - $elapsed);

// Load already-saved answers
global $pdo;
$saved_stmt = $pdo->prepare("SELECT question_id, answer FROM answers WHERE user_id=? AND exam_id=?");
$saved_stmt->execute([$user_id, $exam_id]);
$saved_answers = [];
foreach ($saved_stmt->fetchAll() as $sa) {
    $saved_answers[$sa['question_id']] = $sa['answer'];
}

// Page (no sidebar layout — distraction-free)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam: <?= h($exam['title']) ?> — Exam Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body { background: var(--bg-dark); }
        .exam-topbar {
            position: sticky; top: 0; z-index: 100;
            background: rgba(10,10,26,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .exam-topbar-title { font-weight: 700; font-size: 1rem; }
        .exam-container { max-width: 1100px; margin: 0 auto; padding: 28px 24px; }
    </style>
</head>
<body>

<!-- Distraction-free exam top bar -->
<div class="exam-topbar">
    <div class="exam-topbar-title">🎓 <?= h($exam['title']) ?></div>
    <div style="display:flex;align-items:center;gap:16px;">
        <span style="color:var(--text-muted);font-size:0.85rem;" id="question-counter">1 / <?= $total_q ?></span>
        <span style="color:var(--text-muted);font-size:0.85rem;">⏱ Time Remaining:</span>
        <span id="timer-display"
              class="timer-display"
              data-seconds="<?= $remaining ?>"
              data-exam-id="<?= $exam_id ?>"
              style="font-size:1.2rem;margin:0;letter-spacing:1px;">
            <?= gmdate('H:i:s', $remaining) ?>
        </span>
    </div>
    <div style="color:var(--text-muted);font-size:0.8rem;">
        <?= $total_q ?> Questions &bull; <?= $exam['duration'] ?> min
    </div>
</div>

<div class="exam-container">
    <form method="POST" id="exam-form" action="">
        <input type="hidden" id="total-questions" value="<?= $total_q ?>">
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">

        <div class="exam-layout">
            <!-- Questions Column -->
            <div>
                <?php foreach ($questions as $idx => $q):
                    $saved_val = $saved_answers[$q['id']] ?? '';
                    $opts = [
                        'A' => $q['opt1'],
                        'B' => $q['opt2'],
                        'C' => $q['opt3'],
                        'D' => $q['opt4'],
                    ];
                ?>
                <div class="question-card question-slide" id="question-<?= $idx + 1 ?>" <?= $idx > 0 ? 'style="display:none;"' : '' ?>>
                    <div class="question-number">Question <?= $idx + 1 ?> of <?= $total_q ?> &bull; <?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></div>
                    <div class="question-text"><?= h($q['question']) ?></div>

                    <div class="option-list">
                        <?php foreach ($opts as $letter => $opt_text):
                            $is_saved = ($saved_val === $opt_text);
                        ?>
                        <label class="option-item <?= $is_saved ? 'selected' : '' ?>" id="opt-<?= $q['id'] ?>-<?= $letter ?>">
                            <input type="radio"
                                   name="q_<?= $q['id'] ?>"
                                   value="<?= h($opt_text) ?>"
                                   data-qid="<?= $q['id'] ?>"
                                   <?= $is_saved ? 'checked' : '' ?>>
                            <span class="option-letter"><?= $letter ?></span>
                            <span class="option-text"><?= h($opt_text) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Navigation -->
                    <div style="display:flex;justify-content:space-between;margin-top:28px;gap:12px;">
                        <button type="button" id="prev-btn" class="btn btn-outline" <?= $idx === 0 ? 'disabled' : '' ?>>← Previous</button>
                        <div style="display:flex;gap:10px;">
                            <button type="button" id="next-btn" class="btn btn-info" <?= $idx === $total_q - 1 ? 'style="display:none"' : '' ?>>Next →</button>
                            <button type="submit" id="submit-btn" class="btn btn-success" <?= $idx < $total_q - 1 ? 'style="display:none"' : '' ?>>
                                ✅ Submit Exam
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Timer & Palette Sidebar -->
            <div>
                <div class="timer-card">
                    <div class="timer-label">Time Remaining</div>
                    <div id="timer-display-2" style="font-size:2rem;font-weight:800;color:var(--green);margin:12px 0;font-variant-numeric:tabular-nums;"><?= gmdate('H:i:s', $remaining) ?></div>

                    <div class="question-palette">
                        <div class="palette-title">Question Navigator</div>
                        <div class="palette-grid">
                            <?php foreach ($questions as $idx => $q): ?>
                            <button type="button" class="palette-btn <?= array_key_exists($q['id'], $saved_answers) ? 'answered' : '' ?> <?= $idx === 0 ? 'current' : '' ?>"
                                    data-q="<?= $idx + 1 ?>">
                                <?= $idx + 1 ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="margin-top:16px;font-size:0.75rem;color:var(--text-muted);text-align:left;line-height:1.6;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <div style="width:12px;height:12px;background:var(--purple-1);border-radius:3px;"></div> Answered
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <div style="width:12px;height:12px;border:1.5px solid var(--amber);border-radius:3px;"></div> Current
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:12px;height:12px;border:1.5px solid var(--border);border-radius:3px;"></div> Unanswered
                        </div>
                    </div>

                    <button type="submit" form="exam-form" id="submit-sidebar-btn"
                            class="btn btn-success" style="width:100%;margin-top:20px;justify-content:center;">
                        ✅ Submit Exam
                    </button>
                </div>
            </div>
        </div><!-- .exam-layout -->
    </form>
</div><!-- .exam-container -->

<script src="<?= BASE_URL ?>/assets/js/exam_timer.js"></script>
<script>
// Sync second timer display
setInterval(function() {
    const main = document.getElementById('timer-display');
    const alt  = document.getElementById('timer-display-2');
    if (main && alt) { alt.textContent = main.textContent; alt.className = main.className.replace('timer-display',''); }
}, 500);
</script>
</body>
</html>
