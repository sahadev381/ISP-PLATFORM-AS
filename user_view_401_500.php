                            SUM(acctinputoctets) as upload
                        FROM radacct
                        WHERE username = '$username'
                        GROUP BY month
                        ORDER BY acctstarttime DESC
                        LIMIT 12
                    ");
                    if($usage_history->num_rows > 0):
                        while($uh = $usage_history->fetch_assoc()): ?>
                            <tr>
                                <td><b><?= $uh['month'] ?></b></td>
                                <td><?= formatBytes($uh['download']) ?></td>
                                <td><?= formatBytes($uh['upload']) ?></td>
                                <td><span class="badge" style="background:#eff6ff; color:#3b82f6;"><?= formatBytes($uh['download'] + $uh['upload']) ?></span></td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">No usage history found for this user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Live Graph -->
    <div id="livegraph" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-chart-line"></i> Real-time Usage (60s)</h3>
            <canvas id="liveChart" height="100"></canvas>
        </div>
    </div>

    <!-- TAB: Tickets -->
    <div id="tickets" class="tab-content">
        <div class="info-card">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <h3><i class="fa fa-headset"></i> Support Tickets</h3>
                <a href="ticket_new.php?user=<?= $username ?>" class="btn-action btn-primary" style="padding: 5px 12px; font-size: 12px;"><i class="fa fa-plus"></i> New Ticket</a>
            </div>
            <table>
                <thead><tr><th>ID</th><th>Subject</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php
                    $tks = $conn->query("SELECT * FROM tickets WHERE customer_id = (SELECT id FROM customers WHERE username='$username') ORDER BY id DESC");
                    while($tk = $tks->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $tk['id'] ?></td>
                            <td><a href="ticket_view.php?id=<?= $tk['id'] ?>"><?= htmlspecialchars($tk['subject']) ?></a></td>
                            <td><?= $tk['priority'] ?></td>
                            <td><?= $tk['status'] ?></td>
                            <td><?= date('M d, Y', strtotime($tk['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Invoices -->
    <div id="invoices" class="tab-content">
        <div class="info-card">
            <h3><i class="fa fa-file-invoice"></i> Billing History</h3>
            <table>
                <thead><tr><th>ID</th><th>Amount</th><th>Months</th><th>Expiry</th><th>Date</th></tr></thead>
                <tbody>
                    <?php
                    $invs = $conn->query("SELECT * FROM invoices WHERE username='$username' ORDER BY id DESC");
                    while($inv = $invs->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $inv['id'] ?></td>
                            <td>NPR <?= number_format($inv['amount'], 2) ?></td>
                            <td><?= $inv['months'] ?></td>
                            <td><?= $inv['expiry_date'] ?></td>
                            <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Grace Period -->
    <div id="grace" class="tab-content">
        <div class="info-card" style="max-width: 500px; margin: 0 auto;">
            <h3><i class="fa fa-gift"></i> Add Grace Period</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_grace">
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:8px; font-size:13px; font-weight:600; color:#64748b;">Days to Extend</label>
                    <select name="grace_days" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                        <option value="1">1 Day</option>
                        <option value="2">2 Days</option>
                        <option value="3" selected>3 Days</option>
                        <option value="7">7 Days</option>
                    </select>
                </div>
                <button type="submit" class="btn-action btn-primary" style="width:100%; justify-content:center; padding:15px;">Extend Subscription Now</button>
            </form>
        </div>
    </div>
