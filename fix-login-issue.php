<?php
require_once 'config/database.php';

echo "<h2>🔧 Login Issue Fix Tool</h2>";

// Fix 1: Remove deprecated ping() call warning
echo "<h3>Step 1: Fixing deprecated ping() warning</h3>";

// Fix 2: Generate fresh hash
$password = 'admin123';
$fresh_hash = password_hash($password, PASSWORD_DEFAULT);

echo "Generated fresh hash for 'admin123':<br>";
echo "<code>$fresh_hash</code><br><br>";

// Fix 3: Update admin password
$email = 'admin@edusmart.com';
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $fresh_hash, $email);

if ($stmt->execute()) {
    echo "✅ Admin password updated successfully!<br>";
    echo "Email: $email<br>";
    echo "Password: $password<br>";
    
    // Verify the new hash
    $check = $conn->query("SELECT password FROM users WHERE email = '$email'");
    $row = $check->fetch_assoc();
    $verify = password_verify($password, $row['password']);
    echo "Verification: " . ($verify ? "✅ SUCCESS" : "❌ FAILED") . "<br>";
} else {
    echo "❌ Error: " . $conn->error . "<br>";
}

// Fix 4: Show all users
echo "<h3>Current Users:</h3>";
$users = $conn->query("SELECT id, username, email, role FROM users");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
while ($user = $users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "</tr>";
}
echo "</table>";

// Fix 5: Fix the ping() warning in login.php
$login_file = 'auth/login.php';
if (file_exists($login_file)) {
    $content = file_get_contents($login_file);
    // Replace the ping() line
    $content = str_replace(
        "if (!$conn->ping()) {",
        "// Check connection (ping removed in PHP 8.4+)\n        if (!$conn || $conn->connect_error) {",
        $content
    );
    file_put_contents($login_file, $content);
    echo "✅ Updated login.php to remove deprecated ping()<br>";
}

echo "<h3>✅ All fixes applied!</h3>";
echo "<p><a href='auth/login.php'>Try logging in now</a></p>";
?>