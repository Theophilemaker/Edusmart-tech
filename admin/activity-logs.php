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

$search = $_GET['search'] ?? '';
$user_filter = $_GET['user'] ?? '';

$query = "SELECT al.*, u.full_name FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE 1=1";
if ($user_filter) {
    $user_filter_esc = (int)$user_filter;
    $query .= " AND al.user_id = $user_filter_esc";
}
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $query .= " AND (al.action LIKE '%$search_esc%' OR al.description LIKE '%$search_esc%')";
}
$query .= " ORDER BY al.created_at DESC LIMIT 100";
$logs = $conn->query($query);

// Get list of users for filter
$users = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activity Logs</title>
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
        .log-entry {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        .log-time { color: #999; font-size: 0.85em; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Activity Logs</h1>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:10px; width:100%; flex-wrap:wrap;">
                <select name="user" style="padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                    <option value="">All Users</option>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php if($user_filter == $u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="search" placeholder="Search action or description" value="<?php echo htmlspecialchars($search); ?>" style="flex:2; padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                <button type="submit" class="btn-action view">Filter</button>
                <a href="activity-logs.php" class="btn-action edit">Reset</a>
            </form>
        </div>

        <div class="table-container">
            <?php if ($logs->num_rows == 0): ?>
                <p style="text-align:center; padding:40px;">No logs found.</p>
            <?php else: while($log = $logs->fetch_assoc()): ?>
                <div class="log-entry">
                    <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                    <span class="log-time">(<?php echo $log['ip_address'] ?? ''; ?>)</span>
                    <p><?php echo htmlspecialchars($log['action']); ?> – <?php echo htmlspecialchars($log['description']); ?></p>
                    <small><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></small>
                </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>