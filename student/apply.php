<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $conn->query("SELECT id FROM students WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc()['id'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Check if already applied
$existing = $conn->query("SELECT id FROM applications WHERE job_id = $job_id AND student_id = $student_id");
if ($existing->num_rows > 0) {
    header("Location: my-applications.php?msg=already_applied");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply'])) {
    $cover_letter = trim($_POST['cover_letter']);
    $target_dir = "../uploads/cv/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file = $_FILES['cv'];
    $allowed = ['pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed) && $file['error'] == 0) {
        $filename = 'cv_' . $student_id . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO applications (job_id, student_id, cv_path, cover_letter) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $job_id, $student_id, $target_file, $cover_letter);
            if ($stmt->execute()) {
                $message = "Application submitted successfully!";
            } else {
                $message = "Error: " . $conn->error;
            }
        } else {
            $message = "Error uploading CV.";
        }
    } else {
        $message = "Invalid file type. Allowed: PDF, DOC, DOCX.";
    }
}

$job = $conn->query("SELECT j.*, e.company_name FROM jobs j JOIN employers e ON j.employer_id = e.id WHERE j.id = $job_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Apply for Job</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="student">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <h1>Apply for: <?php echo htmlspecialchars($job['title']); ?> at <?php echo htmlspecialchars($job['company_name']); ?></h1>
            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="apply-form">
                <div class="form-group">
                    <label>Upload CV (PDF, DOC, DOCX)</label>
                    <input type="file" name="cv" accept=".pdf,.doc,.docx" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Cover Letter (optional)</label>
                    <textarea name="cover_letter" class="form-control" rows="5"></textarea>
                </div>
                <button type="submit" name="apply" class="btn-save">Submit Application</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>