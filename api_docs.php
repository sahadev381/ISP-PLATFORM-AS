<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "API Documentation";
$active = "settings";

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="main-content-inner" style="padding: 25px;">
    
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0; color:#1e293b;"><i class="fa fa-code"></i> API Documentation</h2>
        <p style="color:#64748b;">RESTful APIs for ISP Management System integration</p>
    </div>

    <style>
    .api-endpoint { background:#fff; border-radius:15px; padding:25px; margin-bottom:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02); }
    .api-method { display: inline-block; padding: 5px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; margin-right: 10px; }
    .api-method.get { background: #10b981; color: #fff; }
    .api-method.post { background: #3b82f6; color: #fff; }
    .api-method.put { background: #f59e0b; color: #fff; }
    .api-method.delete { background: #ef4444; color: #fff; }
    .api-url { font-family: monospace; font-size: 14px; color: #1e293b; background: #f8fafc; padding: 10px 15px; border-radius: 8px; display: block; margin: 15px 0; border: 1px solid #e2e8f0; }
    .api-param { display: flex; gap: 15px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .api-param:last-child { border-bottom: none; }
    .param-name { font-weight: 700; color: #3b82f6; min-width: 150px; }
    .param-type { color: #f59e0b; min-width: 80px; }
    .param-desc { color: #64748b; }
    .code-block { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; overflow-x: auto; margin: 10px 0; }
    </style>

    <!-- OLT ONT API -->
    <div class="api-endpoint">
        <h3 style="margin-top:0;"><i class="fa fa-server" style="color:#3b82f6;"></i> OLT ONT Management</h3>
        <span class="api-method get">GET</span>
        <span class="api-method post">POST</span>
        
        <code class="api-url">api/olt_ont.php</code>
        
        <h4>Actions:</h4>
        
        <div class="api-param">
            <span class="param-name">?action=list_onts</span>
            <span class="param-type">GET</span>
            <span class="param-desc">List all ONTs on an OLT device. Params: olt_id, pon_port (optional)</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=get_signal</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get ONT signal strength. Params: olt_id, ont_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=reboot_ont</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Reboot an ONT. Params: olt_id, ont_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=disable_ont</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Disable an ONT. Params: olt_id, ont_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=enable_ont</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Enable a disabled ONT. Params: olt_id, ont_id</span>
        </div>
        
        <h4>Example Request:</h4>
        <div class="code-block">curl "http://your-server/api/olt_ont.php?action=list_onts&olt_id=1"</div>
    </div>

    <!-- SNMP Monitor API -->
    <div class="api-endpoint">
        <h3 style="margin-top:0;"><i class="fa fa-heartbeat" style="color:#10b981;"></i> SNMP Monitoring</h3>
        
        <code class="api-url">api/snmp_monitor.php</code>
        
        <div class="api-param">
            <span class="param-name">?action=poll_device</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Poll device for SNMP metrics. Params: device_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=device_history</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get device metrics history. Params: device_id, hours (default 24)</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=all_devices_status</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get status of all network devices</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=mikrotik_stats</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get MikroTik router stats. Params: device_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=switch_stats</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get switch port stats. Params: device_id</span>
        </div>
    </div>

    <!-- Customer API -->
    <div class="api-endpoint">
        <h3 style="margin-top:0;"><i class="fa fa-users" style="color:#8b5cf6;"></i> Customer Management</h3>
        
        <code class="api-url">api/customers.php</code>
        
        <div class="api-param">
            <span class="param-name">?action=list</span>
            <span class="param-type">GET</span>
            <span class="param-desc">List all customers. Params: status, plan_id, limit, offset</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=get</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get customer details. Params: customer_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=create</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Create new customer. Params: username, password, plan_id, name, email, phone, address</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=update</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Update customer. Params: customer_id, (other fields)</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=delete</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Delete customer. Params: customer_id</span>
        </div>
    </div>

    <!-- Billing API -->
    <div class="api-endpoint">
        <h3 style="margin-top:0;"><i class="fa fa-credit-card" style="color:#f59e0b;"></i> Billing API</h3>
        
        <code class="api-url">api/billing.php</code>
        
        <div class="api-param">
            <span class="param-name">?action=get_invoices</span>
            <span class="param-type">GET</span>
            <span class="param-desc">Get customer invoices. Params: customer_id, status</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=create_invoice</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Create new invoice. Params: customer_id, amount, description, due_date</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=record_payment</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Record payment. Params: invoice_id, amount, method, transaction_id</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=recharge</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Add balance to customer account. Params: customer_id, amount</span>
        </div>
    </div>

    <!-- Hotspot API -->
    <div class="api-endpoint">
        <h3 style="margin-top:0;"><i class="fa fa-wifi" style="color:#ef4444;"></i> Hotspot Portal API</h3>
        
        <code class="api-url">api/hotspot.php</code>
        
        <div class="api-param">
            <span class="param-name">?action=login</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Hotspot user login. Params: username, password, mac</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=otp_send</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Send SMS OTP. Params: phone</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=otp_verify</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Verify OTP. Params: phone, otp_code</span>
        </div>
        <div class="api-param">
            <span class="param-name">?action=voucher_validate</span>
            <span class="param-type">POST</span>
            <span class="param-desc">Validate voucher code. Params: voucher_code</span>
        </div>
    </div>

    <!-- Authentication -->
    <div class="api-endpoint">
        <h3 style="margin-top:0;"><i class="fa fa-lock" style="color:#64748b;"></i> Authentication</h3>
        
        <p style="color:#64748b;">All API requests require authentication via header or session cookie.</p>
        
        <h4>API Key Authentication:</h4>
        <div class="code-block">curl -H "Authorization: Bearer YOUR_API_KEY" "http://your-server/api/..."</div>
        
        <h4>Response Format:</h4>
        <div class="code-block">{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}</div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
