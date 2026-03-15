<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Network Faults & AI Localization";
$active = "faults";

// Resolve Fault Logic
if (isset($_GET['resolve'])) {
    $id = intval($_GET['resolve']);
    $conn->query("UPDATE network_faults SET is_resolved = 1 WHERE id = $id");
    header("Location: faults.php?msg=resolved");
    exit;
}

$faults = $conn->query("
    SELECT f.*, n.name as node_name 
    FROM network_faults f
    LEFT JOIN ftth_nodes n ON f.node_id = n.id
    WHERE f.is_resolved = 0
    ORDER BY f.created_at DESC
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 20px;">
    <div class="table-box" style="background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3><i class="fa fa-triangle-exclamation" style="color:#ef4444;"></i> Active Network Faults</h3>
            <span class="badge" style="background: #fee2e2; color: #ef4444; padding: 8px 15px; border-radius: 8px; font-weight: 700;">
                AI-NOC Monitoring ACTIVE
            </span>
        </div>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f8fafc;">
                    <th style="padding: 15px;">Fault Type</th>
                    <th style="padding: 15px;">Origin / Segment</th>
                    <th style="padding: 15px;">Severity</th>
                    <th style="padding: 15px;">Predicted Location</th>
                    <th style="padding: 15px;">Time</th>
                    <th style="padding: 15px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($faults->num_rows > 0): ?>
                    <?php while($f = $faults->fetch_assoc()): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px;">
                            <b style="color: #e11d48;"><?= str_replace('_', ' ', $f['fault_type']) ?></b>
                        </td>
                        <td style="padding: 15px;"><?= $f['node_name'] ?: 'Backbone' ?></td>
                        <td style="padding: 15px;">
                            <span class="badge" style="background: <?= ($f['severity'] == 'CRITICAL') ? '#ef4444' : '#f59e0b' ?>; color: #fff;">
                                <?= $f['severity'] ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <a href="map.php?lat=<?= $f['predicted_lat'] ?>&lng=<?= $f['predicted_lng'] ?>" class="btn btn-sm" style="background: #eff6ff; color: #3b82f6; text-decoration: none;">
                                <i class="fa fa-location-dot"></i> View on GIS
                            </a>
                        </td>
                        <td style="padding: 15px; font-size: 12px; color: #64748b;">
                            <?= date('M d, h:i A', strtotime($f['created_at'])) ?>
                        </div>
                        <td style="padding: 15px;">
                            <a href="?resolve=<?= $f['id'] ?>" class="btn btn-sm" style="background: #dcfce7; color: #16a34a; text-decoration: none;">Resolve</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 50px; color: #94a3b8;">All clear! No active network faults.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
