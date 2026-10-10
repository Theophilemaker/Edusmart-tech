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

// Handle status update
if (isset($_POST['update_status'])) {
    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    $conn->query("UPDATE advice_requests SET status = '$status' WHERE id = $id");
    header("Location: advice-requests.php");
    exit();
}

// Fetch all requests
$requests = $conn->query("SELECT * FROM advice_requests ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advice Requests</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <style>
        .table-container {
            overflow-x: auto;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .status-new { background: #4cc9f0; color: white; }
        .status-contacted { background: #f59e0b; color: white; }
        .status-closed { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Personal Advice Requests</h1>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests->num_rows == 0): ?>
                        <tr><td colspan="8" style="text-align:center;">No requests yet.</td></tr>
                    <?php else: while ($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['message'], 0, 50)); ?>...</td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <select name="status" onchange="this.form.submit()" class="status-badge status-<?php echo $row['status']; ?>">
                                    <option value="new" <?php echo $row['status']=='new'?'selected':''; ?>>New</option>
                                    <option value="contacted" <?php echo $row['status']=='contacted'?'selected':''; ?>>Contacted</option>
                                    <option value="closed" <?php echo $row['status']=='closed'?'selected':''; ?>>Closed</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="mailto:<?php echo $row['email']; ?>" class="btn-action view" title="Email"><i class="fas fa-envelope"></i></a>
                            <?php if (!empty($row['phone'])): ?>
                                <a href="tel:<?php echo $row['phone']; ?>" class="btn-action edit" title="Call"><i class="fas fa-phone"></i></a>
                            <?php endif; ?>
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