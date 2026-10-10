<?php
require_once 'config/database.php';

// Check if admin exists
$result = $conn->query("SELECT id, email, role FROM users WHERE role = 'admin' OR email = 'admin@edusmart.com'");

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<h2>✅ Admin Found</h2>";
    echo "ID: " . $admin['id'] . "<br>";
    echo "Email: " . $admin['email'] . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    
    // Generate new password
    $new_password = 'admin123';
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $admin['id']);
    
    if ($stmt->execute()) {
        echo "<h3 style='color:green'>✅ Password reset successful!</h3>";
        echo "<p><strong>New Login Credentials:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Email:</strong> " . $admin['email'] . "</li>";
        echo "<li><strong>Password:</strong> " . $new_password . "</li>";
        echo "</ul>";
        
        // Verify the password works
        $check = $conn->query("SELECT password FROM users WHERE id = " . $admin['id']);
        $row = $check->fetch_assoc();
        if (password_verify($new_password, $row['password'])) {
            echo "<p style='color:green'>✅ Password verification successful!</p>";
        } else {
            echo "<p style='color:red'>❌ Password verification failed!</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Error updating password: " . $conn->error . "</p>";
    }
} else {
    echo "<h2>❌ No Admin Found</h2>";
    echo "<p>Creating new admin...</p>";
    
    // Create new admin
    $new_password = 'admin123';
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $email = 'admin@edusmart.com';
    $username = 'admin';
    $full_name = 'System Administrator';
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
    $stmt->bind_param("ssss", $username, $email, $hashed, $full_name);
    
    if ($stmt->execute()) {
        echo "<h3 style='color:green'>✅ Admin created successfully!</h3>";
        echo "<p><strong>Login Credentials:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Email:</strong> admin@edusmart.com</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "</ul>";
    } else {
        echo "<p style='color:red'>❌ Error creating admin: " . $conn->error . "</p>";
    }
}

// Show all users for reference
echo "<h3>All Users in System:</h3>";
$users = $conn->query("SELECT id, username, email, role, is_active FROM users");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th></tr>";
while ($user = $users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";
?>