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
if (!$employer) {
    die("Employer profile not found.");
}
$employer_id = $employer['id'];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $location = trim($_POST['location']);
    $salary = trim($_POST['salary']);
    $type = $_POST['type'];
    $deadline = $_POST['deadline'];

    $errors = [];
    if (empty($title)) $errors[] = "Job title is required";
    if (empty($description)) $errors[] = "Description is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO jobs (employer_id, title, description, requirements, location, salary, type, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $employer_id, $title, $description, $requirements, $location, $salary, $type, $deadline);
        if ($stmt->execute()) {
            $message = "Job posted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post a Job</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Post a New Job</h1>
            <a href="manage-jobs.php" class="btn-action edit">Back to Jobs</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container" style="max-width:700px;">
            <form method="POST" class="job-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label>Job Title*</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description*</label>
                    <textarea name="description" class="form-control" rows="5" required></textarea>
                </div>
                <div class="form-group">
                    <label>Requirements</label>
                    <textarea name="requirements" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Salary (e.g., $50k–$70k)</label>
                        <input type="text" name="salary" class="form-control">
                    </div>
                </div>
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Job Type</label>
                        <select name="type" class="form-control">
                            <option value="full-time">Full‑time</option>
                            <option value="part-time">Part‑time</option>
                            <option value="contract">Contract</option>
                            <option value="internship">Internship</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Application Deadline</label>
                        <input type="date" name="deadline" class="form-control">
                    </div>
                </div>
                <button type="submit" name="post_job" class="btn-save">Post Job</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>