<?php
// admin/faculty.php — Faculty Management Dashboard
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('admin');

global $pdo;

// Handle POST actions (Create, Edit, Delete/Deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? 'Admin@123';
        
        $faculty_id = trim($_POST['faculty_id'] ?? '');
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = trim($_POST['designation'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $office_location = trim($_POST['office_location'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
        $qualification = trim($_POST['qualification'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (empty($name) || empty($email) || empty($faculty_id) || empty($designation) || empty($joining_date) || empty($qualification)) {
            flash('error', 'All fields except Phone, Office Location, and Bio are required.');
        } else {
            try {
                $pdo->beginTransaction();
                
                // 1. Insert into users
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified, is_verified) VALUES (?, ?, ?, 'faculty', 1, 1)");
                $stmt->execute([$name, $email, $hashed_password]);
                $user_id = $pdo->lastInsertId();
                
                // 2. Insert into faculty_profiles
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_profiles 
                    (user_id, faculty_id, department_id, designation, phone, office_location, status, joining_date, qualification, bio) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $faculty_id,
                    $department_id,
                    $designation,
                    $phone !== '' ? $phone : null,
                    $office_location !== '' ? $office_location : null,
                    $status,
                    $joining_date,
                    $qualification,
                    $bio !== '' ? $bio : null
                ]);
                
                $pdo->commit();
                flash('success', "Faculty '$name' created successfully.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() == 23000) {
                    flash('error', 'Error: Email address or Faculty ID already exists in the system.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        $faculty_id = trim($_POST['faculty_id'] ?? '');
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = trim($_POST['designation'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $office_location = trim($_POST['office_location'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $joining_date = $_POST['joining_date'] ?? '';
        $qualification = trim($_POST['qualification'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (!$user_id || empty($name) || empty($email) || empty($faculty_id) || empty($designation) || empty($joining_date) || empty($qualification)) {
            flash('error', 'Required fields are missing.');
        } else {
            try {
                $pdo->beginTransaction();
                
                // 1. Update users table
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $user_id]);
                
                // 2. Update faculty_profiles table
                $stmt = $pdo->prepare("
                    UPDATE faculty_profiles SET 
                        faculty_id = ?, 
                        department_id = ?, 
                        designation = ?, 
                        phone = ?, 
                        office_location = ?, 
                        status = ?, 
                        joining_date = ?, 
                        qualification = ?, 
                        bio = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $faculty_id,
                    $department_id,
                    $designation,
                    $phone !== '' ? $phone : null,
                    $office_location !== '' ? $office_location : null,
                    $status,
                    $joining_date,
                    $qualification,
                    $bio !== '' ? $bio : null,
                    $user_id
                ]);
                
                $pdo->commit();
                flash('success', "Faculty '$name' updated successfully.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() == 23000) {
                    flash('error', 'Error: Email address or Faculty ID already exists in the system.');
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id) {
            try {
                // Delete user from users table (cascades to faculty_profiles)
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
                $stmt->execute([$user_id]);
                flash('success', 'Faculty deleted successfully.');
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
    
    redirect($_SERVER['PHP_SELF']);
    exit;
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$dept_filter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$desig_filter = trim($_GET['designation'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = "u.role = 'faculty'";
$params = [];

if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR fp.faculty_id LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($dept_filter) {
    $where .= " AND fp.department_id = ?";
    $params[] = $dept_filter;
}
if ($desig_filter !== '') {
    $where .= " AND fp.designation = ?";
    $params[] = $desig_filter;
}
if ($status_filter !== '') {
    $where .= " AND fp.status = ?";
    $params[] = $status_filter;
}

// Fetch Faculty profiles joined with users and departments
$stmt = $pdo->prepare("
    SELECT fp.*, u.name, u.email, d.name AS department_name 
    FROM faculty_profiles fp 
    JOIN users u ON fp.user_id = u.id 
    LEFT JOIN departments d ON fp.department_id = d.id 
    WHERE $where 
    ORDER BY u.name ASC
");
$stmt->execute($params);
$faculty_list = $stmt->fetchAll();

// Fetch auxiliary lists
$departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
$designations = $pdo->query("SELECT DISTINCT designation FROM faculty_profiles ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);

// Make sure default designations are available if DB list is empty
if (empty($designations)) {
    $designations = ['Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer', 'Teaching Assistant'];
}

$fs = get_flash('success');
$fe = get_flash('error');
$page_title = 'Faculty Management';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header flex-between">
    <div>
        <h2>🧑‍🏫 Faculty Management</h2>
        <p>Assign departments and manage academic staff records</p>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">+ Add Faculty</button>
</div>

<?php if ($fs): ?><div class="alert alert-success">✅ <?= h($fs) ?></div><?php endif; ?>
<?php if ($fe): ?><div class="alert alert-error">❌ <?= h($fe) ?></div><?php endif; ?>

<!-- Search & Filters -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group mb-0" style="flex:2;min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or Faculty ID…" value="<?= h($search) ?>">
            </div>
            
            <div class="form-group mb-0" style="flex:1;min-width:140px;">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $dept_filter === (int)$dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?> (<?= h($dept['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-0" style="flex:1;min-width:140px;">
                <label class="form-label">Designation</label>
                <select name="designation" class="form-control">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $desig): ?>
                        <option value="<?= h($desig) ?>" <?= $desig_filter === $desig ? 'selected' : '' ?>><?= h($desig) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-0" style="flex:1;min-width:120px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="faculty.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
</div>

<!-- Faculty Members List -->
<div class="card">
    <div class="card-header">
        <h3>All Faculty Members (<?= count($faculty_list) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Faculty ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($faculty_list): ?>
                    <?php foreach ($faculty_list as $fac): ?>
                    <tr>
                        <td><span class="badge" style="background:rgba(168,85,247,0.15);color:var(--purple-3);border:1px solid rgba(168,85,247,0.3)"><?= h($fac['faculty_id']) ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="user-avatar" style="width:32px;height:32px;font-size:0.8rem;"><?= strtoupper(substr($fac['name'], 0, 1)) ?></div>
                                <strong><?= h($fac['name']) ?></strong>
                            </div>
                        </td>
                        <td><?= h($fac['email']) ?></td>
                        <td><strong><?= h($fac['department_name'] ?: 'Not Assigned') ?></strong></td>
                        <td><?= h($fac['designation']) ?></td>
                        <td>
                            <?php if ($fac['status'] === 'active'): ?>
                                <span class="badge" style="background:rgba(16,185,129,0.15);color:var(--green);border:1px solid rgba(16,185,129,0.3)">Active</span>
                            <?php elseif ($fac['status'] === 'inactive'): ?>
                                <span class="badge" style="background:rgba(239,68,68,0.15);color:var(--red);border:1px solid rgba(239,68,68,0.3)">Inactive</span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(245,158,11,0.15);color:var(--amber);border:1px solid rgba(245,158,11,0.3)">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="openViewModal(<?= h(json_encode($fac)) ?>)" class="btn btn-outline btn-sm">👁 View Profile</button>
                            <button onclick="openEditModal(<?= h(json_encode($fac)) ?>)" class="btn btn-outline btn-sm">✏️ Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this faculty member? This is irreversible.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $fac['user_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 24px; color:var(--text-muted);">No faculty members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;overflow-y:auto;padding:24px 0;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:600px;margin:auto;">
        <h3 style="margin-bottom:20px;">➕ Add Faculty Member</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Dr. John Doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@exam.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Faculty ID</label>
                    <input type="text" name="faculty_id" class="form-control" placeholder="e.g. FAC101" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Default Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Default: Admin@123">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">Select Department (Optional)</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= h($dept['name']) ?> (<?= h($dept['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-control" placeholder="e.g. Professor / Lecturer" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. +1234567890">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Location</label>
                    <input type="text" name="office_location" class="form-control" placeholder="e.g. Block B, Room 402">
                </div>
                <div class="form-group">
                    <label class="form-label">Qualification</label>
                    <input type="text" name="qualification" class="form-control" placeholder="e.g. Ph.D. in Computer Science" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Biography</label>
                <textarea name="bio" class="form-control" placeholder="Short biography/profile context..." style="resize:vertical;height:80px;"></textarea>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Faculty</button>
                <button type="button" onclick="closeAddModal()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;overflow-y:auto;padding:24px 0;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:600px;margin:auto;">
        <h3 style="margin-bottom:20px;">✏️ Edit Faculty Member</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit-user-id">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit-email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Faculty ID</label>
                    <input type="text" name="faculty_id" id="edit-faculty-id" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="edit-dept-id" class="form-control">
                        <option value="">Select Department (Optional)</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= h($dept['name']) ?> (<?= h($dept['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" id="edit-designation" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="edit-phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Location</label>
                    <input type="text" name="office_location" id="edit-office-location" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Qualification</label>
                    <input type="text" name="qualification" id="edit-qualification" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" id="edit-joining-date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit-status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Biography</label>
                <textarea name="bio" id="edit-bio" class="form-control" style="resize:vertical;height:80px;"></textarea>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">Update Profile</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Profile Modal -->
<div id="view-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#0f0f20;border:1px solid var(--border);border-radius:16px;padding:32px;width:90%;max-width:550px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:12px;">
            <h3 style="margin:0;">👤 Faculty Detailed Profile</h3>
            <button onclick="closeViewModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
        
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px;">
                <div class="user-avatar" id="view-avatar" style="width:56px;height:56px;font-size:1.5rem;font-weight:700;"></div>
                <div>
                    <h2 id="view-name" style="margin:0;font-size:1.4rem;"></h2>
                    <span class="badge" id="view-designation" style="background:rgba(6,182,212,0.15);color:var(--teal);margin-top:4px;"></span>
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.9rem;">
                <div><span style="color:var(--text-muted);">Faculty ID:</span> <strong id="view-id-val"></strong></div>
                <div><span style="color:var(--text-muted);">Email:</span> <strong id="view-email-val"></strong></div>
                <div><span style="color:var(--text-muted);">Department:</span> <strong id="view-dept-val"></strong></div>
                <div><span style="color:var(--text-muted);">Phone:</span> <strong id="view-phone-val"></strong></div>
                <div><span style="color:var(--text-muted);">Office Location:</span> <strong id="view-office-val"></strong></div>
                <div><span style="color:var(--text-muted);">Joining Date:</span> <strong id="view-join-val"></strong></div>
                <div style="grid-column: span 2;"><span style="color:var(--text-muted);">Qualification:</span> <strong id="view-qual-val"></strong></div>
                <div><span style="color:var(--text-muted);">Status:</span> <span id="view-status-val"></span></div>
            </div>
            
            <div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
                <h4 style="color:var(--purple-3);margin-bottom:6px;">Biography</h4>
                <p id="view-bio-val" style="font-size:0.85rem;color:var(--text-muted);line-height:1.5;max-height:120px;overflow-y:auto;"></p>
            </div>
        </div>
        
        <div style="display:flex;justify-content:flex-end;margin-top:24px;">
            <button onclick="closeViewModal()" class="btn btn-outline">Close</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('add-modal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('add-modal').style.display = 'none';
}

function openEditModal(fac) {
    document.getElementById('edit-user-id').value = fac.user_id;
    document.getElementById('edit-name').value = fac.name;
    document.getElementById('edit-email').value = fac.email;
    document.getElementById('edit-faculty-id').value = fac.faculty_id;
    document.getElementById('edit-dept-id').value = fac.department_id || '';
    document.getElementById('edit-designation').value = fac.designation;
    document.getElementById('edit-phone').value = fac.phone || '';
    document.getElementById('edit-office-location').value = fac.office_location || '';
    document.getElementById('edit-qualification').value = fac.qualification;
    document.getElementById('edit-joining-date').value = fac.joining_date;
    document.getElementById('edit-status').value = fac.status;
    document.getElementById('edit-bio').value = fac.bio || '';
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

function openViewModal(fac) {
    document.getElementById('view-avatar').textContent = fac.name.charAt(0).toUpperCase();
    document.getElementById('view-name').textContent = fac.name;
    document.getElementById('view-designation').textContent = fac.designation;
    document.getElementById('view-id-val').textContent = fac.faculty_id;
    document.getElementById('view-email-val').textContent = fac.email;
    document.getElementById('view-dept-val').textContent = fac.department_name || 'Not Assigned';
    document.getElementById('view-phone-val').textContent = fac.phone || 'N/A';
    document.getElementById('view-office-val').textContent = fac.office_location || 'N/A';
    document.getElementById('view-join-val').textContent = fac.joining_date;
    document.getElementById('view-qual-val').textContent = fac.qualification;
    
    // Status Badge
    const statusVal = document.getElementById('view-status-val');
    statusVal.textContent = fac.status.toUpperCase();
    statusVal.className = 'badge';
    if (fac.status === 'active') {
        statusVal.style.background = 'rgba(16,185,129,0.15)';
        statusVal.style.color = 'var(--green)';
        statusVal.style.border = '1px solid rgba(16,185,129,0.3)';
    } else if (fac.status === 'inactive') {
        statusVal.style.background = 'rgba(239,68,68,0.15)';
        statusVal.style.color = 'var(--red)';
        statusVal.style.border = '1px solid rgba(239,68,68,0.3)';
    } else {
        statusVal.style.background = 'rgba(245,158,11,0.15)';
        statusVal.style.color = 'var(--amber)';
        statusVal.style.border = '1px solid rgba(245,158,11,0.3)';
    }
    
    document.getElementById('view-bio-val').textContent = fac.bio || 'No biography details provided.';
    document.getElementById('view-modal').style.display = 'flex';
}
function closeViewModal() {
    document.getElementById('view-modal').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
