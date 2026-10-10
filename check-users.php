<?php
require_once 'config/database.php';

echo "<h2>📊 User Database Check</h2>";

$result = $conn->query("SELECT id, username, email, role, is_active FROM users ORDER BY role");

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";

while ($row = $result->fetch_assoc()) {
    $color = $row['role'] == 'admin' ? '#667eea' : ($row['role'] == 'teacher' ? '#f72585' : '#4cc9f0');
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['username']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td style='color:$color; font-weight:bold'>{$row['role']}</td>";
    echo "<td>" . ($row['is_active'] ? '✅ Active' : '❌ Inactive') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Login Credentials (No Password Needed):</h3>";
echo "<ul>";
echo "<li><strong>Admin:</strong> username 'admin' or email 'admin@edusmart.com'</li>";
echo "<li><strong>Teacher:</strong> username 'teacher1' or email 'teacher@edusmart.com'</li>";
echo "<li><strong>Student:</strong> username 'student1' or email 'student@edusmart.com'</li>";
echo "</ul>";

echo "<p><a href='auth/login.php'>Go to Unified Login</a></p>";
?>