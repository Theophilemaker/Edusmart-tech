<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($product_id > 0);

// Fetch product if editing
$product = null;
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    if (!$product) {
        header("Location: manage-products.php");
        exit();
    }
}

$message = '';
$message_type = '';

// Helper to generate unique slug
function generateSlug($name, $conn, $current_id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $original = $slug;
    $counter = 1;
    while (true) {
        $query = "SELECT id FROM products WHERE slug = ?";
        if ($current_id > 0) {
            $query .= " AND id != ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $slug, $current_id);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $slug);
        }
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) break;
        $slug = $original . '-' . $counter++;
    }
    return $slug;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = trim($_POST['category']);

    $errors = [];
    if (empty($name)) $errors[] = "Product name is required";
    if ($price <= 0) $errors[] = "Price must be greater than zero";
    if ($stock < 0) $stock = 0;

    // Image upload handling
    $image_path = $product['image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/products/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $new_filename)) {
                // Delete old image
                if (!empty($product['image']) && file_exists($target_dir . $product['image'])) {
                    unlink($target_dir . $product['image']);
                }
                $image_path = $new_filename;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid image type. Allowed: JPG, PNG, GIF, WEBP.";
        }
    }

    if (empty($errors)) {
        // Generate slug
        if ($is_edit) {
            $slug = ($product['name'] != $name) ? generateSlug($name, $conn, $product_id) : $product['slug'];
        } else {
            $slug = generateSlug($name, $conn);
        }

        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, image = ?, slug = ? WHERE id = ?");
            $stmt->bind_param("ssdisssi", $name, $description, $price, $stock, $category, $image_path, $slug, $product_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, category, image, slug) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdisss", $name, $description, $price, $stock, $category, $image_path, $slug);
        }

        if ($stmt->execute()) {
            $message = "Product saved successfully!";
            $message_type = 'success';
            if (!$is_edit) {
                $new_id = $conn->insert_id;
                header("Location: edit-product.php?id=$new_id&msg=saved");
                exit();
            }
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Product</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></h1>
            <a href="manage-products.php" class="btn-action edit">Back to Products</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container" style="max-width:700px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label>Product Name*</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Price ($)*</label>
                        <input type="number" name="price" step="0.01" min="0" class="form-control" value="<?php echo $product['price'] ?? '0.00'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" class="form-control" value="<?php echo $product['stock'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Image</label>
                    <?php if ($is_edit && !empty($product['image'])): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../uploads/products/<?php echo $product['image']; ?>" style="max-width:150px; max-height:150px; border:1px solid #ddd; padding:5px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <small>Leave empty to keep current image (if editing).</small>
                </div>

                <button type="submit" name="save_product" class="btn-save">Save Product</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>