<?php
require_once 'config/database.php';

echo "<h2>🔧 Database Structure Fix Tool</h2>";

// Fix 1: Rename s_active to is_active
try {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 's_active'");
    if ($check->num_rows > 0) {
        $conn->query("ALTER TABLE users CHANGE s_active is_active TINYINT(1) DEFAULT 1");
        echo "✅ Renamed s_active to is_active<br>";
    } else {
        echo "ℹ️ Column s_active not found, checking if is_active exists...<br>";
        
        $check2 = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if ($check2->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER role");
            echo "✅ Added missing is_active column<br>";
        } else {
            echo "✅ is_active column already exists<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Fix 2: Update admin role
try {
    $conn->query("UPDATE users SET role = 'admin' WHERE email = 'admin@edusmart.com'");
    echo "✅ Updated admin role<br>";
} catch (Exception $e) {
    echo "❌ Error updating admin: " . $e->getMessage() . "<br>";
}

// Fix 3: Reset admin password
try {
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, is_active = 1 WHERE email = ?");
    $stmt->bind_param("ss", $hashed, $admin_email);
    $admin_email = 'admin@edusmart.com';
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo "✅ Admin password reset to: admin123<br>";
    } else {
        echo "⚠️ Admin user not found. Creating new admin...<br>";
        
        // Create admin if doesn't exist
        $insert = "INSERT INTO users (username, email, password, full_name, role, is_active) 
                   VALUES ('admin', 'admin@edusmart.com', ?, 'System Administrator', 'admin', 1)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("s", $hashed);
        $stmt->execute();
        echo "✅ New admin created with password: admin123<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Fix 4: Show current users
$users = $conn->query("SELECT id, username, email, role, is_active FROM users");
echo "<h3>Current Users:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th></tr>";
while ($user = $users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>✅ Database fixes applied!</h3>";
echo "<p>Try logging in now with:</p>";
echo "<p><strong>Email:</strong> admin@edusmart.com<br>";
echo "<strong>Password:</strong> admin123</p>";
echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";
?>