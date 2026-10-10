<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) die("Student record not found.");
$student_id = $student['id'];

// Get enrolled courses
$courses = $conn->prepare("
    SELECT c.* FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY e.enrollment_date DESC
");
$courses->bind_param("i", $student_id);
$courses->execute();
$courses_result = $courses->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Courses</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <h1>My Courses</h1>
        <?php if ($courses_result->num_rows == 0): ?>
            <p>You are not enrolled in any courses yet. <a href="courses.php">Browse courses</a></p>
        <?php else: ?>
            <div class="course-grid">
                <?php while ($course = $courses_result->fetch_assoc()): ?>
                <div class="course-card">
                    <div class="course-header">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    </div>
                    <div class="course-body">
                        <p><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>
                        <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn-enroll">Go to Course</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>