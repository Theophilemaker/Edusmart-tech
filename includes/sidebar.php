<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Safely retrieve user name from session (fallback if key missing)
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_role_display = ucfirst($user_role); // Capitalize first letter
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-school"></i>
        </div>
        <h3><?php echo htmlspecialchars($user_name); ?></h3>
        <p><?php echo htmlspecialchars($user_role_display); ?></p>
    </div>

    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="manage-employers.php" class="menu-item"><i class="fas fa-building"></i> <span>Employers</span></a>
<a href="manage-jobs.php" class="menu-item"><i class="fas fa-briefcase"></i> <span>All Jobs</span></a>
<a href="manage-applications.php" class="menu-item"><i class="fas fa-file-signature"></i> <span>Applications</span></a>
<a href="manage-courses.php" class="menu-item">Courses</a>
<a href="manage-classes.php" class="menu-item">Classes</a>
<a href="manage-exams.php" class="menu-item">Exams</a>
<a href="manage-certificates.php" class="menu-item">Certificates</a>
<a href="manage-interviews.php" class="menu-item">Interviews</a>
        <a href="manage-services.php" class="menu-item">Services</a>
        <a href="advice-requests.php" class="menu-item">Advice Requests</a>
        <!-- AI Tools -->
<div class="menu-section">AI Tools</div>
<a href="ai-analytics.php" class="menu-item">AI Analytics</a>

<!-- Analytics & Gamification -->
<div class="menu-section">Analytics</div>
<a href="analytics.php" class="menu-item">Analytics Dashboard</a>
<a href="leaderboard.php" class="menu-item">Leaderboard</a>
<a href="manage-badges.php" class="menu-item">Manage Badges</a>
<!-- AI Tools -->
<!-- AI Tools -->
<div class="menu-section">AI Tools</div>
<a href="ai-assistant.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'ai-assistant.php') ? 'active' : ''; ?>">
    <i class="fas fa-robot"></i> <span>AI Assistant</span>
</a>
<a href="ai-analytics.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'ai-analytics.php') ? 'active' : ''; ?>">
    <i class="fas fa-chart-line"></i> <span>AI Analytics</span>
</a>
<!-- E-Commerce -->
<div class="menu-section">E-Commerce</div>
<a href="manage-products.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage-products.php') ? 'active' : ''; ?>">
    <i class="fas fa-boxes"></i> <span>Products</span>
</a>
<a href="payment-settings.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'payment-settings.php') ? 'active' : ''; ?>">
    <i class="fas fa-credit-card"></i> <span>Payment Settings</span>
</a>
<a href="orders.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">
    <i class="fas fa-shopping-cart"></i> <span>Orders</span>
</a>
<a href="invoices.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'invoices.php') ? 'active' : ''; ?>">
    <i class="fas fa-file-invoice"></i> <span>Invoices</span>
</a>

<!-- System -->
<div class="menu-section">System</div>
<a href="activity-logs.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'activity-logs.php') ? 'active' : ''; ?>">
    <i class="fas fa-history"></i> <span>Activity Logs</span>
</a>
        <a href="profile.php" class="menu-item">
            <i class="fas fa-user-cog"></i> <span>Profile</span>
        </a>

        <!-- User Management -->
        <div class="menu-section">User Management</div>
        <a href="manage-users.php" class="menu-item <?php echo ($current_page == 'manage-users.php') ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i>
            <span>All Users</span>
        </a>
        <a href="manage-students.php" class="menu-item <?php echo (strpos($current_page, 'student') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Students</span>
        </a>
        <a href="manage-teachers.php" class="menu-item <?php echo (strpos($current_page, 'teacher') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher"></i>
            <span>Teachers</span>
        </a>
        <a href="manage-employers.php" class="menu-item <?php echo ($current_page == 'manage-employers.php') ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Employers</span>
        </a>

        <!-- Academic -->
        <div class="menu-section">Academic</div>
        <a href="manage-classes.php" class="menu-item <?php echo ($current_page == 'manage-classes.php') ? 'active' : ''; ?>">
            <i class="fas fa-door-open"></i>
            <span>Classes</span>
        </a>
        <a href="manage-courses.php" class="menu-item <?php echo ($current_page == 'manage-courses.php') ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Courses</span>
        </a>
        <a href="manage-exams.php" class="menu-item <?php echo ($current_page == 'manage-exams.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Exams</span>
        </a>
        <a href="manage-certificates.php" class="menu-item <?php echo ($current_page == 'manage-certificates.php') ? 'active' : ''; ?>">
            <i class="fas fa-certificate"></i>
            <span>Certificates</span>
        </a>

        <!-- AI Assistant -->
        <div class="menu-section">AI Tools</div>
        <a href="../ai-assistant/dashboard.php" class="menu-item <?php echo (strpos($current_page, 'ai-') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-robot"></i>
            <span>AI Assistant</span>
        </a>
        <a href="../ai-assistant/analytics.php" class="menu-item">
            <i class="fas fa-chart-pie"></i>
            <span>AI Analytics</span>
        </a>

        <!-- Job Portal -->
        <div class="menu-section">Job Portal</div>
        <a href="manage-jobs.php" class="menu-item <?php echo ($current_page == 'manage-jobs.php') ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i>
            <span>Manage Jobs</span>
        </a>
        <a href="manage-applications.php" class="menu-item">
            <i class="fas fa-file-signature"></i>
            <span>Applications</span>
        </a>
        <a href="manage-interviews.php" class="menu-item">
            <i class="fas fa-calendar-check"></i>
            <span>Interviews</span>
        </a>

        <!-- E-Commerce -->
        <div class="menu-section">E-Commerce</div>
        <a href="manage-products.php" class="menu-item <?php echo ($current_page == 'manage-products.php') ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i>
            <span>Products</span>
        </a>
        <a href="manage-orders.php" class="menu-item <?php echo ($current_page == 'manage-orders.php') ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
        </a>
        <a href="payment-settings.php" class="menu-item">
            <i class="fas fa-credit-card"></i>
            <span>Payment Settings</span>
        </a>
        

        <!-- Financial -->
        <div class="menu-section">Financial</div>
        <a href="reports.php" class="menu-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>

        <!-- System -->
        <div class="menu-section">System</div>
        <a href="settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="backup.php" class="menu-item">
            <i class="fas fa-database"></i>
            <span>Backup</span>
        </a>

        <!-- Profile & Logout -->
        <div class="menu-section">Account</div>
        <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>
       <a href="../auth/logout.php" class="menu-item" onclick="return confirm('Logout?')">
    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
</a>
    </div>
</div>