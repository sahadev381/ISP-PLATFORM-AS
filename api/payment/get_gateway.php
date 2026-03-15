<?php
header('Content-Type: text/html');
include_once '../../config.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo 'Invalid gateway ID';
    exit;
}

$gateway = $conn->query("SELECT * FROM payment_gateways WHERE id = $id")->fetch_assoc();

if (!$gateway) {
    echo 'Gateway not found';
    exit;
}
?>
<form method="POST">
    <div class="modal-header">
        <h5>Edit Gateway</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" name="action" value="update_gateway">
        <input type="hidden" name="gateway_id" value="<?= $gateway['id'] ?>">
        
        <div class="mb-3">
            <label>Gateway Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($gateway['name']) ?>" required>
        </div>
        
        <div class="mb-3">
            <label>API Key</label>
            <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($gateway['api_key']) ?>">
        </div>
        
        <div class="mb-3">
            <label>API Secret</label>
            <input type="password" name="api_secret" class="form-control" placeholder="Leave blank to keep current">
        </div>
        
        <div class="mb-3">
            <label>Merchant ID</label>
            <input type="text" name="merchant_id" class="form-control" value="<?= htmlspecialchars($gateway['merchant_id']) ?>">
        </div>
        
        <div class="mb-3">
            <label>Public Key</label>
            <textarea name="public_key" class="form-control" rows="2"><?= htmlspecialchars($gateway['public_key']) ?></textarea>
        </div>
        
        <div class="mb-3">
            <label>Webhook URL</label>
            <input type="text" name="webhook_url" class="form-control" value="<?= htmlspecialchars($gateway['webhook_url']) ?>">
        </div>
        
        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <option value="active" <?= $gateway['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $gateway['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>
