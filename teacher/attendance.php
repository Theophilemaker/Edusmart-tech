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

// Get teacher's courses
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

// Get selected course
$selected_course = isset($_GET['course']) ? intval($_GET['course']) : (isset($courses[0]['id']) ? $courses[0]['id'] : 0);
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get students for selected course
$students = [];
if ($selected_course > 0) {
    try {
        $query = "SELECT s.id, u.full_name, u.email, s.admission_no
                  FROM students s
                  JOIN users u ON s.user_id = u.id
                  JOIN enrollments e ON s.id = e.student_id
                  WHERE e.course_id = ?
                  ORDER BY u.full_name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $selected_course);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting students: " . $e->getMessage());
    }
}

// Get existing attendance for selected date and course
$attendance = [];
if ($selected_course > 0) {
    try {
        $query = "SELECT student_id, status FROM attendance 
                  WHERE course_id = ? AND date = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $selected_course, $selected_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $attendance[$row['student_id']] = $row['status'];
        }
    } catch (Exception $e) {
        error_log("Error getting attendance: " . $e->getMessage());
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $course_id = intval($_POST['course_id']);
    $date = $_POST['date'];
    $attendance_data = $_POST['attendance'] ?? [];
    
    if ($course_id > 0 && !empty($date)) {
        $conn->begin_transaction();
        
        try {
            // Delete existing attendance for this date and course
            $delete = "DELETE FROM attendance WHERE course_id = ? AND date = ?";
            $stmt = $conn->prepare($delete);
            $stmt->bind_param("is", $course_id, $date);
            $stmt->execute();
            
            // Insert new attendance records
            $insert = "INSERT INTO attendance (student_id, course_id, date, status, marked_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert);
            
            foreach ($attendance_data as $student_id => $status) {
                $stmt->bind_param("iissi", $student_id, $course_id, $date, $status, $teacher_id);
                $stmt->execute();
            }
            
            $conn->commit();
            $message = "Attendance saved successfully!";
            $message_type = 'success';
            
            // Refresh attendance data
            $attendance = $attendance_data;
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error saving attendance: " . $e->getMessage();
            $message_type = 'error';
            error_log("Attendance save error: " . $e->getMessage());
        }
    }
}

