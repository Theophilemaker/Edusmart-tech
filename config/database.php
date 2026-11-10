<?php
// Database Configuration for PHP 8.5+
$host = 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$username = 'TNgsFn1wYuV4wMq.root';
$password = '<PASSWORD>';
$database = 'edusmart_pro';

// Create connection with error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Site URL (update this for your setup)
define('SITE_URL', 'http://localhost/THEOPHILE%20PROJECT/allavi.local/');
define('SITE_NAME', 'EduSmart Pro');

// Function to check connection (replacement for ping())
function isConnected($conn) {
    return $conn && !$conn->connect_error;
}

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
