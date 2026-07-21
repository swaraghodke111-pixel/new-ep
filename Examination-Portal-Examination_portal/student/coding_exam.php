<?php
// student/coding_exam.php — Student Programming Workspace & Compiler Simulator
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('student');

$user_id = (int)$_SESSION['user_id'];
$problem_id = (int)($_GET['problem_id'] ?? 0);
global $pdo;

// Fetch problem details
$stmt = $pdo->prepare("SELECT * FROM coding_problems WHERE id = ?");
$stmt->execute([$problem_id]);
$problem = $stmt->fetch();

if (!$problem) {
    flash('error', 'Problem not found.');
    redirect(BASE_URL . '/student/coding_problems.php');
    exit;
}

$error = '';
$success = '';
$run_output = '';
$run_status = '';

// Handle AJAX or POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $language = $_POST['language'] ?? 'python';
    $action = $_POST['action'] ?? 'run'; // 'run' or 'submit'
    
    if (empty($code)) {
        $error = 'Please write some code before execution.';
    } else {
        // Simulated execution results
        // Checks if code contains some correct terms for basic syntax matching
        $is_correct = false;
        
        // Simple mock matching patterns
        if ($problem_id == 1) { // Two Sum
            if (stripos($code, 'target') !== false && (stripos($code, 'index') !== false || stripos($code, 'dict') !== false || stripos($code, 'map') !== false || stripos($code, 'for') !== false)) {
                $is_correct = true;
            }
        } elseif ($problem_id == 2) { // Fibonacci
            if (stripos($code, 'fib') !== false || (stripos($code, 'recur') !== false || stripos($code, 'loop') !== false || stripos($code, 'while') !== false || stripos($code, 'for') !== false)) {
                $is_correct = true;
            }
        } else {
            $is_correct = (strlen($code) > 40); // default sanity length
        }
        
        // Simulating run constraints
        $runtime = rand(15, 80); // milliseconds
        $memory = rand(10, 32);  // MB
        
        if ($action === 'run') {
            $run_status = $is_correct ? 'Sample Test Passed' : 'Wrong Answer';
            $run_output = "Execution Stats:\n- Language: " . ucfirst($language) . "\n- Runtime: {$runtime}ms\n- Memory: {$memory}MB\n\nOutput Result:\n" . ($is_correct ? $problem['sample_output'] : "Actual output did not match expected sample.");
        } else {
            // Submit
            $status = $is_correct ? 'Accepted' : 'Wrong Answer';
            
            // Randomly trigger TLE or MLE if code is tiny or huge
            if (strlen($code) < 15) {
                $status = 'Compilation Error';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO coding_submissions (user_id, problem_id, language, code, status, runtime, memory) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$user_id, $problem_id, $language, $code, $status, $runtime, $memory])) {
                $success = "Submission registered! Result: " . $status;
                if ($status === 'Accepted') {
                    send_notification($user_id, "🎉 Your solution for '{$problem['title']}' was ACCEPTED!");
                }
            } else {
                $error = 'Failed to submit solution.';
            }
        }
    }
}

// Fetch past submissions
$stmt = $pdo->prepare("SELECT * FROM coding_submissions WHERE problem_id = ? AND user_id = ? ORDER BY submitted_at DESC");
$stmt->execute([$problem_id, $user_id]);
$past_submissions = $stmt->fetchAll();

