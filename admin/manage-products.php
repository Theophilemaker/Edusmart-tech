

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

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // First get image path to delete file
    $img = $conn->query("SELECT image FROM products WHERE id = $id")->fetch_assoc();
    if ($img && !empty($img['image'])) {
        $file = "../uploads/products/" . $img['image'];
        if (file_exists($file)) unlink($file);
    }
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: manage-products.php");
    exit();
}

$products = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Products</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .action-buttons { display: flex; gap: 5px; }
        .btn-action.edit { background: #4cc9f0; }
        .btn-action.delete { background: #f72585; }
        .product-image { max-width: 50px; max-height: 50px; object-fit: cover; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Manage Products</h1>
            <a href="edit-product.php" class="btn-action edit">Add New Product</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->num_rows == 0): ?>
                        <tr><td colspan="7" style="text-align:center;">No products yet.</td></tr>
                    <?php else: while($p = $products->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td>
                            <?php if (!empty($p['image'])): ?>
                                <img src="../uploads/products/<?php echo $p['image']; ?>" class="product-image">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td>$<?php echo number_format($p['price'], 2); ?></td>
                        <td><?php echo $p['stock']; ?></td>
                        <td><?php echo htmlspecialchars($p['category']); ?></td>
                        <td class="action-buttons">
                            <a href="edit-product.php?id=<?php echo $p['id']; ?>" class="btn-action edit">Edit</a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this product?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>