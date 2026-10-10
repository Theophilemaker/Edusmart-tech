<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$settings_file = '../config/payment_settings.json';
$default_settings = [
    'stripe' => ['enabled' => false, 'public_key' => '', 'secret_key' => ''],
    'paypal' => ['enabled' => false, 'client_id' => '', 'secret' => '', 'mode' => 'sandbox'],
    'mobile_money' => ['enabled' => false, 'provider' => '', 'api_key' => '', 'merchant_code' => '']
];

// Load existing settings
$settings = $default_settings;
if (file_exists($settings_file)) {
    $loaded = json_decode(file_get_contents($settings_file), true);
    if (is_array($loaded)) {
        $settings = array_merge($default_settings, $loaded);
    }
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    // Build new settings array from POST
    $new_settings = [
        'stripe' => [
            'enabled' => isset($_POST['stripe_enabled']),
            'public_key' => trim($_POST['stripe_public_key'] ?? ''),
            'secret_key' => trim($_POST['stripe_secret_key'] ?? '')
        ],
        'paypal' => [
            'enabled' => isset($_POST['paypal_enabled']),
            'client_id' => trim($_POST['paypal_client_id'] ?? ''),
            'secret' => trim($_POST['paypal_secret'] ?? ''),
            'mode' => $_POST['paypal_mode'] ?? 'sandbox'
        ],
        'mobile_money' => [
            'enabled' => isset($_POST['mobile_enabled']),
            'provider' => trim($_POST['mobile_provider'] ?? ''),
            'api_key' => trim($_POST['mobile_api_key'] ?? ''),
            'merchant_code' => trim($_POST['mobile_merchant'] ?? '')
        ]
    ];

    // Save to file
    if (file_put_contents($settings_file, json_encode($new_settings, JSON_PRETTY_PRINT))) {
        $message = "Payment settings saved successfully!";
        $message_type = 'success';
        $settings = $new_settings;
    } else {
        $message = "Error saving settings. Check file permissions.";
        $message_type = 'error';
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Settings</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Payment Settings</h1>
            <p>Configure payment gateways</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container" style="max-width:800px;">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <h3>Stripe</h3>
                <div style="background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <div style="margin-bottom:10px;">
                        <label>
                            <input type="checkbox" name="stripe_enabled" <?php echo $settings['stripe']['enabled'] ? 'checked' : ''; ?>> Enable Stripe
                        </label>
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Public Key</label>
                            <input type="text" name="stripe_public_key" class="form-control" value="<?php echo htmlspecialchars($settings['stripe']['public_key']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Secret Key</label>
                            <input type="text" name="stripe_secret_key" class="form-control" value="<?php echo htmlspecialchars($settings['stripe']['secret_key']); ?>">
                        </div>
                    </div>
                </div>

                <h3>PayPal</h3>
                <div style="background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <div style="margin-bottom:10px;">
                        <label>
                            <input type="checkbox" name="paypal_enabled" <?php echo $settings['paypal']['enabled'] ? 'checked' : ''; ?>> Enable PayPal
                        </label>
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Client ID</label>
                            <input type="text" name="paypal_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['paypal']['client_id']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Secret</label>
                            <input type="text" name="paypal_secret" class="form-control" value="<?php echo htmlspecialchars($settings['paypal']['secret']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mode</label>
                        <select name="paypal_mode" class="form-control">
                            <option value="sandbox" <?php echo $settings['paypal']['mode'] == 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                            <option value="live" <?php echo $settings['paypal']['mode'] == 'live' ? 'selected' : ''; ?>>Live</option>
                        </select>
                    </div>
                </div>

                <h3>Mobile Money</h3>
                <div style="background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <div style="margin-bottom:10px;">
                        <label>
                            <input type="checkbox" name="mobile_enabled" <?php echo $settings['mobile_money']['enabled'] ? 'checked' : ''; ?>> Enable Mobile Money
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Provider (e.g., MTN, Airtel, Orange)</label>
                        <input type="text" name="mobile_provider" class="form-control" value="<?php echo htmlspecialchars($settings['mobile_money']['provider']); ?>">
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>API Key</label>
                            <input type="text" name="mobile_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['mobile_money']['api_key']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Merchant Code</label>
                            <input type="text" name="mobile_merchant" class="form-control" value="<?php echo htmlspecialchars($settings['mobile_money']['merchant_code']); ?>">
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_settings" class="btn-save">Save Settings</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>