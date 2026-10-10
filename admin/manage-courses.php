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
    $conn->query("DELETE FROM courses WHERE id = $id");
    header("Location: manage-courses.php");
    exit();
}

// Fetch all courses with teacher name
$courses = $conn->query("
    SELECT c.*, u.full_name as teacher_name
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY c.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Courses</title>
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
                <h1>Manage Courses</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <div class="table-container">
                <div style="margin-bottom:15px;">
                    <a href="add-course.php" class="btn-action edit">Add New Course</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th>Teacher</th>
                            <th>Price</th>
                            <th>Published</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($c = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><?php echo $c['category']; ?></td>
                            <td><?php echo $c['level']; ?></td>
                            <td><?php echo htmlspecialchars($c['teacher_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $c['price'] ? '$'.$c['price'] : 'Free'; ?></td>
                            <td><?php echo $c['is_published'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            <td class="action-buttons">
                                <a href="edit-course.php?id=<?php echo $c['id']; ?>" class="btn-action edit">Edit</a>
                                <a href="?delete=<?php echo $c['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this course?')">Delete</a>
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