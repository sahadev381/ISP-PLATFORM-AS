            <div class="info-card">
                <h3><i class="fa fa-shield-alt"></i> Account Security</h3>
                <?php $is_locked = $conn->query("SELECT * FROM radcheck WHERE username='$username' AND attribute='Calling-Station-Id' LIMIT 1")->num_rows > 0; ?>
                <p>MAC Lock Status: <b style="color:<?= $is_locked?'#ef4444':'#10b981' ?>;"><?= $is_locked?'LOCKED':'UNLOCKED' ?></b></p>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="<?= $is_locked ? 'unlock_pppoe' : 'lock_pppoe' ?>">
                    <button type="submit" class="btn-action <?= $is_locked ? 'btn-danger' : 'btn-primary' ?>" style="width: 100%; justify-content: center;">
                        <?= $is_locked ? 'Unlock Account' : 'Lock to Current MAC' ?>
                    </button>
                </form>
            </div>

            <!-- FUP Usage Card -->
            <div class="info-card">
                <h3><i class="fa fa-chart-pie"></i> Monthly FUP Usage</h3>
                <?php
                    $limit = (float)($user['data_limit'] ?? 0);
                    $monthly = $conn->query("SELECT SUM(acctoutputoctets+acctinputoctets) as total FROM radacct WHERE username='$username' AND MONTH(acctstarttime)=MONTH(NOW()) AND YEAR(acctstarttime)=YEAR(NOW())")->fetch_assoc();
                    $used = (float)($monthly['total'] ?? 0);
                    $percent = ($limit > 0) ? min(100, round(($used / $limit) * 100, 1)) : 0;
                    $color = ($percent > 90) ? '#ef4444' : (($percent > 70) ? '#f59e0b' : '#10b981');
                ?>
                <div class="detail-row">
                    <label>Data Limit</label>
                    <span><?= $limit > 0 ? formatBytes($limit) : 'Unlimited' ?></span>
                </div>
                <div class="detail-row">
                    <label>Used (This Month)</label>
                    <span><?= formatBytes($used) ?></span>
                </div>
                <div class="detail-row">
                    <label>Downloaded</label>
                    <span><?= formatBytes($conn->query("SELECT SUM(acctoutputoctets) as d FROM radacct WHERE username='$username' AND MONTH(acctstarttime)=MONTH(NOW()) AND YEAR(acctstarttime)=YEAR(NOW())")->fetch_assoc()['d'] ?? 0) ?></span>
                </div>
                <div class="detail-row">
                    <label>Uploaded</label>
                    <span><?= formatBytes($conn->query("SELECT SUM(acctinputoctets) as u FROM radacct WHERE username='$username' AND MONTH(acctstarttime)=MONTH(NOW()) AND YEAR(acctstarttime)=YEAR(NOW())")->fetch_assoc()['u'] ?? 0) ?></span>
                </div>
                <?php if($limit > 0): ?>
                <div class="usage-bar-bg">
                    <div class="usage-bar-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?= $percent ?>% used</span>
                    <form method="POST" onsubmit="return confirm('Reset FUP usage for this user?');">
                        <input type="hidden" name="action" value="reset_fup">
                        <button type="submit" class="btn-action" style="padding: 4px 10px; font-size: 11px; background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2;">
                            <i class="fa fa-rotate-left"></i> Reset FUP
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- FTTH Inventory Card -->
            <div class="info-card">
                <h3><i class="fa fa-network-wired"></i> FTTH & Inventory</h3>
                <div class="detail-row"><label>OLT Name/ID</label><span><?= htmlspecialchars($user['olt'] ?: '-') ?></span></div>
                <div class="detail-row"><label>OLT Port</label><span><?= $user['olt_port'] ?: '-' ?></span></div>
                <div class="detail-row"><label>Master Box</label><span><?= htmlspecialchars($user['master_box'] ?: '-') ?></span></div>
                <div class="detail-row"><label>DB Name/ID</label><span><?= htmlspecialchars($user['db_box'] ?: '-') ?></span></div>
                <div class="detail-row"><label>DB Port</label><span><?= $user['db_port'] ?: '-' ?></span></div>
            </div>

            <!-- Map Card -->
            <div class="info-card">
                <h3><i class="fa fa-map-location-dot"></i> Installation Location</h3>
                <?php if (!empty($user['lat']) && !empty($user['lng'])): ?>
                    <div id="userMap"></div>
                <?php else: ?>
                    <div style="height: 200px; background: #f8fafc; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #94a3b8; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <i class="fa fa-map-marked-alt" style="font-size: 30px;"></i>
                        <span>No coordinates set</span>
                        <a href="user_edit.php?user=<?= $username ?>" class="btn-action btn-primary" style="padding: 5px 12px; font-size: 11px;">Add Location</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB: Usage History -->
    <div id="usage_history" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-history"></i> Monthly Data Usage (Last 12 Months)</h3>
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Download</th>
                        <th>Upload</th>
                        <th>Total Usage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $usage_history = $conn->query("
                        SELECT
                            DATE_FORMAT(acctstarttime, '%Y-%M') as month,
                            SUM(acctoutputoctets) as download,
