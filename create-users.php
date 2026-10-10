<?php
// save as create-users.php
require_once 'includes/security.php';

$db = getDB();

// Create Admin
$admin_password = Security::hashPassword('Admin@123');
$admin = $db->prepare("
    INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$admin->execute([
    'admin',
    'admin@school.com',
    $admin_password,
    'Admin',
    'User',
    'super_admin',
    1
]);

$admin_id = $db->lastInsertId();

// Create Teacher
$teacher_password = Security::hashPassword('Teacher@123');
$teacher = $db->prepare("
    INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$teacher->execute([
    'teacher',
    'teacher@school.com',
    $teacher_password,
    'John',
    'Doe',
    'teacher',
    1
]);

$teacher_user_id = $db->lastInsertId();

// Add teacher details
$teacher_details = $db->prepare("
    INSERT INTO teachers (user_id, employee_id, qualification, joining_date) 
    VALUES (?, ?, ?, CURDATE())
");

$teacher_details->execute([
    $teacher_user_id,
    'TCH001',
    'Masters in Computer Science'
]);

echo "Users created successfully!\n";
echo "Admin - Username: admin, Password: Admin@123\n";
echo "Teacher - Username: teacher, Password: Teacher@123\n";
?>