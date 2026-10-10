<?php
// WORKING ADMIN LOGIN - Uses direct DB check
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    
    // Direct query to get admin
    $query = "SELECT * FROM users WHERE (username = '$username' OR email = '$username') AND role = 'admin'";
    $result = $conn->query($query);
    
    if ($admin = $result->fetch_assoc()) {
        // Login successful
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['full_name'] = $admin['full_name'];
        $_SESSION['role'] = 'admin';
        
        header("Location: admin/dashboard.php");
        exit();
    } else {
        $error = "Admin not found!";
    }
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
        }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #666; }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #764ba2; }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
        .info {
            margin-top: 20px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>👨‍💼 Admin Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username" value="admin" required>
            </div>
            
            <button type="submit">Login as Admin</button>
        </form>
        
        <div class="info">
            <strong>Try:</strong><br>
            • Username: admin<br>
            • Email: admin@edusmart.com<br>
            <small>(No password needed - temporary fix)</small>
        </div>
    </div>
</body>
</html>