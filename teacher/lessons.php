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
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if (!$course_id) {
    header("Location: courses.php");
    exit();
}

// Verify teacher owns the course
$stmt = $conn->prepare("SELECT id, title FROM courses WHERE id = ? AND teacher_id = (SELECT id FROM teachers WHERE user_id = ?)");
$stmt->bind_param("ii", $course_id, $teacher_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) {
    die("Access denied or course not found.");
}

$message = '';
$message_type = '';

// Handle lesson deletion
if (isset($_GET['delete'])) {
    $lesson_id = (int)$_GET['delete'];
    // First delete any associated file
    $stmt = $conn->prepare("SELECT file_path FROM lessons WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $lesson_id, $course_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    if ($lesson && !empty($lesson['file_path'])) {
        $file_path = '../' . $lesson['file_path'];
        if (file_exists($file_path)) unlink($file_path);
    }
    $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $lesson_id, $course_id);
    if ($stmt->execute()) {
        $message = "Lesson deleted successfully.";
        $message_type = 'success';
    } else {
        $message = "Error deleting lesson.";
        $message_type = 'error';
    }
}

// Handle add/edit lesson
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid security token.";
        $message_type = 'error';
    } else {
        $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $video_url = trim($_POST['video_url']);
        $content = trim($_POST['content']);
        $order_index = (int)$_POST['order_index'];

        $errors = [];
        if (empty($title)) $errors[] = "Lesson title is required.";

        // Handle file upload
        $file_path = null;
        $file_type = null;
        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] == UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['lesson_file'], '../uploads/lessons/', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'png']);
            if ($upload['success']) {
                $file_path = $upload['path'];
                $file_type = pathinfo($_FILES['lesson_file']['name'], PATHINFO_EXTENSION);
                // If editing and there was an old file, delete it
                if ($lesson_id > 0) {
                    $old = $conn->prepare("SELECT file_path FROM lessons WHERE id = ?");
                    $old->bind_param("i", $lesson_id);
                    $old->execute();
                    $old_file = $old->get_result()->fetch_assoc();
                    if ($old_file && !empty($old_file['file_path'])) {
                        $old_path = '../' . $old_file['file_path'];
                        if (file_exists($old_path)) unlink($old_path);
                    }
                }
            } else {
                $errors[] = "File upload failed: " . $upload['message'];
            }
        } else {
            // If editing, keep existing file
            if ($lesson_id > 0) {
                $stmt = $conn->prepare("SELECT file_path, file_type FROM lessons WHERE id = ?");
                $stmt->bind_param("i", $lesson_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                if ($existing) {
                    $file_path = $existing['file_path'];
                    $file_type = $existing['file_type'];
                }
            }
        }

        if (empty($errors)) {
            if ($lesson_id > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE lessons SET title=?, description=?, video_url=?, file_path=?, file_type=?, content=?, order_index=? WHERE id=? AND course_id=?");
                $stmt->bind_param("ssssssiii", $title, $description, $video_url, $file_path, $file_type, $content, $order_index, $lesson_id, $course_id);
            } else {
                // Insert new lesson (get max order_index)
                $max_order = $conn->prepare("SELECT MAX(order_index) as max FROM lessons WHERE course_id = ?");
                $max_order->bind_param("i", $course_id);
                $max_order->execute();
                $max = $max_order->get_result()->fetch_assoc()['max'] ?? 0;
                $order_index = $max + 1;
                $stmt = $conn->prepare("INSERT INTO lessons (course_id, title, description, video_url, file_path, file_type, content, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssssi", $course_id, $title, $description, $video_url, $file_path, $file_type, $content, $order_index);
            }
            if ($stmt->execute()) {
                $message = $lesson_id ? "Lesson updated successfully." : "Lesson added successfully.";
                $message_type = 'success';
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

// Fetch all lessons for this course
$lessons = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index");
$lessons->bind_param("i", $course_id);
$lessons->execute();
$lessons_result = $lessons->get_result();

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lessons - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="teacher">
    <style>
        .lessons-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .lesson-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #f72585;
        }
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .lesson-title {
            font-size: 1.2em;
            font-weight: bold;
        }
        .lesson-actions a, .lesson-actions button {
            margin-left: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }
        .lesson-actions .edit { color: #4cc9f0; }
        .lesson-actions .delete { color: #f72585; }
        .lesson-meta {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
        }
        .lesson-meta i {
            margin-right: 5px;
        }
        .add-form {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .current-file { margin-top: 5px; font-size: 0.9em; }
        .current-file a { color: #4cc9f0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>Lessons for: <?php echo htmlspecialchars($course['title']); ?></h1>
                <a href="courses.php" class="btn-secondary">Back to Courses</a>
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

        <div class="lessons-container">
            <h2>Existing Lessons</h2>
            <?php if ($lessons_result->num_rows == 0): ?>
                <p>No lessons yet. Add one below.</p>
            <?php else: ?>
                <?php while ($lesson = $lessons_result->fetch_assoc()): ?>
                <div class="lesson-card" id="lesson-<?php echo $lesson['id']; ?>">
                    <div class="lesson-header">
                        <span class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></span>
                        <div class="lesson-actions">
                            <a href="?course_id=<?php echo $course_id; ?>&edit=<?php echo $lesson['id']; ?>" class="edit" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="?course_id=<?php echo $course_id; ?>&delete=<?php echo $lesson['id']; ?>" class="delete" onclick="return confirm('Delete this lesson?')" title="Delete"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                    <?php if (!empty($lesson['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                    <?php endif; ?>
                    <div class="lesson-meta">
                        <?php if (!empty($lesson['video_url'])): ?>
                            <span><i class="fas fa-video"></i> <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" target="_blank">Video</a></span>
                        <?php endif; ?>
                        <?php if (!empty($lesson['file_path'])): ?>
                            <span><i class="fas fa-file"></i> <a href="../download.php?id=<?php echo $lesson['id']; ?>">Download Material</a></span>
                        <?php endif; ?>
                        <span><i class="fas fa-sort-numeric-up"></i> Order: <?php echo $lesson['order_index']; ?></span>
                    </div>
                    <?php if (!empty($lesson['content'])): ?>
                        <div class="lesson-content">
                            <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <?php
            $editing = false;
            $edit_lesson = null;
            if (isset($_GET['edit'])) {
                $edit_id = (int)$_GET['edit'];
                $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ? AND course_id = ?");
                $stmt->bind_param("ii", $edit_id, $course_id);
                $stmt->execute();
                $edit_lesson = $stmt->get_result()->fetch_assoc();
                if ($edit_lesson) $editing = true;
            }
            ?>
            <div class="add-form">
                <h3><?php echo $editing ? 'Edit Lesson' : 'Add New Lesson'; ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="lesson_id" value="<?php echo $edit_lesson['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?php echo $editing ? htmlspecialchars($edit_lesson['title']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"><?php echo $editing ? htmlspecialchars($edit_lesson['description']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Video URL (optional)</label>
                        <input type="url" name="video_url" class="form-control" value="<?php echo $editing ? htmlspecialchars($edit_lesson['video_url']) : ''; ?>" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label>Upload Material (PDF, DOC, PPT, MP4, etc.)</label>
                        <?php if ($editing && !empty($edit_lesson['file_path'])): ?>
                            <div class="current-file">
                                Current: <a href="../download.php?id=<?php echo $edit_lesson['id']; ?>"><?php echo basename($edit_lesson['file_path']); ?></a>
                                <small>(upload a new file to replace)</small>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="lesson_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.png">
                    </div>
                    <div class="form-group">
                        <label>Text Content (optional)</label>
                        <textarea name="content" class="form-control" rows="4"><?php echo $editing ? htmlspecialchars($edit_lesson['content']) : ''; ?></textarea>
                    </div>
                    <?php if (!$editing): ?>
                        <input type="hidden" name="order_index" value="0">
                    <?php endif; ?>
                    <button type="submit" class="btn-submit"><?php echo $editing ? 'Update Lesson' : 'Add Lesson'; ?></button>
                    <?php if ($editing): ?>
                        <a href="?course_id=<?php echo $course_id; ?>" class="btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>