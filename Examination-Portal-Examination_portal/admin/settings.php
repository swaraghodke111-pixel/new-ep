<?php
// admin/settings.php — Admin Settings dashboard
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_role('admin');

$error = '';
$success = '';
global $pdo;

// Handle Update Settings POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = $_POST['settings'] ?? [];
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($settings as $key => $val) {
            $stmt->execute([$val, $key]);
        }
        $pdo->commit();
        $success = 'System configurations updated successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update system configurations: ' . $e->getMessage();
    }
}

// Fetch all system settings
$stmt = $pdo->query("SELECT * FROM system_settings");
$settings_list = $stmt->fetchAll();

$page_title = 'System Settings';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>⚙️ System Configurations</h2>
    <p>Adjust system features, maintenance controls, SMTP server simulations, and defaults.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= h($success) ?></div>
<?php endif; ?>

<div class="card" style="max-width:800px;">
    <div class="card-header">
        <h3>🔧 Portal Global Variables</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php foreach ($settings_list as $s): ?>
                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label" style="font-weight:600; text-transform:none;">
                        🔑 <?= h(str_replace('_', ' ', strtoupper($s['setting_key']))) ?>
                    </label>
                    
                    <?php if ($s['setting_key'] === 'allow_registration' || $s['setting_key'] === 'maintenance_mode' || $s['setting_key'] === 'smtp_server_simulation'): ?>
                        <select name="settings[<?= h($s['setting_key']) ?>]" class="form-control">
                            <option value="1" <?= $s['setting_value'] === '1' ? 'selected' : '' ?>>Enabled / True</option>
                            <option value="0" <?= $s['setting_value'] === '0' ? 'selected' : '' ?>>Disabled / False</option>
                        </select>
                    <?php else: ?>
                        <input type="text" name="settings[<?= h($s['setting_key']) ?>]" class="form-control" value="<?= h($s['setting_value']) ?>">
                    <?php endif; ?>
                    
                    <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-top:4px;">
                        System identifier: <code><?= h($s['setting_key']) ?></code>
                    </span>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top:12px;">Save Configurations</button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
