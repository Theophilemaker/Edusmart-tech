<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files (adjust paths if needed)
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get current admin data
$admin = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    if (!$admin) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    error_log("Error loading profile: " . $e->getMessage());
    $message = "Error loading profile.";
    $message_type = 'error';
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $target_dir = "../uploads/profile/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            $filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                // Delete old picture if exists
                if (!empty($admin['profile_pic']) && file_exists($target_dir . $admin['profile_pic'])) {
                    unlink($target_dir . $admin['profile_pic']);
                }
                $update = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $update->bind_param("si", $filename, $user_id);
                if ($update->execute()) {
                    $message = "Profile picture updated!";
                    $message_type = 'success';
                    $admin['profile_pic'] = $filename;
                } else {
                    $message = "Database update failed.";
                    $message_type = 'error';
                }
            } else {
                $message = "Error uploading file.";
                $message_type = 'error';
            }
        } else {
            $message = "Invalid file type. Allowed: JPG, PNG, GIF";
            $message_type = 'error';
        }
    } else {
        $message = "No file selected or upload error.";
        $message_type = 'error';
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');

    $errors = [];

    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($username)) $errors[] = "Username is required";
    if (strlen($username) < 4) $errors[] = "Username must be at least 4 characters";

    // Check username uniqueness
    if ($username != $admin['username']) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Username already taken";
        }
    }

    // Check email uniqueness
    if ($email != $admin['email']) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Email already taken";
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, username = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $username, $user_id);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            $message = "Profile updated successfully!";
            $message_type = 'success';
            $admin['full_name'] = $full_name;
            $admin['email'] = $email;
            $admin['phone'] = $phone;
            $admin['username'] = $username;
        } else {
            $message = "Error updating profile: " . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        $errors[] = "Current password is incorrect";
    }
    if (strlen($new) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    if ($new !== $confirm) {
        $errors[] = "New passwords do not match";
    }

    if (empty($errors)) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
            $message_type = 'success';
        } else {
            $message = "Error changing password.";
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .profile-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #667eea;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #667eea;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: 0.3s;
        }
        .avatar-upload:hover {
            background: #764ba2;
        }
        .avatar-upload input {
            display: none;
        }
        .profile-info h2 {
            margin-bottom: 5px;
            color: #333;
        }
        .profile-info p {
            color: #666;
        }
        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
            color: #666;
            position: relative;
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
            transition: 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-save {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: 0.3s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
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
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body class="admin-dashboard">
    <?php include '../includes/sidebar.php'; ?> <!-- Ensure this file exists -->

    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Admin Profile</h1>
                <p>Manage your account</p>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?php 
                        echo !empty($admin['profile_pic']) 
                            ? '../uploads/profile/' . htmlspecialchars($admin['profile_pic']) 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($admin['full_name'] ?? 'Admin') . '&size=120';
                    ?>" alt="Profile">
                    <form method="POST" enctype="multipart/form-data" id="photoForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <label class="avatar-upload">
                            <i class="fas fa-camera"></i>
                            <input type="file" name="profile_photo" accept="image/*" onchange="this.form.submit()">
                        </label>
                        <input type="hidden" name="upload_photo" value="1">
                    </form>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($admin['full_name'] ?? 'N/A'); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></p>
                    <p><i class="fas fa-user-tag"></i> <?php echo ucfirst($admin['role'] ?? 'admin'); ?></p>
                </div>
            </div>

            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('info')">Personal Info</button>
                <button class="tab-btn" onclick="showTab('password')">Change Password</button>
            </div>

            <!-- Personal Info Tab -->
            <div id="info-tab" class="tab-pane active">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" placeholder="Enter phone number">
                    </div>
                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-pane">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-save">Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- AI Assistant Widget (if used) -->
    <script src="../assets/js/ai-widget.js"></script>
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
    </script>
</body>
</html>