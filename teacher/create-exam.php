<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get teacher's courses for dropdown
try {
    $stmt = $conn->prepare("SELECT c.id, c.title FROM courses c JOIN teachers t ON c.teacher_id = t.id WHERE t.user_id = ? ORDER BY c.title");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $courses = $stmt->get_result();
} catch (Exception $e) {
    $courses = [];
    error_log("Error fetching courses: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_exam'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid security token.";
        $message_type = 'error';
    } else {
        $course_id = intval($_POST['course_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $duration = intval($_POST['duration']);
        $total_marks = intval($_POST['total_marks']);
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        $errors = [];
        if (!$course_id) $errors[] = "Please select a course.";
        if (empty($title)) $errors[] = "Exam title is required.";
        if ($duration <= 0) $errors[] = "Duration must be greater than 0.";
        if ($total_marks <= 0) $errors[] = "Total marks must be greater than 0.";

        // Collect questions
        $questions = [];
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
                $questions[] = $question;
            }
        }

        if (empty($questions)) {
            $errors[] = "At least one question is required.";
        }

        if (empty($errors)) {
            $questions_json = json_encode($questions);
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO exams (course_id, title, description, duration, total_marks, start_date, end_date, questions, is_published, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issiisssi", $course_id, $title, $description, $duration, $total_marks, $start_date, $end_date, $questions_json, $is_published);
                $stmt->execute();
                $conn->commit();
                $message = "Exam created successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Exam creation error: " . $e->getMessage());
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .create-exam-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 12px;
            border-left: 4px solid #f72585;
        }
        .form-section h3 {
            margin-bottom: 20px;
            color: #333;
        }
        .question-item {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .question-header h4 {
            color: #f72585;
        }
        .remove-question {
            background: #f72585;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 10px;
            cursor: pointer;
        }
        .options-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .options-row input[type="radio"] {
            width: auto;
            margin-right: 5px;
        }
        .btn-add-question {
            background: #4cc9f0;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            margin: 10px 0 20px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-size: 1.1em;
            cursor: pointer;
            width: 100%;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Create Exam</h1>
                <p>Add a new exam with multiple‑choice and essay questions</p>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Teacher'); ?>&background=f72585&color=fff&size=45" class="profile-img">
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="create-exam-container">
            <form method="POST" id="examForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Exam Details -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Exam Details</h3>
                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">Select a course</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration (minutes) *</label>
                            <input type="number" name="duration" class="form-control" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Total Marks *</label>
                            <input type="number" name="total_marks" class="form-control" required min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date & Time</label>
                            <input type="datetime-local" name="start_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>End Date & Time</label>
                            <input type="datetime-local" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="is_published" value="1"> Publish immediately (visible to students)
                        </label>
                    </div>
                </div>

                <!-- Questions -->
                <div class="form-section">
                    <h3><i class="fas fa-question-circle"></i> Questions</h3>
                    <div id="questions-container">
                        <!-- Question 1 (default) -->
                        <div class="question-item" data-index="0">
                            <div class="question-header">
                                <h4>Question 1</h4>
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
                                <!-- Options for MCQ will be inserted here by JavaScript -->
                                <div class="options-container"></div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-add-question" onclick="addQuestion()">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </div>

                <button type="submit" name="create_exam" class="btn-submit">
                    <i class="fas fa-save"></i> Create Exam
                </button>
            </form>
        </div>
    </div>

    <script>
        let questionCount = 1;

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
            // Add default MCQ options for the new question
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
                optionsContainer.innerHTML = ''; // remove options
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

        // Initialize options for first question
        document.addEventListener('DOMContentLoaded', function() {
            const firstQuestion = document.querySelector('.question-item');
            if (firstQuestion) {
                firstQuestion.dataset.index = 0;
                const container = firstQuestion.querySelector('.options-container');
                renderOptions(container, 0);
            }
        });
    </script>
</body>
</html>