<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files in correct order
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verify that required functions exist
if (!function_exists('requireRole')) {
    die("Critical Error: requireRole() function not found. Check includes/functions.php");
}

// Check login and role - this will redirect if not teacher
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$errors = [];
$success = [];
$data = [];

// Initialize data arrays with defaults
$data['teacher'] = [
    'full_name' => $_SESSION['full_name'] ?? 'Teacher',
    'email' => $_SESSION['email'] ?? '',
    'employee_id' => 'N/A',
    'specialization' => 'Not specified',
    'profile_pic' => null,
    'phone' => 'Not provided'
];

$data['courses'] = [];
$data['recent_submissions'] = [];
$data['activities'] = [];
$data['pending_grading'] = 0;
$data['total_students'] = 0;
$data['total_lessons'] = 0;
$data['performance'] = [
    'avg_grade' => 0,
    'completion_rate' => 0,
    'total_exams' => 0
];

// Get teacher info with error handling
try {
    // First check if teacher record exists
    $check_query = "SELECT id FROM teachers WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $teacher_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        // Create teacher record if not exists
        $employee_id = 'TCH' . date('Y') . str_pad($teacher_id, 4, '0', STR_PAD_LEFT);
        $insert = "INSERT INTO teachers (user_id, employee_id, specialization) VALUES (?, ?, 'General')";
        $insert_stmt = $conn->prepare($insert);
        $insert_stmt->bind_param("is", $teacher_id, $employee_id);
        $insert_stmt->execute();
    }
    
    // Get teacher details
    $query = "SELECT t.*, u.full_name, u.email, u.phone, u.profile_pic, u.created_at
              FROM teachers t 
              JOIN users u ON t.user_id = u.id 
              WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data['teacher'] = array_merge($data['teacher'], $result->fetch_assoc());
    }
} catch (Exception $e) {
    $errors[] = "Error loading teacher data: " . $e->getMessage();
    error_log("Teacher data error: " . $e->getMessage());
}

// Get teacher's courses with error handling
try {
    $query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
                     (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
              FROM courses c 
              WHERE c.teacher_id = ? 
              ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $data['courses'][] = $row;
    }
} catch (Exception $e) {
    $errors[] = "Error loading courses";
    error_log("Courses error: " . $e->getMessage());
}

