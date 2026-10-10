<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in (optional – you may allow guests? For lessons, usually require login)
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$lesson_id) {
    die("Invalid request.");
}

// Get lesson details and verify enrollment if user is a student
if ($_SESSION['role'] == 'student') {
    // Get student ID
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if (!$student) {
        die("Student record not found.");
    }
    $student_id = $student['id'];

    // Check enrollment in the course this lesson belongs to
    $stmt = $conn->prepare("
        SELECT l.file_path FROM lessons l
        JOIN courses c ON l.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE l.id = ? AND e.student_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $student_id);
} elseif ($_SESSION['role'] == 'teacher') {
    // Teachers can download any lesson from their own courses
    $stmt = $conn->prepare("
        SELECT l.file_path FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id = ? AND c.teacher_id = (SELECT id FROM teachers WHERE user_id = ?)
    ");
    $stmt->bind_param("ii", $lesson_id, $user_id);
} else {
    // Admin or other roles – you may allow or deny
    die("Access denied.");
}

$stmt->execute();
$result = $stmt->get_result();
$lesson = $result->fetch_assoc();

if (!$lesson || empty($lesson['file_path'])) {
    die("File not found or you do not have permission.");
}

// Construct full server path
$file_path = __DIR__ . '/' . $lesson['file_path']; // assumes file_path is relative to project root

if (!file_exists($file_path)) {
    die("File does not exist on server.");
}

// Serve the file
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
readfile($file_path);
exit;