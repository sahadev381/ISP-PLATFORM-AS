<?php
session_start();
$page_title = "Captive Portal Customization";

chdir(__DIR__ . '/..');
include_once 'config.php';
include_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $primary_color = $_POST['primary_color'] ?? '#6366f1';
    $logo_url = $_POST['logo_url'] ?? '';
    $portal_title = $_POST['portal_title'] ?? 'Hotspot Portal';
    $welcome_message = $_POST['welcome_message'] ?? 'Welcome to our WiFi';
    $footer_text = $_POST['footer_text'] ?? '';
    $enable_video_bg = isset($_POST['enable_video_bg']) ? 1 : 0;
    $video_url = $_POST['video_url'] ?? '';
    $show_features = isset($_POST['show_features']) ? 1 : 0;
    $bg_type = $_POST['bg_type'] ?? 'gradient';
    
    $settings = [
        'captive_primary_color' => $primary_color,
        'captive_logo_url' => $logo_url,
        'captive_portal_title' => $portal_title,
        'captive_welcome_message' => $welcome_message,
        'captive_footer_text' => $footer_text,
        'captive_enable_video_bg' => $enable_video_bg,
        'captive_video_url' => $video_url,
        'captive_show_features' => $show_features,
        'captive_bg_type' => $bg_type,
    ];
    
    foreach ($settings as $key => $value) {
        $conn->query("INSERT INTO hotspot_settings (setting_key, setting_value) VALUES ('$key', '$value') 
            ON DUPLICATE KEY UPDATE setting_value = '$value'");
    }
    
    $message = 'Portal customization saved successfully!';
}

