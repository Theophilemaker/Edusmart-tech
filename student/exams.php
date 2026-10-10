<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student database ID
try {
    $query = "SELECT id FROM students WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_db_id = $result->fetch_assoc()['id'] ?? 0;
} catch (Exception $e) {
    $student_db_id = 0;
}

// Get upcoming exams
$upcoming_exams = [];
if ($student_db_id > 0) {
    try {
        $query = "SELECT e.*, c.title as course_title
                  FROM exams e
                  JOIN courses c ON e.course_id = c.id
                  JOIN enrollments en ON c.id = en.course_id
                  WHERE en.student_id = ? AND e.start_date > NOW()
                  ORDER BY e.start_date ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $upcoming_exams[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting upcoming exams: " . $e->getMessage());
    }
}

// Get available exams (active now)
$available_exams = [];
if ($student_db_id > 0) {
    try {
        $query = "SELECT e.*, c.title as course_title
                  FROM exams e
                  JOIN courses c ON e.course_id = c.id
                  JOIN enrollments en ON c.id = en.course_id
                  WHERE en.student_id = ? 
                    AND e.start_date <= NOW() 
                    AND e.end_date >= NOW()
                  ORDER BY e.start_date ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $available_exams[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting available exams: " . $e->getMessage());
    }
}

// Get results
$results = [];
if ($student_db_id > 0) {
    try {
        $query = "SELECT r.*, e.title as exam_title, e.total_marks, c.title as course_title
                  FROM results r
                  JOIN exams e ON r.exam_id = e.id
                  JOIN courses c ON e.course_id = c.id
                  WHERE r.student_id = ?
                  ORDER BY r.submitted_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting results: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exams - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/protect-design.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></h3>
                <p>Student</p>
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
                <a href="learning.php" class="menu-item">
                    <i class="fas fa-play-circle"></i>
                    <span>Learning</span>
                </a>
                <a href="exams.php" class="menu-item active">
                    <i class="fas fa-file-alt"></i>
                    <span>Exams</span>
                </a>
                <a href="certificate.php" class="menu-item">
                    <i class="fas fa-certificate"></i>
                    <span>Certificate</span>
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
                    <h1>My Exams</h1>
                    <p>View and take exams</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($available_exams) + count($upcoming_exams); ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4361ee&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Available Exams (Now) -->
            <?php if(!empty($available_exams)): ?>
            <div class="table-container" style="margin-bottom: 30px;">
                <h3><i class="fas fa-play-circle" style="color: var(--success);"></i> Available Now</h3>
                
                <?php foreach($available_exams as $exam): ?>
                <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h4><?php echo htmlspecialchars($exam['title']); ?></h4>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($exam['course_title']); ?></p>
                            <p><strong>Duration:</strong> <?php echo $exam['duration']; ?> minutes</p>
                            <p><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
                            <p><small>Deadline: <?php echo date('M d, Y H:i', strtotime($exam['end_date'])); ?></small></p>
                        </div>
                        <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn-login" style="width: auto; padding: 12px 30px;">
                            <i class="fas fa-play"></i> Start Exam
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Exams -->
            <div class="table-container" style="margin-bottom: 30px;">
                <h3><i class="fas fa-calendar-alt"></i> Upcoming Exams</h3>
                
                <?php if(empty($upcoming_exams)): ?>
                    <p style="text-align: center; padding: 40px; color: var(--gray);">No upcoming exams</p>
                <?php else: ?>
                    <?php foreach($upcoming_exams as $exam): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: var(--light); border-radius: 10px; margin-bottom: 10px;">
                        <div style="width: 50px; height: 50px; background: var(--gradient-2); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4><?php echo htmlspecialchars($exam['title']); ?></h4>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($exam['course_title']); ?></p>
                            <p><small>Starts: <?php echo date('M d, Y H:i', strtotime($exam['start_date'])); ?></small></p>
                        </div>
                        <div>
                            <span class="status-badge pending">Upcoming</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Results -->
            <div class="table-container">
                <h3><i class="fas fa-chart-line"></i> My Results</h3>
                
                <?php if(empty($results)): ?>
                    <p style="text-align: center; padding: 40px; color: var(--gray);">No results yet</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Course</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                <td><?php echo $result['marks_obtained']; ?>/<?php echo $result['total_marks']; ?></td>
                                <td><?php echo round($result['percentage'], 2); ?>%</td>
                                <td><strong style="color: <?php 
                                    $grade = $result['grade'] ?? 'F';
                                    if($grade == 'A+' || $grade == 'A') echo '#10b981';
                                    elseif($grade == 'B' || $grade == 'C') echo '#4361ee';
                                    elseif($grade == 'D') echo '#f8961e';
                                    else echo '#f72585';
                                ?>;"><?php echo $grade; ?></strong></td>
                                <td>
                                    <span class="status-badge <?php echo ($result['status'] ?? 'pass') == 'pass' ? 'active' : 'pending'; ?>">
                                        <?php echo strtoupper($result['status'] ?? 'PASS'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($result['submitted_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>