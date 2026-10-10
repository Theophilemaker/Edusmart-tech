<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Login Fix</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin: 10px 0; }
        .info { background: #e7f3ff; color: #004085; padding: 15px; border-radius: 10px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        button { background: #4361ee; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        input { padding: 8px; width: 300px; margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🔐 Admin Login Fix Tool</h1>";

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows == 0) {
    echo "<div class='error'>❌ Users table does not exist! Creating...</div>";
    
    // Create users table
    $sql = "CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin','teacher','student') DEFAULT 'student',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Users table created successfully</div>";
    } else {
        echo "<div class='error'>❌ Error creating table: " . $conn->error . "</div>";
    }
}

// Show all users
$users = $conn->query("SELECT id, username, email, role, is_active FROM users ORDER BY id");
echo "<h2>Current Users:</h2>";
if ($users->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
    while ($user = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . ($user['is_active'] ? '✅ Active' : '❌ Inactive') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found</p>";
}

// Handle creating admin
if (isset($_POST['create_admin'])) {
    $email = $_POST['email'] ?? 'admin@edusmart.com';
    $password = $_POST['password'] ?? 'admin123';
    $username = $_POST['username'] ?? 'admin';
    $full_name = $_POST['full_name'] ?? 'System Administrator';
    
    // Check if admin already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->bind_param("ss", $email, $username);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>⚠️ User with this email/username already exists. Updating password...</div>";
        
        // Update existing user
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, role = 'admin', is_active = 1 WHERE email = ? OR username = ?");
        $update->bind_param("sss", $hashed, $email, $username);
        
        if ($update->execute()) {
            echo "<div class='success'>✅ Admin password updated successfully!</div>";
            echo "<div class='success'>Email: $email<br>Password: $password</div>";
        } else {
            echo "<div class='error'>❌ Error updating: " . $conn->error . "</div>";
        }
    } else {
        // Create new admin
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
        $insert->bind_param("ssss", $username, $email, $hashed, $full_name);
        
        if ($insert->execute()) {
            echo "<div class='success'>✅ Admin created successfully!</div>";
            echo "<div class='success'>Email: $email<br>Password: $password</div>";
        } else {
            echo "<div class='error'>❌ Error creating: " . $conn->error . "</div>";
        }
    }
}

// Handle resetting all passwords
if (isset($_POST['reset_all'])) {
    $default_password = 'admin123';
    $hashed = password_hash($default_password, PASSWORD_DEFAULT);
    
    $users = $conn->query("SELECT id, email, role FROM users");
    $count = 0;
    
    while ($user = $users->fetch_assoc()) {
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $user['id']);
        if ($update->execute()) {
            $count++;
        }
    }
    
    echo "<div class='success'>✅ Reset $count user passwords to: $default_password</div>";
}

// Handle fixing admin role
if (isset($_POST['fix_admin_role'])) {
    $update = $conn->query("UPDATE users SET role = 'admin' WHERE email = 'admin@edusmart.com' OR username = 'admin'");
    echo "<div class='success'>✅ Admin role fixed</div>";
}

// Test login
if (isset($_POST['test_login'])) {
    $email = $_POST['test_email'];
    $password = $_POST['test_password'];
    
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        echo "<div class='info'>";
        echo "<h3>Login Test Result:</h3>";
        echo "User found: {$user['email']}<br>";
        echo "Role: {$user['role']}<br>";
        echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "<br>";
        
        if (password_verify($password, $user['password'])) {
            echo "<span style='color:green; font-weight:bold'>✅ Password CORRECT! Login will work.</span><br>";
        } else {
            echo "<span style='color:red; font-weight:bold'>❌ Password INCORRECT!</span><br>";
            echo "Stored hash: {$user['password']}<br>";
            echo "New hash for '$password': " . password_hash($password, PASSWORD_DEFAULT) . "<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='error'>❌ User not found with email: $email</div>";
    }
}

?>

<h2>Create/Reset Admin</h2>
<form method="POST">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 10px;">
        <div style="margin-bottom: 10px;">
            <label>Email:</label><br>
            <input type="email" name="email" value="admin@edusmart.com" required>
        </div>
        <div style="margin-bottom: 10px;">
            <label>Password:</label><br>
            <input type="text" name="password" value="admin123" required>
        </div>
        <div style="margin-bottom: 10px;">
            <label>Username:</label><br>
            <input type="text" name="username" value="admin">
        </div>
        <div style="margin-bottom: 10px;">
            <label>Full Name:</label><br>
            <input type="text" name="full_name" value="System Administrator">
        </div>
        <button type="submit" name="create_admin">Create/Reset Admin</button>
    </div>
</form>

<h2>Test Login</h2>
<form method="POST">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 10px;">
        <div style="margin-bottom: 10px;">
            <label>Email:</label><br>
            <input type="email" name="test_email" value="admin@edusmart.com" required>
        </div>
        <div style="margin-bottom: 10px;">
            <label>Password:</label><br>
            <input type="text" name="test_password" value="admin123" required>
        </div>
        <button type="submit" name="test_login">Test Login</button>
    </div>
</form>

<h2>Quick Actions</h2>
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <form method="POST">
        <button type="submit" name="reset_all" style="background: #f59e0b;">Reset All Passwords to 'admin123'</button>
    </form>
    <form method="POST">
        <button type="submit" name="fix_admin_role" style="background: #10b981;">Fix Admin Role</button>
    </form>
    <form action="auth/login.php" method="get">
        <button type="submit" style="background: #4361ee;">Go to Login Page</button>
    </form>
</div>

<p style="color: red; margin-top: 30px;"><strong>⚠️ DELETE THIS FILE AFTER USE!</strong></p>

</body>
</html>