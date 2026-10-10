<?php
require_once 'config/database.php';

$email = 'admin@edusmart.com';
$password = 'admin123';

echo "<h1>🔍 Final Verification</h1>";

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
        echo "<h2 style='color:green; font-size:2em'>✅✅✅ SUCCESS!</h2>";
        echo "<p style='font-size:1.2em'>Password verification works! You can now login.</p>";
        echo "<p><a href='auth/login.php' style='background:#4361ee; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Go to Login Page</a></p>";
    } else {
        echo "<h2 style='color:red; font-size:2em'>❌ FAILED</h2>";
        echo "<p>The hash is still incorrect. Please run the emergency-fix.php script.</p>";
        
        // Show the exact difference
        $correct = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        echo "<h3>Hash Comparison:</h3>";
        echo "<pre>";
        echo "Your hash:    {$user['password']}\n";
        echo "Correct hash: $correct\n";
        echo "</pre>";
        
        // Highlight the difference
        for ($i = 0; $i < strlen($correct); $i++) {
            $c1 = substr($user['password'], $i, 1);
            $c2 = substr($correct, $i, 1);
            if ($c1 != $c2) {
                echo "<p style='color:red'>Difference at position " . ($i+1) . ": '{$c1}' vs '{$c2}'</p>";
                break;
            }
        }
    }
} else {
    echo "<h2 style='color:red'>❌ Admin user not found!</h2>";
    echo "<p>Please create admin first.</p>";
}
?>