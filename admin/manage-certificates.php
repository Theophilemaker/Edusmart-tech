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

$certificates = $conn->query("
    SELECT c.*, u.full_name as student_name, co.title as course_title
    FROM certificates c
    JOIN students s ON c.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN courses co ON c.course_id = co.id
    ORDER BY c.issue_date DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Certificates</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .btn-action.view { background: #10b981; }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Manage Certificates</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Certificate No.</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($cert = $certificates->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $cert['id']; ?></td>
                            <td><?php echo $cert['certificate_no']; ?></td>
                            <td><?php echo htmlspecialchars($cert['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($cert['course_title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($cert['issue_date'])); ?></td>
                            <td><?php echo ucfirst($cert['status']); ?></td>
                            <td>
                                <a href="../student/view-certificate.php?id=<?php echo $cert['id']; ?>" target="_blank" class="btn-action view">View</a>
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