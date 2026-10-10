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

// Check if 'class' column exists
$class_column_exists = false;
$check = $conn->query("SHOW COLUMNS FROM students LIKE 'class'");
if ($check && $check->num_rows > 0) {
    $class_column_exists = true;
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "";
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $where = "WHERE u.full_name LIKE '%$search_esc%' OR s.admission_number LIKE '%$search_esc%'";
    if ($class_column_exists) {
        $where .= " OR s.class LIKE '%$search_esc%'";
    }
}

// Total count
$total = $conn->query("SELECT COUNT(*) as total FROM students s JOIN users u ON s.user_id = u.id $where")->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total / $limit);

// Build SELECT fields
$select_fields = "u.full_name, u.email, s.admission_number, s.points, s.level";
if ($class_column_exists) {
    $select_fields .= ", s.class";
} else {
    $select_fields .= ", NULL as class";
}

// Fetch students with ranking
$query = "
    SELECT $select_fields,
           (SELECT COUNT(*) + 1 FROM students WHERE points > s.points) as student_rank
    FROM students s
    JOIN users u ON s.user_id = u.id
    $where
    ORDER BY s.points DESC
    LIMIT $offset, $limit
";
$students = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Leaderboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
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
        }
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
        }
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
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
        .leaderboard-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .rank-1 { color: gold; font-weight: bold; }
        .rank-2 { color: silver; font-weight: bold; }
        .rank-3 { color: #cd7f32; font-weight: bold; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 5px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
        }
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        .pagination .active {
            background: #667eea;
            color: white;
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
            .search-bar {
                flex-direction: column;
            }
            .btn-primary, .btn-secondary {
                width: 100%;
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
                <h1>Student Leaderboard</h1>
            </div>

            <div class="leaderboard-container">
                <form method="GET" class="search-bar">
                    <input type="text" name="search" placeholder="Search by name, admission number<?php echo $class_column_exists ? ', or class' : ''; ?>..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="leaderboard.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Admission No.</th>
                                <?php if ($class_column_exists): ?><th>Class</th><?php endif; ?>
                                <th>Points</th>
                                <th>Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students->num_rows == 0): ?>
                                <tr><td colspan="<?php echo $class_column_exists ? 7 : 6; ?>" style="text-align:center;">No students found.</td></tr>
                            <?php else: while ($row = $students->fetch_assoc()): ?>
                                <tr>
                                    <td class="<?php echo $row['student_rank'] <= 3 ? 'rank-'.$row['student_rank'] : ''; ?>">
                                        #<?php echo $row['student_rank']; ?>
                                        <?php if ($row['student_rank'] == 1): ?>🥇
                                        <?php elseif ($row['student_rank'] == 2): ?>🥈
                                        <?php elseif ($row['student_rank'] == 3): ?>🥉
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['admission_number']); ?></td>
                                    <?php if ($class_column_exists): ?>
                                        <td><?php echo htmlspecialchars($row['class'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><strong><?php echo $row['points']; ?></strong></td>
                                    <td>Level <?php echo $row['level']; ?></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">« Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next »</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>