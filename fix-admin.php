<?php
// IMPORTANT: Delete this file after use!
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Fix Tool</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin: 10px 0; }
        .info { background: #e7f3ff; color: #004085; padding: 15px; border-radius: 10px; margin: 10px 0; }
        button { background: #4361ee; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        input { padding: 8px; width: 300px; margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🔧 Admin Password Fix Tool</h1>";

// Check if form submitted
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'reset_admin') {
        // Reset admin password
        $new_password = $_POST['password'] ?? 'admin123';
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // First check if admin exists
        $check = $conn->query("SELECT id, email FROM users WHERE role = 'admin' OR email = 'admin@edusmart.com'");
        
        if ($check->num_rows > 0) {
            $admin = $check->fetch_assoc();
            
            $update = "UPDATE users SET password = '$hashed', reset_token = NULL, reset_expires = NULL WHERE id = {$admin['id']}";
            
            if ($conn->query($update)) {
                echo "<div class='success'>✅ Admin password reset successfully!<br>";
                echo "Email: {$admin['email']}<br>";
                echo "New Password: " . htmlspecialchars($new_password) . "</div>";
            } else {
                echo "<div class='error'>❌ Error: " . $conn->error . "</div>";
            }
        } else {
            // Create new admin
            $hashed = password_hash('admin123', PASSWORD_DEFAULT);
            $insert = "INSERT INTO users (username, email, password, full_name, role) 
                      VALUES ('admin', 'admin@edusmart.com', '$hashed', 'System Administrator', 'admin')";
            
            if ($conn->query($insert)) {
                echo "<div class='success'>✅ New admin created!<br>";
                echo "Email: admin@edusmart.com<br>";
                echo "Password: admin123</div>";
            } else {
                echo "<div class='error'>❌ Error: " . $conn->error . "</div>";
            }
        }
    }
    elseif ($_POST['action'] == 'check_users') {
        echo "<div class='info'><h3>Current Users:</h3>";
        $users = $conn->query("SELECT id, username, email, role, full_name FROM users");
        echo "<table border='1' cellpadding='8' style='width:100%; border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th></tr>";
        while($user = $users->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "</tr>";
        }
        echo "</table></div>";
    }
    elseif ($_POST['action'] == 'fix_database') {
        // Add reset token columns if missing
        $alter1 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL AFTER password";
        $alter2 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL AFTER reset_token";
        
        if ($conn->query($alter1) && $conn->query($alter2)) {
            echo "<div class='success'>✅ Database fixed! Reset token columns added.</div>";
        } else {
            echo "<div class='error'>❌ Error: " . $conn->error . "</div>";
        }
    }
}

// Show current admin info
$admin_check = $conn->query("SELECT id, email, role FROM users WHERE role = 'admin' OR email = 'admin@edusmart.com'");
?>

<div class="info">
    <h3>Current Admin Status:</h3>
    <?php if ($admin_check->num_rows > 0): 
        $admin = $admin_check->fetch_assoc();
    ?>
        <p>✅ Admin found: <?php echo $admin['email']; ?> (ID: <?php echo $admin['id']; ?>)</p>
    <?php else: ?>
        <p>❌ No admin found! Will create new one.</p>
    <?php endif; ?>
</div>

<h2>Quick Fix Options:</h2>

<form method="POST" style="margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 10px;">
    <h3>1. Reset Admin Password</h3>
    <input type="hidden" name="action" value="reset_admin">
    <label>New Password:</label><br>
    <input type="text" name="password" value="admin123" required><br>
    <button type="submit">Reset Admin Password</button>
</form>

<form method="POST" style="margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 10px;">
    <h3>2. Check All Users</h3>
    <input type="hidden" name="action" value="check_users">
    <button type="submit">Show All Users</button>
</form>

<form method="POST" style="margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 10px;">
    <h3>3. Fix Database (Add Reset Columns)</h3>
    <input type="hidden" name="action" value="fix_database">
    <button type="submit">Fix Database</button>
</form>

<div class="info">
    <h3>Default Login After Fix:</h3>
    <p><strong>Email:</strong> admin@edusmart.com</p>
    <p><strong>Password:</strong> admin123</p>
</div>

<p style="color: red; margin-top: 30px;">
    <strong>⚠️ IMPORTANT: Delete this file (fix-admin.php) after fixing!</strong>
</p>

</body>
</html>