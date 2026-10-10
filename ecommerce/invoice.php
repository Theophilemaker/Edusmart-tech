<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$order_id) {
    die("Invalid order.");
}

$order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
if (!$order) {
    die("Order not found.");
}

$items = $conn->query("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice #<?php echo $order_id; ?></title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.15); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .total { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="invoice-box">
        <h1>Invoice</h1>
        <p><strong>Order ID:</strong> <?php echo $order_id; ?></p>
        <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($order['created_at'])); ?></p>
        <p><strong>Customer:</strong> <?php echo $order['guest_name'] ?: 'Registered User'; ?></p>
        <p><strong>Email:</strong> <?php echo $order['guest_email'] ?: 'N/A'; ?></p>
        <p><strong>Shipping Address:</strong> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
        <h2>Items</h2>
        <table>
            <thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
                <?php while($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" align="right"><strong>Total:</strong></td><td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td></tr>
            </tfoot>
        </table>
        <p class="no-print"><button onclick="window.print()">Print Invoice</button></p>
    </div>
</body>
</html>