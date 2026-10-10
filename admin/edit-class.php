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

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$class_id) {
    header("Location: manage-classes.php");
    exit();
}

$class = $conn->query("SELECT * FROM classes WHERE id = $class_id")->fetch_assoc();
if (!$class) {
    header("Location: manage-classes.php");
    exit();
}

$message = '';
$message_type = '';

$teachers = $conn->query("
    SELECT t.id, u.full_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_class'])) {
    $class_name = trim($_POST['class_name']);
    $section = trim($_POST['section']);
    $teacher_id = $_POST['teacher_id'] ? (int)$_POST['teacher_id'] : null;
    $academic_year = trim($_POST['academic_year']);

    $errors = [];
    if (empty($class_name)) $errors[] = "Class name is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE classes 
            SET class_name = ?, section = ?, teacher_id = ?, academic_year = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssisi", $class_name, $section, $teacher_id, $academic_year, $class_id);
        if ($stmt->execute()) {
            $message = "Class updated successfully!";
            $message_type = 'success';
            $class = $conn->query("SELECT * FROM classes WHERE id = $class_id")->fetch_assoc();
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
    <title>Edit Class</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Edit Class</h1>
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
                        <input type="text" name="class_name" class="form-control" value="<?php echo htmlspecialchars($class['class_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($class['section']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Assigned Teacher</label>
                        <select name="teacher_id" class="form-control">
                            <option value="">-- None --</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($t = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $t['id']; ?>" <?php if($t['id'] == $class['teacher_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($t['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($class['academic_year']); ?>">
                    </div>
                    <button type="submit" name="update_class" class="btn-save">Update Class</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>