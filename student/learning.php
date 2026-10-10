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
$course_id = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$message = '';

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

// Verify enrollment
if ($student_db_id > 0 && $course_id > 0) {
    try {
        $check = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
        $check->bind_param("ii", $student_db_id, $course_id);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            header("Location: courses.php");
            exit();
        }
    } catch (Exception $e) {
        header("Location: courses.php");
        exit();
    }
} else {
    header("Location: courses.php");
    exit();
}

// Mark lesson as viewed
if (isset($_GET['view']) && isset($_GET['lesson'])) {
    $lesson_id = (int)$_GET['lesson'];
    
    try {
        // Check if already viewed
        $check = $conn->prepare("SELECT id FROM lesson_views WHERE student_id = ? AND lesson_id = ?");
        $check->bind_param("ii", $student_db_id, $lesson_id);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            // Insert view
            $insert = $conn->prepare("INSERT INTO lesson_views (student_id, lesson_id) VALUES (?, ?)");
            $insert->bind_param("ii", $student_db_id, $lesson_id);
            $insert->execute();
            
            // Update progress
            $total = $conn->query("SELECT COUNT(*) as total FROM lessons WHERE course_id = $course_id")->fetch_assoc()['total'];
            $viewed = $conn->query("
                SELECT COUNT(*) as total 
                FROM lesson_views 
                WHERE student_id = $student_db_id 
                AND lesson_id IN (SELECT id FROM lessons WHERE course_id = $course_id)
            ")->fetch_assoc()['total'];
            
            $progress = ($viewed / $total) * 100;
            
            $update = $conn->prepare("UPDATE enrollments SET progress = ? WHERE student_id = ? AND course_id = ?");
            $update->bind_param("dii", $progress, $student_db_id, $course_id);
            $update->execute();
        }
    } catch (Exception $e) {
        error_log("Error marking lesson: " . $e->getMessage());
    }
}

// Get course info
$course = [];
try {
    $query = "SELECT c.*, u.full_name as teacher_name 
              FROM courses c
              JOIN teachers t ON c.teacher_id = t.user_id
              JOIN users u ON t.user_id = u.id
              WHERE c.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Error getting course: " . $e->getMessage());
}

