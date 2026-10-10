<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    
    // Validation
    $errors = [];
    
    // Validate full name
    if (empty($form_data['full_name'])) {
        $errors[] = "Full name is required";
    } elseif (strlen($form_data['full_name']) < 3) {
        $errors[] = "Full name must be at least 3 characters";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $form_data['full_name'])) {
        $errors[] = "Full name can only contain letters and spaces";
    }
    
    // Validate email
    if (empty($form_data['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $form_data['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        }
    }
    
    // Validate phone
    if (empty($form_data['phone'])) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match("/^[0-9+\-\s]+$/", $form_data['phone'])) {
        $errors[] = "Invalid phone number format";
    }
    
    // Validate username
    if (empty($form_data['username'])) {
        $errors[] = "Username is required";
    } elseif (strlen($form_data['username']) < 4) {
        $errors[] = "Username must be at least 4 characters";
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $form_data['username'])) {
        $errors[] = "Username can only contain letters, numbers and underscore";
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $form_data['username']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Username already taken";
        }
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, create user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert user
            $query = "INSERT INTO users (username, email, phone, password, full_name, role, is_active, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssss", $form_data['username'], $form_data['email'], 
                             $form_data['phone'], $hashed_password, $form_data['full_name'], $role);
            $stmt->execute();
            
            $user_id = $conn->insert_id;
            
            // Create role-specific record
            if ($role == 'student') {
                // Check what columns exist in students table
                $columns = [];
                $col_result = $conn->query("SHOW COLUMNS FROM students");
                while ($col = $col_result->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
                
                // Generate admission number
                $admission_no = 'STU' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                
                // Build insert query based on existing columns
                if (in_array('admission_no', $columns)) {
                    $student_query = "INSERT INTO students (user_id, admission_no) VALUES (?, ?)";
                    $student_stmt = $conn->prepare($student_query);
                    $student_stmt->bind_param("is", $user_id, $admission_no);
                } elseif (in_array('admission_number', $columns)) {
                    $student_query = "INSERT INTO students (user_id, admission_number) VALUES (?, ?)";
                    $student_stmt = $conn->prepare($student_query);
                    $student_stmt->bind_param("is", $user_id, $admission_no);
                } else {
                    // Create minimal insert
                    $student_query = "INSERT INTO students (user_id) VALUES (?)";
                    $student_stmt = $conn->prepare($student_query);
                    $student_stmt->bind_param("i", $user_id);
                }
                
                $student_stmt->execute();
                
            } elseif ($role == 'teacher') {
                $employee_id = 'TCH' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                
                // Check if teachers table exists
                $check_teacher = $conn->query("SHOW TABLES LIKE 'teachers'");
                if ($check_teacher->num_rows > 0) {
                    $teacher_query = "INSERT INTO teachers (user_id, employee_id) VALUES (?, ?)";
                    $teacher_stmt = $conn->prepare($teacher_query);
                    $teacher_stmt->bind_param("is", $user_id, $employee_id);
                    $teacher_stmt->execute();
                }
            }
            
            $conn->commit();
            
            // Log registration
            if (function_exists('logActivity')) {
                logActivity($user_id, 'Registration', 'New user registered as ' . $role, $conn);
            }
            
            // Redirect to login with success message
            header("Location: login.php?registered=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
    
    // If there are errors, join them
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
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
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image:url("regi.png"); 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            width: 100%;
            max-width: 600px;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2em;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .register-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }

        .register-header p {
            color: #666;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f72585;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #4cc9f0;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #999;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-control:focus + i {
            color: #667eea;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
        }

        .field-hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .password-strength.weak { background: #f72585; width: 33%; }
        .password-strength.medium { background: #f59e0b; width: 66%; }
        .password-strength.strong { background: #10b981; width: 100%; }

        .checkbox-group {
            margin: 20px 0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-label a {
            color: #667eea;
            text-decoration: none;
        }

        .checkbox-label a:hover {
            text-decoration: underline;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .demo-note {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 0.9em;
            color: #666;
        }

        .demo-note i {
            color: #667eea;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Account</h2>
                <p>Join <?php echo defined('SITE_NAME') ? SITE_NAME : 'EduSmart Pro'; ?> today</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="full_name" required 
                           value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>"
                           placeholder="Enter your full name"
                           pattern="[A-Za-z\s]+"
                           title="Only letters and spaces allowed">
                    <div class="field-hint">Only letters and spaces allowed</div>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <i class="fas fa-at"></i>
                    <input type="text" class="form-control" name="username" required 
                           value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                           placeholder="Choose a username (min. 4 characters)"
                           minlength="4"
                           pattern="[a-zA-Z0-9_]+"
                           title="Letters, numbers, and underscore only">
                    <div class="field-hint">4+ characters, letters, numbers, underscore</div>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" required 
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                           placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <i class="fas fa-phone"></i>
                    <input type="tel" class="form-control" name="phone" required 
                           value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                           placeholder="+1234567890"
                           pattern="[0-9+\-\s]+"
                           title="Numbers, +, -, and spaces only">
                    <div class="field-hint">Include country code (e.g., +1234567890)</div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" id="password" required 
                           placeholder="Create a password" minlength="6">
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="field-hint">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required 
                           placeholder="Confirm your password">
                    <div id="passwordMatch" style="font-size: 0.85em; margin-top: 5px;"></div>
                </div>
                
                <div class="form-group">
                    <label>Register as</label>
                    <i class="fas fa-users"></i>
                    <select class="form-control" name="role" required>
                        <option value="student" <?php echo (isset($form_data['role']) && $form_data['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo (isset($form_data['role']) && $form_data['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required>
                        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn-register" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Register
                </button>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
            
            <div class="demo-note">
                <i class="fas fa-info-circle"></i>
                By registering, you agree to receive important notifications via email and SMS.
            </div>
        </div>
    </div>
    
    <script>
        // Password strength checker
        const password = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');
        
        if (password) {
            password.addEventListener('input', function() {
                const val = this.value;
                let strength = 0;
                
                if (val.length >= 6) strength++;
                if (val.match(/[a-z]/)) strength++;
                if (val.match(/[A-Z]/)) strength++;
                if (val.match(/[0-9]/)) strength++;
                if (val.match(/[^a-zA-Z0-9]/)) strength++;
                
                strengthDiv.className = 'password-strength';
                if (val.length === 0) {
                    strengthDiv.style.width = '0';
                } else if (strength < 2) {
                    strengthDiv.className += ' weak';
                } else if (strength < 4) {
                    strengthDiv.className += ' medium';
                } else {
                    strengthDiv.className += ' strong';
                }
            });
        }
        
        // Password match checker
        const confirm = document.getElementById('confirm_password');
        const matchDiv = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            if (password && confirm) {
                if (password.value && confirm.value) {
                    if (password.value === confirm.value) {
                        matchDiv.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Passwords match';
                        matchDiv.style.color = '#10b981';
                    } else {
                        matchDiv.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #f72585;"></i> Passwords do not match';
                        matchDiv.style.color = '#f72585';
                    }
                } else {
                    matchDiv.innerHTML = '';
                }
            }
        }
        
        if (password && confirm) {
            password.addEventListener('input', checkPasswordMatch);
            confirm.addEventListener('input', checkPasswordMatch);
        }
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const terms = document.querySelector('input[name="terms"]');
            const passwordMatch = password.value === confirm.value;
            
            if (!terms.checked) {
                e.preventDefault();
                alert('You must agree to the Terms of Service and Privacy Policy');
                return false;
            }
            
            if (!passwordMatch) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>