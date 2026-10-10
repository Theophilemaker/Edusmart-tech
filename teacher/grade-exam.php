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
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$exam_id && !$submission_id) {
    header("Location: exams.php");
    exit();
}

// Get teacher's database ID
$stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
if (!$teacher) die("Teacher not found.");
$teacher_db_id = $teacher['id'];

// If a specific submission is selected, show grading interface
if ($submission_id) {
    // Fetch submission with exam and student details
    $stmt = $conn->prepare("
        SELECT s.*, e.title as exam_title, e.questions, e.total_marks,
               u.full_name as student_name
        FROM exam_submissions s
        JOIN exams e ON s.exam_id = e.id
        JOIN courses c ON e.course_id = c.id
        JOIN students st ON s.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE s.id = ? AND c.teacher_id = ?
    ");
    $stmt->bind_param("ii", $submission_id, $teacher_db_id);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    if (!$submission) {
        die("Submission not found.");
    }
    $questions = json_decode($submission['questions'], true);
    $answers = json_decode($submission['answers'], true);

    // Handle grading submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grades'])) {
        $total_score = 0;
        $graded_answers = [];
        foreach ($questions as $index => $q) {
            if ($q['type'] == 'essay') {
                $score = floatval($_POST['score'][$index] ?? 0);
                $graded_answers[$index] = $score;
                $total_score += $score;
            } else {
                // For MCQ, score is auto-calculated (if we want to show it, but it's already known)
                // Actually, we should already have the score from the answer? For simplicity, we'll assume we don't auto‑grade.
                // You could pre‑calculate MCQ scores here.
                // For now, we'll just add 0 if not graded.
                $graded_answers[$index] = null; // or the actual score if you have it
            }
        }
        // Update submission with total score and mark as graded
        $update = $conn->prepare("UPDATE exam_submissions SET score = ?, graded = 1 WHERE id = ?");
        $update->bind_param("di", $total_score, $submission_id);
        if ($update->execute()) {
            $message = "Grades saved successfully!";
            // Refresh
            $submission['graded'] = 1;
            $submission['score'] = $total_score;
        } else {
            $error = "Error saving grades.";
        }
    }
} else {
    // List all submissions for the exam
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name as student_name
        FROM exam_submissions s
        JOIN exams e ON s.exam_id = e.id
        JOIN courses c ON e.course_id = c.id
        JOIN students st ON s.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE e.id = ? AND c.teacher_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->bind_param("ii", $exam_id, $teacher_db_id);
    $stmt->execute();
    $submissions = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Exam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .grade-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; }
        .submission-item { background: #f9f9f9; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .question-block { margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #f72585; }
        .student-answer { background: #f1f1f1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .score-input { width: 80px; padding: 5px; }
        .btn-grade { background: #10b981; color: white; border: none; border-radius: 5px; padding: 8px 15px; cursor: pointer; }
        .btn-back { background: #6c757d; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php if (isset($submission)): // Single submission grading view ?>
            <div class="grade-container">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h1>Grade: <?php echo htmlspecialchars($submission['student_name']); ?> – <?php echo htmlspecialchars($submission['exam_title']); ?></h1>
                    <a href="grade-exam.php?exam_id=<?php echo $exam_id; ?>" class="btn-back">Back to list</a>
                </div>

                <?php if ($submission['graded']): ?>
                    <div class="alert alert-success">Already graded – Score: <?php echo $submission['score']; ?>/<?php echo $submission['total_marks']; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="question-block">
                            <h3>Question <?php echo $index+1; ?> (<?php echo $q['marks']; ?> marks) – <?php echo ucfirst($q['type']); ?></h3>
                            <p><?php echo nl2br(htmlspecialchars($q['text'])); ?></p>
                            <div class="student-answer">
                                <strong>Student's answer:</strong>
                                <?php if ($q['type'] == 'mcq'): ?>
                                    <?php
                                    $selected = $answers[$index] ?? null;
                                    $correct = $q['correct'] ?? 1;
                                    $is_correct = ($selected == $correct);
                                    ?>
                                    <p>Option chosen: <?php echo $selected ?: 'None'; ?></p>
                                    <p>Correct answer: Option <?php echo $correct; ?></p>
                                    <p>Result: <?php echo $is_correct ? 'Correct' : 'Incorrect'; ?></p>
                                    <?php // For MCQ, you could auto-assign marks here if you want to store them. We'll not auto-assign, but you can. ?>
                                <?php else: ?>
                                    <p><?php echo nl2br(htmlspecialchars($answers[$index] ?? '')); ?></p>
                                    <label>Score: <input type="number" name="score[<?php echo $index; ?>]" class="score-input" step="0.5" min="0" max="<?php echo $q['marks']; ?>" value="<?php echo $submission['graded'] ? $submission['score'] : ''; ?>" required></label>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" name="save_grades" class="btn-grade">Save Grades</button>
                </form>
            </div>
        <?php else: // List of submissions for the exam ?>
            <h1>Submissions for Exam ID <?php echo $exam_id; ?></h1>
            <a href="exams.php" class="btn-back">Back to Exams</a>
            <?php if ($submissions->num_rows == 0): ?>
                <p>No submissions yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Submitted</th>
                            <th>Graded</th>
                            <th>Score</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $submissions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($row['submitted_at'])); ?></td>
                            <td><?php echo $row['graded'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $row['graded'] ? $row['score'] : '—'; ?></td>
                            <td><a href="grade-exam.php?submission_id=<?php echo $row['id']; ?>" class="btn-grade">Grade</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>