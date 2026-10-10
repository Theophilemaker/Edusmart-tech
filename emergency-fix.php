<?php
// EMERGENCY FIX - DELETE AFTER USE
require_once 'config/database.php';

// Generate brand new hash
$new_password = 'Admin@2024';
$fresh_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h2>🔧 Emergency Password Fix</h2>";
echo "New hash generated for password '$new_password':<br>";
echo "<code>$fresh_hash</code><br><br>";

// Update admin
$stmt = $conn->prepare("UPDATE users SET password = ?, is_active = 1 WHERE email = ? OR role = ?");
$admin_email = 'admin@edusmart.com';
$admin_role = 'admin';
$stmt->bind_param("sss", $fresh_hash, $admin_email, $admin_role);

if ($stmt->execute()) {
    echo "✅ Admin password updated!<br>";
    echo "Email: admin@edusmart.com<br>";
    echo "New Password: $new_password<br><br>";
    
    // Verify
    $check = $conn->query("SELECT password FROM users WHERE email = 'admin@edusmart.com'");
    if ($row = $check->fetch_assoc()) {
        if (password_verify($new_password, $row['password'])) {
            echo "✅ Verification successful! Login will work.<br>";
            echo "<a href='auth/login.php'>Click here to login</a>";
        }
    }
} else {
    echo "❌ Error: " . $conn->error;
}

// Create new admin if none exists
if ($conn->affected_rows == 0) {
    echo "<p>No admin found. Creating new admin...</p>";
    $insert = "INSERT INTO users (username, email, password, full_name, role, is_active) 
               VALUES ('admin', 'admin@edusmart.com', ?, 'System Administrator', 'admin', 1)";
    $stmt = $conn->prepare($insert);
    $stmt->bind_param("s", $fresh_hash);
    if ($stmt->execute()) {
        echo "✅ New admin created with password: $new_password";
    }
}
?>