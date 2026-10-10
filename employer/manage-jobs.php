<?php
// Safe session start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employer = $conn->query("SELECT id FROM employers WHERE user_id = $user_id")->fetch_assoc();
$employer_id = $employer['id'] ?? 0;

// Handle delete
if (isset($_GET['delete'])) {
    $job_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM jobs WHERE id = $job_id AND employer_id = $employer_id");
    header("Location: manage-jobs.php");
    exit();
}

// Use ORDER BY id DESC (newest first) because 'posted_date' may not exist
$jobs = $conn->query("SELECT * FROM jobs WHERE employer_id = $employer_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Jobs</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
    <style>
        .action-buttons { display: flex; gap: 5px; }
        .btn-action.edit { background: #4cc9f0; }
        .btn-action.delete { background: #f72585; }
        .btn-action.view { background: #10b981; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Manage Jobs</h1>
            <a href="post-job.php" class="btn-action edit">Post New Job</a>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Posted</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($jobs->num_rows == 0): ?>
                        <tr><td colspan="8" style="text-align:center;">No jobs posted yet.</td></tr>
                    <?php else: while($job = $jobs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $job['id']; ?></td>
                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                        <td><?php echo $job['type']; ?></td>
                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                        <td><?php 
                            // Try to use created_at if it exists, otherwise show ID
                            if (!empty($job['created_at'])) {
                                echo date('M d, Y', strtotime($job['created_at']));
                            } else {
                                echo '—';
                            }
                        ?></td>
                        <td><?php echo $job['deadline'] ? date('M d, Y', strtotime($job['deadline'])) : '—'; ?></td>
                        <td>
                            <span class="status-badge <?php echo $job['status']; ?>"><?php echo ucfirst($job['status']); ?></span>
                        </td>
                        <td class="action-buttons">
                            <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn-action edit">Edit</a>
                            <a href="?delete=<?php echo $job['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this job?')">Delete</a>
                            <a href="view-applications.php?job_id=<?php echo $job['id']; ?>" class="btn-action view">Applications</a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>