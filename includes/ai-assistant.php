<?php
session_start();
require_once 'database.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = strtolower(trim($_POST['message'] ?? ''));
$context = $_POST['context'] ?? 'general';

if (empty($message)) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

// Role‑specific response generation
$response = match ($role) {
    'admin'    => adminResponse($message, $conn),
    'teacher'  => teacherResponse($message, $conn),
    'student'  => studentResponse($message, $conn),
    default    => "Hello! I'm here to help."
};

// Log conversation (optional)
file_put_contents(__DIR__ . '/../logs/ai_chat.log',
    date('Y-m-d H:i:s') . " | User: $user_id | Q: $message | A: $response\n",
    FILE_APPEND
);

echo json_encode(['response' => $response]);

function adminResponse($message, $conn) {
    $students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'] ?? 0;
    $teachers = $conn->query("SELECT COUNT(*) as total FROM teachers")->fetch_assoc()['total'] ?? 0;
    $courses  = $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'] ?? 0;
    $revenue  = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status='completed'")->fetch_assoc()['total'] ?? 0;

    if (strpos($message, 'student') !== false) {
        return "📊 We have **$students** enrolled students. Manage them from the Users section.";
    }
    if (strpos($message, 'teacher') !== false) {
        return "👨‍🏫 There are **$teachers** active teachers. Check Users > Teachers.";
    }
    if (strpos($message, 'course') !== false) {
        return "📚 Total courses: **$courses**. Use Reports for analytics.";
    }
    if (strpos($message, 'revenue') !== false || strpos($message, 'payment')) {
        return "💰 Total revenue: **$" . number_format($revenue, 2) . "**. View in Reports > Payments.";
    }
    if (strpos($message, 'help') !== false) {
        return "🆘 Ask me about students, teachers, courses, or revenue.";
    }
    return "Hello Admin! I can help with system stats. Try asking about students, teachers, courses, or revenue.";
}

function teacherResponse($message, $conn) {
    $teacher_id = $_SESSION['user_id'];
    $teacher_db = $conn->query("SELECT id FROM teachers WHERE user_id = $teacher_id")->fetch_assoc();
    $teacher_db_id = $teacher_db['id'] ?? 0;

    $courses = $conn->query("SELECT COUNT(*) as total FROM courses WHERE teacher_id = $teacher_db_id")->fetch_assoc()['total'] ?? 0;
    $students = $conn->query("SELECT COUNT(DISTINCT e.student_id) as total FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = $teacher_db_id")->fetch_assoc()['total'] ?? 0;
    $pending = $conn->query("SELECT COUNT(*) as total FROM exam_submissions s JOIN exams e ON s.exam_id = e.id JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = $teacher_db_id AND s.graded = 0")->fetch_assoc()['total'] ?? 0;

    if (strpos($message, 'course') !== false) {
        return "📖 You have **$courses** courses. Go to 'My Courses' to manage them.";
    }
    if (strpos($message, 'student') !== false) {
        return "👥 You have **$students** students across your courses. See them in the Students section.";
    }
    if (strpos($message, 'grade') !== false || strpos($message, 'pending')) {
        return "✏️ You have **$pending** pending submissions to grade. Head to the Grade section.";
    }
    if (strpos($message, 'exam') !== false) {
        return "📝 Create exams in the Exams section. You can add MCQ or essay questions.";
    }
    if (strpos($message, 'help') !== false) {
        return "🆘 Ask me about your courses, students, pending grades, or exams.";
    }
    return "Hi Teacher! I can help with your courses. Ask about courses, students, pending grades, or exams.";
}

function studentResponse($message, $conn) {
    $student_id = $_SESSION['user_id'];
    $student_db = $conn->query("SELECT id FROM students WHERE user_id = $student_id")->fetch_assoc();
    $student_db_id = $student_db['id'] ?? 0;

    $courses = $conn->query("SELECT COUNT(*) as total FROM enrollments WHERE student_id = $student_db_id")->fetch_assoc()['total'] ?? 0;
    $progress = $conn->query("SELECT AVG(progress) as avg FROM enrollments WHERE student_id = $student_db_id")->fetch_assoc()['avg'] ?? 0;
    $certificates = $conn->query("SELECT COUNT(*) as total FROM certificates WHERE student_id = $student_db_id")->fetch_assoc()['total'] ?? 0;

    if (strpos($message, 'course') !== false) {
        return "📚 You are enrolled in **$courses** courses. Check 'My Courses' to continue learning.";
    }
    if (strpos($message, 'progress') !== false) {
        return "📈 Your average course progress is **" . round($progress) . "%**. Keep it up!";
    }
    if (strpos($message, 'certificate') !== false) {
        return "🏆 You've earned **$certificates** certificates. View them in the Certificates section.";
    }
    if (strpos($message, 'exam') !== false) {
        return "📝 Upcoming exams are listed in the Exams section. Prepare well!";
    }
    if (strpos($message, 'help') !== false) {
        return "🆘 Ask me about your courses, progress, certificates, or exams.";
    }
    return "Hello Student! I'm your learning assistant. Ask about your courses, progress, certificates, or exams.";
}
?>