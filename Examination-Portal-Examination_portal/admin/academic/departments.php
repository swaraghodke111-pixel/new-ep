<?php
// admin/academic/departments.php — Department CRUD
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($code)) {
            flash('error', 'Name and Code are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (name, code, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $description]);
                flash('success', 'Department added successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Department name or code already exists.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id && !empty($name) && !empty($code)) {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, code = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $id]);
                flash('success', 'Department updated successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Department name or code already exists.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        } else {
            flash('error', 'Invalid input parameters.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            flash('success', 'Department deleted successfully.');
        }
    }
    redirect($_SERVER['PHP_SELF']);
    exit;
}

// Fetch Search Query
$search = trim($_GET['search'] ?? '');
$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR code LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("SELECT * FROM departments WHERE $where ORDER BY name ASC");
$stmt->execute($params);
$departments = $stmt->fetchAll();

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Manage Departments';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>🏢 Academic Departments</h2>
        <p>Manage college departments and academic branches</p>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">+ Add Department</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<!-- Search Filter -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name or code…" value="<?= h($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="departments.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<!-- List of Departments -->
<div class="card">
    <div class="card-header">
        <h3>All Departments (<?= count($departments) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($departments): ?>
                    <?php foreach ($departments as $index => $dept): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><span class="badge" style="background:rgba(99,102,241,0.15);color:var(--purple-3);border:1px solid rgba(99,102,241,0.3)"><?= h($dept['code']) ?></span></td>
                        <td><strong><?= h($dept['name']) ?></strong></td>
                        <td style="color:var(--text-muted);"><?= h($dept['description'] ?: 'N/A') ?></td>
                        <td style="font-size:0.8rem;"><?= format_datetime($dept['created_at']) ?></td>
                        <td>
                            <button onclick="openEditModal(<?= $dept['id'] ?>, '<?= h(addslashes($dept['name'])) ?>', '<?= h(addslashes($dept['code'])) ?>', '<?= h(addslashes($dept['description'])) ?>')" class="btn btn-outline btn-sm">✏️ Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this department? All associated programs and subjects will be deleted!')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 24px; color:var(--text-muted);">No departments found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">➕ Add Department</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Department Code</label>
                <input type="text" name="code" class="form-control" placeholder="e.g. CS" required>
            </div>
            <div class="form-group">
                <label class="form-label">Department Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Computer Science" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Department details..." style="resize:vertical;height:80px;"></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" onclick="closeAddModal()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">✏️ Edit Department</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group">
                <label class="form-label">Department Code</label>
                <input type="text" name="code" id="edit-code" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Department Name</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit-description" class="form-control" style="resize:vertical;height:80px;"></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('add-modal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('add-modal').style.display = 'none';
}
function openEditModal(id, name, code, description) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-code').value = code;
    document.getElementById('edit-description').value = description;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