// Get lessons
$lessons = [];
try {
    $query = "SELECT l.*, 
                     (SELECT COUNT(*) FROM lesson_views WHERE student_id = ? AND lesson_id = l.id) as viewed
              FROM lessons l
              WHERE l.course_id = ?
              ORDER BY l.order_index, l.created_at ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_db_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $lessons[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting lessons: " . $e->getMessage());
}

// Get current progress
$progress = 0;
try {
    $query = "SELECT progress FROM enrollments WHERE student_id = ? AND course_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_db_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc()['progress'] ?? 0;
} catch (Exception $e) {
    error_log("Error getting progress: " . $e->getMessage());
}

// Get current lesson
$current_lesson = null;
if (isset($_GET['lesson'])) {
    $lesson_id = (int)$_GET['lesson'];
    foreach($lessons as $lesson) {
        if ($lesson['id'] == $lesson_id) {
            $current_lesson = $lesson;
            break;
        }
    }
}

// If no current lesson, get first lesson
if (!$current_lesson && !empty($lessons)) {
    $current_lesson = $lessons[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning - <?php echo htmlspecialchars($course['title'] ?? 'Course'); ?></title>
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
                <a href="learning.php" class="menu-item active">
                    <i class="fas fa-play-circle"></i>
                    <span>Learning</span>
                </a>
                <a href="exams.php" class="menu-item">
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
                    <h1><?php echo htmlspecialchars($course['title'] ?? 'Course'); ?></h1>
                    <p>Course Progress: <?php echo round($progress); ?>% Complete</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($lessons); ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4361ee&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div style="background: white; border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span><i class="fas fa-tasks"></i> Course Progress</span>
                    <span><strong><?php echo round($progress); ?>%</strong> Complete</span>
                </div>
                <div class="progress-bar" style="height: 10px;">
                    <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                </div>
            </div>
            
            <?php if(empty($lessons)): ?>
                <div class="table-container" style="text-align: center; padding: 60px;">
                    <i class="fas fa-video" style="font-size: 4em; color: #ccc; margin-bottom: 20px;"></i>
                    <h3>No Lessons Available</h3>
                    <p style="color: var(--gray);">The instructor hasn't added any lessons yet.</p>
                    <a href="courses.php" class="btn-enroll" style="margin-top: 20px;">Back to Courses</a>
                </div>
            <?php else: ?>
                <!-- Lesson Content -->
                <?php if($current_lesson): ?>
                <div class="table-container" style="margin-bottom: 25px;">
                    <h2><?php echo htmlspecialchars($current_lesson['title']); ?></h2>
                    
                    <?php if($current_lesson['description']): ?>
                        <p style="color: var(--gray); line-height: 1.8; margin: 20px 0;">
                            <?php echo nl2br(htmlspecialchars($current_lesson['description'])); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php 
                    $file_ext = strtolower(pathinfo($current_lesson['file_path'], PATHINFO_EXTENSION));
                    $video_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
                    
                    if (in_array($file_ext, $video_extensions)): 
                    ?>
                    <div style="margin: 20px 0;">
                        <video controls style="width: 100%; max-height: 500px; border-radius: 10px;" onplay="markAsViewed(<?php echo $current_lesson['id']; ?>)">
                            <source src="<?php echo $current_lesson['file_path']; ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Download Button -->
                    <div style="background: var(--light); padding: 20px; border-radius: 10px; margin: 20px 0;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 50px; height: 50px; background: var(--gradient-3); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-file"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong>Lesson Materials</strong>
                                <p style="color: var(--gray); font-size: 0.9em;"><?php echo strtoupper($file_ext); ?> File</p>
                            </div>
                            <a href="<?php echo $current_lesson['file_path']; ?>" download class="btn-enroll" style="background: var(--info);">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                        <?php
                        $current_index = array_search($current_lesson, $lessons);
                        $prev_lesson = $lessons[$current_index - 1] ?? null;
                        $next_lesson = $lessons[$current_index + 1] ?? null;
                        ?>
                        
                        <?php if($prev_lesson): ?>
                            <a href="?course=<?php echo $course_id; ?>&lesson=<?php echo $prev_lesson['id']; ?>" class="btn-enroll" style="background: var(--gray);">
                                <i class="fas fa-arrow-left"></i> Previous Lesson
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <?php if($next_lesson): ?>
                            <a href="?course=<?php echo $course_id; ?>&lesson=<?php echo $next_lesson['id']; ?>" class="btn-enroll" onclick="markAsViewed(<?php echo $current_lesson['id']; ?>)">
                                Next Lesson <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <?php if($progress >= 100): ?>
                                <a href="certificate.php?generate&course=<?php echo $course_id; ?>" class="btn-enroll" style="background: var(--success);">
                                    <i class="fas fa-certificate"></i> Get Certificate
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Lessons List -->
                <div class="table-container">
                    <h3>Course Lessons</h3>
                    <div style="margin-top: 20px;">
                        <?php foreach($lessons as $index => $lesson): ?>
                        <a href="?course=<?php echo $course_id; ?>&lesson=<?php echo $lesson['id']; ?>" style="text-decoration: none; color: inherit;">
                            <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: <?php echo ($current_lesson && $current_lesson['id'] == $lesson['id']) ? 'var(--primary)' : 'var(--light)'; ?>; border-radius: 10px; margin-bottom: 10px; transition: all 0.3s ease;">
                                <div style="width: 40px; height: 40px; background: <?php echo ($current_lesson && $current_lesson['id'] == $lesson['id']) ? 'white' : 'var(--gradient-3)'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: <?php echo ($current_lesson && $current_lesson['id'] == $lesson['id']) ? 'var(--primary)' : 'white'; ?>;">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="color: <?php echo ($current_lesson && $current_lesson['id'] == $lesson['id']) ? 'white' : 'inherit'; ?>;">
                                        Lesson <?php echo $index + 1; ?>: <?php echo htmlspecialchars($lesson['title']); ?>
                                    </strong>
                                </div>
                                <?php if($lesson['viewed'] > 0): ?>
                                    <i class="fas fa-check-circle" style="color: <?php echo ($current_lesson && $current_lesson['id'] == $lesson['id']) ? 'white' : '#4cc9f0'; ?>;"></i>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function markAsViewed(lessonId) {
            // This will be called when video starts playing
            window.location.href = '?course=<?php echo $course_id; ?>&lesson=' + lessonId + '&view=1';
        }
    </script>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>