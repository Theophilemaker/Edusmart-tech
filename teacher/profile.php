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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get teacher info
$teacher = [];
try {
    $query = "SELECT u.*, t.employee_id, t.specialization, t.qualification, t.bio
              FROM users u
              LEFT JOIN teachers t ON u.id = t.user_id
              WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Error getting teacher info: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $specialization = trim($_POST['specialization']);
        $qualification = trim($_POST['qualification']);
        $bio = trim($_POST['bio']);
        
        $conn->begin_transaction();
        
        try {
            // Update users table
            $query = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $full_name, $email, $phone, $teacher_id);
            $stmt->execute();
            
            // Update teachers table
            $query = "UPDATE teachers SET specialization = ?, qualification = ?, bio = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $specialization, $qualification, $bio, $teacher_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            $message = "Profile updated successfully!";
            $message_type = 'success';
            
            // Refresh teacher data
            $query = "SELECT u.*, t.employee_id, t.specialization, t.qualification, t.bio
                     FROM users u
                     LEFT JOIN teachers t ON u.id = t.user_id
                     WHERE u.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $teacher = $result->fetch_assoc();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating profile: " . $e->getMessage();
            $message_type = 'error';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
    
    elseif ($action == 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        // Verify current password
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($current, $user['password'])) {
            $message = "Current password is incorrect";
            $message_type = 'error';
        } elseif (strlen($new) < 6) {
            $message = "New password must be at least 6 characters";
            $message_type = 'error';
        } elseif ($new !== $confirm) {
            $message = "New passwords do not match";
            $message_type = 'error';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $hashed, $teacher_id);
            
            if ($stmt->execute()) {
                $message = "Password changed successfully!";
                $message_type = 'success';
            } else {
                $message = "Error changing password";
                $message_type = 'error';
            }
        }
    }
    
    elseif ($action == 'upload_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $target_dir = "../uploads/profile/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed)) {
                $new_filename = 'teacher_' . $teacher_id . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    $query = "UPDATE users SET profile_pic = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("si", $new_filename, $teacher_id);
                    
                    if ($stmt->execute()) {
                        $message = "Profile photo updated successfully!";
                        $message_type = 'success';
                        $teacher['profile_pic'] = $new_filename;
                    }
                }
            } else {
                $message = "Invalid file type. Allowed: JPG, PNG, GIF";
                $message_type = 'error';
            }
        }
    }
}

