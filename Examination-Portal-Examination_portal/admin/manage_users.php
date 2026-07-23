<?php
// admin/manage_users.php — Student List & Management
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('admin', 'faculty');

global $pdo;

// Handle actions
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['uid'] ?? 0);
    if ($action === 'delete' && $uid) {
        // Ensure they are deleting a student role
        $check_stmt = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
        $check_stmt->execute([$uid]);
        $target_user = $check_stmt->fetch();

        if ($target_user && $target_user['role'] === 'student') {
            try {
                $pdo->beginTransaction();

                // Safely delete dependent records across all student activity tables
                $pdo->prepare("DELETE FROM answers WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM exam_attempts WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM results WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM task_submissions WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM coding_submissions WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM email_logs WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM feedback WHERE student_id = ? OR faculty_id = ?")->execute([$uid, $uid]);

                // Delete primary user record
                $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$uid]);

                $pdo->commit();
                flash('success', 'Student "' . $target_user['name'] . '" deleted successfully.');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', 'Failed to delete student: ' . $e->getMessage());
            }
        } else {
            flash('error', 'You can only delete student accounts from this page.');
        }
    }
    redirect($_SERVER['PHP_SELF']);
}

// Filters
$search = trim($_GET['search'] ?? '');
$where  = "WHERE role = 'student'";
$params = [];
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$students = $stmt->fetchAll();

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Student List';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.search-container {
    position: relative;
    flex: 1;
}
.clear-search-tooltip {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    display: inline-block;
}
.clear-search-tooltip .tooltip-text {
    visibility: hidden;
    width: 90px;
    background-color: #1e1b4b;
    color: #ffffff;
    text-align: center;
    border-radius: 6px;
    padding: 6px 10px;
    position: absolute;
    z-index: 10;
    bottom: 130%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.2s, bottom 0.2s;
    font-size: 0.7rem;
    font-weight: 600;
    border: 1px solid #4338ca;
    pointer-events: none;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}
.clear-search-tooltip .tooltip-text::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #4338ca transparent transparent transparent;
}
.clear-search-tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
    bottom: 145%;
}
.clear-btn {
    color: var(--text-muted);
    text-decoration: none;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}
.clear-btn:hover {
    color: var(--red);
}
</style>

<div class="page-header">
    <h2>👥 Student List</h2>
    <p>View registered student profiles or remove student accounts from the portal</p>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<!-- Filters -->
<div class="card mb-24">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" style="display:flex; width:100%; margin:0;">
            <div class="search-container">
                <input type="text" name="search" class="form-control" 
                       placeholder="🔍 Search students by name or email (Press Enter)..." 
                       value="<?= h($search) ?>" 
                       style="padding-right: 45px; width: 100%; height: 42px;">
                <?php if ($search !== ''): ?>
                    <div class="clear-search-tooltip">
                        <a href="manage_users.php" class="clear-btn">✖</a>
                        <span class="tooltip-text">Clear Search</span>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Registered Students (<?= count($students) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students): ?>
                    <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="user-avatar" style="width:32px;height:32px;font-size:0.8rem; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                    <?php if (!empty($s['profile_pic']) && file_exists(dirname(__DIR__) . '/' . $s['profile_pic'])): ?>
                                        <img src="<?= BASE_URL . '/' . h($s['profile_pic']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <?= strtoupper(substr($s['name'],0,1)) ?>
                                    <?php endif; ?>
                                </div>
                                <strong><?= h($s['name']) ?></strong>
                            </div>
                        </td>
                        <td><?= h($s['email']) ?></td>
                        <td style="font-size:0.8rem;"><?= format_datetime($s['created_at']) ?></td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm" onclick='confirmDeleteStudent(<?= $s['id'] ?>, <?= json_encode($s['name']) ?>)'>
                                🗑 Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 24px; color: var(--text-muted);">No student accounts found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDeleteStudent(studentId, studentName) {
    if (confirm("Are you sure you want to delete student '" + studentName + "'? This action will permanently remove their profile and examination records.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_users.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        const uidInput = document.createElement('input');
        uidInput.type = 'hidden';
        uidInput.name = 'uid';
        uidInput.value = studentId;
        form.appendChild(uidInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
