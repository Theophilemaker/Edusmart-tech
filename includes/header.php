<?php
// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection (adjust path if needed)
require_once __DIR__ . '/../config/database.php';

// Get cart count (for guests or logged-in users)
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
    $cart_count = $result->fetch_assoc()['total'] ?? 0;
} elseif (isset($_SESSION['cart_session'])) {
    $session_id = $_SESSION['cart_session'];
    $result = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE session_id = '$session_id'");
    $cart_count = $result->fetch_assoc()['total'] ?? 0;
}

// Fetch site logo from settings table
$logo = '';
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
if ($result && $row = $result->fetch_assoc()) {
    $logo = $row['setting_value'];
}

// Determine base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_dir = rtrim(dirname($script_name), '/\\') . '/';
define('BASE_URL', $protocol . $host . $base_dir);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('SITE_NAME') ? SITE_NAME : 'EduSmart Pro'; ?></title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <meta name="theme-color" content="#4361ee">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/global.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/ai-widget.css">
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <!-- Menu Toggle (hamburger) -->
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Logo -->
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                    <?php
                    $logo_path = __DIR__ . '/../uploads/logo/' . $logo;
                    if (!empty($logo) && file_exists($logo_path)): ?>
                        <img src="<?php echo BASE_URL; ?>uploads/logo/<?php echo $logo; ?>" alt="<?php echo SITE_NAME; ?>" style="height:40px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap" style="font-size: 1.8rem; color: #667eea;"></i>
                    <?php endif; ?>
                    <span style="font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                        <?php echo defined('SITE_NAME') ? SITE_NAME : 'EduSmart Pro'; ?>
                    </span>
                </a>
            </div>

            <!-- Install App Button (hidden initially) -->
            <button id="installButton" style="display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #333;" title="Install App">
                <i class="fas fa-download"></i>
            </button>

            <!-- Main Navigation -->
            <nav class="main-nav">
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>products.php" class="<?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">Shop</a></li>

                    <?php if (isset($_SESSION['user_id'])): 
                        $role = $_SESSION['role'];
                        $dashboard_link = BASE_URL . $role . '/dashboard.php';
                    ?>
                        <li><a href="<?php echo $dashboard_link; ?>">Dashboard</a></li>

                        <?php if ($role == 'admin' || $role == 'super_admin'): ?>
                            <li><a href="<?php echo BASE_URL; ?>admin/manage-products.php">Manage Products</a></li>
                        <?php endif; ?>

                        <!-- Messages (renamed from chat.php to advice.php) -->
                        <li><a href="<?php echo BASE_URL; ?>advice.php">Messages</a></li>

                        <li><a href="<?php echo BASE_URL; ?>my-orders.php">My Orders</a></li>
                        <li><a href="<?php echo BASE_URL; ?>profile.php">Profile</a></li>
                        <li><a href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>auth/login.php">Login</a></li>
                        <li><a href="<?php echo BASE_URL; ?>auth/register.php">Register</a></li>
                        <li><a href="<?php echo BASE_URL; ?>auth/register-employer.php">Employer Register</a></li>
                    <?php endif; ?>

                    <!-- Cart always last -->
                    <li class="cart-icon">
                        <a href="<?php echo BASE_URL; ?>cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="site-content">

    <!-- Hidden overlay for sidebar mobile (to be toggled by menuToggle) -->
    <div class="sidebar-overlay"></div>

    <!-- Install Prompt JavaScript -->
    <script>
        let deferredPrompt;
        const installButton = document.getElementById('installButton');

        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent automatic prompt
            e.preventDefault();
            deferredPrompt = e;
            // Show our install button
            installButton.style.display = 'inline-block';
        });

        installButton.addEventListener('click', (e) => {
            // Hide the button
            installButton.style.display = 'none';
            // Show the install prompt
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                } else {
                    console.log('User dismissed the install prompt');
                }
                deferredPrompt = null;
            });
        });
    </script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(registration) {
                    console.log('ServiceWorker registered with scope:', registration.scope);
                }, function(error) {
                    console.log('ServiceWorker registration failed:', error);
                });
            });
        }
    </script>