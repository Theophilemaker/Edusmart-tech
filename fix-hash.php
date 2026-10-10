<?php
require_once 'config/database.php';

// Generate fresh hash
$password = 'admin123';
$fresh_hash = password_hash($password, PASSWORD_DEFAULT);

echo "New hash generated: " . $fresh_hash . "<br>";

// Update database
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $fresh_hash, $email);
$email = 'admin@edusmart.com';

if ($stmt->execute()) {
    echo "✅ Password updated successfully!<br>";
    echo "Email: admin@edusmart.com<br>";
    echo "Password: admin123<br>";
    
    // Verify it works
    $check = $conn->query("SELECT password FROM users WHERE email = 'admin@edusmart.com'");
    $row = $check->fetch_assoc();
    if (password_verify('admin123', $row['password'])) {
        echo "✅ Verification successful! Login will work now.<br>";
    } else {
        echo "❌ Something went wrong with verification.<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}
?>