<?php
// admin/academic/years.php — Academic Year CRUD
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (empty($name) || empty($start_date) || empty($end_date)) {
            flash('error', 'Name, Start Date, and End Date are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO academic_years (name, start_date, end_date, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $start_date, $end_date, $status]);
                flash('success', 'Academic Year added successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Academic Year name already exists.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if ($id && !empty($name) && !empty($start_date) && !empty($end_date)) {
            try {
                $stmt = $pdo->prepare("UPDATE academic_years SET name = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $start_date, $end_date, $status, $id]);
                flash('success', 'Academic Year updated successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Academic Year name already exists.');
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
            $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
            $stmt->execute([$id]);
            flash('success', 'Academic Year deleted successfully.');
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
    $where .= ' AND name LIKE ?';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("SELECT * FROM academic_years WHERE $where ORDER BY name DESC");
$stmt->execute($params);
$years = $stmt->fetchAll();

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Manage Academic Years';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>📅 Academic Years</h2>
        <p>Manage school calendars and active enrollment years</p>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">+ Add Academic Year</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<!-- Search Filter -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name (e.g. 2026-2027)…" value="<?= h($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="years.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<!-- List of Academic Years -->
<div class="card">
    <div class="card-header">
        <h3>All Academic Years (<?= count($years) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($years): ?>
                    <?php foreach ($years as $index => $yr): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= h($yr['name']) ?></strong></td>
                        <td><?= h($yr['start_date']) ?></td>
                        <td><?= h($yr['end_date']) ?></td>
                        <td>
                            <?php if ($yr['status'] === 'active'): ?>
                                <span class="badge" style="background:rgba(16,185,129,0.15);color:var(--green);border:1px solid rgba(16,185,129,0.3)">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(239,68,68,0.15);color:var(--red);border:1px solid rgba(239,68,68,0.3)">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;"><?= format_datetime($yr['created_at']) ?></td>
                        <td>
                            <button onclick="openEditModal(<?= $yr['id'] ?>, '<?= h(addslashes($yr['name'])) ?>', '<?= $yr['start_date'] ?>', '<?= $yr['end_date'] ?>', '<?= $yr['status'] ?>')" class="btn btn-outline btn-sm">✏️ Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this academic year? All associated semesters, sections will be deleted!')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $yr['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 24px; color:var(--text-muted);">No academic years found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">➕ Add Academic Year</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Academic Year Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. 2026-2027" required>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
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
        <h3 style="margin-bottom:20px;">✏️ Edit Academic Year</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group">
                <label class="form-label">Academic Year Name</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" id="edit-start" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" id="edit-end" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit-status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
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
function openEditModal(id, name, start, end, status) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-start').value = start;
    document.getElementById('edit-end').value = end;
    document.getElementById('edit-status').value = status;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
