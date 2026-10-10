<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$course_id) {
    header("Location: courses.php");
    exit();
}

// Verify teacher owns this course
$stmt = $conn->prepare("SELECT c.*, t.id as teacher_db_id FROM courses c JOIN teachers t ON c.teacher_id = t.id WHERE c.id = ? AND t.user_id = ?");
$stmt->bind_param("ii", $course_id, $user_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) {
    die("Access denied or course not found.");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Teacher View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="teacher">
    <style>
        .course-container {
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
            font-size: 2em;
            margin-bottom: 10px;
        }
        .course-meta {
            display: flex;
            gap: 20px;
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
            border-left: 4px solid #f72585;
        }
        .lesson-item h3 {
            margin-bottom: 10px;
        }
        .lesson-meta {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            font-size: 0.9em;
        }
        .lesson-meta a {
            color: #4cc9f0;
            text-decoration: none;
        }
        .btn-edit {
            background: #4cc9f0;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .btn-edit:hover {
            background: #3a8fd9;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?> <!-- Correct: same directory -->

    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Course Preview</h1>
                <a href="edit-course.php?id=<?php echo $course_id; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit Course</a>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Teacher'); ?>&background=f72585&color=fff&size=45" class="profile-img">
            </div>
        </div>

        <div class="course-container">
            <div class="course-header">
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($course['category']); ?> | <strong>Level:</strong> <?php echo ucfirst($course['level']); ?> | <strong>Price:</strong> $<?php echo number_format($course['price'], 2); ?></p>
                <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                <?php if (!empty($course['thumbnail'])): ?>
                    <img src="../uploads/courses/<?php echo $course['thumbnail']; ?>" style="max-width:100%; max-height:200px; border-radius:8px;">
                <?php endif; ?>
            </div>

            <div class="lesson-list">
                <h2>Lessons (<?php echo $lessons_result->num_rows; ?>)</h2>
                <?php if ($lessons_result->num_rows == 0): ?>
                    <p>No lessons added yet. <a href="edit-course.php?id=<?php echo $course_id; ?>">Add lessons</a></p>
                <?php else: ?>
                    <?php while ($lesson = $lessons_result->fetch_assoc()): ?>
                    <div class="lesson-item">
                        <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                        <?php if (!empty($lesson['description'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                        <?php endif; ?>
                        <div class="lesson-meta">
                            <?php if (!empty($lesson['video_url'])): ?>
                                <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" target="_blank"><i class="fas fa-video"></i> Watch Video</a>
                            <?php endif; ?>
                            <?php if (!empty($lesson['file_path'])): ?>
                                <a href="<?php echo $lesson['file_path']; ?>" download><i class="fas fa-download"></i> Download Material</a>
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