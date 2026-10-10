<?php
require_once 'config/database.php';

// ONLY RUN THIS ONCE THEN DELETE IT!

echo "<h2>Admin Password Reset Tool</h2>";

if (isset($_POST['reset'])) {
    $email = $_POST['email'];
    $new_password = $_POST['password'];
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $query = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $hashed, $email);
    
    if ($stmt->execute()) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ Password reset successfully!<br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "New Password: " . htmlspecialchars($new_password);
        echo "</div>";
    } else {
        echo "<div style='color: red;'>❌ Error: " . $conn->error . "</div>";
    }
}

// Show all users
$users = $conn->query("SELECT id, email, full_name, role FROM users");
?>

<div style="max-width: 600px; margin: 20px auto; font-family: Arial;">
    <h3>Current Users:</h3>
    <table border="1" cellpadding="8" style="width: 100%; border-collapse: collapse;">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
        </tr>
        <?php while($user = $users->fetch_assoc()): ?>
        <tr>
            <td><?php echo $user['id']; ?></td>
            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo $user['role']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    
    <h3 style="margin-top: 30px;">Reset Password:</h3>
    <form method="POST" style="background: #f5f5f5; padding: 20px; border-radius: 10px;">
        <div style="margin-bottom: 15px;">
            <label>Select User:</label><br>
            <select name="email" required style="width: 100%; padding: 8px;">
                <?php 
                $users->data_seek(0);
                while($user = $users->fetch_assoc()): 
                ?>
                <option value="<?php echo $user['email']; ?>">
                    <?php echo $user['email'] . ' (' . $user['role'] . ')'; ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label>New Password:</label><br>
            <input type="text" name="password" value="password123" required style="width: 100%; padding: 8px;">
        </div>
        
        <button type="submit" name="reset" style="background: #4361ee; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            Reset Password
        </button>
    </form>
    
    <p style="color: red; margin-top: 20px;">
        <strong>⚠️ DELETE THIS FILE AFTER USE!</strong>
    </p>
</div>