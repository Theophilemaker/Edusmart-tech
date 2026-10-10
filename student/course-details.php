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
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$course_id) {
    header("Location: courses.php");
    exit();
}

// Get student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    die("Student record not found.");
}
$student_id = $student['id'];

// Verify enrollment
$check = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$check->bind_param("ii", $student_id, $course_id);
$check->execute();
if ($check->get_result()->num_rows == 0) {
    header("Location: courses.php");
    exit();
}

// Fetch course details
$stmt = $conn->prepare("SELECT c.*, u.full_name as teacher_name FROM courses c JOIN teachers t ON c.teacher_id = t.id JOIN users u ON t.user_id = u.id WHERE c.id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) {
    header("Location: courses.php");
    exit();
}

// Fetch lessons
$lessons = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index");
$lessons->bind_param("i", $course_id);
$lessons->execute();
$lessons_result = $lessons->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="student">
    <style>
        .course-detail {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .course-header {
            margin-bottom: 30px;
        }
        .course-header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        .course-meta {
            display: flex;
            gap: 30px;
            margin: 20px 0;
            color: #666;
        }
        .lesson-list {
            margin-top: 40px;
        }
        .lesson-item {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #4cc9f0;
        }
        .lesson-item h3 {
            margin-bottom: 10px;
            color: #333;
        }
        .lesson-meta {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            color: #666;
            font-size: 0.9em;
        }
        .download-link {
            background: #4cc9f0;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
        }
        .video-link {
            color: #f72585;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Course Details</h1>
                <a href="courses.php" class="btn-secondary">← Back to Courses</a>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4cc9f0&color=fff&size=45" class="profile-img">
            </div>
        </div>

        <div class="course-detail">
            <div class="course-header">
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                <p><strong>Instructor:</strong> <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                <div class="course-meta">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['category']); ?></span>
                    <span><i class="fas fa-signal"></i> <?php echo ucfirst($course['level']); ?></span>
                    <span><i class="fas fa-dollar-sign"></i> <?php echo $course['price'] > 0 ? '$'.$course['price'] : 'Free'; ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
            </div>

            <div class="lesson-list">
                <h2>Course Lessons</h2>
                <?php if ($lessons_result->num_rows == 0): ?>
                    <p>No lessons available yet.</p>
                <?php else: ?>
                    <?php while ($lesson = $lessons_result->fetch_assoc()): ?>
                    <div class="lesson-item">
                        <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                        <?php if (!empty($lesson['description'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                        <?php endif; ?>
                        <div class="lesson-meta">
                            <?php if (!empty($lesson['video_url'])): ?>
                                <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" target="_blank" class="video-link"><i class="fas fa-video"></i> Watch Video</a>
                            <?php endif; ?>
                            <?php if (!empty($lesson['file_path'])): ?>
                                <a href="<?php echo $lesson['file_path']; ?>" download class="download-link"><i class="fas fa-download"></i> Download Material</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($lesson['content'])): ?>
                            <div class="lesson-content">
                                <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>