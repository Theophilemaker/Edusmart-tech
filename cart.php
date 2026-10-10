<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Generate session ID for guests
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = bin2hex(random_bytes(16));
}
$session_id = $_SESSION['cart_session'];

// Handle quantity update / remove
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantity'] as $cart_id => $qty) {
            $qty = max(1, (int)$qty);
            $conn->query("UPDATE cart SET quantity = $qty WHERE id = $cart_id");
        }
    } elseif (isset($_POST['remove'])) {
        $cart_id = (int)$_POST['remove'];
        $conn->query("DELETE FROM cart WHERE id = $cart_id");
    }
    header("Location: cart.php");
    exit();
}

// Get cart items for logged-in user or guest
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $cart_items = $conn->query("
        SELECT c.*, p.name, p.price, p.image 
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = $user_id
    ");
} else {
    $cart_items = $conn->query("
        SELECT c.*, p.name, p.price, p.image 
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.session_id = '$session_id'
    ");
}

$total = 0;
?>
<?php include 'includes/header.php'; ?>
<div class="main-content" style="padding:20px;">
    <h1>Your Shopping Cart</h1>

    <?php if ($cart_items->num_rows == 0): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added anything yet.</p>
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <table class="table cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $cart_items->fetch_assoc()): 
                        $item_total = $item['price'] * $item['quantity'];
                        $total += $item_total;
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['image'])): ?>
                                <img src="uploads/products/<?php echo $item['image']; ?>" class="cart-product-img">
                            <?php endif; ?>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td>
                            <input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="cart-quantity-input">
                        </td>
                        <td>$<?php echo number_format($item_total, 2); ?></td>
                        <td>
                            <button type="submit" name="remove" value="<?php echo $item['id']; ?>" class="btn btn-danger btn-sm">Remove</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Total:</strong></td>
                        <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <div style="margin-top:20px;">
                <button type="submit" name="update_cart" class="btn btn-primary">Update Cart</button>
                <a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>