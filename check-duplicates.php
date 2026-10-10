<?php
echo "<h1>🔍 Function Duplication Checker</h1>";

$files_to_check = [
    '../includes/functions.php',
    '../includes/security.php',
    '../teacher/dashboard.php',
    '../admin/dashboard.php',
    '../student/dashboard.php',
    '../auth/login.php'
];

$functions_in_functions = [];
$functions_in_security = [];

// Read functions.php
if (file_exists('../includes/functions.php')) {
    $content = file_get_contents('../includes/functions.php');
    preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
    $functions_in_functions = $matches[1];
    echo "<h3>Functions in functions.php:</h3>";
    echo "<pre>" . implode("\n", $functions_in_functions) . "</pre>";
}

// Read security.php
if (file_exists('../includes/security.php')) {
    $content = file_get_contents('../includes/security.php');
    preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
    $functions_in_security = $matches[1];
    echo "<h3>Functions in security.php:</h3>";
    echo "<pre>" . implode("\n", $functions_in_security) . "</pre>";
}

// Find duplicates
$duplicates = array_intersect($functions_in_functions, $functions_in_security);
if (!empty($duplicates)) {
    echo "<h3 style='color:red'>❌ DUPLICATE FUNCTIONS FOUND:</h3>";
    echo "<pre style='color:red'>" . implode("\n", $duplicates) . "</pre>";
    echo "<p>These functions are defined in both files! Remove them from security.php</p>";
} else {
    echo "<h3 style='color:green'>✅ No duplicate functions found!</h3>";
}

// Check include order in dashboard files
echo "<h3>Checking include order in teacher/dashboard.php:</h3>";
if (file_exists('../teacher/dashboard.php')) {
    $content = file_get_contents('../teacher/dashboard.php');
    if (strpos($content, "require_once '../includes/functions.php'") !== false) {
        echo "✅ functions.php is included<br>";
    } else {
        echo "❌ functions.php might be missing<br>";
    }
    if (strpos($content, "require_once '../includes/security.php'") !== false) {
        echo "✅ security.php is included<br>";
    } else {
        echo "❌ security.php might be missing<br>";
    }
}

echo "<p><a href='teacher/dashboard.php'>Test Teacher Dashboard</a></p>";
?>