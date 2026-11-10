
<?php
/**
 * EduSmart Pro - Database Configuration
 * PHP 8.5+ | TiDB Cloud | Vercel
 */

// Prevent direct error disclosure
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database settings from environment variables
$host     = getenv('TIDB_HOST') ?: 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$port     = (int)(getenv('TIDB_PORT') ?: 4000);
$username = getenv('TIDB_USER') ?: 'TNgsFn1wYuV4wMq.root';
$password = getenv('TIDB_PASSWORD') ?: 'PesOl6gndNTb33j5';
$database = getenv('TIDB_DATABASE') ?: 'edusmart_pro';

// SSL certificate path
$caPath = getenv('TIDB_CA_PATH')
    ?: __DIR__ . '/../ca.pem';

// Check required configuration
if (
    $host === '' ||
    $username === '' ||
    $password === '' ||
    $database === ''
) {
    error_log('EduSmart: Missing database environment variables.');
    http_response_code(500);
    exit('Database configuration is incomplete.');
}

// Verify CA certificate exists
if (!is_file($caPath) || !is_readable($caPath)) {
    error_log('EduSmart: TiDB CA certificate is unavailable.');
    http_response_code(500);
    exit('Database security configuration is incomplete.');
}

try {
    // Initialize MySQLi
    $conn = mysqli_init();

    if (!$conn) {
        throw new RuntimeException('MySQLi initialization failed.');
    }

    // Verify the database server certificate
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

    // Configure TLS certificate
    $conn->ssl_set(
        null,
        null,
        $caPath,
        null,
        null
    );

    // Connect to TiDB Cloud
    $conn->real_connect(
        $host,
        $username,
        $password,
        $database,
        $port,
        null,
        MYSQLI_CLIENT_SSL
    );

    // Character encoding
    $conn->set_charset('utf8mb4');

} catch (Throwable $e) {

    error_log('EduSmart database error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to connect to the database. Please try again later.');
}

// Application configuration
define(
    'SITE_URL',
    rtrim(getenv('SITE_URL') ?: 'http://localhost', '/') . '/'
);

define('SITE_NAME', 'EduSmart Pro');

// Database connection health check
function isConnected($conn) {
    return $conn instanceof mysqli && !$conn->connect_errno;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
