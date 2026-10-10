<?php
// Safe session start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['full_name'] ?? 'Employer';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo"><i class="fas fa-briefcase"></i></div>
        <h3><?php echo htmlspecialchars($user_name); ?></h3>
        <p>Employer</p>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="post-job.php" class="menu-item <?php echo ($current_page == 'post-job.php') ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i> <span>Post Job</span>
        </a>
        <a href="manage-jobs.php" class="menu-item <?php echo ($current_page == 'manage-jobs.php') ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i> <span>Manage Jobs</span>
        </a>
        <a href="view-applications.php" class="menu-item <?php echo ($current_page == 'view-applications.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-signature"></i> <span>Applications</span>
        </a>
        <a href="search-candidates.php" class="menu-item <?php echo ($current_page == 'search-candidates.php') ? 'active' : ''; ?>">
            <i class="fas fa-search"></i> <span>Search Candidates</span>
        </a>
        <a href="advice.php" class="menu-item <?php echo ($current_page == 'advice.php') ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i> <span>Messages</span>
        </a>
        <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> <span>Profile</span>
        </a>
        <a href="../auth/logout.php" class="menu-item" style="margin-top:50px;" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>