<!-- Mobile Header -->
<div class="mobile-header">
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fa fa-bars"></i>
    </button>
    <span>ISP SYSTEM</span>
    <div style="width: 40px;"></div>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<?php if(!isset($base_path)) $base_path = ''; ?>

<div class="sidebar">
    <div class="sidebar-logo">
        <?php if(!empty($logo) && file_exists($base_path.'uploads/'.$logo)): ?>
            <img src="<?= $base_path ?>uploads/<?= htmlspecialchars($logo) ?>" alt="ISP SYSTEM">
        <?php else: ?>
            <h2>ISP SYSTEM</h2>
        <?php endif; ?>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?= $base_path ?>dashboard.php" class="<?=($active=='dashboard')?'active':''?>">
            <i class="fa fa-gauge-high"></i>Dashboard
        </a>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleCustomerMenu()">
            <i class="fa fa-users"></i>Customers <i class="fa fa-chevron-down float-end" id="customer-arrow"></i>
        </a>
        
        <div class="submenu" id="customer-submenu">
            <a href="<?= $base_path ?>users.php" class="<?=($active=='users')?'active':''?>">
                <i class="fa fa-user"></i>All Customers
            </a>
            <a href="<?= $base_path ?>online_users.php">
                <i class="fa fa-signal"></i>Online Users
            </a>
            <a href="<?= $base_path ?>add_user.php">
                <i class="fa fa-user-plus"></i>Add Customer
            </a>
            <a href="<?= $base_path ?>pppoe_users.php">
                <i class="fa fa-key"></i>PPPoE Users
            </a>
            <a href="<?= $base_path ?>hotspot_users.php">
                <i class="fa fa-wifi"></i>Hotspot Users
            </a>
            <a href="<?= $base_path ?>pending_users.php">
                <i class="fa fa-clock"></i>Pending Activation
            </a>
            <a href="<?= $base_path ?>expired_users.php">
                <i class="fa fa-calendar-xmark"></i>Expired Users
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleTicketMenu()">
            <i class="fa fa-ticket"></i>Tickets <i class="fa fa-chevron-down float-end" id="ticket-arrow"></i>
        </a>
        
        <div class="submenu" id="ticket-submenu">
            <a href="<?= $base_path ?>tickets.php">
                <i class="fa fa-list"></i>All Tickets
            </a>
            <a href="<?= $base_path ?>tickets.php?status=open">
                <i class="fa fa-folder-open"></i>Open Tickets
            </a>
            <a href="<?= $base_path ?>tickets.php?status=pending">
                <i class="fa fa-clock"></i>Pending Tickets
            </a>
            <a href="<?= $base_path ?>tickets.php?status=resolved">
                <i class="fa fa-check-circle"></i>Resolved Tickets
            </a>
            <a href="<?= $base_path ?>tickets.php?status=closed">
                <i class="fa fa-xmark-circle"></i>Closed Tickets
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleNetworkMenu()">
            <i class="fa fa-network-wired"></i>Network <i class="fa fa-chevron-down float-end" id="network-arrow"></i>
        </a>
        
        <div class="submenu" id="network-submenu">
            <a href="<?= $base_path ?>nas.php" class="<?=($active=='nas')?'active':''?>">
                <i class="fa fa-server"></i>Devices
            </a>
            <a href="<?= $base_path ?>network_monitoring.php" class="<?=($active=='nas')?'active':''?>">
                <i class="fa fa-desktop"></i>Network Monitoring
            </a>
            <a href="<?= $base_path ?>olt_dashboard.php">
                <i class="fa fa-server"></i>OLT SDN Controller
            </a>
            <a href="<?= $base_path ?>mikrotik_dashboard.php">
                <i class="fa fa-microchip"></i>MikroTik SDN Controller
            </a>
            <a href="<?= $base_path ?>switch_dashboard.php">
                <i class="fa fa-network-wired"></i>Switch Management
            </a>
            <a href="<?= $base_path ?>network_observability.php">
                <i class="fa fa-gauge-high"></i>Network Health & Metrics
            </a>
            <a href="<?= $base_path ?>network_alerts.php">
                <i class="fa fa-bell"></i>Network Alerts
            </a>
            <a href="<?= $base_path ?>network_topology.php">
                <i class="fa fa-project-diagram"></i>Network Topology (NOC)
            </a>
            <a href="<?= $base_path ?>map.php">
                <i class="fa fa-project-diagram"></i>FTTH Network Map
            </a>
            <a href="<?= $base_path ?>faults.php">
                <i class="fa fa-triangle-exclamation"></i>AI Network Faults
            </a>
            <a href="<?= $base_path ?>noc_dashboard.php">
                <i class="fa fa-robot"></i>AI-NOC Dashboard
            </a>
            <a href="<?= $base_path ?>genieacs_devices.php">
                <i class="fa fa-archive"></i>TR-069 Devices
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleOperationMenu()">
            <i class="fa fa-tools"></i>Operations <i class="fa fa-chevron-down float-end" id="operation-arrow"></i>
        </a>
        
        <div class="submenu" id="operation-submenu">
            <a href="<?= $base_path ?>work_diary.php">
                <i class="fa fa-calendar-check"></i>Work Diary
            </a>
            <a href="<?= $base_path ?>inventory.php">
                <i class="fa fa-boxes"></i>Inventory
            </a>
            <a href="<?= $base_path ?>faults.php">
                <i class="fa fa-exclamation-triangle"></i>Faults
            </a>
            <a href="<?= $base_path ?>sms_outbox.php">
                <i class="fa fa-sms"></i>SMS Outbox
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleLeadMenu()">
            <i class="fa fa-funnel-dollar"></i>Leads <i class="fa fa-chevron-down float-end" id="lead-arrow"></i>
        </a>
        
        <div class="submenu" id="lead-submenu">
            <a href="<?= $base_path ?>leads.php">
                <i class="fa fa-list"></i>All Leads
            </a>
            <a href="<?= $base_path ?>leads.php?status=new">
                <i class="fa fa-star"></i>New Leads
            </a>
            <a href="<?= $base_path ?>leads.php?status=contacted">
                <i class="fa fa-phone"></i>Contacted
            </a>
            <a href="<?= $base_path ?>leads.php?status=qualified">
                <i class="fa fa-check-circle"></i>Qualified
            </a>
            <a href="<?= $base_path ?>leads.php?status=proposal">
                <i class="fa fa-file-contract"></i>Proposal Sent
            </a>
            <a href="<?= $base_path ?>leads.php?status=converted">
                <i class="fa fa-trophy"></i>Converted
            </a>
            <a href="<?= $base_path ?>leads.php?status=lost">
                <i class="fa fa-times-circle"></i>Lost
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleHotspotMenu()">
            <i class="fa fa-wifi"></i>Hotspot Portal <i class="fa fa-chevron-down float-end" id="hotspot-arrow"></i>
        </a>
        
        <div class="submenu" id="hotspot-submenu">
            <a href="<?= $base_path ?>hotspot/admin/index.php" class="<?=($active=='hotspot')?'active':''?>">
                <i class="fa fa-gauge-high"></i>Dashboard
            </a>
            <a href="<?= $base_path ?>hotspot/admin/plans.php">
                <i class="fa fa-tags"></i>Plans & Vouchers
            </a>
            <a href="<?= $base_path ?>hotspot/admin/users.php">
                <i class="fa fa-user-circle"></i>Hotspot Users
            </a>
            <a href="<?= $base_path ?>hotspot/admin/hotel.php">
                <i class="fa fa-hotel"></i>Hotel Solution
            </a>
            <a href="<?= $base_path ?>hotspot/admin/blacklist.php">
                <i class="fa fa-shield-alt"></i>Access Control
            </a>
            <a href="<?= $base_path ?>hotspot/admin/logs.php">
                <i class="fa fa-history"></i>Access Logs
            </a>
            <a href="<?= $base_path ?>hotspot/captive_portal.php">
                <i class="fa fa-desktop"></i>Captive Portal
            </a>
            <a href="<?= $base_path ?>hotspot/admin/settings.php">
                <i class="fa fa-cog"></i>Settings
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleReportMenu()">
            <i class="fa fa-chart-bar"></i>Reports <i class="fa fa-chevron-down float-end" id="report-arrow"></i>
        </a>
        
        <div class="submenu" id="report-submenu">
            <a href="<?= $base_path ?>report/index.php">
                <i class="fa fa-gauge-high"></i>Dashboard
            </a>
            <a href="<?= $base_path ?>report/active_users.php">
                <i class="fa fa-signal"></i>Active Users
            </a>
            <a href="<?= $base_path ?>report/new_users.php">
                <i class="fa fa-user-plus"></i>New Users
            </a>
            <a href="<?= $base_path ?>report/expiring_users.php">
                <i class="fa fa-calendar-alt"></i>Expiring Users
            </a>
            <a href="<?= $base_path ?>report/expired_users.php">
                <i class="fa fa-calendar-xmark"></i>Expired Users
            </a>
            <a href="<?= $base_path ?>admin_logs.php">
                <i class="fa fa-history"></i>Activity Logs
            </a>
            <a href="<?= $base_path ?>system_logs.php">
                <i class="fa fa-server"></i>System Logs
            </a>
            <a href="<?= $base_path ?>reports/financial.php">
                <i class="fa fa-chart-line"></i>Financial Report
            </a>
            <a href="<?= $base_path ?>reports/accounting.php">
                <i class="fa fa-calculator"></i>Accounting Report
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleSettingsMenu()">
            <i class="fa fa-cogs"></i>Settings <i class="fa fa-chevron-down float-end" id="settings-arrow"></i>
        </a>
        
        <div class="submenu" id="settings-submenu">
            <a href="<?= $base_path ?>admin.php">
                <i class="fa fa-user-gear"></i>Admin Users
            </a>
            <?php if(isSuperAdmin()): ?>
            <a href="<?= $base_path ?>branches.php">
                <i class="fa fa-sitemap"></i>Branches
            </a>
            <?php endif; ?>
            <a href="<?= $base_path ?>plans.php">
                <i class="fa fa-list-ul"></i>Plans
            </a>
            <a href="<?= $base_path ?>system_config.php">
                <i class="fa fa-cog"></i>System Config
            </a>
            <a href="<?= $base_path ?>notification_settings.php">
                <i class="fa fa-bell"></i>Notifications
            </a>
            <a href="<?= $base_path ?>api_docs.php">
                <i class="fa fa-code"></i>API Documentation
            </a>
            <a href="<?= $base_path ?>hotspot/admin/roles.php">
                <i class="fa fa-user-shield"></i>Roles & Permissions
            </a>
        </div>
        
        <a href="javascript:void(0)" class="menu-toggle-item" onclick="toggleFinanceMenu()">
            <i class="fa fa-wallet"></i>Account/Finance <i class="fa fa-chevron-down float-end" id="finance-arrow"></i>
        </a>
        
        <div class="submenu" id="finance-submenu">
            <a href="<?= $base_path ?>billing/">
                <i class="fa fa-tachometer-alt"></i>Billing Dashboard
            </a>
            <a href="<?= $base_path ?>billing/subscriptions.php">
                <i class="fa fa-users-cog"></i>Subscriptions
            </a>
            <a href="<?= $base_path ?>billing/invoices.php">
                <i class="fa fa-file-invoice"></i>Invoices
            </a>
            <a href="<?= $base_path ?>billing/payments.php">
                <i class="fa fa-credit-card"></i>Payments
            </a>
            <a href="<?= $base_path ?>billing/gateways.php">
                <i class="fa fa-cog"></i>Payment Gateways
            </a>
            <a href="<?= $base_path ?>reports/financial.php">
                <i class="fa fa-chart-line"></i>Financial Report
            </a>
            <a href="<?= $base_path ?>advanced_reports.php">
                <i class="fa fa-file-excel"></i>Advanced Reports
            </a>
            <a href="<?= $base_path ?>reports/accounting.php">
                <i class="fa fa-calculator"></i>Accounting Report
            </a>
        </div>
        
        <a href="<?= $base_path ?>knowledge_base.php">
            <i class="fa fa-book"></i>Knowledge Base
        </a>
        
        <a href="<?= $base_path ?>logout.php" style="margin-top: 20px;">
            <i class="fa fa-right-from-bracket"></i>Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="main">
