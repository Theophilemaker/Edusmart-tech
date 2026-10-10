<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) {
    header("Location: products.php");
    exit();
}

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: products.php");
    exit();
}

// Ensure guest has a cart session
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = bin2hex(random_bytes(16));
}
$session_id = $_SESSION['cart_session'];

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $quantity = (int)$_POST['quantity'];
    if ($quantity < 1) $quantity = 1;
    if ($quantity > $product['stock']) $quantity = $product['stock'];

    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->bind_param("ii", $user_id, $product_id);
    } else {
        $check = $conn->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
        $check->bind_param("si", $session_id, $product_id);
    }
    $check->execute();
    $cart_item = $check->get_result()->fetch_assoc();

    if ($cart_item) {
        $new_qty = $cart_item['quantity'] + $quantity;
        $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update->bind_param("ii", $new_qty, $cart_item['id']);
        $update->execute();
    } else {
        if (isset($_SESSION['user_id'])) {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $user_id, $product_id, $quantity);
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $session_id, $product_id, $quantity);
        }
        $stmt->execute();
    }

    header("Location: cart.php");
    exit();
}
?>
<?php include 'includes/header.php'; ?>

<div class="main-content" style="padding:20px;">
    <div class="product-detail" style="max-width:1000px; margin:0 auto; background:white; border-radius:16px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px;">
            <!-- Product Image -->
            <div>
                <?php if (!empty($product['image'])): ?>
                    <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:100%; border-radius:12px;">
                <?php else: ?>
                    <div style="width:100%; height:300px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border-radius:12px;">No Image</div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div>
                <h1 style="font-size:2em; margin-bottom:10px;"><?php echo htmlspecialchars($product['name']); ?></h1>
                <p style="font-size:1.2em; color:#666; margin-bottom:20px;"><?php echo htmlspecialchars($product['category']); ?></p>
                <p style="font-size:2em; font-weight:bold; color:#667eea; margin-bottom:20px;">$<?php echo number_format($product['price'], 2); ?></p>
                <p style="margin-bottom:20px;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                <div style="margin-bottom:20px;">
                    <strong>Availability:</strong> 
                    <?php if ($product['stock'] > 0): ?>
                        <span style="color:#28a745;">In Stock (<?php echo $product['stock']; ?> available)</span>
                    <?php else: ?>
                        <span style="color:#dc3545;">Out of Stock</span>
                    <?php endif; ?>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <form method="POST">
                        <div style="display:flex; gap:10px; align-items:center;">
                            <label for="quantity">Quantity:</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" style="width:80px; padding:8px; border:2px solid #e0e0e0; border-radius:8px;">
                            <button type="submit" name="add_to_cart" class="btn btn-primary">Add to Cart</button>
                        </div>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>Out of Stock</button>
                <?php endif; ?>

                <div style="margin-top:30px;">
                    <a href="products.php" class="btn btn-secondary">← Back to Products</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>