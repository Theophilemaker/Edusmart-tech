<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

$error = '';
$success = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login']);
    $role = $_POST['role'];
    $remember = isset($_POST['remember']);

    $query = "SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $login, $login, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Check if account is active
        if (isset($user['is_active']) && $user['is_active'] == 0) {
            $error = "Your account has been deactivated. Contact admin.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Update last login
            $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");

            // Merge guest cart into user cart
            if (isset($_SESSION['cart_session'])) {
                $session_id = $_SESSION['cart_session'];
                // Transfer cart items from session_id to user_id
                $conn->query("UPDATE cart SET user_id = {$user['id']} WHERE session_id = '$session_id' AND user_id IS NULL");
            }

            // Redirect logic: if a redirect URL was provided (e.g., from checkout), go there; otherwise go to products page.
            if (!empty($redirect)) {
                header("Location: $redirect");
            } else {
                // ***** All users now go to products.php after login *****
                header("Location: ../products.php");
            }
            exit();
        }
    } else {
        $error = "Invalid credentials for selected role!";
    }
}

// Get counts for badges
$count_admins   = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin' OR role='super_admin'")->fetch_assoc()['total'] ?? 0;
$count_teachers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='teacher'")->fetch_assoc()['total'] ?? 0;
$count_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student'")->fetch_assoc()['total'] ?? 0;
$count_employers= $conn->query("SELECT COUNT(*) as total FROM users WHERE role='employer'")->fetch_assoc()['total'] ?? 0;

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image:url("logi.jpg"); 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-wrapper { width:100%; max-width:480px; animation:fadeIn 0.6s ease; }
        @keyframes fadeIn { from{opacity:0; transform:translateY(20px);} to{opacity:1; transform:translateY(0);} }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content:'';
            position: absolute;
            top:0; left:0; right:0;
            height:6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
        }
        .login-header { text-align:center; margin-bottom:32px; }
        .logo {
            width:90px; height:90px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius:50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin:0 auto 20px;
            color: white;
            font-size:2.5em;
            box-shadow:0 10px 20px rgba(102,126,234,0.3);
            transition:transform 0.3s ease;
        }
        .logo:hover { transform:scale(1.05); }
        .login-header h2 { color:#1a1a1a; font-size:2em; font-weight:600; margin-bottom:8px; }
        .login-header p { color:#666; font-size:1em; }

        .role-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 6px;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
            justify-content: center;
        }
        .role-option { flex:1 1 auto; min-width:90px; position:relative; }
        .role-option input[type="radio"] { display:none; }
        .role-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 12px;
            border-radius: 40px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }
        .role-option input[type="radio"]:checked + label {
            background: white;
            color: #667eea;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .role-option.employer input[type="radio"]:checked + label { color: #8b5cf6; }
        .role-option label i { font-size:1.1em; }
        .role-option .badge {
            position: absolute;
            top: -8px; right: -8px;
            background: #ef4444;
            color: white;
            font-size:0.7em;
            padding:2px 6px;
            border-radius:12px;
            min-width:20px;
            text-align:center;
        }

        .alert {
            padding:16px; border-radius:12px; margin-bottom:24px; display:flex; align-items:center; gap:12px; animation:slideIn 0.3s ease;
        }
        @keyframes slideIn { from{opacity:0; transform:translateX(-10px);} to{opacity:1; transform:translateX(0);} }
        .alert-error { background:#fef2f2; color:#991b1b; border-left:4px solid #dc2626; }
        .alert-success { background:#f0fdf4; color:#166534; border-left:4px solid #16a34a; }
        .alert i { font-size:1.2em; }

        .form-group { margin-bottom:24px; }
        .form-group label { display:block; margin-bottom:8px; color:#1e293b; font-weight:500; font-size:0.95em; }
        .input-wrapper { position:relative; display:flex; align-items:center; }
        .input-icon { position:absolute; left:16px; color:#94a3b8; font-size:1.1em; transition:color 0.3s ease; z-index:1; }
        .form-control {
            width:100%;
            padding:14px 16px 14px 48px;
            border:2px solid #e2e8f0;
            border-radius:12px;
            font-size:1em;
            transition:all 0.3s ease;
            background:white;
        }
        .form-control:focus { outline:none; border-color:#667eea; box-shadow:0 0 0 4px rgba(102,126,234,0.1); }
        .form-control:focus + .input-icon { color:#667eea; }

        .input-hint { margin-top:8px; font-size:0.85em; color:#64748b; display:flex; align-items:center; gap:6px; }
        .input-hint i { font-size:0.9em; color:#94a3b8; }

        .options-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
        .remember-me { display:flex; align-items:center; gap:8px; color:#4b5563; cursor:pointer; }
        .remember-me input[type="checkbox"] { width:18px; height:18px; cursor:pointer; accent-color:#667eea; }
        .forgot-link { color:#667eea; text-decoration:none; font-size:0.95em; font-weight:500; transition:color 0.3s ease; }
        .forgot-link:hover { color:#764ba2; text-decoration:underline; }

        .btn-login {
            width:100%; padding:16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:white; border:none; border-radius:12px;
            font-size:1.1em; font-weight:600; cursor:pointer;
            transition:all 0.3s ease;
            position:relative; overflow:hidden;
        }
        .btn-login:hover { transform:translateY(-2px); box-shadow:0 10px 25px -5px rgba(102,126,234,0.5); }
        .btn-login:active { transform:translateY(0); }
        .btn-login i { margin-right:8px; }

        .quick-access { margin-top:32px; }
        .quick-access h3 { color:#1e293b; font-size:1em; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .quick-access h3 i { color:#667eea; }

        .user-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .user-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .user-card:hover { transform:translateY(-3px); box-shadow:0 10px 20px -5px rgba(0,0,0,0.1); }
        .user-card.admin:hover { border-color:#667eea; background:#eef2ff; }
        .user-card.teacher:hover { border-color:#f72585; background:#fdf2f8; }
        .user-card.student:hover { border-color:#4cc9f0; background:#e6f7ff; }
        .user-card.employer:hover { border-color:#8b5cf6; background:#ede9fe; }

        .user-card i { font-size:2em; margin-bottom:6px; }
        .user-card.admin i { color:#667eea; }
        .user-card.teacher i { color:#f72585; }
        .user-card.student i { color:#4cc9f0; }
        .user-card.employer i { color:#8b5cf6; }

        .user-card h4 { color:#1e293b; font-size:0.95em; margin-bottom:4px; }
        .user-card p { color:#64748b; font-size:0.75em; margin-bottom:6px; word-break:break-word; }
        .user-badge {
            display:inline-block; padding:3px 6px; background:#e2e8f0; border-radius:20px;
            font-size:0.65em; color:#475569; font-weight:600;
        }
        .user-card.admin .user-badge { background:#667eea20; color:#667eea; }
        .user-card.teacher .user-badge { background:#f7258520; color:#f72585; }
        .user-card.student .user-badge { background:#4cc9f020; color:#4cc9f0; }
        .user-card.employer .user-badge { background:#8b5cf620; color:#8b5cf6; }

        .register-link { text-align:center; margin-top:24px; padding-top:24px; border-top:1px solid #e2e8f0; color:#64748b; }
        .register-link a { color:#667eea; font-weight:600; text-decoration:none; margin-left:4px; transition:color 0.3s ease; }
        .register-link a:hover { color:#764ba2; text-decoration:underline; }
        .login-footer { text-align:center; margin-top:20px; color:rgba(255,255,255,0.8); font-size:0.9em; }
        .btn-loading { position:relative; pointer-events:none; opacity:0.7; }
        .btn-loading i { animation:spin 1s linear infinite; }
        @keyframes spin { from{transform:rotate(0deg);} to{transform:rotate(360deg);} }

        @media (max-width:640px) {
            .login-card { padding:30px 20px; }
            .user-cards { grid-template-columns:1fr; }
            .role-selector { flex-direction:column; }
            .role-option label { padding:10px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2> most Welcome dear </h2>
                <p>Sign in to continue to <?php echo SITE_NAME; ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <?php if ($redirect): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <?php endif; ?>
                
                <!-- Role Selection -->
                <div class="role-selector">
                    <div class="role-option admin">
                        <input type="radio" name="role" id="role-admin" value="admin" checked>
                        <label for="role-admin">
                            <i class="fas fa-user-shield"></i>
                            <span>Admin</span>
                        </label>
                        <?php if ($count_admins > 0): ?>
                            <span class="badge"><?php echo $count_admins; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="role-option teacher">
                        <input type="radio" name="role" id="role-teacher" value="teacher">
                        <label for="role-teacher">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Teacher</span>
                        </label>
                        <?php if ($count_teachers > 0): ?>
                            <span class="badge"><?php echo $count_teachers; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="role-option student">
                        <input type="radio" name="role" id="role-student" value="student">
                        <label for="role-student">
                            <i class="fas fa-user-graduate"></i>
                            <span>Student</span>
                        </label>
                        <?php if ($count_students > 0): ?>
                            <span class="badge"><?php echo $count_students; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="role-option employer">
                        <input type="radio" name="role" id="role-employer" value="employer">
                        <label for="role-employer">
                            <i class="fas fa-briefcase"></i>
                            <span>Employer</span>
                        </label>
                        <?php if ($count_employers > 0): ?>
                            <span class="badge"><?php echo $count_employers; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Login Input -->
                <div class="form-group">
                    <label for="login">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon" id="inputIcon"></i>
                        <input type="text" class="form-control" id="login" name="login" 
                               placeholder="Enter your username or email" 
                               value="admin" required>
                    </div>
                    <div class="input-hint" id="inputHint">
                        <i class="fas fa-info-circle"></i>
                        <span id="hintText">Admin access</span>
                    </div>
                </div>
                
                <!-- Options -->
                <div class="options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> 
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
                
                <!-- Login Button -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>
            
            <!-- Quick Access Cards -->
            <div class="quick-access">
                <h3>
                    <i class="fas fa-bolt"></i>
                    Quick Access
                </h3>
                <div class="user-cards">
                    <div class="user-card admin" onclick="selectRoleAndFill('admin', 'admin')">
                        <i class="fas fa-user-shield"></i>
                        <h4>Admin</h4>
                        <p>admin</p>
                        <span class="user-badge"><?php echo $count_admins; ?> account</span>
                    </div>
                    <div class="user-card teacher" onclick="selectRoleAndFill('teacher', 'teacher@edusmart.com')">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h4>Teacher</h4>
                        <p>teacher@edusmart.com</p>
                        <span class="user-badge"><?php echo $count_teachers; ?> accounts</span>
                    </div>
                    <div class="user-card student" onclick="selectRoleAndFill('student', 'student@edusmart.com')">
                        <i class="fas fa-user-graduate"></i>
                        <h4>Student</h4>
                        <p>student@edusmart.com</p>
                        <span class="user-badge"><?php echo $count_students; ?> accounts</span>
                    </div>
                    <div class="user-card employer" onclick="selectRoleAndFill('employer', 'employer@example.com')">
                        <i class="fas fa-briefcase"></i>
                        <h4>Employer</h4>
                        <p>employer@example.com</p>
                        <span class="user-badge"><?php echo $count_employers; ?> accounts</span>
                    </div>
                </div>
            </div>
            
            <!-- Register Links -->
            <div class="register-link">
                <span>Don't have an account?</span>
                <a href="register.php">create account </a>
                <span> | </span>
                <a href="register-employer.php">Employer</a>
            </div>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        // Role selection handler
        const roleRadios = document.querySelectorAll('input[name="role"]');
        const loginInput = document.getElementById('login');
        const inputIcon = document.getElementById('inputIcon');
        const hintText = document.getElementById('hintText');
        const loginCard = document.querySelector('.login-card');
        
        const roleConfig = {
            admin: {
                icon: 'fa-user-shield',
                placeholder: 'Enter admin username',
                hint: 'Admin access',
                example: 'admin',
                color: '#667eea'
            },
            teacher: {
                icon: 'fa-chalkboard-teacher',
                placeholder: 'Enter teacher email',
                hint: 'Teacher access',
                example: 'teacher@edusmart.com',
                color: '#f72585'
            },
            student: {
                icon: 'fa-user-graduate',
                placeholder: 'Enter student email',
                hint: 'Student access',
                example: 'student@edusmart.com',
                color: '#4cc9f0'
            },
            employer: {
                icon: 'fa-briefcase',
                placeholder: 'Enter employer email or username',
                hint: 'Employer access',
                example: 'employer@example.com',
                color: '#8b5cf6'
            }
        };
        
        function updateRoleUI(role) {
            const config = roleConfig[role];
            inputIcon.className = `fas ${config.icon} input-icon`;
            loginInput.placeholder = config.placeholder;
            hintText.textContent = config.hint;
            loginCard.className = `login-card role-${role}`;
            if (!loginInput.value) {
                loginInput.value = config.example;
            }
        }
        
        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) updateRoleUI(this.value);
            });
        });
        
        function selectRoleAndFill(role, value) {
            document.getElementById(`role-${role}`).checked = true;
            updateRoleUI(role);
            loginInput.value = value;
            loginInput.style.backgroundColor = '#f0f9ff';
            loginInput.style.borderColor = roleConfig[role].color;
            setTimeout(() => {
                loginInput.style.backgroundColor = '';
                loginInput.style.borderColor = '';
            }, 500);
        }
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            btn.classList.add('btn-loading');
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
                btn.classList.remove('btn-loading');
            }, 5000);
        });
        
        updateRoleUI('admin');
        
        loginInput.addEventListener('focus', function() {
            const role = document.querySelector('input[name="role"]:checked').value;
            this.style.borderColor = roleConfig[role].color;
            inputIcon.style.color = roleConfig[role].color;
        });
        loginInput.addEventListener('blur', function() {
            this.style.borderColor = '#e2e8f0';
            inputIcon.style.color = '#94a3b8';
        });
    </script>
</body>
</html>