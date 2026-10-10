<?php
require_once 'config/database.php';

echo "<h2>Creating Sample Users</h2>";

// Array of users to create
$users = [
    ['admin', 'admin@edusmart.com', 'System Administrator', 'admin'],
    ['teacher1', 'teacher@edusmart.com', 'John Teacher', 'teacher'],
    ['student1', 'student@edusmart.com', 'Jane Student', 'student']
];

foreach ($users as $user) {
    // Check if exists
    $check = $conn->query("SELECT id FROM users WHERE username = '{$user[0]}' OR email = '{$user[1]}'");
    
    if ($check->num_rows == 0) {
        $insert = "INSERT INTO users (username, email, password, full_name, role, is_active) 
                   VALUES ('{$user[0]}', '{$user[1]}', 'temp123', '{$user[2]}', '{$user[3]}', 1)";
        
        if ($conn->query($insert)) {
            echo "<p style='color:green'>✅ Created: {$user[2]} ({$user[3]})</p>";
        } else {
            echo "<p style='color:red'>❌ Error: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:orange'>⚠️ Already exists: {$user[0]}</p>";
    }
}

echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";
?>