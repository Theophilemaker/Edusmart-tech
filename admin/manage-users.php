<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle activation/deactivation/deletion
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    if ($target_id == $_SESSION['user_id']) {
        $message = "You cannot change your own status.";
        $message_type = 'error';
    } else {
        if ($_GET['action'] == 'activate') {
            $conn->query("UPDATE users SET is_active = 1 WHERE id = $target_id");
            $message = "User activated.";
            $message_type = 'success';
        } elseif ($_GET['action'] == 'deactivate') {
            $conn->query("UPDATE users SET is_active = 0 WHERE id = $target_id");
            $message = "User deactivated.";
            $message_type = 'success';
        } elseif ($_GET['action'] == 'delete') {
            $conn->query("DELETE FROM users WHERE id = $target_id");
            $message = "User deleted.";
            $message_type = 'success';
        }
    }
    header("Location: manage-users.php?msg=" . urlencode($message) . "&type=$message_type");
    exit();
}

// Handle add user form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    $phone    = trim($_POST['phone'] ?? '');

    $errors = [];
    if (empty($username)) $errors[] = "Username required";
    if (empty($email)) $errors[] = "Email required";
    if (empty($password)) $errors[] = "Password required";
    if (strlen($password) < 6) $errors[] = "Password min 6 characters";
    if (empty($full_name)) $errors[] = "Full name required";

    // Check uniqueness
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $errors[] = "Username or email already exists.";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssss", $username, $email, $hashed, $full_name, $phone, $role);
            $stmt->execute();
            $new_user_id = $conn->insert_id;

            // Create role-specific record
            if ($role == 'student') {
                $admission = 'STU' . date('Y') . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                // Use correct column name: admission_number
                $conn->query("INSERT INTO students (user_id, admission_number) VALUES ($new_user_id, '$admission')");
            } elseif ($role == 'teacher') {
                $emp_id = 'TCH' . date('Y') . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                $conn->query("INSERT INTO teachers (user_id, employee_id) VALUES ($new_user_id, '$emp_id')");
            } elseif ($role == 'employer') {
                $company = trim($_POST['company_name'] ?? '');
                if (empty($company)) throw new Exception("Company name required for employer");
                $conn->query("INSERT INTO employers (user_id, company_name) VALUES ($new_user_id, '$company')");
            }

            $conn->commit();
            $message = "User added successfully.";
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

// Filter by role
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with correct column names
$query = "SELECT u.*, 
          s.admission_number, t.employee_id, e.company_name 
          FROM users u
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN teachers t ON u.id = t.user_id
          LEFT JOIN employers e ON u.id = e.user_id
          WHERE 1=1";
if ($role_filter != 'all') {
    $role_filter_esc = $conn->real_escape_string($role_filter);
    $query .= " AND u.role = '$role_filter_esc'";
}
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $query .= " AND (u.full_name LIKE '%$search_esc%' OR u.email LIKE '%$search_esc%' OR u.username LIKE '%$search_esc%')";
}
$query .= " ORDER BY u.created_at DESC";
$users = $conn->query($query);

// Counts for filter badges
$count_all = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$count_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student'")->fetch_assoc()['total'];
$count_teachers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='teacher'")->fetch_assoc()['total'];
$count_employers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='employer'")->fetch_assoc()['total'];
$count_admins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin' OR role='super_admin'")->fetch_assoc()['total'];

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Manage Users</h1>
                    <p>All system users</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <!-- Filter & Actions -->
            <div style="background:white; padding:20px; border-radius:10px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <select name="role" style="padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                        <option value="all" <?php if($role_filter=='all') echo 'selected'; ?>>All (<?php echo $count_all; ?>)</option>
                        <option value="student" <?php if($role_filter=='student') echo 'selected'; ?>>Students (<?php echo $count_students; ?>)</option>
                        <option value="teacher" <?php if($role_filter=='teacher') echo 'selected'; ?>>Teachers (<?php echo $count_teachers; ?>)</option>
                        <option value="employer" <?php if($role_filter=='employer') echo 'selected'; ?>>Employers (<?php echo $count_employers; ?>)</option>
                        <option value="admin" <?php if($role_filter=='admin') echo 'selected'; ?>>Admins (<?php echo $count_admins; ?>)</option>
                    </select>
                    <input type="text" name="search" placeholder="Search by name, email, username" value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:8px; border:2px solid #e0e0e0; border-radius:6px;">
                    <button type="submit" class="btn-action view">Filter</button>
                    <button type="button" class="btn-action edit" onclick="showModal('addUserModal')"><i class="fas fa-plus"></i> Add User</button>
                </form>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?>">
                    <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Identifier</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows == 0): ?>
                            <tr><td colspan="9" style="text-align:center;">No users found.</td></tr>
                        <?php else: while($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                    if ($user['role'] == 'student') echo $user['admission_number'] ?? '—';
                                    elseif ($user['role'] == 'teacher') echo $user['employee_id'] ?? '—';
                                    elseif ($user['role'] == 'employer') echo htmlspecialchars($user['company_name'] ?? '—');
                                    else echo '—';
                                ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo ($user['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                    <?php echo ($user['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($user['is_active'] ?? 1): ?>
                                        <a href="?action=deactivate&id=<?php echo $user['id']; ?>" class="btn-action deactivate" onclick="return confirm('Deactivate this user?')">Deactivate</a>
                                    <?php else: ?>
                                        <a href="?action=activate&id=<?php echo $user['id']; ?>" class="btn-action activate" onclick="return confirm('Activate this user?')">Activate</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn-action delete" onclick="return confirm('Permanently delete this user? All associated data will be removed.')">Delete</a>
                                <?php else: ?>
                                    <span class="status-badge">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <h2>Add New User</h2>
                <span class="close" onclick="closeModal('addUserModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Full Name*</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username*</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                </div>
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Email*</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Password* (min 6)</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Role*</label>
                        <select name="role" id="roleSelect" class="form-control" required onchange="toggleCompanyField()">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="employer">Employer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="companyField" style="display:none;">
                    <label>Company Name* (for employer)</label>
                    <input type="text" name="company_name" class="form-control">
                </div>
                <button type="submit" name="add_user" class="btn-save">Add User</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/ai-widget.js"></script>
    <script>
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        function toggleCompanyField() {
            var role = document.getElementById('roleSelect').value;
            var field = document.getElementById('companyField');
            if (role === 'employer') {
                field.style.display = 'block';
                field.querySelector('input').required = true;
            } else {
                field.style.display = 'none';
                field.querySelector('input').required = false;
            }
        }
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>