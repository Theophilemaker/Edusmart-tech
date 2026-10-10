<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login and role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$message_type = '';
$settings_file = __DIR__ . '/../config/settings.json';

// Default settings
$default_settings = [
    'school_name' => 'EduSmart Pro',
    'email' => 'info@edusmart.com',
    'phone' => '+1234567890',
    'address' => '123 Education Street, Learning City',
    'timezone' => 'UTC',
    'date_format' => 'M d, Y',
    'currency' => 'USD',
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => '587',
        'user' => '',
        'pass' => '',
        'encryption' => 'tls'
    ],
    'sms' => [
        'api_key' => '',
        'sender_id' => 'EduSmart'
    ],
    'email_notifications' => true,
    'sms_notifications' => false,
    'maintenance_mode' => false,
    'registration_open' => true
];

// Ensure settings table exists (for logo)
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT
)");

// Handle logo upload (now inside branding tab)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_logo'])) {
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $target_dir = "../uploads/logo/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        if (in_array($ext, $allowed)) {
            $filename = 'logo.' . $ext; // fixed name (overwrites previous)
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $target_file)) {
                // Save to settings table
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', '$filename') ON DUPLICATE KEY UPDATE setting_value='$filename'");
                $message = "Logo updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error uploading file.";
                $message_type = 'error';
            }
        } else {
            $message = "Invalid file type.";
            $message_type = 'error';
        }
    }
}

// Load settings from JSON file
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    $settings = array_merge($default_settings, $settings);
} else {
    $settings = $default_settings;
    // Create default settings file
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
}

// Update settings (general, email, sms)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'general') {
        $settings = [
            'school_name' => $_POST['school_name'] ?? $default_settings['school_name'],
            'email' => $_POST['email'] ?? $default_settings['email'],
            'phone' => $_POST['phone'] ?? $default_settings['phone'],
            'address' => $_POST['address'] ?? $default_settings['address'],
            'timezone' => $_POST['timezone'] ?? 'UTC',
            'date_format' => $_POST['date_format'] ?? 'M d, Y',
            'currency' => $_POST['currency'] ?? 'USD',
            'smtp' => $settings['smtp'] ?? $default_settings['smtp'],
            'sms' => $settings['sms'] ?? $default_settings['sms'],
            'email_notifications' => isset($_POST['email_notifications']),
            'sms_notifications' => isset($_POST['sms_notifications']),
            'maintenance_mode' => isset($_POST['maintenance_mode']),
            'registration_open' => isset($_POST['registration_open'])
        ];
        
        if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT))) {
            $message = "General settings saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Error saving settings. Check file permissions.";
            $message_type = 'error';
        }
    } elseif ($action == 'email') {
        $settings['smtp'] = [
            'host' => $_POST['smtp_host'] ?? '',
            'port' => $_POST['smtp_port'] ?? '587',
            'user' => $_POST['smtp_user'] ?? '',
            'pass' => $_POST['smtp_pass'] ?? '',
            'encryption' => $_POST['smtp_encryption'] ?? 'tls'
        ];
        $settings['email_notifications'] = isset($_POST['email_notifications']);
        
        if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT))) {
            $message = "Email settings saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Error saving email settings.";
            $message_type = 'error';
        }
    } elseif ($action == 'sms') {
        $settings['sms'] = [
            'api_key' => $_POST['sms_api'] ?? '',
            'sender_id' => $_POST['sms_sender'] ?? 'EduSmart'
        ];
        $settings['sms_notifications'] = isset($_POST['sms_notifications']);
        
        if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT))) {
            $message = "SMS settings saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Error saving SMS settings.";
            $message_type = 'error';
        }
    }
}

// Get system info (unchanged)
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'total_users' => 0,
    'database_size' => 0,
    'last_backup' => 'Never'
];

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $system_info['total_users'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                           FROM information_schema.tables 
                           WHERE table_schema = DATABASE()");
    $system_info['database_size'] = $result->fetch_assoc()['size'] ?? 0;
    
    // Check for backup files
    $backup_dir = __DIR__ . '/../backups/';
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '*.sql');
        if (!empty($files)) {
            $latest = max(array_combine($files, array_map('filemtime', $files)));
            $system_info['last_backup'] = date('M d, Y H:i', $latest);
        }
    }
} catch (Exception $e) {
    error_log("System info error: " . $e->getMessage());
}

