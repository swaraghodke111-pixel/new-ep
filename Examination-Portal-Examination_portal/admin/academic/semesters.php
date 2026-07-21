<?php
// admin/academic/semesters.php — Semester CRUD
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (empty($name) || !$academic_year_id) {
            flash('error', 'Academic Year and Semester Name are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO semesters (academic_year_id, name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $academic_year_id, 
                    $name, 
                    empty($start_date) ? null : $start_date, 
                    empty($end_date) ? null : $end_date, 
                    $status
                ]);
                flash('success', 'Semester added successfully.');
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if ($id && $academic_year_id && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE semesters SET academic_year_id = ?, name = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $academic_year_id, 
                    $name, 
                    empty($start_date) ? null : $start_date, 
                    empty($end_date) ? null : $end_date, 
                    $status, 
                    $id
                ]);
                flash('success', 'Semester updated successfully.');
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        } else {
            flash('error', 'Invalid input parameters.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM semesters WHERE id = ?");
            $stmt->execute([$id]);
            flash('success', 'Semester deleted successfully.');
        }
    }
    redirect($_SERVER['PHP_SELF']);
    exit;
}

// Fetch Filters
$search = trim($_GET['search'] ?? '');
$ay_filter = (int)($_GET['academic_year_id'] ?? 0);
$where = '1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND s.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($ay_filter) {
    $where .= ' AND s.academic_year_id = ?';
    $params[] = $ay_filter;
}

// Fetch semesters joined with academic years
$stmt = $pdo->prepare("
    SELECT s.*, ay.name AS academic_year_name 
    FROM semesters s 
    JOIN academic_years ay ON s.academic_year_id = ay.id 
    WHERE $where 
    ORDER BY ay.name DESC, s.name ASC
");
$stmt->execute($params);
$semesters = $stmt->fetchAll();

// Fetch academic years list for forms and filter
$academic_years = $pdo->query("SELECT id, name FROM academic_years ORDER BY name DESC")->fetchAll();

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Manage Semesters';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>🏫 Semesters</h2>
        <p>Manage semesters within academic calendars</p>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary" <?= empty($academic_years) ? 'disabled title="Please create an academic year first"' : '' ?>>+ Add Semester</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<?php if (empty($academic_years)): ?>
    <div class="alert alert-error">⚠️ Please add at least one academic year first under <a href="years.php" class="link">Academic Years</a>.</div>
<?php endif; ?>

<!-- Search Filter -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name (e.g. Fall 2026)…" value="<?= h($search) ?>">
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:180px;">
                <label class="form-label">Academic Year</label>
                <select name="academic_year_id" class="form-control">
                    <option value="">All Academic Years</option>
                    <?php foreach ($academic_years as $ay): ?>
                        <option value="<?= $ay['id'] ?>" <?= $ay_filter === (int)$ay['id'] ? 'selected' : '' ?>><?= h($ay['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="semesters.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<!-- List of Semesters -->
<div class="card">
    <div class="card-header">
        <h3>All Semesters (<?= count($semesters) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Academic Year</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($semesters): ?>
                    <?php foreach ($semesters as $index => $sem): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= h($sem['name']) ?></strong></td>
                        <td><strong><?= h($sem['academic_year_name']) ?></strong></td>
                        <td><?= h($sem['start_date'] ?: 'N/A') ?></td>
                        <td><?= h($sem['end_date'] ?: 'N/A') ?></td>
                        <td>
                            <?php if ($sem['status'] === 'active'): ?>
                                <span class="badge" style="background:rgba(16,185,129,0.15);color:var(--green);border:1px solid rgba(16,185,129,0.3)">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(239,68,68,0.15);color:var(--red);border:1px solid rgba(239,68,68,0.3)">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="openEditModal(<?= $sem['id'] ?>, <?= $sem['academic_year_id'] ?>, '<?= h(addslashes($sem['name'])) ?>', '<?= $sem['start_date'] ?>', '<?= $sem['end_date'] ?>', '<?= $sem['status'] ?>')" class="btn btn-outline btn-sm">✏️ Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this semester? All associated sections will be deleted!')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $sem['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 24px; color:var(--text-muted);">No semesters found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">➕ Add Semester</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Academic Year</label>
                <select name="academic_year_id" class="form-control" required>
                    <option value="">Select Academic Year</option>
                    <?php foreach ($academic_years as $ay): ?>
                        <option value="<?= $ay['id'] ?>"><?= h($ay['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Semester Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Fall / Semester 1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date (Optional)</label>
                <input type="date" name="start_date" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">End Date (Optional)</label>
                <input type="date" name="end_date" class="form-control">
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
        <h3 style="margin-bottom:20px;">✏️ Edit Semester</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group">
                <label class="form-label">Academic Year</label>
                <select name="academic_year_id" id="edit-ay-id" class="form-control" required>
                    <?php foreach ($academic_years as $ay): ?>
                        <option value="<?= $ay['id'] ?>"><?= h($ay['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Semester Name</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date (Optional)</label>
                <input type="date" name="start_date" id="edit-start" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">End Date (Optional)</label>
                <input type="date" name="end_date" id="edit-end" class="form-control">
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
function openEditModal(id, ayId, name, start, end, status) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-ay-id').value = ayId;
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
