<?php
header('Content-Type: text/html');
include_once '../config.php';

$query = $_GET['q'] ?? '';
$query = $conn->real_escape_string($query);

if (strlen($query) < 2) {
    exit;
}

$html = '';

// Search customers
$customers = $conn->query("SELECT id, username, full_name, phone, status FROM customers WHERE username LIKE '%$query%' OR full_name LIKE '%$query%' OR phone LIKE '%$query%' LIMIT 5");

if ($customers->num_rows > 0) {
    $html .= '<div style="padding: 8px 12px; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Customers</div>';
    while ($c = $customers->fetch_assoc()) {
        $statusColor = $c['status'] == 'active' ? '#10b981' : '#94a3b8';
        $html .= '<a href="user_view.php?id=' . $c['id'] . '" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; text-decoration: none; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
            <div style="width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #64748b;">' . strtoupper(substr($c['full_name'] ?: $c['username'], 0, 1)) . '</div>
            <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600;">' . htmlspecialchars($c['full_name'] ?: $c['username']) . '</div>
                <div style="font-size: 11px; color: #94a3b8;">' . htmlspecialchars($c['phone'] ?: $c['username']) . '</div>
            </div>
            <span style="width: 8px; height: 8px; border-radius: 50%; background: ' . $statusColor . ';"></span>
        </a>';
    }
}

// Search tickets
$tickets = $conn->query("SELECT id, subject, status, created_at FROM tickets WHERE subject LIKE '%$query%' OR id LIKE '%$query%' LIMIT 3");

if ($tickets->num_rows > 0) {
    $html .= '<div style="padding: 8px 12px; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Tickets</div>';
    while ($t = $tickets->fetch_assoc()) {
        $statusColor = $t['status'] == 'Open' ? '#f59e0b' : ($t['status'] == 'Resolved' ? '#10b981' : '#94a3b8');
        $html .= '<a href="ticket_view.php?id=' . $t['id'] . '" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; text-decoration: none; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
            <div style="width: 32px; height: 32px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="fa fa-ticket" style="color: #f59e0b; font-size: 12px;"></i></div>
            <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600;">#' . $t['id'] . ' ' . htmlspecialchars(substr($t['subject'], 0, 25)) . '</div>
                <div style="font-size: 11px; color: #94a3b8;">' . date('M d, Y', strtotime($t['created_at'])) . '</div>
            </div>
            <span class="badge" style="background: ' . $statusColor . '; color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px;">' . $t['status'] . '</span>
        </a>';
    }
}

// Search leads
$leads = $conn->query("SELECT id, name, phone, status FROM leads WHERE name LIKE '%$query%' OR phone LIKE '%$query%' LIMIT 3");

if ($leads->num_rows > 0) {
    $html .= '<div style="padding: 8px 12px; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Leads</div>';
    while ($l = $leads->fetch_assoc()) {
        $html .= '<a href="leads.php?action=view&id=' . $l['id'] . '" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; text-decoration: none; color: #1e293b;">
            <div style="width: 32px; height: 32px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #3b82f6;">' . strtoupper(substr($l['name'], 0, 1)) . '</div>
            <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600;">' . htmlspecialchars($l['name']) . '</div>
                <div style="font-size: 11px; color: #94a3b8;">' . htmlspecialchars($l['phone']) . '</div>
            </div>
        </a>';
    }
}

if ($html) {
    echo $html;
}
