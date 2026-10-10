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
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$exam_id) {
    header("Location: ../index.php");
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

// Fetch exam details and questions
$stmt = $conn->prepare("SELECT e.*, c.title as course_title FROM exams e JOIN courses c ON e.course_id = c.id WHERE e.id = ? AND e.is_published = 1");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
if (!$exam) {
    header("Location: ../index.php");
    exit();
}
$questions = json_decode($exam['questions'], true) ?: [];

// Check if already submitted
$submitted = $conn->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
$submitted->bind_param("ii", $exam_id, $student_id);
$submitted->execute();
if ($submitted->get_result()->num_rows > 0) {
    die("You have already taken this exam.");
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam'])) {
    $answers = [];
    foreach ($questions as $index => $q) {
        if ($q['type'] == 'mcq') {
            $answers[$index] = isset($_POST['q'.$index]) ? (int)$_POST['q'.$index] : null;
        } else {
            $answers[$index] = trim($_POST['q'.$index] ?? '');
        }
    }
    $answers_json = json_encode($answers);
    $stmt = $conn->prepare("INSERT INTO exam_submissions (exam_id, student_id, answers) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $exam_id, $student_id, $answers_json);
    if ($stmt->execute()) {
        $message = "Exam submitted successfully!";
        // Optionally redirect
        header("Location: ../index.php?msg=submitted");
        exit();
    } else {
        $error = "Error submitting exam.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($exam['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .exam-container { max-width: 800px; margin: 30px auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .timer { position: sticky; top: 10px; background: #f72585; color: white; padding: 10px; text-align: center; border-radius: 8px; font-size: 1.2em; }
        .question-block { margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .options { margin-left: 20px; }
        .options label { display: block; margin: 5px 0; }
        .btn-submit { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; border-radius: 8px; padding: 12px 30px; font-size: 1.1em; cursor: pointer; width: 100%; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="exam-container">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
            <p><strong>Course:</strong> <?php echo htmlspecialchars($exam['course_title']); ?></p>
            <p><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
            <p><strong>Duration:</strong> <?php echo $exam['duration']; ?> minutes</p>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" id="examForm">
                <?php foreach ($questions as $index => $q): ?>
                <div class="question-block">
                    <h3>Question <?php echo $index+1; ?> (<?php echo $q['marks']; ?> marks)</h3>
                    <p><?php echo nl2br(htmlspecialchars($q['text'])); ?></p>
                    <?php if ($q['type'] == 'mcq'): ?>
                        <div class="options">
                            <?php foreach ($q['options'] as $optIndex => $opt): ?>
                            <label>
                                <input type="radio" name="q<?php echo $index; ?>" value="<?php echo $optIndex+1; ?>" required>
                                <?php echo htmlspecialchars($opt); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <textarea name="q<?php echo $index; ?>" rows="5" class="form-control" placeholder="Your answer..." required></textarea>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <button type="submit" name="submit_exam" class="btn-submit">Submit Exam</button>
            </form>
        </div>
    </div>
</body>
</html>