<?php
require_once 'config/database.php';

echo "<h1>🔐 Password Hash Fix Tool</h1>";

// The correct hash for 'admin123'
$correct_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

// Get current admin info
$result = $conn->query("SELECT id, email, password FROM users WHERE email = 'admin@edusmart.com'");

if ($row = $result->fetch_assoc()) {
    echo "<h3>Current Admin Record:</h3>";
    echo "ID: {$row['id']}<br>";
    echo "Email: {$row['email']}<br>";
    echo "Current Hash: {$row['password']}<br>";
    echo "Correct Hash: $correct_hash<br><br>";
    
    // Update with correct hash
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $correct_hash, $row['id']);
    
    if ($update->execute()) {
        echo "<p style='color:green; font-weight:bold'>✅ PASSWORD FIXED!</p>";
        echo "<p>New hash set correctly for 'admin123'</p>";
        
        // Verify
        $verify = $conn->query("SELECT password FROM users WHERE id = {$row['id']}")->fetch_assoc();
        if (password_verify('admin123', $verify['password'])) {
            echo "<p style='color:green'>✅ Verification successful! Login will work now.</p>";
        } else {
            echo "<p style='color:red'>❌ Verification still failing! Something else is wrong.</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Update failed: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange'>⚠️ Admin not found. Creating new admin...</p>";
    
    // Create new admin with correct hash
    $insert = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
    $username = 'admin';
    $email = 'admin@edusmart.com';
    $full_name = 'System Administrator';
    $insert->bind_param("ssss", $username, $email, $correct_hash, $full_name);
    
    if ($insert->execute()) {
        echo "<p style='color:green'>✅ New admin created with correct hash!</p>";
        echo "<p>Email: admin@edusmart.com<br>Password: admin123</p>";
    } else {
        echo "<p style='color:red'>❌ Error: " . $conn->error . "</p>";
    }
}

// Show hash comparison
echo "<h3>Hash Comparison:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Type</th><th>Hash</th></tr>";
echo "<tr><td>Your Current Hash</td><td style='font-family:monospace; color:red'>{$row['password']}</td></tr>";
echo "<tr><td>Correct Hash</td><td style='font-family:monospace; color:green'>$correct_hash</td></tr>";
echo "<tr><td>Difference</td><td>Position 6: 'l' vs 'I'<br>Position 11: 'O' vs '0'</td></tr>";
echo "</table>";

echo "<p><a href='auth/login.php' style='background: #4361ee; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
?>