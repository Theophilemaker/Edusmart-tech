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

// Get teacher info
$teacher_name = $_SESSION['full_name'] ?? 'Teacher';

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

// Get all students from teacher's courses
$students = [];
try {
    $query = "SELECT DISTINCT 
                     u.id as user_id,
                     u.full_name,
                     u.email,
                     u.phone,
                     u.profile_pic,
                     u.created_at,
                     s.id as student_id,
                     s.admission_no,
                     s.class,
                     s.parent_name,
                     s.parent_phone,
                     GROUP_CONCAT(DISTINCT c.title SEPARATOR ', ') as enrolled_courses,
                     COUNT(DISTINCT e.course_id) as course_count,
                     AVG(e.progress) as avg_progress
              FROM users u
              JOIN students s ON u.id = s.user_id
              JOIN enrollments e ON s.id = e.student_id
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ?
              GROUP BY u.id
              ORDER BY u.full_name ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting students: " . $e->getMessage());
}

// Get attendance summary
$attendance_summary = [];
try {
    $query = "SELECT 
                     DATE_FORMAT(a.date, '%Y-%m') as month,
                     COUNT(DISTINCT a.student_id) as total_students,
                     SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                     SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                     SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late
              FROM attendance a
              JOIN students s ON a.student_id = s.id
              JOIN enrollments e ON s.id = e.student_id
              JOIN courses c ON e.course_id = c.id
              WHERE c.teacher_id = ? AND a.date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
              GROUP BY month
              ORDER BY month DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $attendance_summary[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting attendance summary: " . $e->getMessage());
}

// Handle student search
$search = $_GET['search'] ?? '';
if ($search) {
    $filtered_students = [];
    foreach($students as $student) {
        if (stripos($student['full_name'], $search) !== false || 
            stripos($student['email'], $search) !== false ||
            stripos($student['admission_no'], $search) !== false) {
            $filtered_students[] = $student;
        }
    }
    $students = $filtered_students;
}

// Handle course filter
$course_filter = $_GET['course'] ?? 'all';
if ($course_filter != 'all') {
    $filtered_students = [];
    foreach($students as $student) {
        if (stripos($student['enrolled_courses'], $course_filter) !== false) {
            $filtered_students[] = $student;
        }
    }
    $students = $filtered_students;
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - <?php echo SITE_NAME; ?></title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            margin-bottom: 10px;
            color: white;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-icon.pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

        .stat-info h3 {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
        }

        /* Search and Filter Bar */
        .search-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 2;
            min-width: 250px;
            position: relative;
        }

        .search-input i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #999;
        }

        .search-input input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .search-input input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .filter-select {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Students Grid */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .student-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .student-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .student-info h3 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 5px;
        }

        .student-info p {
            color: #666;
            font-size: 0.9em;
        }

        .student-details {
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.95em;
        }

        .detail-row i {
            width: 20px;
            color: #667eea;
        }

        .courses-enrolled {
            margin: 10px 0;
            font-size: 0.9em;
        }

        .course-tag {
            display: inline-block;
            padding: 3px 10px;
            background: #e7f3ff;
            color: #667eea;
            border-radius: 15px;
            font-size: 0.8em;
            margin: 2px;
        }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .student-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-info {
            background: #4facfe;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Attendance Summary */
        .attendance-summary {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .summary-header h3 {
            font-size: 1.3em;
            color: #333;
        }

        .attendance-chart {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .chart-item {
            flex: 1;
            min-width: 200px;
        }

        .chart-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            color: #666;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4em;
            color: #ccc;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 20px;
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
            .students-grid {
                grid-template-columns: 1fr;
            }
            
            .search-bar {
                flex-direction: column;
            }
            
            .search-input,
            .filter-select {
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
                <h3><?php echo htmlspecialchars($teacher_name); ?></h3>
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
                <a href="students.php" class="menu-item active">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="exams.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Exams</span>
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
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <h1>My Students</h1>
                    <p>Manage and view all your enrolled students</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($students); ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($teacher_name); ?>&background=f72585&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($teacher_name); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo count($students); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Active Courses</h3>
                        <div class="stat-number"><?php echo count($courses); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Avg. Progress</h3>
                        <div class="stat-number">
                            <?php 
                            $avg_progress = 0;
                            if (!empty($students)) {
                                $total = 0;
                                foreach($students as $s) {
                                    $total += $s['avg_progress'] ?? 0;
                                }
                                $avg_progress = round($total / count($students), 1);
                            }
                            echo $avg_progress . '%';
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pink">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Attendance Rate</h3>
                        <div class="stat-number">87%</div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap;">
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name, email, or admission number..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <select name="course" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Courses</option>
                        <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['title']; ?>" 
                                <?php echo $course_filter == $course['title'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn-action btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    
                    <?php if ($search || $course_filter != 'all'): ?>
                        <a href="students.php" class="btn-action btn-warning">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Students Grid -->
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Students Found</h3>
                    <p>You don't have any students enrolled in your courses yet.</p>
                    <a href="courses.php" class="btn-action btn-primary">
                        <i class="fas fa-book"></i> View My Courses
                    </a>
                </div>
            <?php else: ?>
                <div class="students-grid">
                    <?php foreach($students as $student): ?>
                    <div class="student-card">
                        <div class="student-header">
                            <img src="<?php echo $student['profile_pic'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&size=60'; ?>" 
                                 alt="<?php echo htmlspecialchars($student['full_name']); ?>" 
                                 class="student-avatar">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                                <p><?php echo htmlspecialchars($student['admission_no']); ?></p>
                            </div>
                        </div>
                        
                        <div class="student-details">
                            <div class="detail-row">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($student['email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($student['phone'] ?? 'No phone'); ?></span>
                            </div>
                            <?php if (!empty($student['parent_name'])): ?>
                            <div class="detail-row">
                                <i class="fas fa-user-friends"></i>
                                <span>Parent: <?php echo htmlspecialchars($student['parent_name']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <i class="fas fa-calendar"></i>
                                <span>Joined: <?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="courses-enrolled">
                            <strong>Enrolled in:</strong><br>
                            <?php 
                            $courses_list = explode(', ', $student['enrolled_courses']);
                            foreach($courses_list as $course_name): 
                            ?>
                                <span class="course-tag"><?php echo htmlspecialchars($course_name); ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo round($student['avg_progress'] ?? 0); ?>%;"></div>
                        </div>
                        <div style="text-align: right; font-size: 0.9em; color: #666;">
                            Progress: <?php echo round($student['avg_progress'] ?? 0); ?>%
                        </div>
                        
                        <div class="student-footer">
                            <a href="view-student.php?id=<?php echo $student['student_id']; ?>" class="btn-action btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="attendance.php?student=<?php echo $student['student_id']; ?>" class="btn-action btn-success">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </a>
                            <a href="mailto:<?php echo $student['email']; ?>" class="btn-action btn-primary">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Attendance Summary -->
            <?php if (!empty($attendance_summary)): ?>
            <div class="attendance-summary">
                <div class="summary-header">
                    <h3><i class="fas fa-chart-bar"></i> Attendance Overview (Last 3 Months)</h3>
                    <a href="attendance.php" class="btn-action btn-primary">
                        <i class="fas fa-calendar-check"></i> Full Attendance Report
                    </a>
                </div>
                
                <div class="attendance-chart">
                    <?php foreach($attendance_summary as $summary): ?>
                    <div class="chart-item">
                        <div class="chart-label">
                            <span><?php echo date('F Y', strtotime($summary['month'] . '-01')); ?></span>
                            <span><?php echo $summary['total_students']; ?> students</span>
                        </div>
                        <div style="margin-top: 10px;">
                            <div style="display: flex; height: 30px; border-radius: 5px; overflow: hidden;">
                                <?php 
                                $total = $summary['present'] + $summary['absent'] + $summary['late'];
                                if ($total > 0):
                                    $present_pct = ($summary['present'] / $total) * 100;
                                    $absent_pct = ($summary['absent'] / $total) * 100;
                                    $late_pct = ($summary['late'] / $total) * 100;
                                ?>
                                <div style="width: <?php echo $present_pct; ?>%; background: #10b981;" title="Present: <?php echo $summary['present']; ?>"></div>
                                <div style="width: <?php echo $late_pct; ?>%; background: #f59e0b;" title="Late: <?php echo $summary['late']; ?>"></div>
                                <div style="width: <?php echo $absent_pct; ?>%; background: #f72585;" title="Absent: <?php echo $summary['absent']; ?>"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.8em;">
                            <span style="color: #10b981;">Present: <?php echo $summary['present']; ?></span>
                            <span style="color: #f59e0b;">Late: <?php echo $summary['late']; ?></span>
                            <span style="color: #f72585;">Absent: <?php echo $summary['absent']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>