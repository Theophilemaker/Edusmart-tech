<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Update order status
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    $conn->query("UPDATE orders SET status = '$status' WHERE id = $order_id");
    header("Location: manage-orders.php");
    exit();
}

$orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Manage Orders</h1>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($o = $orders->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $o['id']; ?></td>
                    <td><?php echo $o['guest_name'] ?: 'User '.$o['user_id']; ?></td>
                    <td>$<?php echo number_format($o['total_amount'], 2); ?></td>
                    <td><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                    <td><?php echo ucfirst($o['status']); ?></td>
                    <td><?php echo ucfirst($o['payment_status']); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <select name="status">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <button type="submit" name="update_status">Update</button>
                        </form>
                        <a href="view-order.php?id=<?php echo $o['id']; ?>">View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>