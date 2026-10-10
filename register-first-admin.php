<?php
require_once 'includes/security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Check if any admin exists
    $check = $db->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
    
    if ($check == 0) {
        // Create admin
        $password = Security::hashPassword($_POST['password']);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
            VALUES (?, ?, ?, ?, ?, 'super_admin', 1)
        ");
        
        $stmt->execute([
            $_POST['username'],
            $_POST['email'],
            $password,
            $_POST['first_name'],
            $_POST['last_name']
        ]);
        
        $user_id = $db->lastInsertId();
        
        // Create teacher record for admin
        $teacher_stmt = $db->prepare("
            INSERT INTO teachers (user_id, employee_id, qualification, joining_date) 
            VALUES (?, ?, ?, CURDATE())
        ");
        
        $teacher_stmt->execute([
            $user_id,
            'ADM' . str_pad($user_id, 3, '0', STR_PAD_LEFT),
            'Administrator'
        ]);
        
        echo "Admin created successfully! <a href='login.php'>Login</a>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create First Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Create First Administrator</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Admin</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>