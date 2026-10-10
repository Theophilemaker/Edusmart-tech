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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_employer'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $company   = trim($_POST['company_name']);
    $phone     = trim($_POST['phone'] ?? '');
    $website   = trim($_POST['website'] ?? '');

    $errors = [];
    if (empty($username)) $errors[] = "Username required";
    if (empty($email)) $errors[] = "Email required";
    if (empty($password)) $errors[] = "Password required";
    if (strlen($password) < 6) $errors[] = "Password min 6 chars";
    if (empty($full_name)) $errors[] = "Contact person name required";
    if (empty($company)) $errors[] = "Company name required";

    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $errors[] = "Username or email already taken.";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, 'employer', 1)");
            $stmt->bind_param("sssss", $username, $email, $hashed, $full_name, $phone);
            $stmt->execute();
            $new_user_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO employers (user_id, company_name, website) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $new_user_id, $company, $website);
            $stmt2->execute();

            $conn->commit();
            $message = "Employer registered successfully!";
            $message_type = 'success';
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

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register Employer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Register New Employer</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <div class="form-container" style="max-width:600px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="form-group">
                        <label>Company Name*</label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Person Full Name*</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username*</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email*</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password* (min 6)</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" class="form-control">
                    </div>
                    <button type="submit" name="register_employer" class="btn-save">Register Employer</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>