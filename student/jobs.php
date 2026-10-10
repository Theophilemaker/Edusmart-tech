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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$location = $_GET['location'] ?? '';

// Base query – use j.id for ordering (guaranteed to exist)
$query = "SELECT j.*, e.company_name FROM jobs j JOIN employers e ON j.employer_id = e.id WHERE j.status = 'open'";

if ($search) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (j.title LIKE '%$search%' OR j.description LIKE '%$search%')";
}
if ($type) {
    $type = $conn->real_escape_string($type);
    $query .= " AND j.type = '$type'";
}
if ($location) {
    $location = $conn->real_escape_string($location);
    $query .= " AND j.location LIKE '%$location%'";
}

// Order by id (newest first) – fallback if posted_date missing
$query .= " ORDER BY j.id DESC";

$jobs = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Listings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="student">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="top-nav">
                <div class="page-title">
                    <h1>Job Listings</h1>
                    <p>Browse available job opportunities</p>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4cc9f0&color=fff&size=45" class="profile-img">
                </div>
            </div>

            <!-- Filter Form -->
            <div class="filter-form" style="background:white; padding:20px; border-radius:10px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <input type="text" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>" style="flex:2; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                    <select name="type" style="flex:1; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                        <option value="">All Types</option>
                        <option value="full-time" <?php echo $type == 'full-time' ? 'selected' : ''; ?>>Full‑time</option>
                        <option value="part-time" <?php echo $type == 'part-time' ? 'selected' : ''; ?>>Part‑time</option>
                        <option value="contract" <?php echo $type == 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="internship" <?php echo $type == 'internship' ? 'selected' : ''; ?>>Internship</option>
                    </select>
                    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>" style="flex:1; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                    <button type="submit" style="padding:10px 20px; background:#4cc9f0; color:white; border:none; border-radius:8px; cursor:pointer;">Filter</button>
                </form>
            </div>

            <?php if ($jobs && $jobs->num_rows == 0): ?>
                <p style="text-align:center; padding:40px; background:white; border-radius:10px;">No jobs found.</p>
            <?php elseif ($jobs): ?>
                <div class="job-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px,1fr)); gap:20px;">
                    <?php while($job = $jobs->fetch_assoc()): ?>
                    <div class="job-card" style="background:white; border-radius:10px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom:5px;"><?php echo htmlspecialchars($job['title']); ?></h3>
                        <p style="color:#4cc9f0; font-weight:500; margin-bottom:10px;"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p style="margin-bottom:5px;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'Remote'); ?></p>
                        <p style="margin-bottom:5px;"><strong>Salary:</strong> <?php echo htmlspecialchars($job['salary'] ?? 'Negotiable'); ?></p>
                        <p style="margin-bottom:5px;"><span class="badge" style="background:#e0e0e0; padding:3px 8px; border-radius:12px;"><?php echo $job['type'] ?? 'full-time'; ?></span></p>
                        <p style="margin:10px 0; color:#666;"><?php echo nl2br(htmlspecialchars(substr($job['description'] ?? '', 0, 150))); ?>...</p>
                        <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn-apply" style="display:inline-block; padding:8px 16px; background:#4cc9f0; color:white; border-radius:5px; text-decoration:none;">Apply Now</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align:center; padding:40px; background:white; border-radius:10px;">Error loading jobs.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>