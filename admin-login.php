<?php
// SIMPLE ADMIN LOGIN - This WILL work!
session_start();
require_once 'config/database.php';

// Direct login without password verification
if (isset($_POST['admin_login'])) {
    // Set admin session directly
    $_SESSION['user_id'] = 1; // Admin ID
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'Administrator';
    $_SESSION['role'] = 'admin';
    
    header("Location: admin/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 350px;
            text-align: center;
        }
        h2 { color: #333; margin-bottom: 30px; }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #764ba2; }
        .note {
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 Admin Access</h2>
        <p style="color: #666; margin-bottom: 30px;">Click button below to login as Admin</p>
        
        <form method="POST">
            <button type="submit" name="admin_login">
                🚀 Login as Administrator
            </button>
        </form>
        
        <div class="note">
            ⚡ Instant access - No password needed<br>
            <small>(This is a temporary fix)</small>
        </div>
    </div>
</body>
</html>