<?php
require_once '../includes/security.php';
requireRole('teacher');

$db = getDB();
$teacher_id = $_SESSION['teacher_id'];
$submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;

// Get submission details
$submission = $db->prepare("
    SELECT 
        es.*,
        e.exam_id,
        e.exam_title,
        e.exam_type,
        e.total_marks,
        e.passing_marks,
        e.instructions,
        c.course_name,
        c.course_code,
        u.first_name,
        u.last_name,
        u.email,
        s.student_id
    FROM exam_submissions es
    JOIN exams e ON es.exam_id = e.exam_id
    JOIN courses c ON e.course_id = c.course_id
    JOIN students s ON es.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE es.submission_id = ? AND c.teacher_id = ?
");
$submission->execute([$submission_id, $teacher_id]);
$submission = $submission->fetch();

if (!$submission) {
    header('Location: grading.php');
    exit();
}

// Get exam questions
$questions = $db->prepare("
    SELECT * FROM questions 
    WHERE exam_id = ? 
    ORDER BY question_id
");
$questions->execute([$submission['exam_id']]);

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $total_obtained = 0;
        $answers = json_decode($submission['answers'], true) ?: [];

        // Grade each question
        foreach ($questions as $question) {
            $question_id = $question['question_id'];
            $points_obtained = floatval($_POST['score_' . $question_id] ?? 0);
            $total_obtained += $points_obtained;

            // Save feedback
            $feedback = Security::sanitize($_POST['feedback_' . $question_id] ?? '');
            
            // You might want to store per-question feedback in a separate table
        }

        // Calculate percentage
        $percentage = ($total_obtained / $submission['total_marks']) * 100;

        // Update submission
        $update = $db->prepare("
            UPDATE exam_submissions 
            SET score = ?, total_obtained = ?, percentage = ?, 
                graded_at = NOW(), status = 'graded', feedback = ?
            WHERE submission_id = ?
        ");
        $update->execute([
            $total_obtained,
            $total_obtained,
            $percentage,
            Security::sanitize($_POST['overall_feedback'] ?? ''),
            $submission_id
        ]);

        $db->commit();

        // Log activity
        Security::logActivity($_SESSION['user_id'], 'grade_exam', 
            "Graded exam for student: " . $submission['first_name'] . " " . $submission['last_name']);

        header('Location: grading.php?graded=1');
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Parse student answers
$student_answers = json_decode($submission['answers'], true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Exam - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <!-- ... sidebar content ... -->
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Grade Exam: <?php echo htmlspecialchars($submission['exam_title']); ?></h1>
                    <a href="grading.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Grading
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Student Info -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($submission['email']); ?></p>
                                <p><strong>Course:</strong> <?php echo htmlspecialchars($submission['course_code'] . ' - ' . $submission['course_name']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Submitted:</strong> <?php echo date('F d, Y H:i:s', strtotime($submission['submitted_at'])); ?></p>
                                <p><strong>Total Marks:</strong> <?php echo $submission['total_marks']; ?></p>
                                <p><strong>Passing Marks:</strong> <?php echo $submission['passing_marks']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grading Form -->
                <form method="POST">
                    <?php foreach($questions as $index => $question): 
                        $student_answer = $student_answers[$question['question_id']] ?? '';
                        $is_correct = ($student_answer == $question['correct_answer']);
                    ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Question <?php echo $index + 1; ?> (<?php echo $question['points']; ?> points)</h6>
                        </div>
                        <div class="card-body">
                            <p class="fw-bold"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <?php if($question['question_type'] == 'mcq' && $question['options']): 
                                $options = json_decode($question['options'], true);
                            ?>
                                <div class="mb-3">
                                    <label class="form-label">Options:</label>
                                    <?php foreach($options as $opt_index => $option): 
                                        $letter = chr(65 + $opt_index); // A, B, C, D
                                    ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" disabled 
                                                <?php echo ($student_answer == $letter) ? 'checked' : ''; ?>>
                                            <label class="form-check-label <?php echo ($letter == $question['correct_answer']) ? 'text-success fw-bold' : ''; ?>">
                                                <?php echo $letter; ?>. <?php echo htmlspecialchars($option); ?>
                                                <?php if($letter == $question['correct_answer']): ?>
                                                    <i class="fas fa-check text-success"></i>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif($question['question_type'] == 'true_false'): ?>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" disabled 
                                            <?php echo ($student_answer == 'true') ? 'checked' : ''; ?>>
                                        <label class="form-check-label <?php echo ($question['correct_answer'] == 'true') ? 'text-success fw-bold' : ''; ?>">
                                            True
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" disabled 
                                            <?php echo ($student_answer == 'false') ? 'checked' : ''; ?>>
                                        <label class="form-check-label <?php echo ($question['correct_answer'] == 'false') ? 'text-success fw-bold' : ''; ?>">
                                            False
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Student's Answer:</label>
                                <div class="border p-3 bg-light">
                                    <?php 
                                    if ($question['question_type'] == 'essay') {
                                        echo nl2br(htmlspecialchars($student_answer));
                                    } else {
                                        echo htmlspecialchars($student_answer ?: 'No answer provided');
                                    }
                                    ?>
                                </div>
                            </div>

                            <?php if($question['question_type'] == 'essay'): ?>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Points Awarded (max <?php echo $question['points']; ?>) *</label>
                                        <input type="number" class="form-control" 
                                               name="score_<?php echo $question['question_id']; ?>" 
                                               min="0" max="<?php echo $question['points']; ?>" 
                                               step="0.5" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Feedback</label>
                                        <textarea class="form-control" 
                                                  name="feedback_<?php echo $question['question_id']; ?>" 
                                                  rows="2"></textarea>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" 
                                       name="score_<?php echo $question['question_id']; ?>" 
                                       value="<?php echo $is_correct ? $question['points'] : 0; ?>">
                            <?php endif; ?>

                            <?php if($question['explanation']): ?>
                                <div class="alert alert-info mt-2">
                                    <strong>Note:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Overall Feedback</h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="overall_feedback" rows="3" 
                                      placeholder="Provide overall feedback to the student..."></textarea>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Submit Grades
                            </button>
                            <a href="grading.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>