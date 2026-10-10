<?php
// Enable error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employer details
$employer = [];
try {
    $stmt = $conn->prepare("SELECT e.*, u.full_name, u.email, u.phone FROM employers e JOIN users u ON e.user_id = u.id WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employer = $result->fetch_assoc();
    if (!$employer) {
        // If no employer record, create minimal data
        $employer = [
            'company_name' => 'Your Company',
            'full_name' => $_SESSION['full_name'] ?? 'Employer',
            'email' => $_SESSION['email'] ?? '',
            'phone' => '',
            'id' => 0
        ];
    }
} catch (Exception $e) {
    error_log("Error loading employer: " . $e->getMessage());
    $employer = [
        'company_name' => 'Your Company',
        'full_name' => $_SESSION['full_name'] ?? 'Employer',
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
        'id' => 0
    ];
}

$employer_id = $employer['id'] ?? 0;

// Count statistics
$total_jobs = 0;
$total_applications = 0;
$shortlisted = 0;

if ($employer_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE employer_id = ?");
        $stmt->bind_param("i", $employer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_jobs = $result->fetch_assoc()['total'] ?? 0;
    } catch (Exception $e) { error_log("Job count error: " . $e->getMessage()); }

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = ?");
        $stmt->bind_param("i", $employer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_applications = $result->fetch_assoc()['total'] ?? 0;
    } catch (Exception $e) { error_log("Application count error: " . $e->getMessage()); }

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = ? AND a.status = 'shortlisted'");
        $stmt->bind_param("i", $employer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $shortlisted = $result->fetch_assoc()['total'] ?? 0;
    } catch (Exception $e) { error_log("Shortlisted count error: " . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employer Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            margin: 0 auto 15px;
            color: white;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-number { font-size: 2.2em; font-weight: bold; color: #333; }
        .stat-label { color: #666; }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Include employer sidebar -->
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Employer Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($employer['company_name'] ?: $employer['full_name']); ?>!</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employer['full_name'] ?? 'Employer'); ?>&background=8b5cf6&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card" onclick="location.href='manage-jobs.php'">
                    <div class="stat-icon blue"><i class="fas fa-briefcase"></i></div>
                    <div class="stat-number"><?php echo $total_jobs; ?></div>
                    <div class="stat-label">Posted Jobs</div>
                </div>
                <div class="stat-card" onclick="location.href='view-applications.php'">
                    <div class="stat-icon green"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-number"><?php echo $total_applications; ?></div>
                    <div class="stat-label">Applications</div>
                </div>
                <div class="stat-card" onclick="location.href='search-candidates.php?shortlisted=1'">
                    <div class="stat-icon orange"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $shortlisted; ?></div>
                    <div class="stat-label">Shortlisted</div>
                </div>
            </div>

            <!-- You can add recent activity or other widgets here -->
        </div>
    </div>

    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>