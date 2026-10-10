<?php
if (session_status() == PHP_SESSION_NONE) session_start();

// Get current page for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);

$user_name = $_SESSION['full_name'] ?? 'Student';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h3><?php echo htmlspecialchars($user_name); ?></h3>
        <p>Student</p>
    </div>

    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="courses.php" class="menu-item <?php echo ($current_page == 'courses.php') ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> <span>My Courses</span>
        </a>
        <a href="learning.php" class="menu-item <?php echo ($current_page == 'learning.php') ? 'active' : ''; ?>">
            <i class="fas fa-play-circle"></i> <span>Learning</span>
        </a>
        <a href="exams.php" class="menu-item <?php echo ($current_page == 'exams.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> <span>Exams</span>
        </a>
        <a href="certificate.php" class="menu-item <?php echo ($current_page == 'certificate.php') ? 'active' : ''; ?>">
            <i class="fas fa-certificate"></i> <span>Certificates</span>
        </a>
        <a href="jobs.php" class="menu-item <?php echo ($current_page == 'jobs.php') ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i> <span>Job Listings</span>
        </a>
        <a href="my-applications.php" class="menu-item <?php echo ($current_page == 'my-applications.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-signature"></i> <span>My Applications</span>
        </a>
        <a href="leaderboard.php" class="menu-item <?php echo ($current_page == 'leaderboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-trophy"></i> <span>Leaderboard</span>
        </a>
        <a href="badges.php" class="menu-item <?php echo ($current_page == 'badges.php') ? 'active' : ''; ?>">
            <i class="fas fa-medal"></i> <span>My Badges</span>
        </a>
        <a href="advice.php" class="menu-item <?php echo ($current_page == 'advice.php') ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i> <span>Messages</span>
        </a>
        <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> <span>Profile</span>
        </a>
        <a href="../auth/logout.php" class="menu-item" style="margin-top: 50px;" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>