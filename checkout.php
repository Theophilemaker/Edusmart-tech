<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php?redirect=checkout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$session_id = $_SESSION['cart_session'] ?? '';

// Get cart items
$cart_items = $conn->query("
    SELECT c.*, p.name, p.price 
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = $user_id OR c.session_id = '$session_id'
");

if ($cart_items->num_rows == 0) {
    header("Location: products.php");
    exit();
}

// Calculate total
$total = 0;
while ($item = $cart_items->fetch_assoc()) {
    $total += $item['price'] * $item['quantity'];
}
// Reset pointer
$cart_items->data_seek(0);

$message = '';
$message_type = '';

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $zip = trim($_POST['zip']);
    $payment_method = $_POST['payment_method'];

    if (empty($address) || empty($city)) {
        $message = "Address and city are required.";
        $message_type = 'error';
    } else {
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Create order
            $order_number = 'ORD-' . strtoupper(uniqid());
            $stmt = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, status, payment_method, shipping_address) VALUES (?, ?, ?, 'pending', ?, ?)");
            $full_address = "$address, $city $zip";
            $stmt->bind_param("isdss", $user_id, $order_number, $total, $payment_method, $full_address);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert order items and update stock
            $cart_items->data_seek(0);
            while ($item = $cart_items->fetch_assoc()) {
                // Insert order item
                $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $stmt2->execute();

                // Update stock
                $conn->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$item['product_id']}");
            }

            // Clear cart
            $conn->query("DELETE FROM cart WHERE user_id = $user_id OR session_id = '$session_id'");

            $conn->commit();

            // Redirect to order confirmation
            header("Location: order-confirmation.php?order_id=$order_id");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error placing order: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<?php include 'includes/header.php'; ?>

<div class="main-content" style="margin-left:0; padding:20px; max-width:800px; margin:0 auto;">
    <h1>Checkout</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div style="background:white; padding:20px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h3>Order Summary</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $cart_items->data_seek(0);
                while ($item = $cart_items->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong>Total:</strong></td>
                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <h3>Shipping Address</h3>
            <div class="form-group">
                <label>Address *</label>
                <input type="text" name="address" class="form-control" required>
            </div>
            <div class="form-row" style="display:flex; gap:15px;">
                <div class="form-group" style="flex:2;">
                    <label>City *</label>
                    <input type="text" name="city" class="form-control" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>ZIP / Postal Code</label>
                    <input type="text" name="zip" class="form-control">
                </div>
            </div>

            <h3>Payment Method</h3>
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px;">
                    <input type="radio" name="payment_method" value="stripe" required> Stripe (Credit Card)
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="radio" name="payment_method" value="paypal" required> PayPal
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="radio" name="payment_method" value="mobile_money" required> Mobile Money
                </label>
            </div>

            <button type="submit" name="place_order" class="btn btn-success btn-lg">Place Order</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>