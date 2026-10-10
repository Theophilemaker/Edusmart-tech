<?php
// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Error Check</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🔍 System Error Check</h1>";

// Check PHP version
echo "<h2>PHP Information</h2>";
echo "<p>PHP Version: <strong>" . phpversion() . "</strong></p>";

// Check if session is working
echo "<h2>Session Test</h2>";
session_start();
$_SESSION['test'] = 'Working';
echo "<p>Session: " . ($_SESSION['test'] == 'Working' ? '✅ Working' : '❌ Not Working') . "</p>";

// Check required files
echo "<h2>Required Files Check</h2>";
echo "<table>";
echo "<tr><th>File</th><th>Status</th><th>Path</th></tr>";

$files_to_check = [
    'config/database.php',
    'includes/functions.php',
    'includes/security.php',
    'auth/login.php',
    'auth/register.php',
    'index.php'
];

$root_path = __DIR__ . '/';
foreach ($files_to_check as $file) {
    $full_path = $root_path . $file;
    if (file_exists($full_path)) {
        echo "<tr style='background: #d4edda;'>";
        echo "<td>$file</td>";
        echo "<td>✅ EXISTS</td>";
        echo "<td>" . realpath($full_path) . "</td>";
        echo "</tr>";
    } else {
        echo "<tr style='background: #f8d7da;'>";
        echo "<td><strong>$file</strong></td>";
        echo "<td>❌ MISSING</td>";
        echo "<td>" . $full_path . "</td>";
        echo "</tr>";
    }
}
echo "</table>";

// Check database connection
echo "<h2>Database Connection Test</h2>";
if (file_exists($root_path . 'config/database.php')) {
    require_once $root_path . 'config/database.php';
    
    if (isset($conn) && $conn->ping()) {
        echo "<p style='color:green'>✅ Database connected successfully</p>";
        
        // Check users table
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->num_rows > 0) {
            echo "<p>✅ Users table exists</p>";
            
            // Count users
            $count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
            echo "<p>📊 Total users: $count</p>";
            
            // Show sample users
            $users = $conn->query("SELECT id, email, role, is_active FROM users LIMIT 5");
            if ($users->num_rows > 0) {
                echo "<h3>Sample Users:</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Active</th></tr>";
                while ($user = $users->fetch_assoc()) {
                    $active = isset($user['is_active']) ? $user['is_active'] : (isset($user['s_active']) ? $user['s_active'] : 'N/A');
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['email']}</td>";
                    echo "<td>{$user['role']}</td>";
                    echo "<td>$active</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p style='color:red'>❌ Users table not found</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Database connection failed</p>";
        echo "<p>Error: " . ($conn->connect_error ?? 'Unknown') . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Database config file missing</p>";
}

// Check PHP extensions
echo "<h2>Required PHP Extensions</h2>";
$extensions = ['mysqli', 'session', 'json', 'mbstring'];
echo "<table>";
echo "<tr><th>Extension</th><th>Status</th></tr>";
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<tr style='background: #d4edda;'><td>$ext</td><td>✅ Loaded</td></tr>";
    } else {
        echo "<tr style='background: #f8d7da;'><td>$ext</td><td>❌ Not Loaded</td></tr>";
    }
}
echo "</table>";

// Check directory permissions
echo "<h2>Directory Permissions</h2>";
$dirs_to_check = ['uploads', 'logs'];
echo "<table>";
echo "<tr><th>Directory</th><th>Exists</th><th>Writable</th></tr>";
foreach ($dirs_to_check as $dir) {
    $full_dir = $root_path . $dir;
    $exists = is_dir($full_dir) ? '✅ Yes' : '❌ No';
    $writable = is_dir($full_dir) && is_writable($full_dir) ? '✅ Yes' : '❌ No';
    $bg = (is_dir($full_dir) && is_writable($full_dir)) ? '#d4edda' : '#f8d7da';
    echo "<tr style='background: $bg;'><td>$dir</td><td>$exists</td><td>$writable</td></tr>";
}
echo "</table>";

// Check .htaccess (if exists)
echo "<h2>Server Information</h2>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";

// Check for spaces in path (common issue)
$path = __DIR__;
if (strpos($path, ' ') !== false) {
    echo "<p style='color:orange'>⚠️ Warning: Path contains spaces: $path</p>";
    echo "<p>This can cause issues with some PHP functions. Consider renaming folders to remove spaces.</p>";
}

echo "<h2>Quick Fixes</h2>";
echo "<ol>";
echo "<li><a href='auth/login.php' target='_blank'>Try Login Page Directly</a></li>";
echo "<li><a href='index.php' target='_blank'>Try Home Page</a></li>";
echo "<li>Check Apache error logs at: C:\\wamp64\\logs\\apache_error.log</li>";
echo "<li>Check PHP error logs at: C:\\wamp64\\logs\\php_error.log</li>";
echo "</ol>";

echo "<p style='color:red'><strong>⚠️ If you see any MISSING files above, create them immediately!</strong></p>";
echo "</body></html>";
?>