<?php
/**
 * Edit-gateway form fragment, loaded into the modal on billing/gateways.php.
 *
 * This endpoint used to be unauthenticated and echoed the gateway's
 * api_key straight into an <input value="...">, so anyone who could
 * reach the URL could read the payment credentials by requesting
 * ?id=1. The schema says as much next to the column:
 * "secret - never render this".
 *
 * It also read $gateway['name'], ['status'] and ['webhook_url'], none
 * of which exist on payment_gateways, and posted field names that
 * billing/gateways.php does not handle - so saving never worked.
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Same restriction as the page that embeds this fragment.
require_role('superadmin');

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit('Invalid gateway ID');
}

$gateway = db_one(
    $conn,
    // Deliberately not SELECT * : api_key and api_secret must not travel
    // to the browser at all, not even to be discarded there.
    "SELECT id, gateway_name, display_name, merchant_id, public_key,
            is_active, is_test_mode,
            (api_key IS NOT NULL AND api_key <> '')       AS has_api_key,
            (api_secret IS NOT NULL AND api_secret <> '') AS has_api_secret
       FROM payment_gateways WHERE id = ?",
    [$id]
);

if (!$gateway) {
    http_response_code(404);
    exit('Gateway not found');
}
?>
<form method="POST" action="../../billing/gateways.php">
<?= csrf_field() ?>
    <input type="hidden" name="action" value="update_gateway">
    <input type="hidden" name="gateway_id" value="<?= (int) $gateway['id'] ?>">

    <div class="modal-header">
        <h5 class="modal-title">Edit <?= e($gateway['display_name'] ?: $gateway['gateway_name']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Gateway</label>
            <input type="text" name="gateway_name" class="form-control"
                   value="<?= e($gateway['gateway_name']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Display name</label>
            <input type="text" name="display_name" class="form-control"
                   value="<?= e($gateway['display_name']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">API key</label>
            <input type="password" name="api_key" class="form-control" autocomplete="new-password"
                   placeholder="<?= $gateway['has_api_key'] ? 'Set - leave blank to keep it' : 'Not set' ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">API secret</label>
            <input type="password" name="api_secret" class="form-control" autocomplete="new-password"
                   placeholder="<?= $gateway['has_api_secret'] ? 'Set - leave blank to keep it' : 'Not set' ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Merchant ID</label>
            <input type="text" name="merchant_id" class="form-control"
                   value="<?= e($gateway['merchant_id']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Public key</label>
            <textarea name="public_key" class="form-control" rows="2"><?= e($gateway['public_key']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-select">
                <option value="1" <?= $gateway['is_active'] ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $gateway['is_active'] ? '' : 'selected' ?>>Inactive</option>
            </select>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="is_test_mode"
                   name="is_test_mode" value="1" <?= $gateway['is_test_mode'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_test_mode">Test mode</label>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
    </div>
</form>
