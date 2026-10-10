<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Handle job deletion
if (isset($_GET['delete'])) {
    $job_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM jobs WHERE id = $job_id");
    header("Location: manage-jobs.php");
    exit();
}

// Fetch all jobs with company name – order by id (newest first)
$jobs = $conn->query("
    SELECT j.*, e.company_name 
    FROM jobs j 
    JOIN employers e ON j.employer_id = e.id 
    ORDER BY j.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Jobs - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <!-- Include admin sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Manage Jobs</h1>
                    <p>All job listings posted by employers</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <div class="table-container">
                <?php if ($jobs->num_rows == 0): ?>
                    <p style="text-align:center; padding:40px;">No jobs found.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Company</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Posted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($job = $jobs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $job['id']; ?></td>
                                <td><?php echo htmlspecialchars($job['title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($job['company_name'] ?? ''); ?></td>
                                <td><?php echo $job['type'] ?? 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($job['location'] ?? '—'); ?></td>
                                <td>
                                    <?php 
                                        // Try to display date from available columns
                                        if (!empty($job['posted_date'])) {
                                            echo date('M d, Y', strtotime($job['posted_date']));
                                        } elseif (!empty($job['created_at'])) {
                                            echo date('M d, Y', strtotime($job['created_at']));
                                        } else {
                                            echo '—';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $job['status'] ?? 'open'; ?>">
                                        <?php echo ucfirst($job['status'] ?? 'open'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $job['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this job? This will also delete all applications.')">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- AI Assistant Widget -->
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>