<?php
require_once 'config.php';
require_once 'includes/auth.php';

$page_title = "Notification Settings";
$active = "settings";

if (isset($_POST['save'])) {
    $settings = [
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_user' => $_POST['smtp_user'],
        'smtp_pass' => $_POST['smtp_pass'],
        'smtp_from_email' => $_POST['smtp_from_email'],
        'smtp_from_name' => $_POST['smtp_from_name'],
        'sms_api_key' => $_POST['sms_api_key'],
        'sms_api_url' => $_POST['sms_api_url'],
        'sms_sender_id' => $_POST['sms_sender_id'],
        'enable_email' => isset($_POST['enable_email']) ? '1' : '0',
        'enable_sms' => isset($_POST['enable_sms']) ? '1' : '0'
    ];
    
    foreach ($settings as $key => $value) {
        $conn->query("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('$key', '$value')
            ON DUPLICATE KEY UPDATE setting_value = '$value'
        ");
    }
    
    $success = "Settings saved successfully!";
}

// Get current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
    <div class="table-box">
        <div class="table-header">
            <h3><i class="fa fa-bell"></i> Notification Settings</h3>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Email Settings -->
            <h4 style="margin: 20px 0 15px; color: var(--text-main);">
                <i class="fa fa-envelope"></i> Email Settings (SMTP)
            </h4>
            
            <div class="form-group">
                <label class="form-label">Enable Email Notifications</label>
                <input type="checkbox" name="enable_email" <?= ($settings['enable_email'] ?? '1') ? 'checked' : '' ?>>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input type="text" name="smtp_port" class="form-control" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" placeholder="587">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">From Email</label>
                    <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>" placeholder="noreply@yourisp.com">
                </div>
                <div class="form-group">
                    <label class="form-label">From Name</label>
                    <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? 'ISP System') ?>">
                </div>
            </div>
            
            <!-- SMS Settings -->
            <h4 style="margin: 30px 0 15px; color: var(--text-main);">
                <i class="fa fa-comment-sms"></i> SMS Settings
            </h4>
            
            <div class="form-group">
                <label class="form-label">Enable SMS Notifications</label>
                <input type="checkbox" name="enable_sms" <?= ($settings['enable_sms'] ?? '1') ? 'checked' : '' ?>>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">SMS API Key</label>
                    <input type="text" name="sms_api_key" class="form-control" value="<?= htmlspecialchars($settings['sms_api_key'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">SMS Sender ID</label>
                    <input type="text" name="sms_sender_id" class="form-control" value="<?= htmlspecialchars($settings['sms_sender_id'] ?? 'ISP') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">SMS API URL</label>
                <input type="url" name="sms_api_url" class="form-control" value="<?= htmlspecialchars($settings['sms_api_url'] ?? '') ?>" placeholder="https://api.smsprovider.com/send">
            </div>
            
            <button type="submit" name="save" class="btn btn-primary">
                <i class="fa fa-save"></i> Save Settings
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
