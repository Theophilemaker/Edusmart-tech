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

$teacher_id = $_SESSION['user_id'];

// Get teacher's database ID
$stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
if (!$teacher) {
    die("Teacher record not found.");
}
$teacher_db_id = $teacher['id'];

// Fetch exams for courses taught by this teacher – ORDER BY id DESC (safe)
$exams = $conn->prepare("
    SELECT e.*, c.title as course_title,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = e.id) as attempts,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = e.id AND graded = 1) as graded_count
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY e.id DESC
");
$exams->bind_param("i", $teacher_db_id);
$exams->execute();
$exams_result = $exams->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Exams</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="teacher">
    <style>
        .exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .exam-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: 0.3s;
        }
        .exam-card:hover { transform: translateY(-5px); }
        .exam-title { font-size: 1.3em; margin-bottom: 5px; }
        .exam-meta { color: #666; margin-bottom: 10px; font-size: 0.9em; }
        .exam-stats { display: flex; gap: 15px; margin: 15px 0; color: #666; }
        .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-edit { background: #4cc9f0; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; }
        .btn-grade { background: #10b981; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; }
        .btn-preview { background: #f59e0b; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>My Exams</h1>
            <a href="create-exam.php" class="btn-primary">Create New Exam</a>
        </div>

        <?php if ($exams_result->num_rows == 0): ?>
            <p>You haven't created any exams yet.</p>
        <?php else: ?>
            <div class="exam-grid">
                <?php while($exam = $exams_result->fetch_assoc()): ?>
                <div class="exam-card">
                    <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                    <div class="exam-meta">
                        <?php echo htmlspecialchars($exam['course_title']); ?> • <?php echo $exam['total_marks']; ?> marks • <?php echo $exam['duration']; ?> min
                    </div>
                    <div class="exam-stats">
                        <span><i class="fas fa-users"></i> <?php echo $exam['attempts']; ?> attempts</span>
                        <span><i class="fas fa-check-circle"></i> <?php echo $exam['graded_count']; ?> graded</span>
                    </div>
                    <div class="btn-group">
                        <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn-edit">Edit</a>
                        <a href="grade-exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn-grade">Grade</a>
                        <a href="preview-exam.php?id=<?php echo $exam['id']; ?>" class="btn-preview" target="_blank">Preview</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>