<?php
// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Safely retrieve user's name from session
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Teacher';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <h3><?php echo htmlspecialchars($user_name); ?></h3>
        <p>Teacher</p>
    </div>

    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="courses.php" class="menu-item <?php echo ($current_page == 'courses.php' || $current_page == 'create-course.php') ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> <span>Courses</span>
        </a>
        <a href="exams.php" class="menu-item <?php echo ($current_page == 'exams.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> <span>Exams</span>
        </a>
        <a href="grade.php" class="menu-item <?php echo ($current_page == 'grade.php') ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> <span>Grade</span>
        </a>
        <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> <span>Profile</span>
        </a>
        <a href="../auth/logout.php" class="menu-item" style="margin-top: 50px;" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>