<?php
// admin/academic/sections.php — Section CRUD
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $program_id = (int)($_POST['program_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name) || !$program_id || !$semester_id) {
            flash('error', 'Program, Semester, and Section Name are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO sections (program_id, semester_id, name) VALUES (?, ?, ?)");
                $stmt->execute([$program_id, $semester_id, $name]);
                flash('success', 'Section added successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'This section name already exists for the selected program and semester.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $program_id = (int)($_POST['program_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        
        if ($id && $program_id && $semester_id && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE sections SET program_id = ?, semester_id = ?, name = ? WHERE id = ?");
                $stmt->execute([$program_id, $semester_id, $name, $id]);
                flash('success', 'Section updated successfully.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'This section name already exists for the selected program and semester.');
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
            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$id]);
            flash('success', 'Section deleted successfully.');
        }
    }
    redirect($_SERVER['PHP_SELF']);
    exit;
}

// Fetch Filters
$search = trim($_GET['search'] ?? '');
$prog_filter = (int)($_GET['program_id'] ?? 0);
$sem_filter = (int)($_GET['semester_id'] ?? 0);
$where = '1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND s.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($prog_filter) {
    $where .= ' AND s.program_id = ?';
    $params[] = $prog_filter;
}
if ($sem_filter) {
    $where .= ' AND s.semester_id = ?';
    $params[] = $sem_filter;
}

// Fetch sections joined with programs and semesters
$stmt = $pdo->prepare("
    SELECT s.*, p.name AS program_name, p.code AS program_code, sem.name AS semester_name 
    FROM sections s 
    JOIN programs p ON s.program_id = p.id 
    JOIN semesters sem ON s.semester_id = sem.id 
    WHERE $where 
    ORDER BY p.name ASC, sem.name ASC, s.name ASC
");
$stmt->execute($params);
$sections = $stmt->fetchAll();

// Fetch programs and semesters list for forms and filter
$programs = $pdo->query("SELECT id, name, code FROM programs ORDER BY name ASC")->fetchAll();
$semesters = $pdo->query("SELECT s.id, s.name, ay.name AS ay_name FROM semesters s JOIN academic_years ay ON s.academic_year_id = ay.id ORDER BY ay.name DESC, s.name ASC")->fetchAll();

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Manage Sections';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>👥 Sections / Batches</h2>
        <p>Manage student class groups and study sections</p>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary" <?= (empty($programs) || empty($semesters)) ? 'disabled title="Please create programs and semesters first"' : '' ?>>+ Add Section</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<?php if (empty($programs) || empty($semesters)): ?>
    <div class="alert alert-error">⚠️ Please add at least one program and semester first before managing sections.</div>
<?php endif; ?>

<!-- Search Filter -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by section name (e.g. Section A)…" value="<?= h($search) ?>">
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:160px;">
                <label class="form-label">Program</label>
                <select name="program_id" class="form-control">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?= $prog['id'] ?>" <?= $prog_filter === (int)$prog['id'] ? 'selected' : '' ?>><?= h($prog['name']) ?> (<?= h($prog['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:160px;">
                <label class="form-label">Semester</label>
                <select name="semester_id" class="form-control">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['id'] ?>" <?= $sem_filter === (int)$sem['id'] ? 'selected' : '' ?>><?= h($sem['name']) ?> (<?= h($sem['ay_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="sections.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<!-- List of Sections -->
<div class="card">
    <div class="card-header">
        <h3>All Sections (<?= count($sections) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Section Name</th>
                    <th>Program</th>
                    <th>Semester</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sections): ?>
                    <?php foreach ($sections as $index => $sec): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= h($sec['name']) ?></strong></td>
                        <td><strong><?= h($sec['program_name']) ?></strong> (<?= h($sec['program_code']) ?>)</td>
                        <td><?= h($sec['semester_name']) ?></td>
                        <td style="font-size:0.8rem;"><?= format_datetime($sec['created_at']) ?></td>
                        <td>
                            <button onclick="openEditModal(<?= $sec['id'] ?>, <?= $sec['program_id'] ?>, <?= $sec['semester_id'] ?>, '<?= h(addslashes($sec['name'])) ?>')" class="btn btn-outline btn-sm">✏️ Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this section?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 24px; color:var(--text-muted);">No sections found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:20px;">➕ Add Section</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Program</label>
                <select name="program_id" class="form-control" required>
                    <option value="">Select Program</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?= $prog['id'] ?>"><?= h($prog['name']) ?> (<?= h($prog['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Semester</label>
                <select name="semester_id" class="form-control" required>
                    <option value="">Select Semester</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['id'] ?>"><?= h($sem['name']) ?> (<?= h($sem['ay_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Section Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Section A" required>
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
        <h3 style="margin-bottom:20px;">✏️ Edit Section</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group">
                <label class="form-label">Program</label>
                <select name="program_id" id="edit-prog-id" class="form-control" required>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?= $prog['id'] ?>"><?= h($prog['name']) ?> (<?= h($prog['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Semester</label>
                <select name="semester_id" id="edit-sem-id" class="form-control" required>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['id'] ?>"><?= h($sem['name']) ?> (<?= h($sem['ay_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Section Name</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
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
function openEditModal(id, progId, semId, name) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-prog-id').value = progId;
    document.getElementById('edit-sem-id').value = semId;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
