<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Fetch all products
$products = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>E-Store - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .product-card img {
            max-width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
        }
        .product-card h3 {
            margin: 10px 0;
        }
        .product-card .price {
            font-size: 1.3em;
            color: #667eea;
            font-weight: bold;
        }
        .btn-add {
            background: #4cc9f0;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-add:hover {
            background: #3aa8d0;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?> <!-- You may create a simple header -->

    <div class="container">
        <h1>Our Products</h1>
        <div class="product-grid">
            <?php while($p = $products->fetch_assoc()): ?>
            <div class="product-card">
                <img src="../uploads/products/<?php echo $p['image'] ?: 'default.jpg'; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                <h3><?php echo htmlspecialchars($p['name']); ?></h3>
                <p class="price">$<?php echo number_format($p['price'], 2); ?></p>
                <p><?php echo htmlspecialchars(substr($p['description'], 0, 100)); ?>...</p>
                <a href="product.php?id=<?php echo $p['id']; ?>" class="btn-add">View Details</a>
                <form method="post" action="cart.php?action=add">
                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                    <input type="number" name="quantity" value="1" min="1" style="width:60px;">
                    <button type="submit" class="btn-add">Add to Cart</button>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>