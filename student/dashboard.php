<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student database ID (from students table)
$student_db_id = 0;
try {
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_db_id = $result->fetch_assoc()['id'] ?? 0;
} catch (Exception $e) {
    error_log("Error getting student ID: " . $e->getMessage());
}

// Get student info (name, email, etc.)
$student = [];
try {
    $query = "SELECT u.*, s.admission_no, s.class 
              FROM users u 
              LEFT JOIN students s ON u.id = s.user_id 
              WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    if (!$student) {
        $student = [
            'full_name' => $_SESSION['full_name'] ?? 'Student',
            'email' => $_SESSION['email'] ?? '',
            'admission_no' => 'N/A'
        ];
    }
} catch (Exception $e) {
    error_log("Student data error: " . $e->getMessage());
    $student = [
        'full_name' => $_SESSION['full_name'] ?? 'Student',
        'email' => $_SESSION['email'] ?? '',
        'admission_no' => 'N/A'
    ];
}

// --- Statistics with error handling ---

// Total enrolled courses
$enrolled_courses = 0;
try {
    $query = "SELECT COUNT(*) as total FROM enrollments WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrolled_courses = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    error_log("Enrolled courses error: " . $e->getMessage());
}

// Completed courses – first check if 'progress' column exists
$completed_courses = 0;
try {
    // Check if 'progress' column exists in enrollments table
    $check = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'progress'");
    if ($check && $check->num_rows > 0) {
        // Column exists, use it
        $query = "SELECT COUNT(*) as total FROM enrollments WHERE student_id = ? AND progress >= 100";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $completed_courses = $result->fetch_assoc()['total'] ?? 0;
    } else {
        // Column doesn't exist – we can't determine completion. Default to 0.
        // Optionally, we could compute based on lessons viewed, but for now just 0.
        $completed_courses = 0;
    }
} catch (Exception $e) {
    error_log("Completed courses error: " . $e->getMessage());
}

// Certificates count
$certificates = 0;
try {
    $query = "SELECT COUNT(*) as total FROM certificates WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $certificates = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    error_log("Certificates error: " . $e->getMessage());
}

// Upcoming exams (optional)
$upcoming_exams = [];
try {
    $query = "SELECT e.title, c.title as course_title, e.start_date 
              FROM exams e
              JOIN courses c ON e.course_id = c.id
              JOIN enrollments en ON c.id = en.course_id
              WHERE en.student_id = ? AND e.start_date > NOW()
              ORDER BY e.start_date ASC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $upcoming_exams[] = $row;
    }
} catch (Exception $e) {
    error_log("Exams error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="student">
    <style>
        /* Student-specific styles */
        .sidebar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .profile-img {
            border-color: #4cc9f0;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-icon.pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
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
        }
        .page-title p {
            color: #666;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4cc9f0;
        }
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
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
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
        .stat-info h3 {
            color: #666;
            font-size: 0.9em;
        }
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
        }
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 80px;
            }
        }
    </style>
</head>
<body class="student-dashboard">
    <div class="dashboard">
        <!-- Include student sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Student Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($student['full_name']); ?>!</p>
                </div>
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($upcoming_exams); ?></span>
                    </div>
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['full_name']); ?>&background=4cc9f0&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="location.href='courses.php'">
                    <div class="stat-icon blue">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Enrolled Courses</h3>
                        <div class="stat-number"><?php echo $enrolled_courses; ?></div>
                    </div>
                </div>
                <div class="stat-card" onclick="location.href='learning.php'">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Completed Courses</h3>
                        <div class="stat-number"><?php echo $completed_courses; ?></div>
                    </div>
                </div>
                <div class="stat-card" onclick="location.href='certificate.php'">
                    <div class="stat-icon orange">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Certificates</h3>
                        <div class="stat-number"><?php echo $certificates; ?></div>
                    </div>
                </div>
                <div class="stat-card" onclick="location.href='exams.php'">
                    <div class="stat-icon pink">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Upcoming Exams</h3>
                        <div class="stat-number"><?php echo count($upcoming_exams); ?></div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Exams Section -->
            <?php if (!empty($upcoming_exams)): ?>
            <div style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-calendar-alt"></i> Upcoming Exams</h3>
                <?php foreach ($upcoming_exams as $exam): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px;">
                    <div>
                        <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                        <span style="color: #666;"> (<?php echo htmlspecialchars($exam['course_title']); ?>)</span>
                    </div>
                    <span style="color: #4cc9f0;"><?php echo date('M d, Y', strtotime($exam['start_date'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- AI Assistant Widget (optional) -->
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>