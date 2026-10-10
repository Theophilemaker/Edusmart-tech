<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employer = $conn->query("SELECT id FROM employers WHERE user_id = $user_id")->fetch_assoc();
$employer_id = $employer['id'] ?? 0;

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;

// Update application status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $app_id = (int)$_POST['app_id'];
    $status = $_POST['status'];
    $conn->query("UPDATE applications SET status = '$status' WHERE id = $app_id");
    header("Location: view-applications.php?job_id=" . $_POST['job_id']);
    exit();
}

// Build query with correct column name: admission_number instead of admission_no
$query = "SELECT a.*, j.title, u.full_name, u.email, s.admission_number
          FROM applications a 
          JOIN jobs j ON a.job_id = j.id 
          JOIN students s ON a.student_id = s.id 
          JOIN users u ON s.user_id = u.id 
          WHERE j.employer_id = $employer_id";
if ($job_id) {
    $query .= " AND a.job_id = $job_id";
}
if ($application_id) {
    $query .= " AND a.id = $application_id";
}
$query .= " ORDER BY a.applied_date DESC";
$applications = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Applications</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Applications</h1>
            <a href="manage-jobs.php" class="btn-action edit">Back to Jobs</a>
        </div>

        <?php if ($application_id && $app = $applications->fetch_assoc()): ?>
            <div class="application-detail" style="background:white; padding:20px; border-radius:10px;">
                <h2>Application for: <?php echo htmlspecialchars($app['title']); ?></h2>
                <p><strong>Candidate:</strong> <?php echo htmlspecialchars($app['full_name']); ?> (<?php echo $app['email']; ?>)</p>
                <p><strong>Admission No:</strong> <?php echo $app['admission_number']; ?></p>
                <p><strong>Applied:</strong> <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></p>
                <p><strong>Status:</strong> 
                    <span class="status-badge <?php echo $app['status']; ?>"><?php echo ucfirst($app['status']); ?></span>
                </p>
                <h3>CV</h3>
                <p><a href="<?php echo $app['cv_path']; ?>" target="_blank">Download CV</a></p>
                <h3>Cover Letter</h3>
                <p><?php echo nl2br(htmlspecialchars($app['cover_letter'] ?? '')); ?></p>

                <form method="POST" style="margin-top:20px;">
                    <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                    <input type="hidden" name="job_id" value="<?php echo $app['job_id']; ?>">
                    <label>Update Status:</label>
                    <select name="status">
                        <option value="pending" <?php if($app['status']=='pending') echo 'selected'; ?>>Pending</option>
                        <option value="reviewed" <?php if($app['status']=='reviewed') echo 'selected'; ?>>Reviewed</option>
                        <option value="shortlisted" <?php if($app['status']=='shortlisted') echo 'selected'; ?>>Shortlisted</option>
                        <option value="rejected" <?php if($app['status']=='rejected') echo 'selected'; ?>>Rejected</option>
                        <option value="hired" <?php if($app['status']=='hired') echo 'selected'; ?>>Hired</option>
                    </select>
                    <button type="submit" name="update_status" class="btn-save">Update</button>
                </form>
                <hr>
                <a href="schedule-interview.php?application_id=<?php echo $app['id']; ?>" class="btn-primary">Schedule Interview</a>
                <a href="chat.php?user=<?php echo $app['user_id']; ?>" class="btn-primary">Send Message</a>
            </div>
        <?php elseif ($applications->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Candidate</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($app = $applications->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['title']); ?></td>
                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $app['status']; ?>"><?php echo ucfirst($app['status']); ?></span>
                        </td>
                        <td>
                            <a href="view-applications.php?application_id=<?php echo $app['id']; ?>" class="btn-action view">View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No applications found.</p>
        <?php endif; ?>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>