// Get statistics
$stats = [];
try {
    // Total courses
    $query = "SELECT COUNT(*) as total FROM courses WHERE teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['courses'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total students
    $query = "SELECT COUNT(DISTINCT e.student_id) as total
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['students'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total exams
    $query = "SELECT COUNT(*) as total
              FROM exams e
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['exams'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Member since
    $stats['member_since'] = date('F Y', strtotime($teacher['created_at'] ?? 'now'));
    
} catch (Exception $e) {
    error_log("Error getting stats: " . $e->getMessage());
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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

        /* Sidebar Styles */
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Top Navigation */
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

        /* Profile Container */
        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .profile-photo {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }

        .photo-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #667eea;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-upload:hover {
            background: #764ba2;
            transform: scale(1.1);
        }

        .photo-upload input {
            display: none;
        }

        .profile-name {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 5px;
        }

        .profile-role {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .profile-id {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #666;
            display: inline-block;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            margin: 20px 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            font-size: 0.8em;
            color: #666;
        }

        .profile-contact {
            text-align: left;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            color: #666;
        }

        .contact-item i {
            width: 20px;
            color: #667eea;
        }

        /* Profile Main */
        .profile-main {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .tab-nav {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            font-size: 1em;
            color: #666;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: #667eea;
        }

        .tab-btn.active {
            color: #667eea;
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
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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

        .btn-cancel {
            padding: 12px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 10px;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        /* Alert Styles */
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

        /* Responsive */
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
            
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .tab-nav {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h3><?php echo htmlspecialchars($teacher['full_name'] ?? 'Teacher'); ?></h3>
                <p>Teacher</p>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="courses.php" class="menu-item">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
                <a href="students.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="exams.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Exams</span>
                </a>
                <a href="grade.php" class="menu-item">
                    <i class="fas fa-star"></i>
                    <span>Grade</span>
                </a>
                <a href="attendance.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="profile.php" class="menu-item active">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
                <a href="../auth/logout.php" class="menu-item" style="margin-top: 50px;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <h1>My Profile</h1>
                    <p>Manage your account settings and profile information</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge">0</span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="<?php 
                            if (!empty($teacher['profile_pic'])) {
                                echo '../uploads/profile/' . $teacher['profile_pic'];
                            } else {
                                echo 'https://ui-avatars.com/api/?name=' . urlencode($teacher['full_name'] ?? 'Teacher') . '&background=f72585&color=fff&size=45';
                            }
                        ?>" class="profile-img">
                        <span><?php echo htmlspecialchars($teacher['full_name'] ?? 'Teacher'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Container -->
            <div class="profile-container">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-photo">
                        <img src="<?php 
                            if (!empty($teacher['profile_pic'])) {
                                echo '../uploads/profile/' . $teacher['profile_pic'];
                            } else {
                                echo 'https://ui-avatars.com/api/?name=' . urlencode($teacher['full_name'] ?? 'Teacher') . '&size=150';
                            }
                        ?>" alt="Profile Photo">
                        
                        <form method="POST" enctype="multipart/form-data" id="photoForm">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <label class="photo-upload">
                                <i class="fas fa-camera"></i>
                                <input type="file" name="profile_photo" accept="image/*" onchange="this.form.submit()">
                            </label>
                        </form>
                    </div>
                    
                    <h2 class="profile-name"><?php echo htmlspecialchars($teacher['full_name'] ?? 'N/A'); ?></h2>
                    <div class="profile-role">Teacher</div>
                    <div class="profile-id">ID: <?php echo htmlspecialchars($teacher['employee_id'] ?? 'N/A'); ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['courses'] ?? 0; ?></div>
                            <div class="stat-label">Courses</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['students'] ?? 0; ?></div>
                            <div class="stat-label">Students</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['exams'] ?? 0; ?></div>
                            <div class="stat-label">Exams</div>
                        </div>
                    </div>
                    
                    <div class="profile-contact">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($teacher['phone'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined <?php echo $stats['member_since'] ?? 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Main -->
                <div class="profile-main">
                    <div class="tab-nav">
                        <button class="tab-btn active" onclick="showTab('profile')">Profile Information</button>
                        <button class="tab-btn" onclick="showTab('password')">Change Password</button>
                        <button class="tab-btn" onclick="showTab('settings')">Account Settings</button>
                    </div>
                    
                    <!-- Profile Tab -->
                    <div id="profile-tab" class="tab-pane active">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Specialization</label>
                                    <input type="text" name="specialization" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Qualification</label>
                                <input type="text" name="qualification" class="form-control" 
                                       value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Bio / About Me</label>
                                <textarea name="bio" class="form-control" placeholder="Tell something about yourself..."><?php echo htmlspecialchars($teacher['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                    
                    <!-- Password Tab -->
                    <div id="password-tab" class="tab-pane">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                                <small style="color: #666;">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn-save">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                            <button type="reset" class="btn-cancel">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </form>
                    </div>
                    
                    <!-- Settings Tab -->
                    <div id="settings-tab" class="tab-pane">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label>Email Notifications</label>
                                <select name="email_notifications" class="form-control">
                                    <option value="1">Receive email notifications</option>
                                    <option value="0">Don't receive email notifications</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Language Preference</label>
                                <select name="language" class="form-control">
                                    <option value="en">English</option>
                                    <option value="fr">French</option>
                                    <option value="es">Spanish</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Time Zone</label>
                                <select name="timezone" class="form-control">
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">Eastern Time</option>
                                    <option value="America/Chicago">Central Time</option>
                                    <option value="America/Denver">Mountain Time</option>
                                    <option value="America/Los_Angeles">Pacific Time</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
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
        
        // Password match validation
        document.querySelector('form[action="change_password"]')?.addEventListener('submit', function(e) {
            const newPass = this.querySelector('input[name="new_password"]').value;
            const confirmPass = this.querySelector('input[name="confirm_password"]').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New passwords do not match!');
            }
        });
    </script>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>