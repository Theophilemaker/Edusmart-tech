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

// Handle activation/deactivation/deletion
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    if ($_GET['action'] == 'activate') {
        $conn->query("UPDATE users SET is_active = 1 WHERE id = $user_id");
    } elseif ($_GET['action'] == 'deactivate') {
        $conn->query("UPDATE users SET is_active = 0 WHERE id = $user_id");
    } elseif ($_GET['action'] == 'delete') {
        $conn->query("DELETE FROM users WHERE id = $user_id");
    }
    header("Location: manage-employers.php");
    exit();
}

// Get list of columns in the employers table
$columns_result = $conn->query("SHOW COLUMNS FROM employers");
$columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $columns[] = $col['Field'];
}

// Build SELECT clause based on actual columns
$select_fields = "u.*, e.company_name";
$table_headers = "<th>ID</th><th>Company</th><th>Contact Person</th><th>Email</th>";

if (in_array('phone', $columns)) {
    $select_fields .= ", e.phone as company_phone";
    $table_headers .= "<th>Phone</th>";
}
if (in_array('website', $columns)) {
    $select_fields .= ", e.website";
    $table_headers .= "<th>Website</th>";
}
$table_headers .= "<th>Status</th><th>Actions</th>";

$query = "
    SELECT $select_fields
    FROM users u
    JOIN employers e ON u.id = e.user_id
    WHERE u.role = 'employer'
    ORDER BY u.created_at DESC
";
$employers = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Employers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Manage Employers</h1>
                    <p>All registered employers</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <?php echo $table_headers; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($employers->num_rows == 0): ?>
                            <tr><td colspan="99" style="text-align:center;">No employers found.</td></tr>
                        <?php else: while($emp = $employers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $emp['id']; ?></td>
                            <td><?php echo htmlspecialchars($emp['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo $emp['email']; ?></td>
                            <?php if (in_array('phone', $columns)): ?>
                                <td><?php echo $emp['company_phone'] ?? '—'; ?></td>
                            <?php endif; ?>
                            <?php if (in_array('website', $columns)): ?>
                                <td>
                                    <?php if (!empty($emp['website'])): ?>
                                        <a href="<?php echo htmlspecialchars($emp['website']); ?>" target="_blank">Visit</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="status-badge <?php echo ($emp['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                    <?php echo ($emp['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($emp['is_active'] ?? 1): ?>
                                    <a href="?action=deactivate&id=<?php echo $emp['id']; ?>" class="btn-action deactivate" onclick="return confirm('Suspend this employer?')">Suspend</a>
                                <?php else: ?>
                                    <a href="?action=activate&id=<?php echo $emp['id']; ?>" class="btn-action activate" onclick="return confirm('Activate this employer?')">Activate</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $emp['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this employer? All associated data will be removed.')">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>