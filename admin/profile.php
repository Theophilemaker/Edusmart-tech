<?php
// Enable error reporting (disable in production)
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

// Check login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get admin data
$admin = [];
try {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);

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
        $update = "UPDATE users SET full_name = ?, email = ?, phone = ?, username = ? WHERE id = ?";
        $stmt = $conn->prepare($update);
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
            $message = "Error updating profile.";
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $errors = [];

    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
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
        $update = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($update);
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

// Handle profile picture upload (optional)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photo'])) {
    // ... (same as before, omitted for brevity, but you can include it)
}

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
    <!-- Include AI widget CSS if used -->
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        /* Add any additional styles if needed */
        .profile-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; }
        .btn-save { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .tabs { display: flex; border-bottom: 2px solid #e0e0e0; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; background: none; border: none; cursor: pointer; color: #666; }
        .tab-btn.active { color: #667eea; font-weight: 600; border-bottom: 2px solid #667eea; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
    </style>
</head>
<body class="admin-dashboard">
    <?php include '../includes/sidebar.php'; ?> <!-- Make sure this file exists -->

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
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('info')">Personal Info</button>
                <button class="tab-btn" onclick="showTab('password')">Change Password</button>
            </div>

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
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>

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

    <!-- AI Widget Script (if used) -->
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