$page_title = 'Coding Workspace';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.ide-container {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: 20px;
    height: calc(100vh - 160px);
}
.ide-left, .ide-right {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.ide-editor-box {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    overflow: hidden;
}
.ide-editor-header {
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ide-textarea-wrap {
    flex-grow: 1;
    position: relative;
}
.ide-textarea {
    width: 100%;
    height: 100%;
    background: transparent;
    border: none;
    color: #38bdf8;
    font-family: 'Fira Code', 'Courier New', Courier, monospace;
    font-size: 0.95rem;
    padding: 16px;
    resize: none;
    outline: none;
    line-height: 1.5;
}
.ide-console {
    background: #090d16;
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 16px;
    max-height: 200px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 0.85rem;
}
.tab-content {
    overflow-y: auto;
    flex-grow: 1;
}
</style>

<div class="page-header" style="margin-bottom:12px;">
    <h2>💻 Coding Workspace: <?= h($problem['title']) ?></h2>
    <a href="coding_problems.php" style="color:var(--purple-3); font-size:0.9rem; text-decoration:none;">← Back to challenges</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<div class="ide-container">
    <!-- Left panel: Problem definition & past submissions -->
    <div class="ide-left card">
        <div class="card-header" style="padding: 10px 16px; display:flex; gap:16px;">
            <button class="btn btn-sm btn-primary" onclick="switchLeftTab('desc')">Description</button>
            <button class="btn btn-sm btn-outline" onclick="switchLeftTab('subs')">Submissions</button>
        </div>
        
        <div class="card-body tab-content" id="left-desc-tab">
            <h4 style="margin-bottom:12px;"><?= h($problem['title']) ?></h4>
            <div style="margin-bottom:16px;">
                <?php
                if ($problem['difficulty'] === 'easy') {
                    echo '<span class="badge badge-passed">Easy</span>';
                } elseif ($problem['difficulty'] === 'medium') {
                    echo '<span class="badge" style="background:rgba(251,191,36,0.1); color:#fbbf24; border:1px solid #fbbf24;">Medium</span>';
                } else {
                    echo '<span class="badge badge-failed">Hard</span>';
                }
                ?>
                <span style="font-size:0.8rem; color:var(--text-muted); margin-left:12px;">Time Limit: <?= $problem['time_limit'] ?>ms | Memory Limit: <?= $problem['memory_limit'] ?>MB</span>
            </div>
            
            <div style="font-size:0.9rem; line-height:1.6; margin-bottom:24px; color:#cbd5e1;">
                <?= nl2br(h($problem['description'])) ?>
            </div>
            
            <h5 style="margin-top:16px;">Input Format</h5>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;"><?= nl2br(h($problem['input_format'])) ?></p>
            
            <h5>Output Format</h5>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;"><?= nl2br(h($problem['output_format'])) ?></p>
            
            <div style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); padding:16px; border-radius:10px; margin-top:20px;">
                <strong>Sample Input:</strong>
                <pre style="background:rgba(0,0,0,0.3); padding:8px; border-radius:6px; margin-top:6px; font-size:0.8rem; font-family:monospace;"><?= h($problem['sample_input']) ?></pre>
                
                <strong style="display:block; margin-top:12px;">Sample Output:</strong>
                <pre style="background:rgba(0,0,0,0.3); padding:8px; border-radius:6px; margin-top:6px; font-size:0.8rem; font-family:monospace;"><?= h($problem['sample_output']) ?></pre>
            </div>
        </div>

        <div class="card-body tab-content" id="left-subs-tab" style="display:none; padding:0;">
            <?php if ($past_submissions): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Lang</th>
                                <th>Status</th>
                                <th>Specs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_submissions as $ps): ?>
                            <tr>
                                <td style="font-size:0.75rem;"><?= format_datetime($ps['submitted_at']) ?></td>
                                <td style="font-size:0.8rem; font-weight:600;"><?= h($ps['language']) ?></td>
                                <td>
                                    <?php
                                    if ($ps['status'] === 'Accepted') {
                                        echo '<span class="badge badge-passed">Accepted</span>';
                                    } else {
                                        echo '<span class="badge badge-failed">' . h($ps['status']) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="font-size:0.75rem; color:var(--text-muted);"><?= $ps['runtime'] ?>ms / <?= $ps['memory'] ?>MB</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📭</span>
                    <h3>No attempts made yet</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right panel: Code compiler environment -->
    <div class="ide-right">
        <form method="POST" action="" id="ideForm" style="display:flex; flex-direction:column; height:100%;">
            <div class="ide-editor-box">
                <div class="ide-editor-header">
                    <div>
                        <select name="language" class="form-control" style="background:#1e293b; color:#fff; border:1px solid rgba(255,255,255,0.1); padding:4px 8px; font-size:0.85rem; border-radius:6px;">
                            <option value="python">Python</option>
                            <option value="javascript">JavaScript</option>
                            <option value="cpp">C++</option>
                            <option value="c">C</option>
                            <option value="java">Java</option>
                            <option value="go">Go</option>
                            <option value="rust">Rust</option>
                            <option value="php">PHP</option>
                            <option value="kotlin">Kotlin</option>
                            <option value="swift">Swift</option>
                            <option value="ruby">Ruby</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="action" value="run" class="btn btn-outline btn-sm" onclick="setSubmitAction('run')">⚙️ Run Code</button>
                        <button type="submit" name="action" value="submit" class="btn btn-primary btn-sm" onclick="setSubmitAction('submit')">🚀 Submit Solution</button>
                    </div>
                </div>
                
                <input type="hidden" name="action" id="actionField" value="run">

                <div class="ide-textarea-wrap">
                    <textarea name="code" class="ide-textarea" placeholder="// Write your code solution here...&#10;// Make sure to read input and print output format as requested." required><?= h($_POST['code'] ?? '') ?></textarea>
                </div>

                <!-- Console Run Output -->
                <?php if ($run_output || $run_status): ?>
                <div class="ide-console">
                    <div style="font-weight:700; margin-bottom:8px; display:flex; justify-content:space-between;">
                        <span>Console Output</span>
                        <span style="color: <?= $run_status==='Sample Test Passed' ? '#10b981' : '#ef4444' ?>"><?= h($run_status) ?></span>
                    </div>
                    <pre style="white-space:pre-wrap; color:#94a3b8;"><?= h($run_output) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
function switchLeftTab(tab) {
    const descTab = document.getElementById('left-desc-tab');
    const subsTab = document.getElementById('left-subs-tab');
    
    if (tab === 'desc') {
        descTab.style.display = 'block';
        subsTab.style.display = 'none';
    } else {
        descTab.style.display = 'none';
        subsTab.style.display = 'block';
    }
}

function setSubmitAction(action) {
    document.getElementById('actionField').value = action;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
