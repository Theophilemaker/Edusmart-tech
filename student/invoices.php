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

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT p.*, o.order_number, u.full_name as customer_name 
          FROM payments p 
          JOIN orders o ON p.order_id = o.id 
          LEFT JOIN users u ON o.user_id = u.id 
          WHERE 1=1";
if ($status_filter != 'all') {
    $status_filter_esc = $conn->real_escape_string($status_filter);
    $query .= " AND p.status = '$status_filter_esc'";
}
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $query .= " AND (o.order_number LIKE '%$search_esc%' OR u.full_name LIKE '%$search_esc%' OR p.transaction_id LIKE '%$search_esc%')";
}
$query .= " ORDER BY p.created_at DESC";
$payments = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoices</title>
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
        .badge-paid { background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 12px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-failed { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Invoices / Payments</h1>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:10px; width:100%;">
                <select name="status" style="padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                    <option value="all" <?php if($status_filter=='all') echo 'selected'; ?>>All Statuses</option>
                    <option value="paid" <?php if($status_filter=='paid') echo 'selected'; ?>>Paid</option>
                    <option value="pending" <?php if($status_filter=='pending') echo 'selected'; ?>>Pending</option>
                    <option value="failed" <?php if($status_filter=='failed') echo 'selected'; ?>>Failed</option>
                </select>
                <input type="text" name="search" placeholder="Search order #, customer, transaction ID" value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                <button type="submit" class="btn-action view">Filter</button>
                <a href="invoices.php" class="btn-action edit">Reset</a>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments->num_rows == 0): ?>
                        <tr><td colspan="9" style="text-align:center;">No payments found.</td></tr>
                    <?php else: while($p = $payments->fetch_assoc()): ?>
                    <tr>
                        <td>INV-<?php echo str_pad($p['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo $p['order_number']; ?></td>
                        <td><?php echo htmlspecialchars($p['customer_name'] ?? 'Guest'); ?></td>
                        <td>$<?php echo number_format($p['amount'], 2); ?></td>
                        <td><?php echo ucfirst($p['payment_method']); ?></td>
                        <td><?php echo $p['transaction_id'] ?? '—'; ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></td>
                        <td>
                            <span class="badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span>
                        </td>
                        <td>
                            <a href="invoice-detail.php?id=<?php echo $p['id']; ?>" class="btn-action view">View</a>
                            <a href="generate-invoice.php?id=<?php echo $p['id']; ?>" class="btn-action edit" target="_blank">PDF</a>
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