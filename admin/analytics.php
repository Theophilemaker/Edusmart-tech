<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Gather statistics
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?? 0;
$total_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'] ?? 0;
$total_teachers = $conn->query("SELECT COUNT(*) as total FROM teachers")->fetch_assoc()['total'] ?? 0;
$total_courses = $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status='completed'")->fetch_assoc()['total'] ?? 0;

// Monthly enrollments for chart
$enrollments = $conn->query("
    SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as month, COUNT(*) as count
    FROM enrollments
    GROUP BY month
    ORDER BY month
");
$months = [];
$counts = [];
while ($row = $enrollments->fetch_assoc()) {
    $months[] = $row['month'];
    $counts[] = $row['count'];
}

// Course popularity
$popular_courses = $conn->query("
    SELECT c.title, COUNT(e.id) as enrollments
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    GROUP BY c.id
    ORDER BY enrollments DESC
    LIMIT 5
");
$course_names = [];
$course_enrolls = [];
while ($row = $popular_courses->fetch_assoc()) {
    $course_names[] = $row['title'];
    $course_enrolls[] = $row['enrollments'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Make sure these paths are correct -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Inline styles as fallback */
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
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
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
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: white;
            text-decoration: none;
            transition: 0.3s;
        }
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
        }
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 30px;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-nav h1 {
            font-size: 1.8em;
            color: #333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 1rem;
            color: #666;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .chart-container h3 {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        canvas {
            width: 100% !important;
            height: auto !important;
            max-height: 300px;
        }
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            .sidebar-header h3, .sidebar-header p, .sidebar-menu span {
                display: none;
            }
            .main-content {
                margin-left: 80px;
            }
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar (using the same structure as your sidebar.php) -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h3>
                <p>Administrator</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                <a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a>
                <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a>
                <a href="manage-employers.php"><i class="fas fa-building"></i> <span>Employers</span></a>
                <a href="manage-jobs.php"><i class="fas fa-briefcase"></i> <span>All Jobs</span></a>
                <a href="manage-applications.php"><i class="fas fa-file-signature"></i> <span>Applications</span></a>
                <a href="manage-courses.php"><i class="fas fa-book"></i> <span>Courses</span></a>
                <a href="manage-classes.php"><i class="fas fa-door-open"></i> <span>Classes</span></a>
                <a href="manage-exams.php"><i class="fas fa-file-alt"></i> <span>Exams</span></a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </div>

        <div class="main-content">
            <div class="top-nav">
                <h1>Analytics Dashboard</h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><h3>Total Users</h3><div class="stat-number"><?php echo $total_users; ?></div></div>
                <div class="stat-card"><h3>Students</h3><div class="stat-number"><?php echo $total_students; ?></div></div>
                <div class="stat-card"><h3>Teachers</h3><div class="stat-number"><?php echo $total_teachers; ?></div></div>
                <div class="stat-card"><h3>Courses</h3><div class="stat-number"><?php echo $total_courses; ?></div></div>
                <div class="stat-card"><h3>Revenue</h3><div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div></div>
            </div>

            <?php if (!empty($months) && !empty($counts)): ?>
            <div class="charts-row">
                <div class="chart-container">
                    <h3>Monthly Enrollments</h3>
                    <canvas id="enrollmentChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Top Courses</h3>
                    <canvas id="courseChart"></canvas>
                </div>
            </div>
            <?php else: ?>
                <p style="text-align:center; padding:40px; background:white; border-radius:10px;">Not enough data to display charts yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if (!empty($months) && !empty($counts)): ?>
        new Chart(document.getElementById('enrollmentChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?php echo json_encode($counts); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102,126,234,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        new Chart(document.getElementById('courseChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($course_names); ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?php echo json_encode($course_enrolls); ?>,
                    backgroundColor: '#f72585'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        <?php endif; ?>
    </script>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>