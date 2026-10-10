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
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$course_id) {
    header("Location: courses.php");
    exit();
}

// Get teacher's database ID and verify ownership
$stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
if (!$teacher) {
    die("Teacher record not found.");
}
$teacher_db_id = $teacher['id'];

// Fetch course and verify ownership
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $course_id, $teacher_db_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) {
    header("Location: courses.php");
    exit();
}

// Fetch existing lessons
$lessons = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index");
$lessons->bind_param("i", $course_id);
$lessons->execute();
$lessons_result = $lessons->get_result();
$existing_lessons = [];
while ($row = $lessons_result->fetch_assoc()) {
    $existing_lessons[] = $row;
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_course'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid security token.";
        $message_type = 'error';
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $level = $_POST['level'];
        $price = floatval($_POST['price']);

        $errors = [];
        if (empty($title)) $errors[] = "Course title is required";
        if (empty($description)) $errors[] = "Course description is required";
        if (empty($category)) $errors[] = "Category is required";

        // Handle thumbnail upload (optional)
        $thumbnail = $course['thumbnail']; // keep old by default
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['thumbnail'], '../uploads/courses/thumbnails/', ['jpg', 'jpeg', 'png', 'gif']);
            if ($upload['success']) {
                // Delete old thumbnail if exists
                if (!empty($course['thumbnail']) && file_exists('../uploads/courses/' . $course['thumbnail'])) {
                    unlink('../uploads/courses/' . $course['thumbnail']);
                }
                $thumbnail = 'thumbnails/' . $upload['filename'];
            } else {
                $errors[] = "Thumbnail upload failed: " . $upload['message'];
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Update course
                $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, category = ?, level = ?, price = ?, thumbnail = ? WHERE id = ?");
                $stmt->bind_param("ssssdsi", $title, $description, $category, $level, $price, $thumbnail, $course_id);
                $stmt->execute();

                // Delete existing lessons – FIXED: proper mysqli binding
                $delete_stmt = $conn->prepare("DELETE FROM lessons WHERE course_id = ?");
                $delete_stmt->bind_param("i", $course_id);
                $delete_stmt->execute();

                // Insert new lessons
                if (isset($_POST['lesson_title']) && is_array($_POST['lesson_title'])) {
                    foreach ($_POST['lesson_title'] as $index => $lesson_title) {
                        if (empty($lesson_title)) continue;

                        $lesson_desc = $_POST['lesson_description'][$index] ?? '';
                        $video_url = $_POST['lesson_video'][$index] ?? '';
                        $content = $_POST['lesson_content'][$index] ?? '';
                        $order = $index + 1;

                        // File upload for this lesson
                        $file_path = null;
                        $file_type = null;
                        if (isset($_FILES['lesson_files']['name'][$index]) && $_FILES['lesson_files']['error'][$index] == UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['lesson_files']['name'][$index],
                                'tmp_name' => $_FILES['lesson_files']['tmp_name'][$index],
                                'size' => $_FILES['lesson_files']['size'][$index],
                                'error' => $_FILES['lesson_files']['error'][$index]
                            ];
                            $upload = uploadFile($file, '../uploads/lessons/', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'png']);
                            if ($upload['success']) {
                                $file_path = $upload['path'];
                                $file_type = pathinfo($file['name'], PATHINFO_EXTENSION);
                            }
                        }

                        $lesson_stmt = $conn->prepare("INSERT INTO lessons (course_id, title, description, video_url, file_path, file_type, content, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $lesson_stmt->bind_param("issssssi", $course_id, $lesson_title, $lesson_desc, $video_url, $file_path, $file_type, $content, $order);
                        $lesson_stmt->execute();
                    }
                }

                $conn->commit();
                $message = "Course updated successfully!";
                $message_type = 'success';

                // Refresh course data
                $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
                $stmt->bind_param("ii", $course_id, $teacher_db_id);
                $stmt->execute();
                $course = $stmt->get_result()->fetch_assoc();

                // Refresh lessons
                $lessons = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index");
                $lessons->bind_param("i", $course_id);
                $lessons->execute();
                $lessons_result = $lessons->get_result();
                $existing_lessons = [];
                while ($row = $lessons_result->fetch_assoc()) {
                    $existing_lessons[] = $row;
                }

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Course update error: " . $e->getMessage());
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = 'error';
        }
    }
}