$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM hotspot_settings WHERE setting_key LIKE 'captive_%'");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .header a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            font-size: 1.8rem;
            color: #1e293b;
            font-weight: 600;
        }
        
        .page-header p {
            color: #64748b;
            margin-top: 5px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: none;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .card-header {
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #1e293b;
            font-weight: 600;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -15px;
        }
        
        .col-md-6 {
            width: 50%;
            padding: 15px;
        }
        
        .col-md-4 {
            width: 33.33%;
            padding: 15px;
        }
        
        .col-md-3 {
            width: 25%;
            padding: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .color-picker-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .color-picker-wrapper input[type="color"] {
            width: 60px;
            height: 45px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .color-picker-wrapper input[type="text"] {
            flex: 1;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        /* Preview Section */
        .preview-section {
            background: #f8fafc;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        
        .preview-section h4 {
            margin-bottom: 20px;
            color: #1e293b;
        }
        
        .preview-frame {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .preview-navbar {
            background: <?= $settings['captive_primary_color'] ?? '#6366f1' ?>;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-navbar span {
            color: white;
            font-weight: 600;
        }
        
        .preview-content {
            padding: 40px 30px;
            text-align: center;
            background: linear-gradient(135deg, <?= $settings['captive_primary_color'] ?? '#6366f1' ?> 0%, #3b82f6 100%);
            min-height: 300px;
        }
        
        .preview-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Background Type Preview */
        .bg-gradient {
            background: linear-gradient(135deg, <?= $settings['captive_primary_color'] ?? '#6366f1' ?> 0%, #3b82f6 100%);
        }
        
        .bg-solid {
            background: <?= $settings['captive_primary_color'] ?? '#6366f1' ?>;
        }
        
        .bg-image {
            background: url('https://images.unsplash.com/photo-1557683316-973673baf926?w=800') center/cover;
        }
        
        .bg-pattern {
            background: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
                linear-gradient(135deg, <?= $settings['captive_primary_color'] ?? '#6366f1' ?> 0%, #3b82f6 100%);
        }
        
        .bg-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        
        .bg-option {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .bg-option:hover {
            transform: scale(1.05);
        }
        
        .bg-option.active {
            border-color: var(--primary);
        }
        
        .bg-option-preview {
            height: 60px;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .bg-option span {
            font-size: 12px;
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .col-md-6, .col-md-4, .col-md-3 {
                width: 100%;
            }
            
            .header {
                padding: 15px 20px;
            }
            
            .bg-options {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-desktop"></i> Captive Portal Customization</h1>
        <a href="../hotspot/"><i class="fas fa-eye"></i> View Live Portal</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        
        <!-- Live Preview -->
        <div class="preview-section">
            <h4><i class="fas fa-eye"></i> Live Preview</h4>
            <div class="preview-frame">
                <div class="preview-navbar">
                    <span><i class="fas fa-wifi"></i> <?= $settings['captive_portal_title'] ?? 'Hotspot Portal' ?></span>
                    <span style="opacity: 0.8; font-size: 12px;">Menu</span>
                </div>
                <div class="preview-content <?= $settings['captive_bg_type'] ?? 'bg-gradient' ?>">
                    <div class="preview-card">
                        <h3 style="margin-bottom: 10px; color: #1e293b;">
                            <?= $settings['captive_welcome_message'] ?? 'Welcome to our WiFi' ?>
                        </h3>
                        <p style="color: #64748b; margin-bottom: 20px;">
                            Connect to the internet securely
                        </p>
                        <button style="background: <?= $settings['captive_primary_color'] ?? '#6366f1' ?>; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 500;">
                            Connect Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <!-- Basic Settings -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> Basic Settings</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Portal Title</label>
                                <input type="text" name="portal_title" class="form-control" 
                                       value="<?= $settings['captive_portal_title'] ?? 'Hotspot Portal' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Logo URL</label>
                                <input type="text" name="logo_url" class="form-control" 
                                       value="<?= $settings['captive_logo_url'] ?? '' ?>" 
                                       placeholder="https://example.com/logo.png">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Welcome Message</label>
                        <input type="text" name="welcome_message" class="form-control" 
                               value="<?= $settings['captive_welcome_message'] ?? 'Welcome to our WiFi' ?>">
                    </div>
                    <div class="form-group">
                        <label>Footer Text</label>
                        <textarea name="footer_text" class="form-control" 
                                  placeholder="© 2026 Your Company. All rights reserved."><?= $settings['captive_footer_text'] ?? '' ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Colors -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-palette"></i> Colors & Theme</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Primary Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="primary_color" 
                                   value="<?= $settings['captive_primary_color'] ?? '#6366f1' ?>">
                            <input type="text" class="form-control" 
                                   value="<?= $settings['captive_primary_color'] ?? '#6366f1' ?>"
                                   onchange="document.querySelector('input[type=color]').value = this.value">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Background Type</label>
                        <div class="bg-options">
                            <label class="bg-option <?= ($settings['captive_bg_type'] ?? 'bg-gradient') == 'bg-gradient' ? 'active' : '' ?>">
                                <input type="radio" name="bg_type" value="bg-gradient" 
                                       <?= ($settings['captive_bg_type'] ?? 'bg-gradient') == 'bg-gradient' ? 'checked' : '' ?> 
                                       style="display: none;">
                                <div class="bg-option-preview bg-gradient"></div>
                                <span>Gradient</span>
                            </label>
                            <label class="bg-option <?= ($settings['captive_bg_type'] ?? '') == 'bg-solid' ? 'active' : '' ?>">
                                <input type="radio" name="bg_type" value="bg-solid" 
                                       <?= ($settings['captive_bg_type'] ?? '') == 'bg-solid' ? 'checked' : '' ?> 
                                       style="display: none;">
                                <div class="bg-option-preview bg-solid"></div>
                                <span>Solid</span>
                            </label>
                            <label class="bg-option <?= ($settings['captive_bg_type'] ?? '') == 'bg-image' ? 'active' : '' ?>">
                                <input type="radio" name="bg_type" value="bg-image" 
                                       <?= ($settings['captive_bg_type'] ?? '') == 'bg-image' ? 'checked' : '' ?> 
                                       style="display: none;">
                                <div class="bg-option-preview bg-image"></div>
                                <span>Image</span>
                            </label>
                            <label class="bg-option <?= ($settings['captive_bg_type'] ?? '') == 'bg-pattern' ? 'active' : '' ?>">
                                <input type="radio" name="bg_type" value="bg-pattern" 
                                       <?= ($settings['captive_bg_type'] ?? '') == 'bg-pattern' ? 'checked' : '' ?> 
                                       style="display: none;">
                                <div class="bg-option-preview bg-pattern"></div>
                                <span>Pattern</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Options -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-sliders-h"></i> Advanced Options</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="enable_video_bg" id="enable_video_bg" 
                                           <?= ($settings['captive_enable_video_bg'] ?? 0) ? 'checked' : '' ?>>
                                    <label for="enable_video_bg">Enable Video Background</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="show_features" id="show_features" 
                                           <?= ($settings['captive_show_features'] ?? 1) ? 'checked' : '' ?>>
                                    <label for="show_features">Show Features Section</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Video URL (MP4)</label>
                        <input type="text" name="video_url" class="form-control" 
                               value="<?= $settings['captive_video_url'] ?? '' ?>"
                               placeholder="https://example.com/video.mp4">
                        <small style="color: #64748b; margin-top: 5px; display: block;">
                            Enter direct MP4 video URL for background
                        </small>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </form>
    </div>
    
    <script>
        // Color picker sync
        document.querySelector('input[type="color"]').addEventListener('input', function() {
            document.querySelector('input[type="text"]').value = this.value;
        });
        
        // Background option selection
        document.querySelectorAll('.bg-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.bg-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
