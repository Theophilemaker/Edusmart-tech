<?php
require_once 'config/database.php';

echo "<h1>🔐 Admin Login Final Fix</h1>";

// Step 1: Show current admin
echo "<h2>Step 1: Current Admin Status</h2>";
$result = $conn->query("SELECT id, email, password FROM users WHERE email = 'admin@edusmart.com'");

if ($row = $result->fetch_assoc()) {
    echo "✅ Admin found in database<br>";
    echo "ID: {$row['id']}<br>";
    echo "Email: {$row['email']}<br>";
    echo "Current hash: " . substr($row['password'], 0, 30) . "...<br>";
    
    // Step 2: Delete old admin
    echo "<h2>Step 2: Removing problematic admin</h2>";
    $conn->query("DELETE FROM users WHERE email = 'admin@edusmart.com'");
    echo "✅ Old admin removed<br>";
}

// Step 3: Create new admin with correct hash
echo "<h2>Step 3: Creating new admin</h2>";

$username = 'admin';
$email = 'admin@edusmart.com';
$password = 'admin123';
$full_name = 'System Administrator';
$role = 'admin';
$is_active = 1;

// Generate fresh hash
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssi", $username, $email, $hash, $full_name, $role, $is_active);

if ($stmt->execute()) {
    echo "✅ New admin created successfully!<br><br>";
    
    // Step 4: Verify
    echo "<h2>Step 4: Verification</h2>";
    $check = $conn->query("SELECT * FROM users WHERE email = 'admin@edusmart.com'");
    $new_admin = $check->fetch_assoc();
    
    echo "New Admin Details:<br>";
    echo "ID: {$new_admin['id']}<br>";
    echo "Username: {$new_admin['username']}<br>";
    echo "Email: {$new_admin['email']}<br>";
    echo "Role: {$new_admin['role']}<br>";
    echo "Active: " . ($new_admin['is_active'] ? 'Yes' : 'No') . "<br>";
    echo "Hash: {$new_admin['password']}<br><br>";
    
    // Test password
    if (password_verify($password, $new_admin['password'])) {
        echo "✅ <strong style='color:green; font-size:1.2em'>PASSWORD VERIFICATION SUCCESSFUL!</strong><br>";
        echo "Password 'admin123' works correctly.<br><br>";
    } else {
        echo "❌ Password verification failed!<br>";
    }
    
    echo "<h3>Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> admin@edusmart.com<br>";
    echo "<strong>Password:</strong> admin123</p>";
    
    echo "<p><a href='auth/login.php' style='background: #4361ee; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} else {
    echo "❌ Error creating admin: " . $conn->error . "<br>";
}

// Show all users
echo "<h3>All Users in Database:</h3>";
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

echo "<p style='color:red; margin-top:20px;'><strong>⚠️ DELETE THIS FILE AFTER USE!</strong></p>";
?>