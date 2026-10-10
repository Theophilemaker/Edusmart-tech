<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Get student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    die("Student record not found.");
}
$student_id = $student['id'];

// Check if already enrolled
$check = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$check->bind_param("ii", $student_id, $course_id);
$check->execute();
if ($check->get_result()->num_rows == 0) {
    // Enroll
    $insert = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, progress) VALUES (?, ?, NOW(), 0)");
    $insert->bind_param("ii", $student_id, $course_id);
    $insert->execute();
}

header("Location: courses.php?enrolled=1");
exit();