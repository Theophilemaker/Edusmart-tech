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

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    die("Invalid user ID.");
}

// Fetch user data
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
if (!$user) {
    die("User not found.");
}

$role = $user['role'];
$message = '';
$message_type = '';

// Fetch role-specific data
$student = null;
$teacher = null;
$employer = null;
if ($role == 'student') {
    $student = $conn->query("SELECT * FROM students WHERE user_id = $user_id")->fetch_assoc();
} elseif ($role == 'teacher') {
    $teacher = $conn->query("SELECT * FROM teachers WHERE user_id = $user_id")->fetch_assoc();
} elseif ($role == 'employer') {
    $employer = $conn->query("SELECT * FROM employers WHERE user_id = $user_id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);

    $errors = [];

    if (empty($full_name)) $errors[] = "Full name required";
    if (empty($email)) $errors[] = "Email required";
    if (empty($username)) $errors[] = "Username required";

    // Check uniqueness
    if ($username != $user['username']) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errors[] = "Username already taken";
    }
    if ($email != $user['email']) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errors[] = "Email already taken";
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, username = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $username, $user_id);
            $stmt->execute();

            // Role-specific updates
            if ($role == 'student') {
                $class = $_POST['class'] ?? null;
                $parent_name = $_POST['parent_name'] ?? null;
                $parent_phone = $_POST['parent_phone'] ?? null;
                // Only update if columns exist
                $updates = [];
                $params = [];
                if (in_array('class', $columns ?? [])) {
                    $updates[] = "class = ?";
                    $params[] = $class;
                }
                if (in_array('parent_name', $columns ?? [])) {
                    $updates[] = "parent_name = ?";
                    $params[] = $parent_name;
                }
                if (in_array('parent_phone', $columns ?? [])) {
                    $updates[] = "parent_phone = ?";
                    $params[] = $parent_phone;
                }
                if (!empty($updates)) {
                    $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE user_id = ?";
                    $params[] = $user_id;
                    $stmt2 = $conn->prepare($sql);
                    $types = str_repeat('s', count($params)-1) . 'i';
                    $stmt2->bind_param($types, ...$params);
                    $stmt2->execute();
                }
            } elseif ($role == 'teacher') {
                $specialization = $_POST['specialization'] ?? null;
                $qualification = $_POST['qualification'] ?? null;
                $update = $conn->prepare("UPDATE teachers SET specialization = ?, qualification = ? WHERE user_id = ?");
                $update->bind_param("ssi", $specialization, $qualification, $user_id);
                $update->execute();
            } elseif ($role == 'employer') {
                $company = $_POST['company_name'] ?? null;
                $website = $_POST['website'] ?? null;
                $phone = $_POST['company_phone'] ?? null;
                $update = $conn->prepare("UPDATE employers SET company_name = ?, website = ?, phone = ? WHERE user_id = ?");
                $update->bind_param("sssi", $company, $website, $phone, $user_id);
                $update->execute();
            }

            $conn->commit();
            $message = "User updated successfully!";
            $message_type = 'success';
            // Refresh data
            $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
            if ($role == 'student') $student = $conn->query("SELECT * FROM students WHERE user_id = $user_id")->fetch_assoc();
            elseif ($role == 'teacher') $teacher = $conn->query("SELECT * FROM teachers WHERE user_id = $user_id")->fetch_assoc();
            elseif ($role == 'employer') $employer = $conn->query("SELECT * FROM employers WHERE user_id = $user_id")->fetch_assoc();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// Get columns for dynamic display (optional)
$student_columns = [];
if ($role == 'student') {
    $res = $conn->query("SHOW COLUMNS FROM students");
    while ($c = $res->fetch_assoc()) $student_columns[] = $c['Field'];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .form-container { max-width: 600px; margin: 20px auto; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Edit User (<?php echo ucfirst($role); ?>)</h1>
                <a href="<?php echo "manage-{$role}s.php"; ?>" class="btn-action">Back</a>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                    </div>

                    <!-- Role-specific fields -->
                    <?php if ($role == 'student' && $student): ?>
                        <?php if (in_array('class', $student_columns)): ?>
                        <div class="form-group">
                            <label>Class</label>
                            <input type="text" name="class" class="form-control" value="<?php echo htmlspecialchars($student['class'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        <?php if (in_array('parent_name', $student_columns)): ?>
                        <div class="form-group">
                            <label>Parent Name</label>
                            <input type="text" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        <?php if (in_array('parent_phone', $student_columns)): ?>
                        <div class="form-group">
                            <label>Parent Phone</label>
                            <input type="text" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($role == 'teacher' && $teacher): ?>
                        <div class="form-group">
                            <label>Employee ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher['employee_id']); ?>" readonly disabled>
                        </div>
                        <div class="form-group">
                            <label>Specialization</label>
                            <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Qualification</label>
                            <input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($role == 'employer' && $employer): ?>
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($employer['company_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Website</label>
                            <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($employer['website'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Company Phone</label>
                            <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($employer['phone'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="update_user" class="btn-save">Update User</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>