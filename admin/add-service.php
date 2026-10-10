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

$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($service_id > 0);

// Fetch service if editing
$service = null;
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    if (!$service) {
        header("Location: manage-services.php");
        exit();
    }
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_service'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);

    $errors = [];
    if (empty($name)) $errors[] = "Service name is required";
    if ($price <= 0) $errors[] = "Price must be greater than zero";

    // Handle image upload
    $image_path = $service['image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/services/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $new_filename)) {
                // Delete old image if exists
                if (!empty($service['image']) && file_exists($target_dir . $service['image'])) {
                    unlink($target_dir . $service['image']);
                }
                $image_path = $new_filename;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid file type. Allowed: JPG, PNG, GIF, WEBP.";
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, price = ?, category = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssdssi", $name, $description, $price, $category, $image_path, $service_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO services (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdss", $name, $description, $price, $category, $image_path);
        }
        if ($stmt->execute()) {
            $message = "Service saved successfully!";
            $message_type = 'success';
            if (!$is_edit) {
                $new_id = $conn->insert_id;
                header("Location: edit-service.php?id=$new_id&msg=saved");
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
<html>
<head>
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Service</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1><?php echo $is_edit ? 'Edit Service' : 'Add New Service'; ?></h1>
            <a href="manage-services.php" class="btn-action edit">Back to Services</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container" style="max-width:600px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label>Service Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($service['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Price ($)*</label>
                        <input type="number" name="price" step="0.01" class="form-control" value="<?php echo $service['price'] ?? '0.00'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($service['category'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Service Image</label>
                    <?php if ($is_edit && !empty($service['image'])): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../uploads/services/<?php echo $service['image']; ?>" style="max-width:150px; max-height:150px; border:1px solid #ddd; padding:5px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <small>Leave empty to keep current image (if editing).</small>
                </div>

                <button type="submit" name="save_service" class="btn-save">Save Service</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>