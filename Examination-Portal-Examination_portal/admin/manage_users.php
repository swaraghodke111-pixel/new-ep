<?php
// admin/manage_users.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle actions
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['uid'] ?? 0);
    if ($action === 'delete' && $uid && $uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        flash('success', 'User deleted successfully.');
    } elseif ($action === 'change_role' && $uid) {
        $new_role = $_POST['new_role'] ?? '';
        if (in_array($new_role, ['student','faculty','admin'])) {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$new_role, $uid]);
            flash('success', 'User role updated.');
        }
    } elseif ($action === 'add_user') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? 'student';
        if ($name && $email && $pass) {
            if (!get_user_by_email($email)) {
                register_user($name, $email, $pass, $role);
                flash('success', 'User "' . $name . '" added successfully.');
            } else {
                flash('error', 'Email already exists.');
            }
        }
    }
    redirect($_SERVER['PHP_SELF']);
}

// Filters
$search     = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$where = 'WHERE 1=1';
$params = [];
if ($search) { $where .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%'; }
if ($role_filter) { $where .= ' AND role=?'; $params[] = $role_filter; }

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$fs = get_flash('success'); $fe = get_flash('error');
$page_title = 'Manage Users';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>👥 Manage Users</h2>
        <p>Add, edit roles or remove users from the portal</p>
    </div>
    <button onclick="document.getElementById('add-user-modal').style.display='flex'" class="btn btn-primary">+ Add User</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<!-- Filters -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or email…" value="<?= h($search) ?>">
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:140px;">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="student" <?= $role_filter==='student'?'selected':'' ?>>Student</option>
                    <option value="faculty" <?= $role_filter==='faculty'?'selected':'' ?>>Faculty</option>
                    <option value="admin"   <?= $role_filter==='admin'?'selected':'' ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="manage_users.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>All Users (<?= count($users) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="user-avatar" style="width:32px;height:32px;font-size:0.8rem;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                            <strong><?= h($u['name']) ?></strong>
                        </div>
                    </td>
                    <td><?= h($u['email']) ?></td>
                    <td>
                        <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <select name="new_role" class="form-control" style="padding:4px 8px;font-size:0.8rem;width:auto;">
                                <option value="student" <?= $u['role']==='student'?'selected':'' ?>>Student</option>
                                <option value="faculty" <?= $u['role']==='faculty'?'selected':'' ?>>Faculty</option>
                                <option value="admin"   <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn btn-outline btn-sm">Save</button>
                        </form>
                    </td>
                    <td style="font-size:0.8rem;"><?= format_datetime($u['created_at']) ?></td>
                    <td>
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user <?= addslashes($u['name']) ?>? This is irreversible.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                        </form>
                        <?php else: ?>
                            <span class="badge badge-admin">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="add-user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">➕ Add New User</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_user">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Full name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">Add User</button>
                <button type="button" onclick="document.getElementById('add-user-modal').style.display='none'" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
