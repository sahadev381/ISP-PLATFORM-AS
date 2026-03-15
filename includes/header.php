<?php
if(!isset($base_path)) {
    $base_path = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?? 'ISP System' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/theme.css">
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('show');
    document.querySelector('.sidebar-overlay').classList.toggle('show');
}

function toggleHotspotMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('hotspot-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleHotspotMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleNetworkMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('network-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleNetworkMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleCustomerMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('customer-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleCustomerMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleTicketMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('ticket-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleTicketMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleReportMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('report-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleReportMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleLeadMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('lead-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleLeadMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleOperationMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('operation-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleOperationMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleSettingsMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('settings-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleSettingsMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

function toggleFinanceMenu() {
    var toggleBtns = document.querySelectorAll('.menu-toggle-item');
    var submenu = document.getElementById('finance-submenu');
    
    for (var i = 0; i < toggleBtns.length; i++) {
        if (toggleBtns[i].getAttribute('onclick') === 'toggleFinanceMenu()') {
            toggleBtns[i].classList.toggle('expanded');
            break;
        }
    }
    
    if(submenu) submenu.classList.toggle('show');
}

// Auto-expand menus based on current page
document.addEventListener('DOMContentLoaded', function() {
    var currentPage = window.location.href;
    
    // Expand Network menu
    if (currentPage.includes('nas.php') || 
        currentPage.includes('olt_dashboard.php') || 
        currentPage.includes('mikrotik_dashboard.php') ||
        currentPage.includes('network_observability.php') ||
        currentPage.includes('map.php') ||
        currentPage.includes('faults.php') ||
        currentPage.includes('noc_dashboard.php') ||
        currentPage.includes('genieacs_devices.php')) {
        var submenu = document.getElementById('network-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleNetworkMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Hotspot Portal menu
    if (currentPage.includes('hotspot/admin/')) {
        var submenu = document.getElementById('hotspot-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleHotspotMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Settings menu
    if (currentPage.includes('admin.php') || 
        currentPage.includes('branches.php') || 
        currentPage.includes('plans.php') ||
        currentPage.includes('system_config.php') ||
        currentPage.includes('notification_settings.php') ||
        currentPage.includes('roles.php')) {
        var submenu = document.getElementById('settings-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleSettingsMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Customers menu
    if (currentPage.includes('users.php') || 
        currentPage.includes('online_users.php') || 
        currentPage.includes('add_user.php') ||
        currentPage.includes('pppoe_users.php') ||
        currentPage.includes('hotspot_users.php') ||
        currentPage.includes('pending_users.php') ||
        currentPage.includes('expired_users.php')) {
        var submenu = document.getElementById('customer-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleCustomerMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Tickets menu
    if (currentPage.includes('tickets.php')) {
        var submenu = document.getElementById('ticket-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleTicketMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Reports menu
    if (currentPage.includes('report/') || currentPage.includes('reports/') || currentPage.includes('admin_logs.php') || currentPage.includes('system_logs.php') || currentPage.includes('advanced_reports.php')) {
        var submenu = document.getElementById('report-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleReportMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Leads menu
    if (currentPage.includes('leads.php')) {
        var submenu = document.getElementById('lead-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleLeadMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Operations menu
    if (currentPage.includes('work_diary.php') || 
        currentPage.includes('inventory.php') ||
        currentPage.includes('faults.php') ||
        currentPage.includes('sms_outbox.php')) {
        var submenu = document.getElementById('operation-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleOperationMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
    
    // Expand Finance menu
    if (currentPage.includes('financial.php') || 
        currentPage.includes('advanced_reports.php') || 
        currentPage.includes('accounting.php')) {
        var submenu = document.getElementById('finance-submenu');
        var toggleBtns = document.querySelectorAll('.menu-toggle-item');
        if (submenu) submenu.classList.add('show');
        for (var i = 0; i < toggleBtns.length; i++) {
            if (toggleBtns[i].getAttribute('onclick') === 'toggleFinanceMenu()') {
                toggleBtns[i].classList.add('expanded');
                break;
            }
        }
    }
});
</script>
</head>
<body>
    <div class="layout">
