<?php
require_once 'includes/security.php';

$db = getDB();

// Check if already setup
$check = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($check > 0) {
    die("System already has users. Delete database to reinstall.");
}

// Create admin
$admin_password = Security::hashPassword('Admin@123');
$db->prepare("
    INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
    VALUES ('admin', 'admin@school.com', ?, 'System', 'Administrator', 'super_admin', 1)
")->execute([$admin_password]);

$admin_id = $db->lastInsertId();

// Create teacher
$teacher_password = Security::hashPassword('Teacher@123');
$db->prepare("
    INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
    VALUES ('teacher', 'teacher@school.com', ?, 'John', 'Doe', 'teacher', 1)
")->execute([$teacher_password]);

$teacher_user_id = $db->lastInsertId();

// Add teacher details
$db->prepare("
    INSERT INTO teachers (user_id, employee_id, qualification, joining_date) 
    VALUES (?, 'TCH001', 'Masters in Computer Science', CURDATE())
")->execute([$teacher_user_id]);

// Create student
$student_password = Security::hashPassword('Student@123');
$db->prepare("
    INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active) 
    VALUES ('student', 'student@school.com', ?, 'Jane', 'Smith', 'student', 1)
")->execute([$student_password]);

$student_user_id = $db->lastInsertId();

// Add student details
$db->prepare("
    INSERT INTO students (user_id, admission_number, admission_date, date_of_birth, gender) 
    VALUES (?, 'STU001', CURDATE(), '2005-01-15', 'Female')
")->execute([$student_user_id]);

echo "✅ Setup completed successfully!\n";
echo "=======================\n";
echo "Admin   : admin@school.com / Admin@123\n";
echo "Teacher : teacher@school.com / Teacher@123\n";
echo "Student : student@school.com / Student@123\n";
echo "=======================\n";
echo "Please login and change your password immediately!";