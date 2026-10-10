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

$message = '';
$message_type = '';

// Get teachers for dropdown (list all teachers with their names)
$teachers = $conn->query("
    SELECT t.id, u.full_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
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
            INSERT INTO courses (title, description, category, level, teacher_id, price, is_published, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssssidi", $title, $description, $category, $level, $teacher_id, $price, $is_published);
        if ($stmt->execute()) {
            $message = "Course added successfully!";
            $message_type = 'success';
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
    <title>Add Course</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Add New Course</h1>
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
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description*</label>
                        <textarea name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Category*</label>
                            <input type="text" name="category" class="form-control" required>
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
                            <input type="number" name="price" class="form-control" value="0" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Assigned Teacher (optional)</label>
                            <select name="teacher_id" class="form-control">
                                <option value="">-- None --</option>
                                <?php while($t = $teachers->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                            <label>
                                <input type="checkbox" name="is_published" value="1" checked> Published
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="add_course" class="btn-save">Add Course</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>