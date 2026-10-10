<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit();
}

// Get cart items and total
$ids = implode(',', array_keys($_SESSION['cart']));
$products = $conn->query("SELECT * FROM products WHERE id IN ($ids)");
$cart_items = [];
$total = 0;
while ($p = $products->fetch_assoc()) {
    $qty = $_SESSION['cart'][$p['id']];
    $subtotal = $p['price'] * $qty;
    $total += $subtotal;
    $cart_items[] = ['product' => $p, 'quantity' => $qty, 'subtotal' => $subtotal];
}

$user_id = $_SESSION['user_id'] ?? null;
$user = null;
if ($user_id) {
    $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';

    // Validate
    if (!$user_id && (empty($guest_name) || empty($guest_email))) {
        $error = "Name and email required for guest checkout.";
    } elseif (empty($shipping_address)) {
        $error = "Shipping address required.";
    } elseif (empty($payment_method)) {
        $error = "Please select a payment method.";
    } else {
        // Create order
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO orders (user_id, guest_name, guest_email, total_amount, shipping_address, payment_method, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')");
            $stmt->bind_param("issdss", $user_id, $guest_name, $guest_email, $total, $shipping_address, $payment_method);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert order items
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $item_stmt->bind_param("iiid", $order_id, $item['product']['id'], $item['quantity'], $item['product']['price']);
                $item_stmt->execute();
            }

            // Clear cart
            unset($_SESSION['cart']);

            $conn->commit();

            // Redirect to payment page or order confirmation
            header("Location: order-confirmation.php?id=$order_id");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error processing order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h1>Checkout</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <div style="display:flex; gap:30px;">
            <div style="flex:2;">
                <h2>Order Summary</h2>
                <table class="table">
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach($cart_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product']['name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>$<?php echo number_format($item['product']['price'], 2); ?></td>
                            <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="3" align="right"><strong>Total:</strong></td><td><strong>$<?php echo number_format($total, 2); ?></strong></td></tr></tfoot>
                </table>
            </div>
            <div style="flex:1;">
                <h2>Your Details</h2>
                <form method="post">
                    <?php if (!$user_id): ?>
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="guest_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="guest_email" class="form-control" required>
                        </div>
                    <?php else: ?>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Shipping Address</label>
                        <textarea name="shipping_address" class="form-control" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="">Select</option>
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <button type="submit" name="place_order" class="btn-primary">Place Order</button>
                </form>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>