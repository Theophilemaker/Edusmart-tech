<?php
require_once 'config/database.php';

echo "<h1>🔧 Fixing Teacher Foreign Key Constraints</h1>";

// Find all users with role 'teacher' that don't have a record in teachers table
$query = "SELECT u.id, u.username, u.email, u.full_name 
          FROM users u 
          LEFT JOIN teachers t ON u.id = t.user_id 
          WHERE u.role = 'teacher' AND t.id IS NULL";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<h3>Found " . $result->num_rows . " teacher(s) without teacher records:</h3>";
    
    while ($user = $result->fetch_assoc()) {
        echo "<p>Creating record for: {$user['full_name']} ({$user['email']})</p>";
        
        // Generate employee ID
        $employee_id = 'TCH' . date('Y') . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
        
        // Insert into teachers table
        $insert = "INSERT INTO teachers (user_id, employee_id, specialization) VALUES (?, ?, 'General')";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("is", $user['id'], $employee_id);
        
        if ($stmt->execute()) {
            echo "<p style='color:green'>✅ Created teacher record with ID: $employee_id</p>";
        } else {
            echo "<p style='color:red'>❌ Error: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color:green'>✅ All teachers have proper records in teachers table.</p>";
}

// Show current teachers
echo "<h3>Current Teachers in Database:</h3>";
$teachers = $conn->query("SELECT t.id, t.employee_id, u.full_name, u.email 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id");

if ($teachers->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Teacher ID</th><th>Employee ID</th><th>Name</th><th>Email</th></tr>";
    while ($teacher = $teachers->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$teacher['id']}</td>";
        echo "<td>{$teacher['employee_id']}</td>";
        echo "<td>{$teacher['full_name']}</td>";
        echo "<td>{$teacher['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No teachers found in teachers table.</p>";
}

echo "<p><a href='teacher/courses.php'>Go to Teacher Courses Page</a></p>";
?>