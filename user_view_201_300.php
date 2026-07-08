    .info-card h3 { margin: 0 0 20px; font-size: 16px; color: #1e293b; display: flex; align-items: center; gap: 10px; }

    .detail-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
    .detail-row label { color: #64748b; font-weight: 500; }
    .detail-row span { color: #1e293b; font-weight: 600; }

    .session-card { background: #1e293b; color: #fff; border-radius: 15px; padding: 25px; }
    .btn-action { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; cursor: pointer; text-decoration: none; }
    .btn-primary { background: #3b82f6; color: #fff; }
    .btn-danger { background: #fee2e2; color: #ef4444; }

    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px 15px; background: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; }
    td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }

    /* Map Styles */
    #userMap { height: 200px; width: 100%; border-radius: 10px; margin-top: 15px; z-index: 1; border: 1px solid #e2e8f0; }

    /* FUP Styles */
    .usage-bar-bg { background: #e2e8f0; height: 10px; border-radius: 5px; margin: 10px 0; overflow: hidden; }
    .usage-bar-fill { height: 100%; border-radius: 5px; transition: width 0.3s; }
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="profile-container">

    <?php if($success_msg): ?>
        <div style="background: #dcfce7; color: #16a34a; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0;"><i class="fa fa-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="profile-header">
        <div class="profile-id">
            <div class="profile-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <div class="profile-name">
                <h1 style="display:flex; align-items:center; gap:10px;">
                    <?= htmlspecialchars($user['full_name']) ?>
                    <a href="map.php?user=<?= $username ?>" title="View on Map" style="font-size:18px; color:#3b82f6;"><i class="fa fa-map-location-dot"></i></a>
                </h1>
                <p>@<?= htmlspecialchars($user['username']) ?> &bull; <?= htmlspecialchars($user['phone']) ?></p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="recharge.php?user=<?= urlencode($user['username']) ?>" class="btn-action btn-primary"><i class="fa fa-bolt"></i> Renew Account</a>
            <form method="POST" onsubmit="return confirm('Disconnect session?')">
                <input type="hidden" name="action" value="disconnect">
                <button type="submit" class="btn-action btn-danger"><i class="fa fa-power-off"></i> Disconnect</button>
            </form>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="nav-tabs">
        <button class="nav-tab active" onclick="showTab('overview', this)"><i class="fa fa-th-large"></i> Overview</button>
        <button class="nav-tab" onclick="showTab('usage_history', this)"><i class="fa fa-chart-area"></i> Usage History</button>
        <button class="nav-tab" onclick="window.open('map.php?user=<?= $username ?>', '_blank')"><i class="fa fa-map-location-dot"></i> Map View</button>
        <button class="nav-tab" onclick="showTab('livegraph', this)"><i class="fa fa-chart-line"></i> Live Graph</button>
        <button class="nav-tab" onclick="showTab('tickets', this)"><i class="fa fa-headset"></i> Tickets</button>
        <button class="nav-tab" onclick="showTab('invoices', this)"><i class="fa fa-file-invoice"></i> Invoices</button>
        <button class="nav-tab" onclick="showTab('grace', this)"><i class="fa fa-gift"></i> Add Grace</button>
        <button class="nav-tab" onclick="showTab('optical_power', this)"><i class="fa fa-signal"></i> Optical Power</button>
        <button class="nav-tab" onclick="showTab('acspush', this)"><i class="fa fa-microchip"></i> ACS Push</button>
        <button class="nav-tab" onclick="showTab('authlog', this)"><i class="fa fa-history"></i> Auth Log</button>
    </div>

    <!-- TAB: Overview -->
    <div id="overview" class="tab-content active">
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fa fa-info-circle"></i> Basic Info</h3>
                <div class="detail-row"><label>Username</label><span><?= $user['username'] ?></span></div>
                <div class="detail-row"><label>Status</label><span style="color:<?= $user['status']=='active'?'#10b981':'#ef4444' ?>;"><?= ucfirst($user['status']) ?></span></div>
                <div class="detail-row"><label>Plan</label><span><?= htmlspecialchars($user['plan_name']) ?></span></div>
                <div class="detail-row"><label>Expiry</label><span style="color: #ef4444;"><?= $user['expiry'] ?></span></div>
                <div class="detail-row"><label>Address</label><span><?= $user['address'] ?: '-' ?></span></div>
            </div>

            <div class="session-card">
                <h3><i class="fa fa-signal"></i> Active Session</h3>
                <?php if($session): ?>
                    <div style="font-size: 28px; font-weight: 700; margin-bottom: 10px;"><?= gmdate("H:i:s", $session['duration']) ?></div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                            <small style="opacity: 0.6;">User IP</small><br>
                            <b style="font-size: 16px;"><?= $session['framedipaddress'] ?? '-' ?></b>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                            <small style="opacity: 0.6;">User MAC</small><br>
                            <b style="font-size: 16px;"><?= $session['callingstationid'] ?? '-' ?></b>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                        <div><small style="opacity: 0.6;">Download</small><br><b><?= formatBytes($session['acctoutputoctets']) ?></b></div>
                        <div><small style="opacity: 0.6;">Upload</small><br><b><?= formatBytes($session['acctinputoctets']) ?></b></div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; opacity: 0.5;">No active session.</p>
                <?php endif; ?>
            </div>
