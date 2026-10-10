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

// Get student's database ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    // If student record missing, create one
    $admission_no = 'STU' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    $insert = $conn->prepare("INSERT INTO students (user_id, admission_no) VALUES (?, ?)");
    $insert->bind_param("is", $user_id, $admission_no);
    $insert->execute();
    $student_id = $conn->insert_id;
} else {
    $student_id = $student['id'];
}

// Get IDs of courses already enrolled
$enrolled_ids = [];
$stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_result = $stmt->get_result();
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_ids[] = $row['course_id'];
}

// Get all published courses
$courses = $conn->query("SELECT * FROM courses WHERE is_published = 1 ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Available Courses</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="student">
    <style>
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .course-header {
            height: 120px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            padding: 20px;
            color: white;
        }
        .course-header h3 {
            margin: 0;
            font-size: 1.3em;
        }
        .course-body {
            padding: 20px;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            color: #666;
        }
        .btn-enroll {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-enroll[disabled] {
            background: #ccc;
            cursor: not-allowed;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Available Courses</h1>
                <p>Browse and enroll in courses</p>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4cc9f0&color=fff&size=45" class="profile-img">
            </div>
        </div>

        <?php if (isset($_GET['enrolled'])): ?>
            <div class="alert alert-success">Successfully enrolled in the course!</div>
        <?php endif; ?>

        <?php if ($courses->num_rows == 0): ?>
            <p>No courses available at the moment.</p>
        <?php else: ?>
            <div class="course-grid">
                <?php while ($course = $courses->fetch_assoc()): ?>
                <div class="course-card">
                    <div class="course-header">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    </div>
                    <div class="course-body">
                        <p><?php echo htmlspecialchars(substr($course['description'], 0, 120)); ?>...</p>
                        <div class="course-meta">
                            <span><i class="fas fa-video"></i> <?php
                                $lesson_count = $conn->query("SELECT COUNT(*) as total FROM lessons WHERE course_id = {$course['id']}")->fetch_assoc()['total'];
                                echo $lesson_count; ?> lessons
                            </span>
                            <span><i class="fas fa-dollar-sign"></i> <?php echo $course['price'] > 0 ? '$'.$course['price'] : 'Free'; ?></span>
                        </div>
                        <?php if (in_array($course['id'], $enrolled_ids)): ?>
                            <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn-enroll" style="background:#10b981;">Go to Course</a>
                        <?php else: ?>
                            <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="btn-enroll" onclick="return confirm('Enroll in this course?')">Enroll Now</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>