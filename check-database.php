<?php
require_once 'config/database.php';

echo "<h1>🔍 Database Structure Check</h1>";

// Check students table structure
$result = $conn->query("DESCRIBE students");
if ($result) {
    echo "<h2>Students Table Structure:</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
        $columns[] = $row['Field'];
    }
    echo "</table>";
    
    // Check if admission_no exists
    if (in_array('admission_no', $columns)) {
        echo "<p style='color:green'>✅ 'admission_no' column exists</p>";
    } else {
        echo "<p style='color:red'>❌ 'admission_no' column MISSING</p>";
        
        // Check for alternative column names
        $alternatives = ['admission_number', 'admission_no', 'student_id', 'registration_no'];
        foreach ($alternatives as $alt) {
            if (in_array($alt, $columns)) {
                echo "<p style='color:orange'>⚠️ Found alternative column: '$alt'</p>";
            }
        }
    }
} else {
    echo "<p style='color:red'>❌ Students table does not exist!</p>";
}

// Show sample data if exists
$result = $conn->query("SELECT * FROM students LIMIT 1");
if ($result && $result->num_rows > 0) {
    echo "<h2>Sample Student Record:</h2>";
    $row = $result->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}

echo "<h2>Quick Fix Options:</h2>";
echo "<ol>";
echo "<li><a href='fix-registration.php'>Run Auto-Fix Script</a></li>";
echo "<li>Manual SQL: <code>ALTER TABLE students ADD COLUMN admission_no VARCHAR(50) UNIQUE AFTER user_id;</code></li>";
echo "</ol>";
?>