<?php
require_once 'config/database.php';

echo "<h2>🔧 FINAL PASSWORD FIX</h2>";

// Generate a brand new hash
$password = 'admin123';
$fresh_hash = password_hash($password, PASSWORD_DEFAULT);

echo "Generated new hash: <br>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block;'>$fresh_hash</code><br><br>";

// Update the database
$email = 'admin@edusmart.com';
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $fresh_hash, $email);

if ($stmt->execute()) {
    echo "✅ Database updated successfully!<br><br>";
    
    // Verify it worked
    $check = $conn->query("SELECT password FROM users WHERE email = '$email'");
    $row = $check->fetch_assoc();
    
    if (password_verify($password, $row['password'])) {
        echo "✅ <strong>VERIFICATION SUCCESSFUL!</strong><br>";
        echo "Password '$password' works with the new hash.<br><br>";
        echo "<a href='auth/login.php' style='background: #4361ee; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Try Login Now</a>";
    } else {
        echo "❌ Verification failed! Something is wrong with the hash generation.";
    }
} else {
    echo "❌ Error: " . $conn->error;
}

// Show current user
$user = $conn->query("SELECT id, email, role FROM users WHERE email = '$email'")->fetch_assoc();
echo "<h3>Current Admin User:</h3>";
echo "<pre>";
print_r($user);
echo "</pre>";
?>