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
    header("Location: manage-students.php");
    exit();
}

// Get list of columns in the students table
$columns_result = $conn->query("SHOW COLUMNS FROM students");
$columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $columns[] = $col['Field'];
}

// Build SELECT clause based on actual columns
$select_fields = "u.*, s.admission_number";
$table_headers = "<th>ID</th><th>Name</th><th>Email</th><th>Admission No.</th>";

// Check for optional columns
if (in_array('class', $columns)) {
    $select_fields .= ", s.class";
    $table_headers .= "<th>Class</th>";
}
if (in_array('parent_name', $columns)) {
    $select_fields .= ", s.parent_name";
    $table_headers .= "<th>Parent</th>";
}
if (in_array('parent_phone', $columns)) {
    $select_fields .= ", s.parent_phone";
    $table_headers .= "<th>Parent Phone</th>";
}
// Add status and actions headers
$table_headers .= "<th>Status</th><th>Actions</th>";

$query = "
    SELECT $select_fields
    FROM users u
    JOIN students s ON u.id = s.user_id
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
";
$students = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Students</title>
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
                    <h1>Manage Students</h1>
                    <p>All registered students</p>
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
                        <?php if ($students->num_rows == 0): ?>
                            <tr><td colspan="99" style="text-align:center;">No students found.</td></tr>
                        <?php else: while($s = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                            <td><?php echo $s['email']; ?></td>
                            <td><?php echo $s['admission_number']; ?></td>
                            <?php if (in_array('class', $columns)): ?>
                                <td><?php echo $s['class'] ?? '—'; ?></td>
                            <?php endif; ?>
                            <?php if (in_array('parent_name', $columns)): ?>
                                <td><?php echo htmlspecialchars($s['parent_name'] ?? '—'); ?></td>
                            <?php endif; ?>
                            <?php if (in_array('parent_phone', $columns)): ?>
                                <td><?php echo $s['parent_phone'] ?? '—'; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="status-badge <?php echo ($s['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                    <?php echo ($s['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($s['is_active'] ?? 1): ?>
                                    <a href="?action=deactivate&id=<?php echo $s['id']; ?>" class="btn-action deactivate" onclick="return confirm('Deactivate this student?')">Deactivate</a>
                                <?php else: ?>
                                    <a href="?action=activate&id=<?php echo $s['id']; ?>" class="btn-action activate" onclick="return confirm('Activate this student?')">Activate</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $s['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this student? This action cannot be undone.')">Delete</a>
                                <a href="edit-student.php?id=<?php echo $s['id']; ?>" class="btn-action edit">Edit</a>
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