// Get attendance statistics
$stats = [];
try {
    if ($selected_course > 0) {
        $query = "SELECT 
                     status,
                     COUNT(*) as count,
                     DATE_FORMAT(date, '%Y-%m-%d') as day
                  FROM attendance 
                  WHERE course_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY date, status
                  ORDER BY date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $selected_course);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error getting stats: " . $e->getMessage());
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo SITE_NAME; ?></title>
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

        /* Control Panel */
        .control-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .control-group {
            flex: 1;
            min-width: 200px;
        }

        .control-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .control-select,
        .control-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .control-select:focus,
        .control-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .btn-primary {
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Attendance Table */
        .attendance-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .attendance-header h3 {
            font-size: 1.3em;
            color: #333;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .bulk-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .bulk-btn.present { background: #d4edda; color: #155724; }
        .bulk-btn.present:hover { background: #c3e6cb; }
        .bulk-btn.absent { background: #f8d7da; color: #721c24; }
        .bulk-btn.absent:hover { background: #f5c6cb; }
        .bulk-btn.late { background: #fff3cd; color: #856404; }
        .bulk-btn.late:hover { background: #ffe8a1; }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 0.95em;
        }

        .attendance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .attendance-table tr:hover {
            background: #f8f9fa;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .status-options {
            display: flex;
            gap: 10px;
        }

        .status-option {
            padding: 6px 12px;
            border: 2px solid transparent;
            border-radius: 20px;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-option.present {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }

        .status-option.absent {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }

        .status-option.late {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }

        .status-option.selected {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-option input[type="radio"] {
            display: none;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.present { background: #d4edda; color: #155724; }
        .status-badge.absent { background: #f8d7da; color: #721c24; }
        .status-badge.late { background: #fff3cd; color: #856404; }

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

        .stat-icon.green { background: #10b981; }
        .stat-icon.red { background: #f72585; }
        .stat-icon.yellow { background: #f59e0b; }
        .stat-icon.blue { background: #667eea; }

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
            .control-panel {
                flex-direction: column;
            }
            
            .attendance-table {
                font-size: 0.9em;
            }
            
            .status-options {
                flex-direction: column;
                gap: 5px;
            }
            
            .bulk-actions {
                flex-wrap: wrap;
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
                <a href="students.php" class="menu-item">
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
                <a href="attendance.php" class="menu-item active">
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
                    <h1>Attendance Management</h1>
                    <p>Take and manage student attendance</p>
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
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Control Panel -->
            <form method="GET" class="control-panel">
                <div class="control-group">
                    <label for="course">Select Course</label>
                    <select name="course" id="course" class="control-select" onchange="this.form.submit()">
                        <option value="">-- Select a Course --</option>
                        <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" 
                                <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="control-group">
                    <label for="date">Select Date</label>
                    <input type="date" name="date" id="date" class="control-input" 
                           value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Load Students
                </button>
            </form>
            
            <?php if ($selected_course > 0 && !empty($students)): ?>
                <!-- Statistics -->
                <div class="stats-grid">
                    <?php
                    $total = count($students);
                    $present = 0;
                    $absent = 0;
                    $late = 0;
                    
                    foreach($students as $student) {
                        $status = $attendance[$student['id']] ?? '';
                        if ($status == 'present') $present++;
                        elseif ($status == 'absent') $absent++;
                        elseif ($status == 'late') $late++;
                    }
                    ?>
                    
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Students</h3>
                            <div class="stat-number"><?php echo $total; ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Present</h3>
                            <div class="stat-number"><?php echo $present; ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon yellow">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Late</h3>
                            <div class="stat-number"><?php echo $late; ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon red">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Absent</h3>
                            <div class="stat-number"><?php echo $absent; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Form -->
                <form method="POST" class="attendance-container">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="course_id" value="<?php echo $selected_course; ?>">
                    <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                    
                    <div class="attendance-header">
                        <h3>
                            <i class="fas fa-calendar-day"></i> 
                            Attendance for <?php echo date('F d, Y', strtotime($selected_date)); ?>
                        </h3>
                        
                        <div class="bulk-actions">
                            <button type="button" class="bulk-btn present" onclick="setAllStatus('present')">
                                <i class="fas fa-check"></i> All Present
                            </button>
                            <button type="button" class="bulk-btn absent" onclick="setAllStatus('absent')">
                                <i class="fas fa-times"></i> All Absent
                            </button>
                            <button type="button" class="bulk-btn late" onclick="setAllStatus('late')">
                                <i class="fas fa-clock"></i> All Late
                            </button>
                        </div>
                    </div>
                    
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission No.</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                <td>
                                    <div class="status-options">
                                        <label class="status-option present <?php echo ($attendance[$student['id']] ?? '') == 'present' ? 'selected' : ''; ?>">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                   value="present" <?php echo ($attendance[$student['id']] ?? '') == 'present' ? 'checked' : ''; ?>>
                                            Present
                                        </label>
                                        <label class="status-option late <?php echo ($attendance[$student['id']] ?? '') == 'late' ? 'selected' : ''; ?>">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                   value="late" <?php echo ($attendance[$student['id']] ?? '') == 'late' ? 'checked' : ''; ?>>
                                            Late
                                        </label>
                                        <label class="status-option absent <?php echo ($attendance[$student['id']] ?? '') == 'absent' ? 'selected' : ''; ?>">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                   value="absent" <?php echo ($attendance[$student['id']] ?? '') == 'absent' ? 'checked' : ''; ?>>
                                            Absent
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" name="save_attendance" class="btn-primary btn-success">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </form>
            <?php elseif ($selected_course > 0): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Students Found</h3>
                    <p>This course doesn't have any enrolled students yet.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Select a Course</h3>
                    <p>Please select a course and date to take attendance.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function setAllStatus(status) {
            const radios = document.querySelectorAll('input[type="radio"]');
            radios.forEach(radio => {
                if (radio.value === status) {
                    radio.checked = true;
                    // Trigger change event to update UI
                    radio.dispatchEvent(new Event('change'));
                }
            });
            
            // Update selected styling
            document.querySelectorAll('.status-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            document.querySelectorAll('.status-option.' + status).forEach(option => {
                option.classList.add('selected');
            });
        }
        
        // Update selected styling when radio changes
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const parent = this.closest('.status-options');
                parent.querySelectorAll('.status-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.closest('.status-option').classList.add('selected');
            });
        });
    </script>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>