<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Online Users";
$active = "online";

/* Fetch online users */
$q = $conn->query("
    SELECT radacct.username, radacct.acctstarttime, radacct.acctsessiontime,
           radacct.callingstationid,
           customers.plan_id, plans.name AS plan_name, plans.speed
    FROM radacct
    LEFT JOIN customers ON radacct.username = customers.username
    LEFT JOIN plans ON customers.plan_id = plans.id
    WHERE radacct.acctstoptime IS NULL
    ORDER BY radacct.acctstarttime DESC
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
$page_title = "Online Users";
?>

<div class="dashboard-container" style="padding: 20px;">

    <div id="msg-box" style="margin-bottom:10px;"></div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0; color: #1e293b; font-weight: 700;">Online Users</h3>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 13px;">Currently connected PPPoE users</p>
    </div>

    <div class="table-box">
        <table>
            <tr>
                <th>Username</th>
                <th>Plan</th>
                <th>Speed</th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th>Login Time</th>
                <th>Online Duration</th>
                <th>Action</th>
            </tr>
            <?php while($u = $q->fetch_assoc()):
                $login = strtotime($u['acctstarttime']);
                $duration = time() - $login;
                $hours = floor($duration/3600);
                $mins  = floor(($duration%3600)/60);
                $secs  = $duration%60;
            ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['plan_name']) ?></td>
                <td><?= htmlspecialchars($u['speed']) ?></td>
                <td><?= htmlspecialchars($u['callingstationid']) ?></td>
                <td><?= htmlspecialchars($u['callingstationid']) ?></td>
                <td><?= $u['acctstarttime'] ?></td>
                <td><?= sprintf("%02d:%02d:%02d", $hours, $mins, $secs) ?></td>
                <td>
                    <span class="badge active" id="status-<?= htmlspecialchars($u['username']) ?>"></span>
                    <button type="button"
                        class="btn danger disconnect-btn"
                        data-username="<?= htmlspecialchars($u['username']) ?>"
                        style="margin-top:5px;">
                        <i class="fa fa-power-off"></i> Disconnect
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$('.disconnect-btn').on('click', function(){
    if(!confirm('Disconnect this PPPoE user?')) return;

    var username = $(this).data('username');
    var btn = $(this);

    $.post('disconnect_user.php', {username: username}, function(data){
        var res = JSON.parse(data);
        if(res.success){
            $('#status-' + username).remove();
            btn.remove(); // remove button
            $('#msg-box').text(res.msg).css('color','#2ecc71').fadeIn().delay(2000).fadeOut();
        } else {
            $('#msg-box').text(res.msg).css('color','#e74c3c').fadeIn().delay(4000).fadeOut();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>

