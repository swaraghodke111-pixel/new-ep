<?php
// admin/academic/programs.php — Program CRUD
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $department_id = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($code) || !$department_id) {
            flash('error', 'Department, Name, and Code are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO programs (department_id, name, code, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$department_id, $name, $code, $description]);
                flash('success', 'Program added successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Program code already exists.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id && $department_id && !empty($name) && !empty($code)) {
            try {
                $stmt = $pdo->prepare("UPDATE programs SET department_id = ?, name = ?, code = ?, description = ? WHERE id = ?");
                $stmt->execute([$department_id, $name, $code, $description, $id]);
                flash('success', 'Program updated successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Program code already exists.');
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
            $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
            $stmt->execute([$id]);
            flash('success', 'Program deleted successfully.');
        }
    }
    redirect($_SERVER['PHP_SELF']);
    exit;
}

// Fetch Search Query & Department Filter
$search = trim($_GET['search'] ?? '');
$dept_filter = (int)($_GET['department_id'] ?? 0);
$where = '1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (p.name LIKE ? OR p.code LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($dept_filter) {
    $where .= ' AND p.department_id = ?';
    $params[] = $dept_filter;
}

// Fetch all programs joined with departments
$stmt = $pdo->prepare("
    SELECT p.*, d.name AS department_name 
    FROM programs p 
    JOIN departments d ON p.department_id = d.id 
    WHERE $where 
    ORDER BY p.name ASC
");
$stmt->execute($params);
$programs = $stmt->fetchAll();

// Fetch departments list for forms and filter
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Manage Programs';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>🎓 Academic Programs</h2>
        <p>Manage degree and certificate programs</p>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary" <?= empty($departments) ? 'disabled title="Please create a department first"' : '' ?>>+ Add Program</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<?php if (empty($departments)): ?>
    <div class="alert alert-error">⚠️ Please add at least one department first under <a href="departments.php" class="link">Departments</a>.</div>
<?php endif; ?>

<!-- Search Filter -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name or code…" value="<?= h($search) ?>">
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:180px;">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $dept_filter === (int)$dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?> (<?= h($dept['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="programs.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<!-- List of Programs -->
<div class="card">
    <div class="card-header">
        <h3>All Programs (<?= count($programs) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($programs): ?>
                    <?php foreach ($programs as $index => $prog): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><span class="badge" style="background:rgba(6,182,212,0.15);color:var(--teal);border:1px solid rgba(6,182,212,0.3)"><?= h($prog['code']) ?></span></td>
                        <td><strong><?= h($prog['name']) ?></strong></td>
                        <td><strong><?= h($prog['department_name']) ?></strong></td>
                        <td style="color:var(--text-muted);"><?= h($prog['description'] ?: 'N/A') ?></td>
                        <td style="font-size:0.8rem;"><?= format_datetime($prog['created_at']) ?></td>
                        <td>
                            <button onclick="openEditModal(<?= $prog['id'] ?>, <?= $prog['department_id'] ?>, '<?= h(addslashes($prog['name'])) ?>', '<?= h(addslashes($prog['code'])) ?>', '<?= h(addslashes($prog['description'])) ?>')" class="btn btn-outline btn-sm">✏️ Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this program? All associated student sections will be deleted!')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $prog['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 24px; color:var(--text-muted);">No programs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">➕ Add Program</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-control" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= h($dept['name']) ?> (<?= h($dept['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Program Code</label>
                <input type="text" name="code" class="form-control" placeholder="e.g. BSCS" required>
            </div>
            <div class="form-group">
                <label class="form-label">Program Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Bachelor of Science in Computer Science" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Program details..." style="resize:vertical;height:80px;"></textarea>
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
        <h3 style="margin-bottom:20px;">✏️ Edit Program</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group">
                <label class="form-label">Department</label>
                <select name="department_id" id="edit-dept-id" class="form-control" required>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= h($dept['name']) ?> (<?= h($dept['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Program Code</label>
                <input type="text" name="code" id="edit-code" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Program Name</label>
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
function openEditModal(id, deptId, name, code, description) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-dept-id').value = deptId;
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
