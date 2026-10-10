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
    $conn->query("DELETE FROM classes WHERE id = $id");
    header("Location: manage-classes.php");
    exit();
}

$classes = $conn->query("
    SELECT c.*, u.full_name as teacher_name
    FROM classes c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY c.class_name, c.section
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Classes</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .action-buttons { display: flex; gap: 5px; }
        .btn-action.edit { background: #4cc9f0; }
        .btn-action.delete { background: #f72585; }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Manage Classes</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <div class="table-container">
                <div style="margin-bottom:15px;">
                    <a href="add-class.php" class="btn-action edit">Add New Class</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Class Name</th>
                            <th>Section</th>
                            <th>Teacher</th>
                            <th>Academic Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($cls = $classes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $cls['id']; ?></td>
                            <td><?php echo htmlspecialchars($cls['class_name']); ?></td>
                            <td><?php echo $cls['section']; ?></td>
                            <td><?php echo htmlspecialchars($cls['teacher_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $cls['academic_year']; ?></td>
                            <td class="action-buttons">
                                <a href="edit-class.php?id=<?php echo $cls['id']; ?>" class="btn-action edit">Edit</a>
                                <a href="?delete=<?php echo $cls['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this class?')">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>