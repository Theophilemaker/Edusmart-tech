<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher's database ID from teachers table
$stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
if (!$teacher) {
    die("Teacher record not found.");
}
$teacher_db_id = $teacher['id'];

// Fetch courses
$courses = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c
    WHERE c.teacher_id = ?
    ORDER BY c.created_at DESC
");
$courses->bind_param("i", $teacher_db_id);
$courses->execute();
$courses_result = $courses->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Courses</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="teacher">
    <style>
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .course-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .course-title {
            font-size: 1.3em;
            margin-bottom: 10px;
        }
        .course-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            color: #666;
        }
        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
        }
        .btn-edit { background: #4cc9f0; }
        .btn-view { background: #10b981; }
        .btn-lessons { background: #8b5cf6; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>My Courses</h1>
            <a href="create-course.php" class="btn-primary">Create New Course</a>
        </div>

        <?php if ($courses_result->num_rows == 0): ?>
            <p>You haven't created any courses yet.</p>
        <?php else: ?>
            <div class="course-grid">
                <?php while($course = $courses_result->fetch_assoc()): ?>
                <div class="course-card">
                    <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>
                    <div class="course-stats">
                        <span><i class="fas fa-video"></i> <?php echo $course['lesson_count']; ?> lessons</span>
                        <span><i class="fas fa-users"></i> <?php echo $course['student_count']; ?> students</span>
                    </div>
                    <div class="btn-group">
                        <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="view-course.php?id=<?php echo $course['id']; ?>" class="btn btn-view">View</a>
                        <a href="lessons.php?course_id=<?php echo $course['id']; ?>" class="btn btn-lessons">Lessons</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>