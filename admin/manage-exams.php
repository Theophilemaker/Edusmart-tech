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
    $conn->query("DELETE FROM exams WHERE id = $id");
    header("Location: manage-exams.php");
    exit();
}

// Use ORDER BY e.id DESC (newest first) because e.created_at does not exist.
$exams = $conn->query("
    SELECT e.*, c.title as course_title
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Exams</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .action-buttons { display: flex; gap: 5px; }
        .btn-action.edit { background: #4cc9f0; }
        .btn-action.delete { background: #f72585; }
        .btn-action.view { background: #10b981; }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Manage Exams</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <div class="table-container">
                <div style="margin-bottom:15px;">
                    <a href="add-exam.php" class="btn-action edit">Add New Exam</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Course</th>
                            <th>Type</th>
                            <th>Total Marks</th>
                            <th>Duration</th>
                            <th>Start Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($e = $exams->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $e['id']; ?></td>
                            <td><?php echo htmlspecialchars($e['title']); ?></td>
                            <td><?php echo htmlspecialchars($e['course_title']); ?></td>
                            <td><?php echo $e['type']; ?></td>
                            <td><?php echo $e['total_marks']; ?></td>
                            <td><?php echo $e['duration']; ?> min</td>
                            <td><?php echo date('M d, Y H:i', strtotime($e['start_date'])); ?></td>
                            <td class="action-buttons">
                                <a href="edit-exam.php?id=<?php echo $e['id']; ?>" class="btn-action edit">Edit</a>
                                <a href="?delete=<?php echo $e['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this exam?')">Delete</a>
                                <a href="view-results.php?exam=<?php echo $e['id']; ?>" class="btn-action view">Results</a>
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