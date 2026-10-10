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
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$exam_id) {
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

// Fetch exam and verify ownership
$stmt = $conn->prepare("SELECT e.* FROM exams e JOIN courses c ON e.course_id = c.id WHERE e.id = ? AND c.teacher_id = ?");
$stmt->bind_param("ii", $exam_id, $teacher_db_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
if (!$exam) {
    header("Location: exams.php");
    exit();
}
$questions = json_decode($exam['questions'], true) ?: [];

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_exam'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid security token.";
        $message_type = 'error';
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $duration = intval($_POST['duration']);
        $total_marks = intval($_POST['total_marks']);
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        $errors = [];
        if (empty($title)) $errors[] = "Exam title is required.";
        if ($duration <= 0) $errors[] = "Duration must be greater than 0.";
        if ($total_marks <= 0) $errors[] = "Total marks must be greater than 0.";

        // Collect questions
        $new_questions = [];
        if (isset($_POST['question_text']) && is_array($_POST['question_text'])) {
            foreach ($_POST['question_text'] as $index => $q_text) {
                if (empty($q_text)) continue;
                $type = $_POST['question_type'][$index] ?? 'mcq';
                $marks = intval($_POST['question_marks'][$index] ?? 1);
                $question = [
                    'text' => $q_text,
                    'type' => $type,
                    'marks' => $marks
                ];
                if ($type == 'mcq') {
                    $options = [];
                    for ($i = 1; $i <= 4; $i++) {
                        if (!empty($_POST['option_' . $i][$index])) {
                            $options[] = $_POST['option_' . $i][$index];
                        }
                    }
                    $question['options'] = $options;
                    $question['correct'] = $_POST['correct_option'][$index] ?? 1;
                }
                $new_questions[] = $question;
            }
        }

        if (empty($new_questions)) {
            $errors[] = "At least one question is required.";
        }

        if (empty($errors)) {
            $questions_json = json_encode($new_questions);
            $stmt = $conn->prepare("UPDATE exams SET title=?, description=?, duration=?, total_marks=?, start_date=?, end_date=?, questions=?, is_published=? WHERE id=?");
            $stmt->bind_param("ssiisssii", $title, $description, $duration, $total_marks, $start_date, $end_date, $questions_json, $is_published, $exam_id);
            if ($stmt->execute()) {
                $message = "Exam updated successfully!";
                $message_type = 'success';
                // Refresh exam data
                $stmt = $conn->prepare("SELECT e.* FROM exams e JOIN courses c ON e.course_id = c.id WHERE e.id = ? AND c.teacher_id = ?");
                $stmt->bind_param("ii", $exam_id, $teacher_db_id);
                $stmt->execute();
                $exam = $stmt->get_result()->fetch_assoc();
                $questions = json_decode($exam['questions'], true) ?: [];
            } else {
                $message = "Database error: " . $conn->error;
                $message_type = 'error';
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = 'error';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Exam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* (Same styling as create-exam.php) */
        .edit-exam-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 12px; border-left: 4px solid #f72585; }
        .form-section h3 { margin-bottom: 20px; }
        .question-item { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
        .question-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .remove-question { background: #f72585; color: white; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; }
        .options-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .btn-add-question { background: #4cc9f0; color: white; border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer; margin: 10px 0 20px; }
        .btn-submit { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; border-radius: 8px; padding: 12px 30px; font-size: 1.1em; cursor: pointer; width: 100%; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 15px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Edit Exam: <?php echo htmlspecialchars($exam['title']); ?></h1>
            <a href="exams.php" class="btn-secondary">Back to Exams</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="edit-exam-container">
            <form method="POST" id="examForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Exam Details -->
                <div class="form-section">
                    <h3>Exam Details</h3>
                    <div class="form-group">
                        <label>Exam Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration (minutes) *</label>
                            <input type="number" name="duration" class="form-control" value="<?php echo $exam['duration']; ?>" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Total Marks *</label>
                            <input type="number" name="total_marks" class="form-control" value="<?php echo $exam['total_marks']; ?>" required min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date & Time</label>
                            <input type="datetime-local" name="start_date" class="form-control" value="<?php echo $exam['start_date'] ? date('Y-m-d\TH:i', strtotime($exam['start_date'])) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date & Time</label>
                            <input type="datetime-local" name="end_date" class="form-control" value="<?php echo $exam['end_date'] ? date('Y-m-d\TH:i', strtotime($exam['end_date'])) : ''; ?>">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="is_published" value="1" <?php echo $exam['is_published'] ? 'checked' : ''; ?>> Published (visible to students)
                        </label>
                    </div>
                </div>

                <!-- Questions -->
                <div class="form-section">
                    <h3>Questions</h3>
                    <div id="questions-container">
                        <?php foreach ($questions as $index => $q): ?>
                        <div class="question-item" data-index="<?php echo $index; ?>">
                            <div class="question-header">
                                <h4>Question <?php echo $index+1; ?></h4>
                                <button type="button" class="remove-question" onclick="this.closest('.question-item').remove()">Remove</button>
                            </div>
                            <div class="form-group">
                                <label>Question Text *</label>
                                <input type="text" name="question_text[]" class="form-control" value="<?php echo htmlspecialchars($q['text']); ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Type</label>
                                    <select name="question_type[]" class="form-control question-type" onchange="toggleOptions(this)">
                                        <option value="mcq" <?php echo ($q['type']=='mcq')?'selected':''; ?>>Multiple Choice</option>
                                        <option value="essay" <?php echo ($q['type']=='essay')?'selected':''; ?>>Essay</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Marks *</label>
                                    <input type="number" name="question_marks[]" class="form-control" value="<?php echo $q['marks']; ?>" min="1" required>
                                </div>
                            </div>
                            <div class="options-section">
                                <div class="options-container">
                                    <?php if ($q['type'] == 'mcq'): ?>
                                        <?php for ($i=0; $i<4; $i++): $opt = $q['options'][$i] ?? ''; ?>
                                        <div class="options-row">
                                            <input type="radio" name="correct_option[<?php echo $index; ?>]" value="<?php echo $i+1; ?>" <?php echo ($q['correct'] == $i+1) ? 'checked' : ''; ?>>
                                            <input type="text" name="option_<?php echo $i+1; ?>[]" class="form-control" placeholder="Option <?php echo $i+1; ?>" value="<?php echo htmlspecialchars($opt); ?>" style="flex:1;">
                                        </div>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add-question" onclick="addQuestion()">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </div>

                <button type="submit" name="update_exam" class="btn-submit">
                    <i class="fas fa-save"></i> Update Exam
                </button>
            </form>
        </div>
    </div>

    <script>
        let questionCount = <?php echo count($questions); ?>;

        function addQuestion() {
            const container = document.getElementById('questions-container');
            const index = questionCount;
            const div = document.createElement('div');
            div.className = 'question-item';
            div.dataset.index = index;
            div.innerHTML = `
                <div class="question-header">
                    <h4>Question ${index+1}</h4>
                    <button type="button" class="remove-question" onclick="this.closest('.question-item').remove()">Remove</button>
                </div>
                <div class="form-group">
                    <label>Question Text *</label>
                    <input type="text" name="question_text[]" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="question_type[]" class="form-control question-type" onchange="toggleOptions(this)">
                            <option value="mcq">Multiple Choice</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marks *</label>
                        <input type="number" name="question_marks[]" class="form-control" value="1" min="1" required>
                    </div>
                </div>
                <div class="options-section">
                    <div class="options-container"></div>
                </div>
            `;
            container.appendChild(div);
            questionCount++;
            // Auto‑add MCQ options for the new question
            const typeSelect = div.querySelector('.question-type');
            if (typeSelect.value === 'mcq') {
                renderOptions(div.querySelector('.options-container'), index);
            }
        }

        function toggleOptions(select) {
            const questionItem = select.closest('.question-item');
            const optionsContainer = questionItem.querySelector('.options-container');
            const index = questionItem.dataset.index || 0;
            if (select.value === 'mcq') {
                renderOptions(optionsContainer, index);
            } else {
                optionsContainer.innerHTML = '';
            }
        }

        function renderOptions(container, index) {
            let html = '<label>Options (at least two)</label>';
            for (let i = 1; i <= 4; i++) {
                html += `
                    <div class="options-row">
                        <input type="radio" name="correct_option[${index}]" value="${i}" ${i===1 ? 'checked' : ''}>
                        <input type="text" name="option_${i}[]" class="form-control" placeholder="Option ${i}" style="flex:1;">
                    </div>
                `;
            }
            container.innerHTML = html;
        }

        // Ensure options are rendered for existing questions that are MCQ
        document.querySelectorAll('.question-item').forEach((item, idx) => {
            const typeSelect = item.querySelector('.question-type');
            if (typeSelect && typeSelect.value === 'mcq') {
                const container = item.querySelector('.options-container');
                if (container && container.children.length === 0) {
                    renderOptions(container, idx);
                }
            }
        });
    </script>
</body>
</html>