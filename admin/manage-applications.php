<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Remove j.type from SELECT
$apps = $conn->query("
    SELECT a.*, j.title AS job_title, u.full_name AS student_name, e.company_name
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN employers e ON j.employer_id = e.id
    ORDER BY a.applied_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Applications - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <h1>Manage Applications</h1>
                    <p>Overview of all job applications</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <div class="table-container">
                <?php if ($apps->num_rows == 0): ?>
                    <p style="text-align:center; padding:40px;">No applications found.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Company</th>
                                <th>Student</th>
                                <th>Applied</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $apps->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['job_title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($app['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($app['student_name'] ?? ''); ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_date'] ?? 'now')); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $app['status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($app['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>