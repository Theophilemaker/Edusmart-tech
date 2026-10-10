<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php'; // <-- ADD THIS

$csrf_token = generateCSRFToken(); // <-- ADD THIS

// ... rest of your code ...

// Ensure guest cart session
if (!isset($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = bin2hex(random_bytes(16));
}
$session_id = $_SESSION['cart_session'];

// Handle add to cart for products
if (isset($_GET['add_to_cart'])) {
    $product_id = (int)$_GET['add_to_cart'];
    $quantity = 1;

    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->bind_param("ii", $user_id, $product_id);
    } else {
        $check = $conn->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
        $check->bind_param("si", $session_id, $product_id);
    }
    $check->execute();
    $result = $check->get_result();

    if ($row = $result->fetch_assoc()) {
        $new_qty = $row['quantity'] + $quantity;
        $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update->bind_param("ii", $new_qty, $row['id']);
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

    header("Location: products.php?added=1");
    exit();
}

// Fetch products with stock > 0
$products = $conn->query("SELECT * FROM products WHERE stock > 0 ORDER BY id DESC");

// Fetch all services
$services = $conn->query("SELECT * FROM services ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Products & Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/ai-widget.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <!-- Products Section -->
        <h1>Our Products</h1>
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Product added to cart!
            </div>
        <?php endif; ?>

        <?php if ($products->num_rows == 0): ?>
            <p class="empty-state">No products available at the moment.</p>
        <?php else: ?>
            <div class="products-grid">
                <?php while ($product = $products->fetch_assoc()): ?>
                <div class="product-card card">
                    <div class="product-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        <?php if ($product['stock'] < 5 && $product['stock'] > 0): ?>
                            <span class="product-badge">Low Stock</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                        <div class="product-stock">
                            <i class="fas <?php echo $product['stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo $product['stock'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                        </div>
                        <div class="product-actions">
                            <?php if ($product['stock'] > 0): ?>
                                <a href="?add_to_cart=<?php echo $product['id']; ?>" class="btn btn-primary btn-add-to-cart">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </a>
                            <?php endif; ?>
                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- Services Section -->
        <?php if ($services->num_rows > 0): ?>
            <h2 style="margin-top: 40px;">Our Services</h2>
            <div class="products-grid">
                <?php while ($service = $services->fetch_assoc()): ?>
                <div class="product-card card">
                    <div class="product-image">
                        <?php if (!empty($service['image'])): ?>
                            <img src="uploads/services/<?php echo $service['image']; ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($service['category']); ?></div>
                        <h3 class="product-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                        <div class="product-price">$<?php echo number_format($service['price'], 2); ?></div>
                        <p class="product-description"><?php echo htmlspecialchars(substr($service['description'], 0, 100)); ?>...</p>
                        <div class="product-actions">
                            <a href="contact.php?service=<?php echo $service['id']; ?>" class="btn btn-primary">Inquire</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- Advice Request Button & Modal -->
<button class="btn btn-primary" onclick="openAdviceModal()" style="margin: 40px auto; display: block;">
    <i class="fas fa-lightbulb"></i> Get Personal Advice
</button>

<div id="adviceModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Request Personal Advice</h2>
            <span class="close" onclick="closeAdviceModal()">&times;</span>
        </div>
        <form method="POST" action="advice-request.php" id="adviceForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label>Your Name *</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Your Email *</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Phone Number (optional)</label>
                <input type="tel" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label>Your Message *</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" name="submit_advice" class="btn btn-primary">Submit Request</button>
        </form>
    </div>
</div>

<script>
function openAdviceModal() {
    document.getElementById('adviceModal').style.display = 'block';
}
function closeAdviceModal() {
    document.getElementById('adviceModal').style.display = 'none';
}
</script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>