$csrf_token = generateCSRFToken();
$categories = ['Programming', 'Design', 'Business', 'Marketing', 'Language', 'Science', 'Mathematics', 'Other'];
?>
<!-- HTML part remains exactly the same as before – no changes needed -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-course-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 12px; border-left: 4px solid #f72585; }
        .form-section h3 { margin-bottom: 20px; }
        .lesson-item { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
        .lesson-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .remove-lesson { background: #f72585; color: white; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; }
        .btn-add-lesson { background: #4cc9f0; color: white; border: none; border-radius: 8px; padding: 10px 20px; cursor: pointer; margin: 10px 0 20px; }
        .btn-submit { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; border-radius: 8px; padding: 12px 30px; font-size: 1.1em; cursor: pointer; width: 100%; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .current-thumbnail { margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Edit Course: <?php echo htmlspecialchars($course['title']); ?></h1>
            <a href="courses.php" class="btn-secondary">Back to Courses</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="edit-course-container">
            <form method="POST" enctype="multipart/form-data" id="courseForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Course Details -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Course Details</h3>
                    <div class="form-group">
                        <label>Course Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($course['category'] == $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" class="form-control">
                                <option value="beginner" <?php echo ($course['level'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo ($course['level'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo ($course['level'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" class="form-control" value="<?php echo $course['price']; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Course Thumbnail</label>
                        <?php if (!empty($course['thumbnail'])): ?>
                            <div class="current-thumbnail">
                                <img src="../uploads/courses/<?php echo $course['thumbnail']; ?>" style="max-height: 60px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="thumbnail" accept="image/*" class="form-control">
                        <small>Leave empty to keep current image.</small>
                    </div>
                </div>

                <!-- Lessons -->
                <div class="form-section">
                    <h3><i class="fas fa-list"></i> Course Lessons</h3>
                    <div id="lessons-container">
                        <?php foreach ($existing_lessons as $index => $lesson): ?>
                        <div class="lesson-item" data-index="<?php echo $index; ?>">
                            <div class="lesson-header">
                                <h4>Lesson <?php echo $index+1; ?></h4>
                                <button type="button" class="remove-lesson" onclick="this.closest('.lesson-item').remove()">Remove</button>
                            </div>
                            <div class="form-group">
                                <label>Lesson Title *</label>
                                <input type="text" name="lesson_title[]" class="form-control" value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="lesson_description[]" class="form-control" rows="2"><?php echo htmlspecialchars($lesson['description']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Video URL (optional)</label>
                                <input type="url" name="lesson_video[]" class="form-control" value="<?php echo htmlspecialchars($lesson['video_url']); ?>" placeholder="https://...">
                            </div>
                            <div class="form-group">
                                <label>Upload Material (PDF, DOC, PPT, MP4, etc.)</label>
                                <?php if (!empty($lesson['file_path'])): ?>
                                    <p>Current file: <a href="<?php echo $lesson['file_path']; ?>" target="_blank"><?php echo basename($lesson['file_path']); ?></a></p>
                                <?php endif; ?>
                                <input type="file" name="lesson_files[]" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.png">
                            </div>
                            <div class="form-group">
                                <label>Text Content (optional)</label>
                                <textarea name="lesson_content[]" class="form-control" rows="3"><?php echo htmlspecialchars($lesson['content']); ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add-lesson" onclick="addLesson()">
                        <i class="fas fa-plus"></i> Add Another Lesson
                    </button>
                </div>

                <button type="submit" name="update_course" class="btn-submit">
                    <i class="fas fa-save"></i> Update Course
                </button>
            </form>
        </div>
    </div>

    <script>
        let lessonCount = <?php echo count($existing_lessons); ?>;
        function addLesson() {
            lessonCount++;
            const container = document.getElementById('lessons-container');
            const div = document.createElement('div');
            div.className = 'lesson-item';
            div.dataset.index = lessonCount;
            div.innerHTML = `
                <div class="lesson-header">
                    <h4>Lesson ${lessonCount}</h4>
                    <button type="button" class="remove-lesson" onclick="this.closest('.lesson-item').remove()">Remove</button>
                </div>
                <div class="form-group">
                    <label>Lesson Title *</label>
                    <input type="text" name="lesson_title[]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="lesson_description[]" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Video URL (optional)</label>
                    <input type="url" name="lesson_video[]" class="form-control" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Upload Material (PDF, DOC, PPT, MP4, etc.)</label>
                    <input type="file" name="lesson_files[]" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.png">
                </div>
                <div class="form-group">
                    <label>Text Content (optional)</label>
                    <textarea name="lesson_content[]" class="form-control" rows="3"></textarea>
                </div>
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>