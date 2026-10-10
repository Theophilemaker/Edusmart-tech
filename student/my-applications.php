<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/**
 * Get or create student record.
 * Returns student ID or 0 on failure.
 */
function getStudentId($conn, $user_id) {
    // Try to fetch existing student record
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }

    // No record found – create one with a generated admission number
    // Generate a unique admission number (e.g., STU-2025-0001)
    $year = date('Y');
    $admission_number = 'STU-' . $year . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

    // Insert new student record (adjust column name if needed)
    $insert = $conn->prepare("INSERT INTO students (user_id, admission_number) VALUES (?, ?)");
    $insert->bind_param("is", $user_id, $admission_number);
    if ($insert->execute()) {
        return $conn->insert_id;
    } else {
        // If insert fails, log error and return 0
        error_log("Failed to create student record for user $user_id: " . $conn->error);
        return 0;
    }
}

$student_id = getStudentId($conn, $user_id);
if (!$student_id) {
    die("Error: Unable to create or find your student record. Please contact support.");
}

// Fetch applications
$apps = $conn->prepare("
    SELECT a.*, j.title, e.company_name 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    JOIN employers e ON j.employer_id = e.id 
    WHERE a.student_id = ? 
    ORDER BY a.applied_date DESC
");
$apps->bind_param("i", $student_id);
$apps->execute();
$result = $apps->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Applications - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="student">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>My Applications</h1>
                    <p>Track your job applications</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4cc9f0&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <div class="table-container">
                <?php if ($result->num_rows == 0): ?>
                    <p style="text-align:center; padding:40px;">You have not applied to any jobs yet.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Company</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($app['company_name'] ?? ''); ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_date'] ?? 'now')); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $app['status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($app['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view-application.php?id=<?php echo $app['id']; ?>" class="btn-action view">View</a>
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