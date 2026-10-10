<?php
// ADMIN SELECTION LOGIN - Choose admin to login
session_start();
require_once 'config/database.php';

// Get all admin users
$admins = $conn->query("SELECT id, username, email, full_name FROM users WHERE role = 'admin'");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = $_POST['admin_id'];
    
    $query = "SELECT * FROM users WHERE id = $admin_id AND role = 'admin'";
    $result = $conn->query($query);
    
    if ($admin = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['full_name'] = $admin['full_name'];
        $_SESSION['role'] = 'admin';
        
        header("Location: admin/dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Select Admin</title>
    <style>
        body {
            font-family: Arial;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 400px;
        }
        h2 { text-align: center; color: #333; }
        .admin-list {
            margin: 20px 0;
        }
        .admin-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            margin-bottom: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .admin-item:hover {
            background: #f5f5f5;
            border-color: #667eea;
        }
        .admin-item input[type="radio"] {
            margin-right: 15px;
        }
        .admin-info h4 {
            margin: 0;
            color: #333;
        }
        .admin-info p {
            margin: 5px 0 0;
            color: #666;
            font-size: 12px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button:hover { background: #764ba2; }
    </style>
</head>
<body>
    <div class="container">
        <h2>👥 Select Admin Account</h2>
        
        <form method="POST">
            <div class="admin-list">
                <?php while($admin = $admins->fetch_assoc()): ?>
                <label class="admin-item">
                    <input type="radio" name="admin_id" value="<?php echo $admin['id']; ?>" required>
                    <div class="admin-info">
                        <h4><?php echo htmlspecialchars($admin['full_name']); ?></h4>
                        <p><?php echo htmlspecialchars($admin['username']); ?> | <?php echo htmlspecialchars($admin['email']); ?></p>
                    </div>
                </label>
                <?php endwhile; ?>
            </div>
            
            <button type="submit">Login as Selected Admin</button>
        </form>
        
        <?php if ($admins->num_rows == 0): ?>
            <p style="color: red; text-align: center;">No admin users found! Create one first.</p>
        <?php endif; ?>
    </div>
</body>
</html>