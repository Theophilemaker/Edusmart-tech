<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: ../auth/login.php");
    exit();
}

$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
if (!$application_id) {
    header("Location: view-applications.php");
    exit();
}

// Verify application belongs to this employer
$check = $conn->query("SELECT a.id FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = $application_id AND j.employer_id = (SELECT id FROM employers WHERE user_id = {$_SESSION['user_id']})");
if ($check->num_rows == 0) {
    die("Invalid application.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $time = $_POST['time'];
    $duration = (int)$_POST['duration'];
    $location = $_POST['location'];
    $meeting_link = $_POST['meeting_link'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO interviews (application_id, scheduled_date, scheduled_time, duration, location, meeting_link, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $application_id, $date, $time, $duration, $location, $meeting_link, $notes);
    if ($stmt->execute()) {
        $message = "Interview scheduled successfully!";
    } else {
        $message = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Schedule Interview</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <h1>Schedule Interview</h1>
            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="time" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" value="60" class="form-control">
                </div>
                <div class="form-group">
                    <label>Location (optional)</label>
                    <input type="text" name="location" class="form-control">
                </div>
                <div class="form-group">
                    <label>Meeting Link (optional)</label>
                    <input type="url" name="meeting_link" class="form-control">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn-save">Schedule</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>