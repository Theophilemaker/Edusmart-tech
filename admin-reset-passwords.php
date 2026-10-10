<?php
require_once 'config/database.php';
session_start();

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied");
}

if (isset($_POST['reset_user'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    
    if ($stmt->execute()) {
        $message = "Password reset successfully!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Reset</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="main-content">
            <h2>Admin Password Reset Tool</h2>
            
            <?php if (isset($message)) echo "<p style='color:green'>$message</p>"; ?>
            
            <table class="table">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
                <?php
                $users = $conn->query("SELECT id, username, email, role FROM users");
                while ($user = $users->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['username']}</td>";
                    echo "<td>{$user['email']}</td>";
                    echo "<td>{$user['role']}</td>";
                    echo "<td>
                        <form method='POST' style='display:inline'>
                            <input type='hidden' name='user_id' value='{$user['id']}'>
                            <input type='text' name='new_password' value='newpass123' required>
                            <button type='submit' name='reset_user'>Reset</button>
                        </form>
                    </td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>
    </div>
</body>
</html>