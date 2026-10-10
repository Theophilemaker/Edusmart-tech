<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$course_id) {
    header("Location: manage-courses.php");
    exit();
}

// Fetch course data
$course = $conn->query("SELECT * FROM courses WHERE id = $course_id")->fetch_assoc();
if (!$course) {
    header("Location: manage-courses.php");
    exit();
}

$message = '';
$message_type = '';

// Get teachers list
$teachers = $conn->query("
    SELECT t.id, u.full_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $level = $_POST['level'];
    $teacher_id = $_POST['teacher_id'] ? (int)$_POST['teacher_id'] : null;
    $price = floatval($_POST['price'] ?? 0);
    $is_published = isset($_POST['is_published']) ? 1 : 0;

    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($category)) $errors[] = "Category is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE courses 
            SET title = ?, description = ?, category = ?, level = ?, teacher_id = ?, price = ?, is_published = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssidii", $title, $description, $category, $level, $teacher_id, $price, $is_published, $course_id);
        if ($stmt->execute()) {
            $message = "Course updated successfully!";
            $message_type = 'success';
            // Refresh data
            $course = $conn->query("SELECT * FROM courses WHERE id = $course_id")->fetch_assoc();
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Course</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Edit Course</h1>
                <a href="manage-courses.php" class="btn-action edit">Back to Courses</a>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <div class="form-container" style="max-width:700px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="form-group">
                        <label>Title*</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description*</label>
                        <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Category*</label>
                            <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($course['category']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" class="form-control">
                                <option value="beginner" <?php if($course['level']=='beginner') echo 'selected'; ?>>Beginner</option>
                                <option value="intermediate" <?php if($course['level']=='intermediate') echo 'selected'; ?>>Intermediate</option>
                                <option value="advanced" <?php if($course['level']=='advanced') echo 'selected'; ?>>Advanced</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" class="form-control" value="<?php echo $course['price']; ?>" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Assigned Teacher (optional)</label>
                            <select name="teacher_id" class="form-control">
                                <option value="">-- None --</option>
                                <?php 
                                $teachers->data_seek(0);
                                while($t = $teachers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $t['id']; ?>" <?php if($t['id'] == $course['teacher_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($t['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                            <label>
                                <input type="checkbox" name="is_published" value="1" <?php if($course['is_published']) echo 'checked'; ?>> Published
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="update_course" class="btn-save">Update Course</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>