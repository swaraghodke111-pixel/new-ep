<?php
// faculty/manage_coding_problems.php — Faculty coding problems manager
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('faculty', 'admin');

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
global $pdo;

// Handle Create Coding Problem POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_problem'])) {
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $input_format  = trim($_POST['input_format'] ?? '');
    $output_format = trim($_POST['output_format'] ?? '');
    $sample_input  = trim($_POST['sample_input'] ?? '');
    $sample_output = trim($_POST['sample_output'] ?? '');
    $difficulty    = $_POST['difficulty'] ?? 'easy';
    
    if (empty($title) || empty($description) || empty($input_format) || empty($output_format)) {
        $error = 'Title, description, input format, and output format are required.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO coding_problems (title, description, input_format, output_format, sample_input, sample_output, difficulty, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute([$title, $description, $input_format, $output_format, $sample_input, $sample_output, $difficulty, $user_id])) {
            $success = 'Coding challenge created successfully!';
        } else {
            $error = 'Failed to create coding challenge.';
        }
    }
}

// Fetch all coding problems
$problems = $pdo->query("
    SELECT cp.*, u.name AS creator_name, COUNT(cs.id) AS submission_count 
    FROM coding_problems cp
    LEFT JOIN users u ON cp.created_by = u.id
    LEFT JOIN coding_submissions cs ON cs.problem_id = cp.id
    GROUP BY cp.id
    ORDER BY cp.created_at DESC
")->fetchAll();

$page_title = 'Manage Coding Challenges';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>💻 Coding Examination Problems Manager</h2>
    <p>Add coding problems, specify test structures, and review submission statistics.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Left panel: Add new problem -->
    <div class="card">
        <div class="card-header">
            <h3>➕ Create Programming Challenge</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Problem Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Reverse Words in a String" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Problem Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe the constraints, requirements, and background details here..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Input Format</label>
                    <textarea name="input_format" class="form-control" rows="2" placeholder="Specify parameters and data structures..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Output Format</label>
                    <textarea name="output_format" class="form-control" rows="2" placeholder="Specify return value structures..." required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sample Input</label>
                        <textarea name="sample_input" class="form-control" rows="2" placeholder="e.g. 1 2 3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sample Output</label>
                        <textarea name="sample_output" class="form-control" rows="2" placeholder="e.g. 3 2 1"></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Difficulty</label>
                    <select name="difficulty" class="form-control">
                        <option value="easy">🟢 Easy</option>
                        <option value="medium">🟡 Medium</option>
                        <option value="hard">🔴 Hard</option>
                    </select>
                </div>
                <button type="submit" name="create_problem" class="btn btn-primary" style="width:100%;">Create Challenge</button>
            </form>
        </div>
    </div>

    <!-- Right panel: View existing problems list -->
    <div class="card">
        <div class="card-header">
            <h3>📑 Existing Challenges</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($problems): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Challenge Details</th>
                            <th>Difficulty</th>
                            <th>Submissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($problems as $p): ?>
                        <tr>
                            <td>
                                <div><strong><?= h($p['title']) ?></strong></div>
                                <span style="font-size:0.75rem; color:var(--text-muted);">Created by <?= h($p['creator_name'] ?? 'System') ?></span>
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
                                <span style="font-weight:600;"><?= $p['submission_count'] ?> attempts</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">💻</span>
                    <h3>No coding challenges found</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
