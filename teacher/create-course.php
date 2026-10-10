<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
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
$error_details = [];

// Ensure teacher has a record in teachers table
try {
    $check_teacher = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $check_teacher->bind_param("i", $teacher_id);
    $check_teacher->execute();
    $result = $check_teacher->get_result();

    if ($result->num_rows == 0) {
        $employee_id = 'TCH' . date('Y') . str_pad($teacher_id, 4, '0', STR_PAD_LEFT);
        $insert_teacher = $conn->prepare("INSERT INTO teachers (user_id, employee_id, specialization) VALUES (?, ?, 'General')");
        $insert_teacher->bind_param("is", $teacher_id, $employee_id);
        $insert_teacher->execute();
        $teacher_db_id = $conn->insert_id;
    } else {
        $teacher_row = $result->fetch_assoc();
        $teacher_db_id = $teacher_row['id'];
    }
} catch (Exception $e) {
    error_log("Teacher record error: " . $e->getMessage());
    $error_details[] = "Teacher record error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_course'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid security token.";
        $message_type = 'error';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $level = $_POST['level'] ?? 'beginner';
        $price = floatval($_POST['price'] ?? 0);

        $errors = [];
        if (empty($title)) $errors[] = "Course title is required";
        if (empty($description)) $errors[] = "Course description is required";
        if (empty($category)) $errors[] = "Category is required";

        if (empty($errors)) {
            // Handle thumbnail upload
            $thumbnail = null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['thumbnail'], '../uploads/courses/thumbnails/', ['jpg', 'jpeg', 'png', 'gif']);
                if ($upload['success']) {
                    $thumbnail = 'thumbnails/' . $upload['filename'];
                } else {
                    $errors[] = "Thumbnail upload failed: " . $upload['message'];
                }
            }

            if (empty($errors)) {
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Insert course
                    $stmt = $conn->prepare("INSERT INTO courses (title, description, category, level, price, teacher_id, thumbnail, is_published, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                    $stmt->bind_param("ssssdis", $title, $description, $category, $level, $price, $teacher_db_id, $thumbnail);
                    $stmt->execute();
                    $course_id = $conn->insert_id;

                    // Process lessons
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
                                $upload = uploadFile($file, '../uploads/lessons/', ['pdf','doc','docx','ppt','pptx','mp4','avi','mov','jpg','png']);
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
                    $message = "Course created successfully!";
                    $message_type = 'success';
                    // Optionally redirect to courses page
                    // header("Location: courses.php?msg=created");
                    // exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Database error: " . $e->getMessage();
                    $message_type = 'error';
                    error_log("Course creation error: " . $e->getMessage());
                }
            } else {
                $message = implode("<br>", $errors);
                $message_type = 'error';
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Additional styles for create course page */
        .create-course-container {
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
        .lesson-item {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
        }
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .lesson-header h4 {
            color: #f72585;
        }
        .remove-lesson {
            background: #f72585;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .btn-add-lesson {
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Create New Course</h1>
                <p>Add a new course with lessons and materials</p>
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

        <div class="create-course-container">
            <form method="POST" enctype="multipart/form-data" id="courseForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Course Details -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Course Details</h3>
                    <div class="form-group">
                        <label>Course Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" class="form-control">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" value="0" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Course Thumbnail (optional)</label>
                        <input type="file" name="thumbnail" accept="image/*" class="form-control">
                    </div>
                </div>

                <!-- Lessons -->
                <div class="form-section">
                    <h3><i class="fas fa-list"></i> Course Lessons</h3>
                    <div id="lessons-container">
                        <!-- Lesson 1 (default) -->
                        <div class="lesson-item">
                            <div class="lesson-header">
                                <h4>Lesson 1</h4>
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
                                <input type="url" name="lesson_video[]" class="form-control" placeholder="https://youtube.com/...">
                            </div>
                            <div class="form-group">
                                <label>Upload Material (PDF, DOC, PPT, MP4, etc.)</label>
                                <input type="file" name="lesson_files[]" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.png">
                            </div>
                            <div class="form-group">
                                <label>Text Content (optional)</label>
                                <textarea name="lesson_content[]" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-add-lesson" onclick="addLesson()">
                        <i class="fas fa-plus"></i> Add Another Lesson
                    </button>
                </div>

                <button type="submit" name="create_course" class="btn-submit">
                    <i class="fas fa-save"></i> Create Course
                </button>
            </form>
        </div>
    </div>

    <script>
        let lessonCount = 1;
        function addLesson() {
            lessonCount++;
            const container = document.getElementById('lessons-container');
            const div = document.createElement('div');
            div.className = 'lesson-item';
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