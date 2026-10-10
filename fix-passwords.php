<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Password Fix Tool</title>
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
    <h1>🔐 Password Fix Tool</h1>";

// Show all users
$users = $conn->query("SELECT id, username, email, full_name, role, is_active FROM users ORDER BY id");
echo "<h2>Current Users in Database:</h2>";
echo "<table>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Role</th><th>Status</th></tr>";
while ($user = $users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['full_name']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>" . ($user['is_active'] ? '✅ Active' : '❌ Inactive') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Handle password reset
if (isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $hashed, $user_id);
    
    if ($stmt->execute()) {
        echo "<div class='success'>✅ Password reset successfully!<br>";
        echo "User ID: $user_id<br>";
        echo "New Password: $new_password<br>";
        echo "Hash: $hashed</div>";
    } else {
        echo "<div class='error'>❌ Error: " . $conn->error . "</div>";
    }
}

// Handle test login
if (isset($_POST['test_login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    echo "<div class='info'>";
    echo "<h3>Login Test for: $email</h3>";
    
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "✅ User found<br>";
        echo "ID: {$row['id']}<br>";
        echo "Name: {$row['full_name']}<br>";
        echo "Role: {$row['role']}<br>";
        echo "Active: " . ($row['is_active'] ? 'Yes' : 'No') . "<br>";
        echo "Stored Hash: {$row['password']}<br>";
        
        if (password_verify($password, $row['password'])) {
            echo "✅ <strong>Password CORRECT!</strong><br>";
        } else {
            echo "❌ <strong>Password INCORRECT!</strong><br>";
            
            // Generate new hash for comparison
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "What hash should look like: $new_hash<br>";
        }
    } else {
        echo "❌ User not found with email: $email<br>";
    }
    echo "</div>";
}

// Handle fix all passwords
if (isset($_POST['fix_all'])) {
    echo "<div class='info'>";
    echo "<h3>Resetting All User Passwords:</h3>";
    
    $users = $conn->query("SELECT id, email, role FROM users");
    while ($user = $users->fetch_assoc()) {
        $default_password = ($user['role'] == 'admin') ? 'admin123' : 'password123';
        $hashed = password_hash($default_password, PASSWORD_DEFAULT);
        
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $user['id']);
        
        if ($update->execute()) {
            echo "✅ {$user['email']} ({$user['role']}) -> Password: $default_password<br>";
        } else {
            echo "❌ Failed for {$user['email']}<br>";
        }
    }
    echo "</div>";
}

?>

<h2>Reset Single User Password</h2>
<form method="POST">
    <select name="user_id" required>
        <option value="">Select User</option>
        <?php 
        $users = $conn->query("SELECT id, username, email, role FROM users");
        while ($user = $users->fetch_assoc()) {
            echo "<option value='{$user['id']}'>[{$user['role']}] {$user['username']} - {$user['email']}</option>";
        }
        ?>
    </select><br>
    <input type="text" name="new_password" placeholder="New Password" value="password123" required><br>
    <button type="submit" name="reset_password">Reset Password</button>
</form>

<h2>Test Login</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Email" value="admin@edusmart.com" required><br>
    <input type="text" name="password" placeholder="Password" value="password123" required><br>
    <button type="submit" name="test_login">Test Login</button>
</form>

<h2>Fix All Users</h2>
<form method="POST" onsubmit="return confirm('This will reset ALL passwords. Continue?')">
    <button type="submit" name="fix_all" style="background: #f72585;">Reset All Passwords</button>
</form>

<p style="color:red; margin-top:30px;"><strong>⚠️ DELETE THIS FILE AFTER USE!</strong></p>

</body>
</html>