<?php
require_once 'config/database.php';

echo "<h1>🔐 Password Reset Utility</h1>";

// Define passwords for different roles
$passwords = [
    'admin' => 'admin123',
    'teacher' => 'teacher123',
    'student' => 'student123',
    'super_admin' => 'admin123'
];

// Generate fresh hashes
$hashes = [];
foreach ($passwords as $role => $plain_password) {
    $hashes[$role] = password_hash($plain_password, PASSWORD_DEFAULT);
}

echo "<h2>Generated Hashes:</h2>";
echo "<pre>";
foreach ($hashes as $role => $hash) {
    echo "$role: $hash\n";
}
echo "</pre>";

// Update admin users
echo "<h2>Updating Admin Users:</h2>";
$admin_hash = $hashes['admin'];
$result = $conn->query("SELECT id, email FROM users WHERE role IN ('admin', 'super_admin')");
while ($user = $result->fetch_assoc()) {
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $admin_hash, $user['id']);
    if ($update->execute()) {
        echo "✅ Updated admin: {$user['email']} -> password: admin123<br>";
    }
}

// Update teacher users
echo "<h2>Updating Teacher Users:</h2>";
$teacher_hash = $hashes['teacher'];
$result = $conn->query("SELECT id, email FROM users WHERE role = 'teacher'");
while ($user = $result->fetch_assoc()) {
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $teacher_hash, $user['id']);
    if ($update->execute()) {
        echo "✅ Updated teacher: {$user['email']} -> password: teacher123<br>";
    }
}

// Update student users
echo "<h2>Updating Student Users:</h2>";
$student_hash = $hashes['student'];
$result = $conn->query("SELECT id, email FROM users WHERE role = 'student'");
while ($user = $result->fetch_assoc()) {
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $student_hash, $user['id']);
    if ($update->execute()) {
        echo "✅ Updated student: {$user['email']} -> password: student123<br>";
    }
}

// If no users exist, create sample users
echo "<h2>Checking if sample users needed:</h2>";
$count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
if ($count == 0) {
    echo "No users found. Creating sample users...<br>";
    
    // Create admin
    $admin_hash = $hashes['admin'];
    $conn->query("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES 
                  ('admin', 'admin@edusmart.com', '$admin_hash', 'System Administrator', 'admin', 1)");
    echo "✅ Admin created: admin@edusmart.com / admin123<br>";
    
    // Create teacher
    $teacher_hash = $hashes['teacher'];
    $conn->query("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES 
                  ('teacher', 'teacher@edusmart.com', '$teacher_hash', 'John Teacher', 'teacher', 1)");
    echo "✅ Teacher created: teacher@edusmart.com / teacher123<br>";
    
    // Create student
    $student_hash = $hashes['student'];
    $conn->query("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES 
                  ('student', 'student@edusmart.com', '$student_hash', 'Jane Student', 'student', 1)");
    echo "✅ Student created: student@edusmart.com / student123<br>";
}

echo "<h3>✅ Password reset complete!</h3>";
echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";
echo "<p style='color:red'><strong>⚠️ DELETE THIS FILE AFTER USE!</strong></p>";
?>