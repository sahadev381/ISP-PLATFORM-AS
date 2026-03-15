<?php
$page = $page ?? 'dashboard';
$base_path = $base_path ?? '.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Hotspot Portal' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base_path ?>/assets/css/theme.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; }
        
        .top-nav {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 0 25px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
        }
        .top-nav-brand i { font-size: 24px; color: var(--primary); }
        
        .top-nav-menu { display: flex; gap: 5px; }
        .top-nav-item {
            padding: 10px 18px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .top-nav-item:hover, .top-nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .top-nav-item.active { background: var(--primary); }
        
        .top-nav-right { display: flex; align-items: center; gap: 15px; }
        .user-avatar {
            width: 36px; height: 36px;
            background: var(--primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 14px;
        }
        
        .main-content { margin-top: 80px; padding: 25px; }
        
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-body { padding: 24px; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.4); }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table th { padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
        .table td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; }
        .table tr:hover { background: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-primary { background: #dbeafe; color: #1d4ed8; }
        .badge-info { background: #cffafe; color: #0891b2; }
        .badge-secondary { background: #f1f5f9; color: #64748b; }
        
        code { background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-info { background: #dbeafe; color: #1e40af; }
        
        .form-select, .form-control {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stat-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .stat-icon.blue { background: #dbeafe; color: var(--primary); }
        .stat-icon.green { background: #d1fae5; color: var(--success); }
        .stat-icon.orange { background: #fed7aa; color: var(--warning); }
        
        .stat-label { font-size: 13px; color: #64748b; text-transform: uppercase; font-weight: 600; }
        .stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <a href="index.php" class="top-nav-brand">
            <i class="fa fa-wifi"></i>
            <span>Hotspot Portal</span>
        </a>
        
        <div class="top-nav-menu">
            <a href="index.php" class="top-nav-item <?= $page == 'dashboard' ? 'active' : '' ?>">
                <i class="fa fa-gauge-high"></i> Dashboard
            </a>
            <a href="plans.php" class="top-nav-item <?= $page == 'plans' ? 'active' : '' ?>">
                <i class="fa fa-tags"></i> Plans
            </a>
            <a href="users.php" class="top-nav-item <?= $page == 'users' ? 'active' : '' ?>">
                <i class="fa fa-users"></i> Users
            </a>
            <a href="hotel.php" class="top-nav-item <?= $page == 'hotel' ? 'active' : '' ?>">
                <i class="fa fa-hotel"></i> Hotel
            </a>
            <a href="blacklist.php" class="top-nav-item <?= $page == 'blacklist' ? 'active' : '' ?>">
                <i class="fa fa-shield-alt"></i> Access Control
            </a>
            <a href="logs.php" class="top-nav-item <?= $page == 'logs' ? 'active' : '' ?>">
                <i class="fa fa-history"></i> Logs
            </a>
            <a href="settings.php" class="top-nav-item <?= $page == 'settings' ? 'active' : '' ?>">
                <i class="fa fa-cog"></i> Settings
            </a>
        </div>
        
        <div class="top-nav-right">
            <a href="<?= $base_path ?>/index.php" class="btn btn-sm" style="background: rgba(255,255,255,0.1); color: white;">
                <i class="fa fa-arrow-left"></i> Back to Main
            </a>
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $message ?></div>
        <?php endif; ?>
