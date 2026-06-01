
    <!-- TAB: Optical Power -->
    <div id="optical_power" class="tab-content" style="display:none;">
        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px;">
            <div class="info-card">
                <h3><i class="fa fa-tachometer-alt"></i> Current Status</h3>
                <div id="powerDisplay" style="text-align:center; padding:20px 0;">
                    <div style="font-size:32px; font-weight:700; color:#1e293b;" id="currentRx">-- dBm</div>
                    <div style="font-size:12px; color:#64748b; margin-top:5px;">Optical RX Power</div>
                    <div id="powerBadge" style="display:inline-block; margin-top:15px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; background:#f1f5f9; color:#64748b;">WAITING</div>
                </div>
                <hr style="margin:20px 0; border:0; border-top:1px solid #f1f5f9;">
                <div class="detail-row"><label>TX Power</label><span id="currentTx">-- dBm</span></div>
                <div class="detail-row"><label>Last Update</label><span id="lastPowerUpdate">Never</span></div>
                <button onclick="refreshPower()" id="refreshBtn" class="btn-action btn-primary" style="width:100%; justify-content:center; margin-top:20px;">
                    <i class="fa fa-sync"></i> Refresh Power
                </button>
            </div>
            <div class="info-card">
                <h3><i class="fa fa-chart-line"></i> Power History (Last 20)</h3>
                <div style="height: 300px; width:100%;">
                    <canvas id="powerChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: ACS Push -->
    <div id="acspush" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-microchip"></i> TR-069 Operations</h3>
            <?php if($genieacsDevice): ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                    <div>
                        <div class="detail-row"><label>Manufacturer</label><span><?= $genieacsDevice['InternetGatewayDevice']['DeviceInfo']['Manufacturer']['_value'] ?? 'N/A' ?></span></div>
                        <div class="detail-row"><label>Model</label><span><?= $genieacsDevice['InternetGatewayDevice']['DeviceInfo']['ProductClass']['_value'] ?? 'N/A' ?></span></div>
                        <button type="submit" name="reboot" class="btn-action btn-danger" style="margin-top:20px;"><i class="fa fa-power-off"></i> Reboot ONU</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="deviceId" value="<?= $deviceId ?>">
                        <div style="margin-bottom:10px;"><label style="font-size:12px; font-weight:600;">WiFi Name (SSID)</label><input type="text" name="ssid" value="<?= htmlspecialchars($user['wifi_ssid']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></div>
                        <div style="margin-bottom:15px;"><label style="font-size:12px; font-weight:600;">WiFi Password</label><input type="text" name="wifi_pass" value="<?= htmlspecialchars($user['wifi_password']) ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></div>
                        <button type="submit" name="setwifi" class="btn-action btn-primary" style="width:100%; justify-content:center;">Push to ONU</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="text-align:center; color:#94a3b8; padding:40px;">No ONU device connected to ACS for this user.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Auth Log -->
    <div id="authlog" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-history"></i> Recent Authentication Attempts</h3>
            <table>
                <thead><tr><th>#</th><th>Date</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                    <?php
                    $logs = $conn->query("SELECT * FROM radpostauth WHERE username='$username' ORDER BY authdate DESC LIMIT 20");
                    $i=1; while($log = $logs->fetch_assoc()):
                        $success = ($log['reply'] === 'Access-Accept');
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($log['authdate'])) ?></td>
                            <td><span class="badge <?= $success?'bg-success':'bg-danger' ?>"><?= $success?'Success':'Failed' ?></span></td>
                            <td><?= htmlspecialchars($log['reply']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var userMapObj;
var liveChart = null;

// Initialize Map
document.addEventListener("DOMContentLoaded", function() {
    <?php if (!empty($user['lat']) && !empty($user['lng'])): ?>
        var lat = <?= (float)$user['lat'] ?>;
        var lng = <?= (float)$user['lng'] ?>;
        var mapContainer = document.getElementById('userMap');
        if (mapContainer) {
            userMapObj = L.map('userMap').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(userMapObj);
            L.marker([lat, lng]).addTo(userMapObj).bindPopup("<b>Installation Location</b>").openPopup();
        }
    <?php endif; ?>
});

// Live Chart logic
function initLiveChart() {
    if(liveChart) return;
