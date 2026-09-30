<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Global platform configuration - superadmin only.
require_role('superadmin');

$page_title = "System Configuration";
$active = "Config";

$success = '';
$error = '';

// Handle form submission
if (isset($_POST['save'])) {
    csrf_check();

    $upload_dir = __DIR__ . '/uploads/';

    // Logo Upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        // The extension alone is attacker-chosen, so also confirm the bytes
        // really are an image before moving the file into a web-served dir.
        $info = @getimagesize($_FILES['logo']['tmp_name']);
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) {
            $error = "Invalid logo format. Only PNG/JPG/GIF allowed.";
        } elseif ($info === false) {
            $error = "Logo is not a valid image file.";
        } else {
            $filename = 'logo.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) {
                db_exec($conn, "UPDATE system_config SET logo = ? ORDER BY id LIMIT 1", [$filename]);
                $success = "Logo uploaded successfully!";
            } else {
                $error = "Could not save the uploaded logo.";
            }
        }
    }

    // Favicon Upload
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
        $info = @getimagesize($_FILES['favicon']['tmp_name']);
        if (!in_array($ext, ['png', 'ico'], true)) {
            $error = "Invalid favicon format. Only PNG/ICO allowed.";
        } elseif ($ext === 'png' && $info === false) {
            $error = "Favicon is not a valid image file.";
        } else {
            $filename = 'favicon.' . $ext;
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_dir . $filename)) {
                db_exec($conn, "UPDATE system_config SET favicon = ? ORDER BY id LIMIT 1", [$filename]);
                $success = "Favicon uploaded successfully!";
            } else {
                $error = "Could not save the uploaded favicon.";
            }
        }
    }
    
    // Update settings
    $settings_to_save = [
        'session_timeout' => $_POST['session_timeout'] ?? '30',
        'session_idle_timeout' => $_POST['session_idle_timeout'] ?? '15',
        'max_login_attempts' => $_POST['max_login_attempts'] ?? '5',
        'lockout_duration' => $_POST['lockout_duration'] ?? '15',
        'expire_block_time' => $_POST['expire_block_time'] ?? '08:00',
        'tax_tsc_pct' => $_POST['tax_tsc_pct'] ?? '13.00',
        'tax_vat_pct' => $_POST['tax_vat_pct'] ?? '13.00',
        'theme' => $_POST['theme'] ?? 'light'
    ];
    
    foreach ($settings_to_save as $key => $value) {
        db_exec($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $value]);
    }
    
    if (empty($error)) {
        $success = "Settings saved successfully!";
    }
}

// Get current settings
$settings = [];
foreach (db_all($conn, "SELECT setting_key, setting_value FROM system_settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get logo/favicon from system_config table
$config_data = db_one($conn, "SELECT * FROM system_config LIMIT 1") ?? [];
$logo = $config_data['logo'] ?? '';
$favicon = $config_data['favicon'] ?? '';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main">
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-cog"></i> System Configuration</h3>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <!-- Branding Section -->
            <h4 style="margin: 25px 0 15px; color: var(--text-main);">
                <i class="fa fa-image"></i> Branding
            </h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label">Company Logo</label>
                    <?php if(!empty($logo) && file_exists('uploads/'.$logo)): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="uploads/<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-height:60px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpg,image/jpeg,image/gif">
                    <small style="color: var(--text-muted);">Recommended: 200x60px, PNG/JPG</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Favicon</label>
                    <?php if(!empty($favicon) && file_exists('uploads/'.$favicon)): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="uploads/<?= htmlspecialchars($favicon) ?>" alt="Favicon" style="max-height:40px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="favicon" class="form-control" accept="image/png,image/x-icon">
                    <small style="color: var(--text-muted);">Recommended: 32x32px, PNG/ICO</small>
                </div>
            </div>
            
            <!-- Security Settings -->
            <h4 style="margin: 30px 0 15px; color: var(--text-main);">
                <i class="fa fa-shield-alt"></i> Security Settings
            </h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Session Timeout (minutes)</label>
                    <input type="number" name="session_timeout" class="form-control" value="<?= htmlspecialchars($settings['session_timeout'] ?? '30') ?>" min="5" max="240">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Idle Timeout (minutes)</label>
                    <input type="number" name="session_idle_timeout" class="form-control" value="<?= htmlspecialchars($settings['session_idle_timeout'] ?? '15') ?>" min="5" max="120">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Max Login Attempts</label>
                    <input type="number" name="max_login_attempts" class="form-control" value="<?= htmlspecialchars($settings['max_login_attempts'] ?? '5') ?>" min="3" max="10">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Lockout Duration (minutes)</label>
                    <input type="number" name="lockout_duration" class="form-control" value="<?= htmlspecialchars($settings['lockout_duration'] ?? '15') ?>" min="5" max="60">
                </div>
            </div>
            
            <!-- System Settings -->
            <h4 style="margin: 30px 0 15px; color: var(--text-main);">
                <i class="fa fa-sliders-h"></i> System Settings
            </h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Default Theme</label>
                    <select name="theme" class="form-control">
                        <option value="light" <?= ($settings['theme'] ?? 'light') == 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($settings['theme'] ?? '') == 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Expired User Block Time</label>
                    <input type="time" name="expire_block_time" class="form-control" value="<?= htmlspecialchars($settings['expire_block_time'] ?? '08:00') ?>">
                    <small style="color: var(--text-muted);">Time when expired users get blocked</small>
                </div>
            </div>

            <!-- Taxation Settings -->
            <h4 style="margin: 30px 0 15px; color: var(--text-main);">
                <i class="fa fa-percent" style="color: #8b5cf6;"></i> Taxation Settings (compliance)
            </h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label">TSC Percentage (%)</label>
                    <input type="number" step="0.01" name="tax_tsc_pct" class="form-control" value="<?= htmlspecialchars($settings['tax_tsc_pct'] ?? '13.00') ?>">
                    <small style="color: var(--text-muted);">Standard Nepalese TSC is 13%</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">VAT Percentage (%)</label>
                    <input type="number" step="0.01" name="tax_vat_pct" class="form-control" value="<?= htmlspecialchars($settings['tax_vat_pct'] ?? '13.00') ?>">
                    <small style="color: var(--text-muted);">Standard Value Added Tax is 13%</small>
                </div>
            </div>
            
            <button type="submit" name="save" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fa fa-save"></i> Save Configuration
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
