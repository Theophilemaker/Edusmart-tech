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
    // Get image filename to delete from server
    $img = $conn->query("SELECT image FROM services WHERE id = $id")->fetch_assoc();
    if ($img && !empty($img['image'])) {
        $file = "../uploads/services/" . $img['image'];
        if (file_exists($file)) unlink($file);
    }
    $conn->query("DELETE FROM services WHERE id = $id");
    header("Location: manage-services.php");
    exit();
}

$services = $conn->query("SELECT * FROM services ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Services</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .action-buttons { display: flex; gap: 5px; }
        .btn-action.edit { background: #4cc9f0; }
        .btn-action.delete { background: #f72585; }
        .service-image { max-width: 50px; max-height: 50px; object-fit: cover; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Manage Services</h1>
            <a href="edit-service.php" class="btn-action edit">Add New Service</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($services->num_rows == 0): ?>
                        <tr><td colspan="7" style="text-align:center;">No services yet.</td></tr>
                    <?php else: while($s = $services->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td>
                            <?php if (!empty($s['image'])): ?>
                                <img src="../uploads/services/<?php echo $s['image']; ?>" class="service-image">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($s['description'], 0, 50)); ?>...</td>
                        <td>$<?php echo number_format($s['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($s['category']); ?></td>
                        <td class="action-buttons">
                            <a href="edit-service.php?id=<?php echo $s['id']; ?>" class="btn-action edit">Edit</a>
                            <a href="?delete=<?php echo $s['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this service?')">Delete</a>
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