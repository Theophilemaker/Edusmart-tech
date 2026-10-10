<?php
// WARNING: This bypasses security! DELETE after testing.
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // TEMPORARY: Direct password comparison (NO HASHING)
    $query = "SELECT * FROM users WHERE email = ? AND password = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $password); // UNSAFE!
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: " . $user['role'] . "/dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>DEBUG LOGIN - DELETE AFTER USE</title>
    <style>
        body { background: #ffcccc; font-family: monospace; padding: 20px; }
        .warning { background: red; color: white; padding: 20px; text-align: center; font-size: 20px; }
        input, button { padding: 10px; margin: 5px; }
    </style>
</head>
<body>
    <div class="warning">⚠️ WARNING: This is UNSAFE! Delete this file immediately after testing! ⚠️</div>
    
    <h2>🔧 DEBUG LOGIN (No Password Hashing)</h2>
    
    <form method="POST">
        <input type="email" name="email" placeholder="Email" value="admin@edusmart.com"><br>
        <input type="text" name="password" placeholder="Password" value="admin123"><br>
        <button type="submit">Login (INSECURE)</button>
    </form>
    
    <h3>Test these passwords in database:</h3>
    <table border="1">
        <tr>
            <th>Email</th>
            <th>Current Hash</th>
            <th>Should be</th>
        </tr>
        <?php
        $users = $conn->query("SELECT email, password FROM users");
        while ($user = $users->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$user['email']}</td>";
            echo "<td style='font-family: monospace;'>{$user['password']}</td>";
            echo "<td>";
            echo password_hash('admin123', PASSWORD_DEFAULT);
            echo "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    
    <p style="color: red; font-weight: bold;">⚠️ DELETE THIS FILE AFTER TESTING! ⚠️</p>
</body>
</html>