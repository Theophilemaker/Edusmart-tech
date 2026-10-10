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

if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    if ($_GET['action'] == 'activate') {
        $conn->query("UPDATE users SET is_active = 1 WHERE id = $user_id");
    } elseif ($_GET['action'] == 'deactivate') {
        $conn->query("UPDATE users SET is_active = 0 WHERE id = $user_id");
    } elseif ($_GET['action'] == 'delete') {
        $conn->query("DELETE FROM users WHERE id = $user_id");
    }
    header("Location: manage-teachers.php");
    exit();
}

$teachers = $conn->query("
    SELECT u.*, t.employee_id, t.specialization, t.qualification
    FROM users u
    JOIN teachers t ON u.id = t.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Teachers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-nav">
                <h1>Manage Teachers</h1>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Employee ID</th>
                            <th>Specialization</th>
                            <th>Qualification</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($t = $teachers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                            <td><?php echo $t['email']; ?></td>
                            <td><?php echo $t['employee_id']; ?></td>
                            <td><?php echo $t['specialization'] ?? '—'; ?></td>
                            <td><?php echo $t['qualification'] ?? '—'; ?></td>
                            <td>
                                <span class="status-badge <?php echo ($t['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                    <?php echo ($t['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['is_active'] ?? 1): ?>
                                    <a href="?action=deactivate&id=<?php echo $t['id']; ?>" class="btn-action deactivate">Deactivate</a>
                                <?php else: ?>
                                    <a href="?action=activate&id=<?php echo $t['id']; ?>" class="btn-action activate">Activate</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $t['id']; ?>" class="btn-action delete" onclick="return confirm('Delete teacher?')">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>