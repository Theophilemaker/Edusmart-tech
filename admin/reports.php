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
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

$data = [];

// Get statistics
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM students");
    $data['total_students'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    $data['total_students'] = 0;
}

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM teachers");
    $data['total_teachers'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    $data['total_teachers'] = 0;
}

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM courses");
    $data['total_courses'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    $data['total_courses'] = 0;
}

try {
    $result = $conn->query("SELECT COUNT(*) as total FROM enrollments");
    $data['total_enrollments'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    $data['total_enrollments'] = 0;
}

try {
    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status='completed'");
    $data['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {
    $data['total_revenue'] = 0;
}

// Get monthly data for charts
$months = [];
$enrollment_counts = [];

try {
    $query = "SELECT DATE_FORMAT(enrollment_date, '%M') as month, 
                     COUNT(*) as count,
                     MONTH(enrollment_date) as month_num
              FROM enrollments 
              WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY YEAR(enrollment_date), MONTH(enrollment_date)
              ORDER BY enrollment_date ASC";
    $result = $conn->query($query);
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $months[] = $row['month'];
            $enrollment_counts[] = $row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Chart error: " . $e->getMessage());
}

// Get course distribution
$course_categories = [];
$course_counts = [];

try {
    $query = "SELECT category, COUNT(*) as count 
              FROM courses 
              GROUP BY category 
              ORDER BY count DESC";
    $result = $conn->query($query);
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $course_categories[] = $row['category'] ?: 'Uncategorized';
            $course_counts[] = $row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Course distribution error: " . $e->getMessage());
}

// Get recent payments
$recent_payments = [];
try {
    $query = "SELECT p.*, u.full_name as student_name 
              FROM payments p
              JOIN students s ON p.student_id = s.id
              JOIN users u ON s.user_id = u.id
              ORDER BY p.payment_date DESC 
              LIMIT 10";
    $result = $conn->query($query);
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $recent_payments[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Payments error: " . $e->getMessage());
}

// Handle report export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $filename = $type . '_report_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($type == 'students') {
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Admission No', 'Class', 'Parent Name', 'Parent Phone', 'Joined']);
        
        $query = "SELECT s.id, u.full_name, u.email, u.phone, s.admission_no, s.class, 
                         s.parent_name, s.parent_phone, u.created_at
                  FROM students s
                  JOIN users u ON s.user_id = u.id
                  ORDER BY u.created_at DESC";
        $result = $conn->query($query);
        
        while($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    } elseif ($type == 'teachers') {
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Employee ID', 'Specialization', 'Qualification', 'Joined']);
        
        $query = "SELECT t.id, u.full_name, u.email, u.phone, t.employee_id, 
                         t.specialization, t.qualification, u.created_at
                  FROM teachers t
                  JOIN users u ON t.user_id = u.id
                  ORDER BY u.created_at DESC";
        $result = $conn->query($query);
        
        while($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    } elseif ($type == 'payments') {
        fputcsv($output, ['ID', 'Student', 'Amount', 'Method', 'Invoice No', 'Status', 'Date']);
        
        $query = "SELECT p.id, u.full_name as student, p.amount, p.payment_method, 
                         p.invoice_no, p.status, p.payment_date
                  FROM payments p
                  JOIN students s ON p.student_id = s.id
                  JOIN users u ON s.user_id = u.id
                  WHERE p.status='completed'
                  ORDER BY p.payment_date DESC";
        $result = $conn->query($query);
        
        while($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    } elseif ($type == 'courses') {
        fputcsv($output, ['ID', 'Course Title', 'Category', 'Level', 'Teacher', 'Lessons', 'Students', 'Created']);
        
        $query = "SELECT c.id, c.title, c.category, c.level, u.full_name as teacher,
                         (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lessons,
                         (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as students,
                         c.created_at
                  FROM courses c
                  JOIN teachers t ON c.teacher_id = t.user_id
                  JOIN users u ON t.user_id = u.id
                  ORDER BY c.created_at DESC";
        $result = $conn->query($query);
        
        while($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            text-align: center;
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
            margin: 0 auto 10px;
            color: white;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .chart-container h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .report-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .report-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .report-card i {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .report-card h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .report-card p {
            color: #666;
            font-size: 0.9em;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 12px 15px;
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 0.9em;
        }

        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.completed { background: #d4edda; color: #155724; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.failed { background: #f8d7da; color: #721c24; }

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
            
            .charts-row {
                grid-template-columns: 1fr;
            }
            
            .report-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .report-cards {
                grid-template-columns: 1fr;
            }
            
            .top-nav {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h3>
                <p>Administrator</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="users.php" class="menu-item"><i class="fas fa-users"></i><span>Users</span></a>
                <a href="reports.php" class="menu-item active"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
                <a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
                <a href="../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Reports & Analytics</h1>
                    <p>View system statistics and generate reports</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($recent_payments); ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-value"><?php echo number_format($data['total_students']); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pink"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-value"><?php echo number_format($data['total_teachers']); ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-book"></i></div>
                    <div class="stat-value"><?php echo number_format($data['total_courses']); ?></div>
                    <div class="stat-label">Total Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-value">$<?php echo number_format($data['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            
            <!-- Charts -->
            <?php if (!empty($months) && !empty($enrollment_counts)): ?>
            <div class="charts-row">
                <div class="chart-container">
                    <h3>Enrollment Trends (Last 6 Months)</h3>
                    <canvas id="enrollmentChart" style="height: 300px;"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Course Distribution by Category</h3>
                    <canvas id="courseChart" style="height: 300px;"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Report Generation Cards -->
            <h3 style="margin-bottom: 15px; color: #333;">Generate Reports</h3>
            <div class="report-cards">
                <a href="?export=students" class="report-card">
                    <i class="fas fa-file-pdf" style="color: #f72585;"></i>
                    <h4>Students Report</h4>
                    <p>Download complete student list with details</p>
                </a>
                
                <a href="?export=teachers" class="report-card">
                    <i class="fas fa-file-excel" style="color: #4cc9f0;"></i>
                    <h4>Teachers Report</h4>
                    <p>Download teacher information and qualifications</p>
                </a>
                
                <a href="?export=courses" class="report-card">
                    <i class="fas fa-file-alt" style="color: #f59e0b;"></i>
                    <h4>Courses Report</h4>
                    <p>Download course catalog with statistics</p>
                </a>
                
                <a href="?export=payments" class="report-card">
                    <i class="fas fa-file-invoice" style="color: #10b981;"></i>
                    <h4>Financial Report</h4>
                    <p>Download payment history and revenue data</p>
                </a>
            </div>
            
            <!-- Recent Payments -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Payments</h3>
                    <a href="?export=payments" class="btn-action view" style="padding: 8px 20px;">Export All</a>
                </div>
                
                <?php if (empty($recent_payments)): ?>
                    <p style="text-align: center; padding: 40px; color: #666;">No payment records found</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['invoice_no']); ?></td>
                                <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                <td><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($months) && !empty($enrollment_counts)): ?>
    <script>
        // Enrollment Chart
        const ctx1 = document.getElementById('enrollmentChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?php echo json_encode($enrollment_counts); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102,126,234,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Course Chart
        <?php if (!empty($course_categories) && !empty($course_counts)): ?>
        const ctx2 = document.getElementById('courseChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($course_categories); ?>,
                datasets: [{
                    data: <?php echo json_encode($course_counts); ?>,
                    backgroundColor: ['#667eea', '#f72585', '#4cc9f0', '#f59e0b', '#10b981', '#764ba2'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
    <?php endif; ?>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>