// Get current logo for display
$logo = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_logo'")->fetch_assoc();
$logo_file = $logo['setting_value'] ?? '';

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* (Keep all existing styles – they are already comprehensive) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: #667eea;
            font-size: 2em;
        }

        .sidebar-header h3 {
            margin-bottom: 5px;
            font-size: 1.2em;
        }

        .sidebar-header p {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .menu-item i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.2em;
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 30px;
        }

        .menu-item.active {
            background: rgba(255,255,255,0.2);
            border-left: 3px solid white;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .top-nav {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .page-title h1 {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 5px;
        }

        .page-title p {
            color: #666;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification {
            position: relative;
            cursor: pointer;
        }

        .notification i {
            font-size: 1.3em;
            color: #666;
        }

        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f72585;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7em;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .profile-dropdown:hover {
            background: #f8f9fa;
        }

        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }

        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
        }

        .info-content h3 {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 5px;
        }

        .info-content p {
            color: #666;
            font-size: 1.2em;
            font-weight: bold;
        }

        .settings-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .settings-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 1em;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .tab-btn:hover {
            color: #667eea;
        }

        .tab-btn.active {
            color: #667eea;
            font-weight: 600;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #667eea;
        }

        .tab-pane {
            display: none;
            padding: 30px;
        }

        .tab-pane.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .btn-save {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-test {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 10px;
        }

        .btn-test:hover {
            background: #059669;
        }

        .backup-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .backup-actions {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .btn-backup {
            padding: 10px 20px;
            background: #4cc9f0;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-backup:hover {
            background: #3a8fd9;
        }

        .btn-restore {
            padding: 10px 20px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-restore:hover {
            background: #d97706;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .logo-preview {
            max-height: 60px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 5px;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h3,
            .sidebar-header p,
            .menu-item span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .settings-tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h3>
                <p>Administrator</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="users.php" class="menu-item"><i class="fas fa-users"></i><span>Users</span></a>
                <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
                <a href="settings.php" class="menu-item active"><i class="fas fa-cog"></i><span>Settings</span></a>
                <a href="../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>System Settings</h1>
                    <p>Configure your system preferences</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge">0</span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- System Info Cards -->
            <div class="system-info">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="info-content">
                        <h3>Database Size</h3>
                        <p><?php echo $system_info['database_size']; ?> MB</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="info-content">
                        <h3>Total Users</h3>
                        <p><?php echo $system_info['total_users']; ?></p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h3>Last Backup</h3>
                        <p><?php echo $system_info['last_backup']; ?></p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="info-content">
                        <h3>PHP Version</h3>
                        <p><?php echo $system_info['php_version']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Settings Tabs -->
            <div class="settings-container">
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="showTab('general')">General</button>
                    <button class="tab-btn" onclick="showTab('branding')">Branding</button>
                    <button class="tab-btn" onclick="showTab('email')">Email</button>
                    <button class="tab-btn" onclick="showTab('sms')">SMS</button>
                    <button class="tab-btn" onclick="showTab('backup')">Backup</button>
                </div>
                
                <!-- General Settings Tab (unchanged) -->
                <div id="general-tab" class="tab-pane active">
                    <!-- (same as before) -->
                    <form method="POST">
                        <input type="hidden" name="action" value="general">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>School Name</label>
                                <input type="text" name="school_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['school_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['phone']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['address']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Timezone</label>
                                <select name="timezone" class="form-control">
                                    <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="America/New_York" <?php echo $settings['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?php echo $settings['timezone'] == 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                    <option value="America/Denver" <?php echo $settings['timezone'] == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                    <option value="America/Los_Angeles" <?php echo $settings['timezone'] == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Date Format</label>
                                <select name="date_format" class="form-control">
                                    <option value="M d, Y" <?php echo $settings['date_format'] == 'M d, Y' ? 'selected' : ''; ?>>Jan 15, 2024</option>
                                    <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>2024-01-15</option>
                                    <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>15/01/2024</option>
                                    <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>01/15/2024</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Currency</label>
                                <select name="currency" class="form-control">
                                    <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                    <option value="GBP" <?php echo $settings['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="registration_open" id="registration_open" 
                                           <?php echo $settings['registration_open'] ? 'checked' : ''; ?>>
                                    <label for="registration_open">Allow new registrations</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="maintenance_mode" id="maintenance_mode" 
                                   <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                            <label for="maintenance_mode">Maintenance Mode (only admins can access)</label>
                        </div>
                        
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save General Settings
                        </button>
                    </form>
                </div>
                
                <!-- Branding Tab (NEW) -->
                <div id="branding-tab" class="tab-pane">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label>Site Logo</label>
                            <?php if (!empty($logo_file)): ?>
                                <div class="logo-preview">
                                    <img src="../uploads/logo/<?php echo $logo_file; ?>" alt="Site Logo" style="max-height:60px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="site_logo" accept="image/*" class="form-control">
                            <small>Recommended size: 200x50px. Uploading a new file will replace the current logo.</small>
                        </div>
                        
                        <button type="submit" name="upload_logo" class="btn-save">
                            <i class="fas fa-upload"></i> Upload Logo
                        </button>
                    </form>
                </div>
                
                <!-- Email Settings Tab (unchanged) -->
                <div id="email-tab" class="tab-pane">
                    <!-- (same as before) -->
                    <form method="POST">
                        <input type="hidden" name="action" value="email">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['smtp']['host']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>SMTP Port</label>
                                <input type="text" name="smtp_port" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['smtp']['port']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>SMTP Username</label>
                                <input type="text" name="smtp_user" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['smtp']['user']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>SMTP Password</label>
                                <input type="password" name="smtp_pass" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['smtp']['pass']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Encryption</label>
                                <select name="smtp_encryption" class="form-control">
                                    <option value="tls" <?php echo $settings['smtp']['encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo $settings['smtp']['encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo empty($settings['smtp']['encryption']) ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="email_notifications" id="email_notifications" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                    <label for="email_notifications">Enable email notifications</label>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center;">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Email Settings
                            </button>
                            <button type="button" class="btn-test" onclick="testEmailSettings()">
                                <i class="fas fa-paper-plane"></i> Test Connection
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- SMS Settings Tab (unchanged) -->
                <div id="sms-tab" class="tab-pane">
                    <form method="POST">
                        <input type="hidden" name="action" value="sms">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>SMS API Key</label>
                                <input type="text" name="sms_api" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['sms']['api_key']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>SMS Sender ID</label>
                                <input type="text" name="sms_sender" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['sms']['sender_id']); ?>">
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="sms_notifications" id="sms_notifications" 
                                   <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                            <label for="sms_notifications">Enable SMS notifications</label>
                        </div>
                        
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save SMS Settings
                        </button>
                    </form>
                </div>
                
                <!-- Backup Tab (unchanged) -->
                <div id="backup-tab" class="tab-pane">
                    <h3 style="margin-bottom: 20px;">Database Backup</h3>
                    
                    <div class="backup-section">
                        <h4>Create Backup</h4>
                        <p style="color: #666; margin-bottom: 15px;">Download a complete backup of your database.</p>
                        <div class="backup-actions">
                            <a href="backup.php?action=download" class="btn-backup">
                                <i class="fas fa-download"></i> Download Backup
                            </a>
                            <a href="backup.php?action=create" class="btn-backup" style="background: #10b981;">
                                <i class="fas fa-plus-circle"></i> Create & Download
                            </a>
                        </div>
                    </div>
                    
                    <div class="backup-section">
                        <h4>Restore Backup</h4>
                        <p style="color: #666; margin-bottom: 15px;">Restore from a previous backup file.</p>
                        <form action="restore.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="form-group">
                                <input type="file" name="backup_file" class="form-control" accept=".sql">
                            </div>
                            <button type="submit" class="btn-restore" onclick="return confirm('Restoring will overwrite current data. Continue?')">
                                <i class="fas fa-undo-alt"></i> Restore Backup
                            </button>
                        </form>
                    </div>
                    
                    <div class="backup-section">
                        <h4>Last Backup</h4>
                        <p style="color: #666;"><?php echo $system_info['last_backup']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        function testEmailSettings() {
            fetch('test-email.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Email test successful! Check your inbox.');
                    } else {
                        alert('Email test failed: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error testing email settings');
                });
        }
    </script>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>