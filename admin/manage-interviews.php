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

// Use ORDER BY i.id DESC because i.scheduled_date may not exist.
$interviews = $conn->query("
    SELECT i.*, a.job_id, j.title as job_title, u.full_name as candidate_name, e.company_name
    FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN employers e ON j.employer_id = e.id
    ORDER BY i.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Interviews</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Manage Interviews</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Job</th>
                            <th>Company</th>
                            <th>Candidate</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Location/Link</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($i = $interviews->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i['id']; ?></td>
                            <td><?php echo htmlspecialchars($i['job_title']); ?></td>
                            <td><?php echo htmlspecialchars($i['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($i['candidate_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($i['scheduled_date'] ?? '')); ?></td>
                            <td><?php echo date('H:i', strtotime($i['scheduled_time'] ?? '')); ?></td>
                            <td><?php echo $i['location'] ?: ($i['meeting_link'] ? '<a href="'.$i['meeting_link'].'">Link</a>' : '—'); ?></td>
                            <td><?php echo ucfirst($i['status']); ?></td>
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