<?php
require_once 'config/database.php';

echo "<h1>🔧 Registration Fix Tool</h1>";

// Check if students table exists
$table_check = $conn->query("SHOW TABLES LIKE 'students'");
if ($table_check->num_rows == 0) {
    echo "<p style='color:orange'>⚠️ Students table doesn't exist. Creating...</p>";
    
    // Create students table
    $sql = "CREATE TABLE students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT UNIQUE,
        admission_no VARCHAR(50) UNIQUE,
        class VARCHAR(50),
        parent_name VARCHAR(100),
        parent_phone VARCHAR(20),
        date_of_birth DATE,
        gender ENUM('male','female','other') DEFAULT 'other',
        address TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✅ Students table created successfully</p>";
    } else {
        echo "<p style='color:red'>❌ Error creating table: " . $conn->error . "</p>";
    }
}

// Check existing columns
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM students");
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

echo "<h3>Current columns in students table:</h3>";
echo "<ul>";
foreach ($columns as $col) {
    echo "<li>$col</li>";
}
echo "</ul>";

// Add missing admission_no column
if (!in_array('admission_no', $columns)) {
    echo "<p style='color:orange'>⚠️ 'admission_no' column missing. Adding...</p>";
    
    // Check for alternative names
    if (in_array('admission_number', $columns)) {
        $sql = "ALTER TABLE students CHANGE admission_number admission_no VARCHAR(50) UNIQUE";
        echo "<p>Renaming 'admission_number' to 'admission_no'...</p>";
    } else {
        $sql = "ALTER TABLE students ADD COLUMN admission_no VARCHAR(50) UNIQUE AFTER user_id";
    }
    
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✅ 'admission_no' column added successfully</p>";
    } else {
        echo "<p style='color:red'>❌ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:green'>✅ 'admission_no' column already exists</p>";
}

// Check if any other columns are missing
$required_columns = ['id', 'user_id', 'admission_no', 'class', 'parent_name', 'parent_phone'];
foreach ($required_columns as $col) {
    if (!in_array($col, $columns) && $col != 'admission_no') {
        echo "<p style='color:orange'>⚠️ Adding missing column: $col</p>";
        $sql = "ALTER TABLE students ADD COLUMN $col VARCHAR(50) NULL";
        $conn->query($sql);
    }
}

echo "<h3>✅ Fix completed!</h3>";
echo "<p><a href='auth/register.php'>Go to Registration Page</a></p>";
?>