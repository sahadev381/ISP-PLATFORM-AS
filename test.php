<?php
// user_view.php
include 'config.php';
include 'includes/auth.php';

// DEMO DATA (replace with DB / API values)
$user = [
    'organization' => 'Shailung',
    'branch' => 'Shailung',
    'customer_code' => '907210',
    'username' => 'slg_lakpa',
    'password' => '******',
    'company' => '',
    'name' => 'Lakpa Tamang',
    'email' => '',
    'mobile' => '9761754667',
    'address1' => 'Shailung 1 dudh pokhari'
];

$tech = [
    'package' => 'SLG-FTTH60-Mbps-5G',
    'grace' => 'No',
    'mac1' => '74:E1:9A:32:FB:A1',
    'mac1_locked' => false,
    'mac2_locked' => true,
    'registered' => '1970-01-01'
];

$session = [
    'status' => 'Active',
    'end_date' => '2027-01-05',
    'remaining' => '365 Days',
    'online' => true,
    'last_logoff' => '2026-01-05 12:36:57',
    'duration' => '36 min',
    'ip' => '100.119.219.112',
    'nas' => '103.90.144.27',
    'ipv6' => '2400:f6c0:400:fb3e::/64',
    'download' => '325 MB',
    'upload' => '18 MB'
];
?>

<!DOCTYPE html>
<html>
<head>
    <title>User View</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

</head>
<body class="bg-light">

<div class="container-fluid mt-3">
    <div class="row g-3">

        <!-- CUSTOMER INFO -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <b>Customer Information</b>
                    <i class="bi bi-pencil"></i>
                </div>
                <div class="card-body small">
                    <p><i class="bi bi-building"></i> Organization: <b><?= $user['organization'] ?></b></p>
                    <p><i class="bi bi-diagram-3"></i> Branch: <b><?= $user['branch'] ?></b></p>
                    <p><i class="bi bi-hash"></i> Customer Code: <b><?= $user['customer_code'] ?></b></p>
                    <p><i class="bi bi-person"></i> Username: <b><?= $user['username'] ?></b></p>
                    <p><i class="bi bi-key"></i> Password: <b><?= $user['password'] ?></b></p>
                    <p><i class="bi bi-person-badge"></i> Name: <b><?= $user['name'] ?></b></p>
                    <p><i class="bi bi-phone"></i> Mobile: <b><?= $user['mobile'] ?></b></p>
                    <p><i class="bi bi-geo-alt"></i> Address: <b><?= $user['address1'] ?></b></p>
                    <button class="btn btn-success btn-sm">Update GEO</button>
                </div>
            </div>
        </div>

        <!-- TECHNICAL INFO -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-purple text-white" style="background:#9b4de0">
                    <b>Technical Information</b>
                </div>
                <div class="card-body small">
                    <div class="mb-2">
                        <span class="badge bg-primary">PON Status</span>
                        <span class="badge bg-primary">ONU Status</span>
                        <span class="badge bg-primary">Power</span>
                        <span class="badge bg-primary">Distance</span>
                    </div>

                    <p><i class="bi bi-box"></i> Package: <b><?= $tech['package'] ?></b></p>
                    <p><i class="bi bi-shield"></i> Grace Period: 
                        <span class="badge bg-danger"><?= $tech['grace'] ?></span>
                    </p>

                    <p>
                        <i class="bi bi-diagram-2"></i> MAC Address 1:<br>
                        <b><?= $tech['mac1'] ?></b>
                        <?php if($tech['mac1_locked']): ?>
                            <button class="btn btn-danger btn-sm">Unlock</button>
                        <?php else: ?>
                            <button class="btn btn-success btn-sm">Lock</button>
                        <?php endif; ?>
                    </p>

                    <p>
                        <i class="bi bi-diagram-2"></i> MAC Address 2:
                        <button class="btn btn-success btn-sm">Lock</button>
                    </p>

                    <p><i class="bi bi-calendar"></i> Registered: <?= $tech['registered'] ?></p>
                </div>
            </div>
        </div>

        <!-- CURRENT SESSION -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between">
                    <b>Current Session</b>
                    <button class="btn btn-warning btn-sm">Disconnect</button>
                </div>
                <div class="card-body small">
                    <p>Package: <b><?= $tech['package'] ?></b></p>
                    <p>Status: 
                        <span class="badge bg-success"><?= $session['status'] ?></span>
                    </p>
                    <p>End Date: <span class="badge bg-success"><?= $session['end_date'] ?></span></p>
                    <p>Remaining: <?= $session['remaining'] ?></p>
                    <p>Session: 
                        <span class="badge bg-success">
                            <?= $session['online'] ? 'Online' : 'Offline' ?>
                        </span>
                    </p>
                    <p>Last Logoff: <?= $session['last_logoff'] ?></p>
                    <p>Online Duration: <?= $session['duration'] ?></p>
                    <p>IP Address: <span class="badge bg-success"><?= $session['ip'] ?></span></p>
                    <p>NAS IP: <?= $session['nas'] ?></p>
                    <p>IPv6 Prefix: <span class="badge bg-success"><?= $session['ipv6'] ?></span></p>
                    <p>Download: <?= $session['download'] ?></p>
                    <p>Upload: <?= $session['upload'] ?></p>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>

