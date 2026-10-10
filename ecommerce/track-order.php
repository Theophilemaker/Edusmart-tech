<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$order = null;
if (isset($_POST['track'])) {
    $order_id = (int)$_POST['order_id'];
    $email = trim($_POST['email']);
    $order = $conn->query("SELECT * FROM orders WHERE id = $order_id AND (guest_email = '$email' OR user_id = (SELECT id FROM users WHERE email = '$email'))")->fetch_assoc();
    if ($order) {
        $items = $conn->query("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
    } else {
        $error = "Order not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Track Order</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h1>Track Your Order</h1>
        <?php if (isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <form method="post">
            <div class="form-group">
                <label>Order ID</label>
                <input type="number" name="order_id" required class="form-control">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required class="form-control">
            </div>
            <button type="submit" name="track" class="btn-primary">Track</button>
        </form>
        <?php if ($order): ?>
            <h2>Order #<?php echo $order['id']; ?></h2>
            <p>Status: <?php echo ucfirst($order['status']); ?></p>
            <p>Payment Status: <?php echo ucfirst($order['payment_status']); ?></p>
            <h3>Items</h3>
            <table class="table">
                <thead><tr><th>Product</th><th>Quantity</th><th>Price</th></tr></thead>
                <tbody>
                    <?php while($item = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <p><a href="invoice.php?id=<?php echo $order['id']; ?>">Download Invoice</a></p>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>