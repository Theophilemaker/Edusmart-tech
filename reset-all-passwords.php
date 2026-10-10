<?php
require_once 'config/database.php';

echo "<h2>Resetting All Passwords</h2>";

$default_password = 'password123';
$hashed = password_hash($default_password, PASSWORD_DEFAULT);

$users = $conn->query("SELECT id, email, role FROM users");

while ($user = $users->fetch_assoc()) {
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed, $user['id']);
    
    if ($update->execute()) {
        echo "✅ {$user['email']} ({$user['role']}) - Password: $default_password<br>";
    } else {
        echo "❌ Failed for {$user['email']}<br>";
    }
}

echo "<h3>✅ All passwords reset to: $default_password</h3>";
echo "<p><a href='auth/login.php'>Go to Login</a></p>";
?>