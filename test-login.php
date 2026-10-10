<?php
require_once 'config/database.php';

$email = 'admin@edusmart.com';
$password = 'admin123';

echo "<h2>Login Test</h2>";

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo "✅ User found<br>";
    echo "Email: {$user['email']}<br>";
    echo "Role: {$user['role']}<br>";
    echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "<br>";
    echo "Stored hash: {$user['password']}<br>";
    
    if (password_verify($password, $user['password'])) {
        echo "<span style='color:green; font-size:1.2em'>✅ PASSWORD VERIFICATION SUCCESSFUL!</span><br>";
        echo "<a href='auth/login.php'>Go to Login Page</a>";
    } else {
        echo "<span style='color:red; font-size:1.2em'>❌ PASSWORD VERIFICATION FAILED</span><br>";
        echo "The hash is corrupted. Please run the fix script.";
    }
} else {
    echo "❌ User not found. Please create admin first.";
}
?>