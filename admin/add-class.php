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

// Get teachers for dropdown
$teachers = $conn->query("
    SELECT t.id, u.full_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name']);
    $section = trim($_POST['section']);
    $teacher_id = $_POST['teacher_id'] ? (int)$_POST['teacher_id'] : null;
    $academic_year = trim($_POST['academic_year']);

    $errors = [];
    if (empty($class_name)) $errors[] = "Class name is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO classes (class_name, section, teacher_id, academic_year)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssis", $class_name, $section, $teacher_id, $academic_year);
        if ($stmt->execute()) {
            $message = "Class added successfully!";
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
    <title>Add Class</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Add New Class</h1>
                <a href="manage-classes.php" class="btn-action edit">Back to Classes</a>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <div class="form-container" style="max-width:500px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="form-group">
                        <label>Class Name*</label>
                        <input type="text" name="class_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Section (optional)</label>
                        <input type="text" name="section" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Assigned Teacher (optional)</label>
                        <select name="teacher_id" class="form-control">
                            <option value="">-- None --</option>
                            <?php while($t = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year (e.g., 2025-2026)</label>
                        <input type="text" name="academic_year" class="form-control">
                    </div>
                    <button type="submit" name="add_class" class="btn-save">Add Class</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>