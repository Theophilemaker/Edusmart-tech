<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $conn->real_escape_string(trim($_POST['full_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $conn->real_escape_string($_POST['role']);
    
    // Validation
    $errors = [];
    
    if (strlen($full_name) < 3) $errors[] = "Full name must be at least 3 characters";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (strlen($username) < 4) $errors[] = "Username must be at least 4 characters";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    
    // Check if email exists
    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($check->num_rows > 0) $errors[] = "Email already exists";
    
    // Check if username exists
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) $errors[] = "Username already taken";
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $query = "INSERT INTO users (username, email, phone, password, full_name, role) 
                  VALUES ('$username', '$email', '$phone', '$hashed_password', '$full_name', '$role')";
        
        if ($conn->query($query)) {
            $user_id = $conn->insert_id;
            
            if ($role == 'student') {
                $admission_no = 'STU' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $conn->query("INSERT INTO students (user_id, admission_no) VALUES ($user_id, '$admission_no')");
            } elseif ($role == 'teacher') {
                $employee_id = 'TCH' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $conn->query("INSERT INTO teachers (user_id, employee_id) VALUES ($user_id, '$employee_id')");
            }
            
            // Log registration (but don't send email)
            $logFile = '../logs/registrations.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) mkdir($logDir, 0777, true);
            
            $logEntry = date('Y-m-d H:i:s') . " - New registration: $full_name ($email) as $role\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
            $success = "Registration successful! You can now login.";
        } else {
            $error = "Registration failed: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card" style="max-width: 650px;">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Account</h2>
                <p>Join <?php echo SITE_NAME; ?> today</p>
            </div>
            
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <p style="margin-top: 15px;">
                        <a href="login.php" class="btn-enroll" style="display: inline-block;">Login Now</a>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="full_name" required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <i class="fas fa-at"></i>
                    <input type="text" class="form-control" name="username" required placeholder="Choose a username">
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <i class="fas fa-phone"></i>
                    <input type="tel" class="form-control" name="phone" required placeholder="+1234567890">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" required placeholder="Create a password" minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="confirm_password" required placeholder="Confirm your password">
                </div>
                
                <div class="form-group">
                    <label>Register as</label>
                    <i class="fas fa-users"></i>
                    <select class="form-control" name="role" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-user-plus"></i> Register
                </button>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p>Already have an account? <a href="login.php" style="color: var(--primary);">Login here</a></p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>