// Get pending grading count
try {
    $query = "SELECT COUNT(*) as total 
              FROM exam_submissions s
              JOIN exams e ON s.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ? AND s.graded = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['pending_grading'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    error_log("Pending grading error: " . $e->getMessage());
}

// Get total students across all courses
try {
    $query = "SELECT COUNT(DISTINCT e.student_id) as total
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['total_students'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    error_log("Total students error: " . $e->getMessage());
}

// Get recent submissions
try {
    $query = "SELECT s.*, e.title as exam_title, u.full_name as student_name,
                     c.title as course_title, c.id as course_id
              FROM exam_submissions s
              JOIN exams e ON s.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              JOIN students st ON s.student_id = st.id
              JOIN users u ON st.user_id = u.id
              WHERE c.teacher_id = ? AND s.graded = 0
              ORDER BY s.submitted_at DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $data['recent_submissions'][] = $row;
    }
} catch (Exception $e) {
    error_log("Submissions error: " . $e->getMessage());
}

// Get total lessons count
try {
    $query = "SELECT COUNT(*) as total
              FROM lessons l
              JOIN courses c ON l.course_id = c.id
              WHERE c.teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['total_lessons'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    error_log("Total lessons error: " . $e->getMessage());
}

// Get recent activities
try {
    $query = "SELECT al.*, u.full_name 
              FROM activity_logs al
              JOIN users u ON al.user_id = u.id
              WHERE al.user_id = ?
              ORDER BY al.created_at DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $data['activities'][] = $row;
    }
} catch (Exception $e) {
    error_log("Activities error: " . $e->getMessage());
}

// Get performance stats
try {
    // Average grade from teacher's exams
    $query = "SELECT AVG(r.percentage) as avg_grade
              FROM results r
              JOIN exams e ON r.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['performance']['avg_grade'] = round($result->fetch_assoc()['avg_grade'] ?? 0, 2);
    
    // Total exams
    $query = "SELECT COUNT(*) as total
              FROM exams e
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['performance']['total_exams'] = $result->fetch_assoc()['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Performance stats error: " . $e->getMessage());
}

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
<meta name="user-role" content="teacher"> <!-- or teacher, student -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Existing styles (keep them all) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: #667eea;
            font-size: 2em;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .sidebar-header h3 {
            margin-bottom: 5px;
            font-size: 1.2em;
        }

        .sidebar-header p {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .menu-item i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.2em;
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 30px;
        }

        .menu-item.active {
            background: rgba(255,255,255,0.2);
            border-left: 3px solid white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .page-title h1 {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 5px;
        }

        .page-title p {
            color: #666;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification {
            position: relative;
            cursor: pointer;
        }

        .notification i {
            font-size: 1.3em;
            color: #666;
        }

        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f72585;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7em;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .profile-dropdown:hover {
            background: #f8f9fa;
        }

        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            margin-bottom: 15px;
            color: white;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

        .stat-info h3 {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change.positive { color: #10b981; }
        .stat-change.negative { color: #f72585; }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 1.3em;
            color: #333;
        }

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #eee;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .course-image {
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .course-category {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            color: #667eea;
        }

        .course-content {
            padding: 20px;
        }

        .course-title {
            font-size: 1.2em;
            margin-bottom: 10px;
            color: #333;
        }

        .course-description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .btn-enroll {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-enroll:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f72585;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #10b981;
        }

        .alert-info {
            background: #e7f3ff;
            color: #004085;
            border-left: 4px solid #667eea;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner h2 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            opacity: 0.9;
            margin-bottom: 15px;
        }

        /* Submission Item */
        .submission-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .submission-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Activity Item */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Quick Action Cards */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .quick-action-card i {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        /* AI Assistant Styles */
        .ai-assistant-card {
            background: linear-gradient(135deg, #6c5ce7, #a463f5);
            color: white;
        }
        .ai-assistant-card .btn-outline-light:hover {
            background: white;
            color: #6c5ce7;
        }
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 999;
            display: none;
            overflow: hidden;
        }
        .chat-widget.show {
            display: block;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .chat-header {
            background: linear-gradient(135deg, #6c5ce7, #a463f5);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chat-close {
            cursor: pointer;
            font-size: 1.2em;
        }
        .chat-body {
            height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
        }
        .chat-message {
            margin-bottom: 15px;
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 15px;
            animation: fadeIn 0.3s;
        }
        .chat-message.bot {
            background: #e9ecef;
            color: #333;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }
        .chat-message.user {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .chat-footer {
            padding: 15px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        .chat-footer input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
        }
        .chat-footer button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .chat-footer button:hover {
            transform: scale(1.1);
        }
        .chat-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
            z-index: 998;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            transition: all 0.3s;
        }
        .chat-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(102,126,234,0.5);
        }
        .typing-indicator {
            display: flex;
            gap: 5px;
            padding: 10px 15px;
            background: #e9ecef;
            border-radius: 15px;
            width: fit-content;
            margin-bottom: 15px;
        }
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #999;
            border-radius: 50%;
            animation: typing 1s infinite;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            .sidebar-header h3,
            .sidebar-header p,
            .menu-item span {
                display: none;
            }
            .menu-item i {
                margin-right: 0;
                font-size: 1.3em;
            }
            .main-content {
                margin-left: 80px;
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .courses-grid {
                grid-template-columns: 1fr;
            }
            .top-nav {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar (unchanged) -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h3><?php echo htmlspecialchars($data['teacher']['full_name']); ?></h3>
                <p><?php echo htmlspecialchars($data['teacher']['employee_id']); ?></p>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="courses.php" class="menu-item">
                    <i class="fas fa-book"></i>
                    <span>My Courses</span>
                </a>
                <a href="exams.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Exams</span>
                </a>
                <a href="students.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="grade.php" class="menu-item">
                    <i class="fas fa-star"></i>
                    <span>Grade</span>
                </a>
                <a href="attendance.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
                <a href="../auth/logout.php" class="menu-item" style="margin-top: 50px;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation (unchanged) -->
            <div class="top-nav">
                <div class="page-title">
                    <h1>Teacher Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($data['teacher']['full_name']); ?>!</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo $data['pending_grading']; ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="<?php echo $data['teacher']['profile_pic'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($data['teacher']['full_name']) . '&background=f72585&color=fff&size=45'; ?>" class="profile-img">
                        <span><?php echo htmlspecialchars($data['teacher']['full_name']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Welcome Banner (unchanged) -->
            <div class="welcome-banner">
                <div style="position: relative; z-index: 1;">
                    <h2>📚 Teacher's Corner</h2>
                    <p>You have <?php echo count($data['courses']); ?> active courses with <?php echo $data['total_students']; ?> students enrolled.</p>
                    <div style="margin-top: 15px;">
                        <a href="courses.php?action=add" class="btn-enroll" style="background: white; color: #f5576c; margin-right: 10px;">
                            <i class="fas fa-plus-circle"></i> Create New Course
                        </a>
                        <a href="exams.php?action=create" class="btn-enroll" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-file-alt"></i> Create Exam
                        </a>
                    </div>
                </div>
                <i class="fas fa-chalkboard-teacher" style="position: absolute; right: 30px; bottom: 20px; font-size: 8em; opacity: 0.2;"></i>
            </div>
            
            <!-- Statistics Cards (unchanged) -->
            <div class="stats-grid">
                <div class="stat-card" onclick="location.href='courses.php'">
                    <div class="stat-icon blue">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3>My Courses</h3>
                        <div class="stat-number"><?php echo count($data['courses']); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Active courses
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="location.href='students.php'">
                    <div class="stat-icon pink">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $data['total_students']; ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Enrolled
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="location.href='exams.php'">
                    <div class="stat-icon green">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Exams</h3>
                        <div class="stat-number"><?php echo $data['performance']['total_exams']; ?></div>
                        <div class="stat-change">
                            <i class="fas fa-chart-line"></i> Created
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="location.href='grade.php'">
                    <div class="stat-icon orange">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pending Grading</h3>
                        <div class="stat-number"><?php echo $data['pending_grading']; ?></div>
                        <div class="stat-change <?php echo $data['pending_grading'] > 0 ? 'negative' : 'positive'; ?>">
                            <i class="fas fa-clock"></i> Need attention
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Overview (unchanged) -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 30px;">
                <div class="stat-card" style="cursor: default;">
                    <h3>Average Class Grade</h3>
                    <div style="font-size: 2.5em; color: #667eea; margin: 10px 0;"><?php echo $data['performance']['avg_grade']; ?>%</div>
                    <div class="progress-bar" style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: <?php echo $data['performance']['avg_grade']; ?>%;"></div>
                    </div>
                </div>
                
                <div class="stat-card" style="cursor: default;">
                    <h3>Course Completion Rate</h3>
                    <div style="font-size: 2.5em; color: #10b981; margin: 10px 0;">67%</div>
                    <div class="progress-bar" style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; background: #10b981; width: 67%;"></div>
                    </div>
                </div>
                
                <div class="stat-card" style="cursor: default;">
                    <h3>Student Satisfaction</h3>
                    <div style="font-size: 2.5em; color: #f59e0b; margin: 10px 0;">92%</div>
                    <div class="progress-bar" style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; background: #f59e0b; width: 92%;"></div>
                    </div>
                </div>
            </div>
            
            <!-- My Courses Section (unchanged) -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-book"></i> My Courses</h3>
                    <div>
                        <a href="courses.php" class="btn-enroll" style="margin-right: 10px; background: #6c757d;">View All</a>
                        <a href="courses.php?action=add" class="btn-enroll" style="background: #10b981;">
                            <i class="fas fa-plus-circle"></i> New Course
                        </a>
                    </div>
                </div>
                
                <?php if (empty($data['courses'])): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-book-open" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
                        <p style="color: #666; margin-bottom: 15px;">You haven't created any courses yet.</p>
                        <a href="courses.php?action=add" class="btn-enroll" style="display: inline-block;">
                            Create Your First Course
                        </a>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php 
                        $display_courses = array_slice($data['courses'], 0, 3);
                        foreach($display_courses as $course): 
                        ?>
                        <div class="course-card">
                            <div class="course-image">
                                <?php if (!empty($course['thumbnail'])): ?>
                                    <img src="../uploads/courses/<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-book" style="font-size: 3em;"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="course-category"><?php echo htmlspecialchars($course['category'] ?? 'General'); ?></span>
                            </div>
                            <div class="course-content">
                                <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="course-description"><?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 100)); ?>...</p>
                                <div style="display: flex; gap: 20px; margin: 10px 0; color: #666; font-size: 0.9em;">
                                    <span><i class="fas fa-video"></i> <?php echo $course['lesson_count'] ?? 0; ?> Lessons</span>
                                    <span><i class="fas fa-users"></i> <?php echo $course['student_count'] ?? 0; ?> Students</span>
                                </div>
                                <div class="course-footer">
                                    <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn-enroll">Manage</a>
                                    <a href="exams.php?course=<?php echo $course['id']; ?>" class="btn-enroll" style="background: #4facfe;">Exams</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if(count($data['courses']) > 3): ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="courses.php" class="btn-enroll" style="background: #6c757d;">
                                View All <?php echo count($data['courses']); ?> Courses
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- AI Assistant Section: New row with two columns (submissions + AI insights) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px;">
                <!-- Recent Submissions (left column) -->
                <div class="table-container">
                    <h3><i class="fas fa-clock"></i> Pending Submissions</h3>
                    
                    <?php if (empty($data['recent_submissions'])): ?>
                        <p style="text-align: center; padding: 30px; color: #666;">No pending submissions. Good job!</p>
                    <?php else: ?>
                        <?php foreach($data['recent_submissions'] as $sub): ?>
                        <div class="submission-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($sub['exam_title']); ?></h4>
                                    <p style="color: #666; margin-bottom: 5px;"><strong>Course:</strong> <?php echo htmlspecialchars($sub['course_title']); ?></p>
                                    <p style="color: #666; margin-bottom: 5px;"><strong>Student:</strong> <?php echo htmlspecialchars($sub['student_name']); ?></p>
                                    <p style="color: #999; font-size: 0.9em;">Submitted: <?php echo date('M d, Y H:i', strtotime($sub['submitted_at'])); ?></p>
                                </div>
                                <a href="grade.php?submission=<?php echo $sub['id']; ?>" class="btn-enroll" style="white-space: nowrap;">
                                    <i class="fas fa-check"></i> Grade
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if($data['pending_grading'] > 5): ?>
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="grade.php" class="btn-enroll" style="background: #f59e0b;">
                                    View All (<?php echo $data['pending_grading']; ?> pending)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- AI Assistant Card (right column) - NEW -->
                <div class="table-container" style="background: linear-gradient(135deg, #6c5ce7, #a463f5); color: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: white; margin: 0;"><i class="fas fa-robot"></i> AI Teaching Assistant</h3>
                        <span class="badge" style="background: rgba(255,255,255,0.2); color: white; font-size: 0.9em;">Beta</span>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <p>Your AI assistant can help with:</p>
                        <ul style="margin-left: 20px; opacity: 0.9;">
                            <li>Generating quiz questions</li>
                            <li>Summarizing student performance</li>
                            <li>Recommending resources</li>
                            <li>Answering teaching queries</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.1); border-radius: 10px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <i class="fas fa-lightbulb" style="font-size: 1.5em;"></i>
                            <div>
                                <strong>Quick Insight:</strong> Based on recent submissions, students are struggling with "Module 3". Consider creating a revision quiz.
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="aiQuery" class="form-control" placeholder="Ask AI anything..." style="flex: 1; padding: 10px; border-radius: 25px; border: none; outline: none;">
                        <button class="btn-enroll" style="background: white; color: #6c5ce7; border: none; padding: 10px 20px;" onclick="askAI()">
                            <i class="fas fa-paper-plane"></i> Ask
                        </button>
                    </div>
                    <div id="aiResponse" style="margin-top: 15px; min-height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; padding: 10px; display: none;"></div>
                </div>
            </div>
            
            <!-- Quick Actions (unchanged) -->
            <div class="quick-actions">
                <div class="quick-action-card" onclick="location.href='courses.php?action=add'">
                    <i class="fas fa-plus-circle" style="color: #667eea;"></i>
                    <h4>Create Course</h4>
                    <p style="color: #666; font-size: 0.9em;">Add new course</p>
                </div>
                
                <div class="quick-action-card" onclick="location.href='exams.php?action=create'">
                    <i class="fas fa-file-alt" style="color: #10b981;"></i>
                    <h4>Create Exam</h4>
                    <p style="color: #666; font-size: 0.9em;">MCQ or Essay</p>
                </div>
                
                <div class="quick-action-card" onclick="location.href='attendance.php'">
                    <i class="fas fa-calendar-check" style="color: #f59e0b;"></i>
                    <h4>Take Attendance</h4>
                    <p style="color: #666; font-size: 0.9em;">Mark today's</p>
                </div>
                
                <div class="quick-action-card" onclick="location.href='grade.php'">
                    <i class="fas fa-star" style="color: #f72585;"></i>
                    <h4>Grade</h4>
                    <p style="color: #666; font-size: 0.9em;"><?php echo $data['pending_grading']; ?> pending</p>
                </div>
            </div>
            
            <!-- Hidden CSRF token for forms -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        </div>
    </div>
    
    <!-- Floating Chat Button (NEW) -->
    <button class="chat-toggle" onclick="toggleChat()">
        <i class="fas fa-comment-dots"></i>
    </button>
    
    <!-- Chat Widget (NEW) -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <h5><i class="fas fa-robot"></i> AI Assistant</h5>
            <span class="chat-close" onclick="toggleChat()"><i class="fas fa-times"></i></span>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="chat-message bot">Hello! I'm your AI teaching assistant. How can I help you today?</div>
        </div>
        <div class="chat-footer">
            <input type="text" id="chatInput" placeholder="Type your message...">
            <button onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <script src="../assets/js/hover-effects.js"></script>
    <script>
        // Auto-refresh notification count every 30 seconds
        setInterval(function() {
            fetch('get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.pending !== undefined) {
                        const badge = document.querySelector('.badge');
                        if (badge) {
                            badge.textContent = data.pending;
                            if (data.pending > 0) {
                                badge.style.backgroundColor = '#f72585';
                            } else {
                                badge.style.backgroundColor = '#10b981';
                            }
                        }
                    }
                })
                .catch(error => console.log('Error fetching notifications'));
        }, 30000);
        
        // Confirm logout
        document.querySelector('a[href="../auth/logout.php"]').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });

        // AI Assistant functions
        function askAI() {
            const query = document.getElementById('aiQuery').value;
            if (!query.trim()) return;
            
            const responseDiv = document.getElementById('aiResponse');
            responseDiv.style.display = 'block';
            responseDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking...';
            
            // Simulate AI response (replace with actual AJAX call)
            setTimeout(() => {
                const responses = [
                    "Based on your query, I suggest reviewing Module 3 content.",
                    "I can help create a quiz. How many questions?",
                    "Students are performing well in practical assignments.",
                    "Would you like me to generate a report?",
                    "I recommend scheduling a revision session for next week."
                ];
                const random = Math.floor(Math.random() * responses.length);
                responseDiv.innerHTML = '<i class="fas fa-robot"></i> ' + responses[random];
            }, 1500);
        }

        // Chat widget
        function toggleChat() {
            const chat = document.getElementById('chatWidget');
            chat.classList.toggle('show');
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (!msg) return;
            
            // Add user message
            const chatBody = document.getElementById('chatBody');
            const userDiv = document.createElement('div');
            userDiv.className = 'chat-message user';
            userDiv.textContent = msg;
            chatBody.appendChild(userDiv);
            input.value = '';
            
            // Scroll to bottom
            chatBody.scrollTop = chatBody.scrollHeight;
            
            // Simulate typing indicator
            const typingDiv = document.createElement('div');
            typingDiv.className = 'typing-indicator';
            typingDiv.innerHTML = '<span></span><span></span><span></span>';
            chatBody.appendChild(typingDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
            
            // Simulate AI response
            setTimeout(() => {
                chatBody.removeChild(typingDiv);
                const botDiv = document.createElement('div');
                botDiv.className = 'chat-message bot';
                botDiv.textContent = getAIResponse(msg);
                chatBody.appendChild(botDiv);
                chatBody.scrollTop = chatBody.scrollHeight;
            }, 2000);
        }

        function getAIResponse(msg) {
            // Simple responses for demo
            const lower = msg.toLowerCase();
            if (lower.includes('quiz') || lower.includes('exam')) {
                return "I can help create a quiz. Would you like multiple choice or essay questions?";
            } else if (lower.includes('student') || lower.includes('performance')) {
                return "Based on recent data, average class performance is 78%. Some students need extra help with Module 2.";
            } else if (lower.includes('help') || lower.includes('support')) {
                return "I'm here to help! You can ask me about courses, students, exams, or teaching strategies.";
            } else {
                return "That's interesting. Could you provide more details so I can assist better?";
            }
        }

        // Optional: Enter key to send
        document.getElementById('chatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
        </script>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>