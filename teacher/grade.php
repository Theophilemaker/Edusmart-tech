<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Initialize data arrays
$submissions = [];
$graded_results = [];
$stats = [];

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_grade'])) {
    $submission_id = intval($_POST['submission_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    $exam_id = intval($_POST['exam_id'] ?? 0);
    $marks = floatval($_POST['marks'] ?? 0);
    $total_marks = intval($_POST['total_marks'] ?? 0);
    $feedback = $conn->real_escape_string($_POST['feedback'] ?? '');
    
    // Validate input
    if ($submission_id <= 0 || $student_id <= 0 || $exam_id <= 0) {
        $message = "Invalid submission data";
        $message_type = 'error';
    } elseif ($marks < 0 || $marks > $total_marks) {
        $message = "Marks must be between 0 and $total_marks";
        $message_type = 'error';
    } else {
        // Calculate percentage and grade
        $percentage = ($marks / $total_marks) * 100;
        $grade = calculateGrade($percentage);
        $status = $percentage >= 50 ? 'pass' : 'fail';
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert result
            $query = "INSERT INTO results (student_id, exam_id, marks_obtained, total_marks, percentage, grade, status, feedback, submitted_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiddssss", $student_id, $exam_id, $marks, $total_marks, $percentage, $grade, $status, $feedback);
            $stmt->execute();
            
            // Update submission as graded
            $update = "UPDATE exam_submissions SET graded = 1, graded_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param("i", $submission_id);
            $stmt->execute();
            
            $conn->commit();
            
            $message = "Grade submitted successfully!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error submitting grade: " . $e->getMessage();
            $message_type = 'error';
            error_log("Grade submission error: " . $e->getMessage());
        }
    }
}

// Get teacher's courses for filter
$courses = [];
try {
    $query = "SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting courses: " . $e->getMessage());
}

// Get pending submissions
try {
    $query = "SELECT s.*, 
                     e.title as exam_title, 
                     e.total_marks,
                     e.type as exam_type,
                     c.title as course_title,
                     c.id as course_id,
                     u.full_name as student_name,
                     u.email as student_email,
                     st.id as student_db_id
              FROM exam_submissions s
              JOIN exams e ON s.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              JOIN students st ON s.student_id = st.id
              JOIN users u ON st.user_id = u.id
              WHERE c.teacher_id = ? AND s.graded = 0
              ORDER BY s.submitted_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting submissions: " . $e->getMessage());
}

