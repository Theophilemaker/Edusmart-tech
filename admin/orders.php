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

// Update order status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    $conn->query("UPDATE orders SET status = '$status' WHERE id = $order_id");
    header("Location: orders.php");
    exit();
}

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT o.*, u.full_name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
if ($status_filter != 'all') {
    $status_filter_esc = $conn->real_escape_string($status_filter);
    $query .= " AND o.status = '$status_filter_esc'";
}
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $query .= " AND (o.order_number LIKE '%$search_esc%' OR u.full_name LIKE '%$search_esc%')";
}
$query .= " ORDER BY o.created_at DESC";
$orders = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .status-select {
            padding: 4px;
            border-radius: 4px;
        }
        .badge-pending { background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 12px; }
        .badge-processing { background: #cce5ff; color: #004085; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Orders</h1>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:10px; width:100%;">
                <select name="status" style="padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                    <option value="all">All Statuses</option>
                    <option value="pending" <?php if($status_filter=='pending') echo 'selected'; ?>>Pending</option>
                    <option value="processing" <?php if($status_filter=='processing') echo 'selected'; ?>>Processing</option>
                    <option value="completed" <?php if($status_filter=='completed') echo 'selected'; ?>>Completed</option>
                    <option value="cancelled" <?php if($status_filter=='cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
                <input type="text" name="search" placeholder="Search order # or customer" value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                <button type="submit" class="btn-action view">Filter</button>
                <a href="orders.php" class="btn-action edit">Reset</a>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders->num_rows == 0): ?>
                        <tr><td colspan="7" style="text-align:center;">No orders found.</td></tr>
                    <?php else: while($o = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $o['order_number']; ?></td>
                        <td><?php echo htmlspecialchars($o['customer_name'] ?? 'Guest'); ?></td>
                        <td>$<?php echo number_format($o['total_amount'], 2); ?></td>
                        <td><?php echo ucfirst($o['payment_method']); ?></td>
                        <td>
                            <span class="badge-<?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <select name="status" class="status-select">
                                    <option value="pending" <?php if($o['status']=='pending') echo 'selected'; ?>>Pending</option>
                                    <option value="processing" <?php if($o['status']=='processing') echo 'selected'; ?>>Processing</option>
                                    <option value="completed" <?php if($o['status']=='completed') echo 'selected'; ?>>Completed</option>
                                    <option value="cancelled" <?php if($o['status']=='cancelled') echo 'selected'; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn-action view">Update</button>
                            </form>
                            <a href="order-detail.php?id=<?php echo $o['id']; ?>" class="btn-action edit">View</a>
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