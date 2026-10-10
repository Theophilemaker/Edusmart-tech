<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'config/database.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$order_id || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$order = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = {$_SESSION['user_id']}")->fetch_assoc();
if (!$order) {
    header("Location: index.php");
    exit();
}
?>
<?php include 'includes/header.php'; ?>
<div class="main-content" style="text-align:center; padding:50px;">
    <i class="fas fa-check-circle" style="font-size:5em; color:#28a745;"></i>
    <h1>Thank You for Your Order!</h1>
    <p>Your order number is <strong><?php echo $order['order_number']; ?></strong></p>
    <p>You will receive a confirmation email shortly.</p>
    <a href="my-orders.php" class="btn btn-primary">View My Orders</a>
    <a href="products.php" class="btn btn-secondary">Continue Shopping</a>
</div>
<?php include 'includes/footer.php'; ?>