// Get graded results
try {
    $query = "SELECT r.*, 
                     e.title as exam_title,
                     e.total_marks,
                     c.title as course_title,
                     u.full_name as student_name
              FROM results r
              JOIN exams e ON r.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              JOIN students s ON r.student_id = s.id
              JOIN users u ON s.user_id = u.id
              WHERE c.teacher_id = ?
              ORDER BY r.submitted_at DESC
              LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $graded_results[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting results: " . $e->getMessage());
}

// Get grade statistics
try {
    // Average grade per course
    $query = "SELECT c.title, 
                     AVG(r.percentage) as avg_grade,
                     COUNT(r.id) as total_grades,
                     SUM(CASE WHEN r.status = 'pass' THEN 1 ELSE 0 END) as passed,
                     SUM(CASE WHEN r.status = 'fail' THEN 1 ELSE 0 END) as failed
              FROM results r
              JOIN exams e ON r.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?
              GROUP BY c.id
              ORDER BY avg_grade DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting stats: " . $e->getMessage());
}

// Calculate grade function
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    return 'F';
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading System - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: #667eea;
            font-size: 2em;
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

        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* Stats Cards */
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h3 {
            font-size: 1.3em;
            color: #333;
        }

        .filter-select {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            min-width: 200px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Submission Card */
        .submission-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .submission-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .submission-header h4 {
            font-size: 1.2em;
            color: #333;
        }

        .exam-type {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .exam-type.mcq { background: #4cc9f0; color: white; }
        .exam-type.essay { background: #f72585; color: white; }

        .submission-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }

        .detail-item i {
            width: 20px;
            color: #667eea;
        }

        .answer-box {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border-left: 3px solid #667eea;
        }

        .grade-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .grade-form .form-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .btn-submit {
            padding: 10px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-view {
            padding: 6px 15px;
            background: #4facfe;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: #3a8fd9;
        }

        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 0.9em;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .grade-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .grade-badge.A, .grade-badge.A\+ { background: #10b981; color: white; }
        .grade-badge.B { background: #4cc9f0; color: white; }
        .grade-badge.C { background: #f59e0b; color: white; }
        .grade-badge.D { background: #f97316; color: white; }
        .grade-badge.F { background: #f72585; color: white; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-badge.pass { background: #d4edda; color: #155724; }
        .status-badge.fail { background: #f8d7da; color: #721c24; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4em;
            color: #ccc;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
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
            
            .main-content {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .submission-details {
                grid-template-columns: 1fr;
            }
            
            .grade-form .form-row {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .btn-submit {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Teacher'); ?></h3>
                <p>Teacher</p>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="courses.php" class="menu-item">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
                <a href="exams.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Exams</span>
                </a>
                <a href="students.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="grade.php" class="menu-item active">
                    <i class="fas fa-star"></i>
                    <span>Grade</span>
                </a>
                <a href="../auth/logout.php" class="menu-item" style="margin-top: 50px;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <h1>Grading System</h1>
                    <p>Manage and grade student submissions</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($submissions); ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Teacher'); ?>&background=f72585&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Teacher'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pending Submissions</h3>
                        <div class="stat-number"><?php echo count($submissions); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Graded Submissions</h3>
                        <div class="stat-number"><?php echo count($graded_results); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Average Grade</h3>
                        <div class="stat-number">
                            <?php 
                            $avg = 0;
                            if (!empty($stats)) {
                                $total = 0;
                                $count = 0;
                                foreach($stats as $s) {
                                    $total += $s['avg_grade'];
                                    $count++;
                                }
                                $avg = $count > 0 ? round($total / $count, 1) : 0;
                            }
                            echo $avg . '%';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Submissions Section -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-clock"></i> Pending Submissions (<?php echo count($submissions); ?>)</h3>
                    <select class="filter-select" id="courseFilter" onchange="filterSubmissions()">
                        <option value="all">All Courses</option>
                        <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (empty($submissions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Pending Submissions</h3>
                        <p>All submissions have been graded. Great job!</p>
                    </div>
                <?php else: ?>
                    <div id="submissionsContainer">
                        <?php foreach($submissions as $sub): ?>
                        <div class="submission-card" data-course="<?php echo $sub['course_id']; ?>">
                            <div class="submission-header">
                                <div>
                                    <h4><?php echo htmlspecialchars($sub['exam_title']); ?></h4>
                                    <span class="exam-type <?php echo $sub['exam_type']; ?>">
                                        <?php echo strtoupper($sub['exam_type']); ?>
                                    </span>
                                </div>
                                <span class="badge" style="position: static; background: #f59e0b;">
                                    Submitted <?php echo timeAgo($sub['submitted_at']); ?>
                                </span>
                            </div>
                            
                            <div class="submission-details">
                                <div class="detail-item">
                                    <i class="fas fa-user"></i>
                                    <span><strong>Student:</strong> <?php echo htmlspecialchars($sub['student_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-book"></i>
                                    <span><strong>Course:</strong> <?php echo htmlspecialchars($sub['course_title']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-file-alt"></i>
                                    <span><strong>Total Marks:</strong> <?php echo $sub['total_marks']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($sub['student_email']); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($sub['submission_text'])): ?>
                            <div class="answer-box">
                                <strong><i class="fas fa-pencil-alt"></i> Student's Answer:</strong>
                                <p style="margin-top: 10px;"><?php echo nl2br(htmlspecialchars($sub['submission_text'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="grade-form" onsubmit="return validateGrade(this)">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                <input type="hidden" name="student_id" value="<?php echo $sub['student_db_id']; ?>">
                                <input type="hidden" name="exam_id" value="<?php echo $sub['exam_id']; ?>">
                                <input type="hidden" name="total_marks" value="<?php echo $sub['total_marks']; ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Marks Obtained (out of <?php echo $sub['total_marks']; ?>)</label>
                                        <input type="number" name="marks" class="form-control" 
                                               min="0" max="<?php echo $sub['total_marks']; ?>" 
                                               step="0.5" required 
                                               placeholder="Enter marks">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Feedback (Optional)</label>
                                        <input type="text" name="feedback" class="form-control" 
                                               placeholder="Add feedback for student">
                                    </div>
                                    
                                    <button type="submit" name="submit_grade" class="btn-submit">
                                        <i class="fas fa-check"></i> Submit Grade
                                    </button>
                                </div>
                                
                                <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                    <i class="fas fa-info-circle"></i>
                                    Grade will be calculated as: 
                                    <span class="grade-preview">0% (F)</span>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Graded Results -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-history"></i> Recent Graded Results</h3>
                </div>
                
                <?php if (empty($graded_results)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h3>No Graded Results Yet</h3>
                        <p>Start grading submissions to see results here.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>Course</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($graded_results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                <td><?php echo $result['marks_obtained']; ?>/<?php echo $result['total_marks']; ?></td>
                                <td><?php echo round($result['percentage'], 1); ?>%</td>
                                <td>
                                    <span class="grade-badge <?php echo $result['grade']; ?>">
                                        <?php echo $result['grade']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $result['status']; ?>">
                                        <?php echo strtoupper($result['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($result['submitted_at'])); ?></td>
                                <td>
                                    <button class="btn-view" onclick="viewResult(<?php echo $result['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Grade Statistics -->
            <?php if (!empty($stats)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-chart-pie"></i> Course Statistics</h3>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Average Grade</th>
                            <th>Total Grades</th>
                            <th>Passed</th>
                            <th>Failed</th>
                            <th>Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($stats as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['title']); ?></td>
                            <td><?php echo round($stat['avg_grade'], 1); ?>%</td>
                            <td><?php echo $stat['total_grades']; ?></td>
                            <td style="color: #10b981;"><?php echo $stat['passed']; ?></td>
                            <td style="color: #f72585;"><?php echo $stat['failed']; ?></td>
                            <td>
                                <?php 
                                $pass_rate = $stat['total_grades'] > 0 ? 
                                    round(($stat['passed'] / $stat['total_grades']) * 100, 1) : 0;
                                echo $pass_rate . '%';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filter submissions by course
        function filterSubmissions() {
            const filter = document.getElementById('courseFilter').value;
            const submissions = document.querySelectorAll('.submission-card');
            
            submissions.forEach(sub => {
                if (filter === 'all' || sub.dataset.course === filter) {
                    sub.style.display = 'block';
                } else {
                    sub.style.display = 'none';
                }
            });
        }
        
        // Preview grade calculation
        document.querySelectorAll('input[name="marks"]').forEach(input => {
            input.addEventListener('input', function() {
                const form = this.closest('form');
                const marks = parseFloat(this.value) || 0;
                const total = parseFloat(form.querySelector('input[name="total_marks"]').value);
                const preview = form.querySelector('.grade-preview');
                
                if (total > 0) {
                    const percentage = (marks / total) * 100;
                    let grade = 'F';
                    if (percentage >= 90) grade = 'A+';
                    else if (percentage >= 80) grade = 'A';
                    else if (percentage >= 70) grade = 'B';
                    else if (percentage >= 60) grade = 'C';
                    else if (percentage >= 50) grade = 'D';
                    
                    preview.textContent = percentage.toFixed(1) + '% (' + grade + ')';
                    preview.style.color = percentage >= 50 ? '#10b981' : '#f72585';
                }
            });
        });
        
        // Validate grade form
        function validateGrade(form) {
            const marks = parseFloat(form.querySelector('input[name="marks"]').value);
            const total = parseFloat(form.querySelector('input[name="total_marks"]').value);
            
            if (isNaN(marks) || marks < 0 || marks > total) {
                alert('Please enter valid marks between 0 and ' + total);
                return false;
            }
            
            return confirm('Submit this grade? This action cannot be undone.');
        }
        
        // View result details
        function viewResult(id) {
            // You can implement a modal or redirect to result details
            alert('View result details for ID: ' + id);
        }
        
        // Auto-refresh pending count
        setInterval(function() {
            location.reload();
        }, 60000); // Refresh every minute
    </script>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>