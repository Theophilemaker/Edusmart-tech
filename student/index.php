<?php
// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration (database connection, functions, etc.)
require_once 'config/database.php';
require_once 'includes/functions.php';

// You can define SITE_NAME if not already defined
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'EduSmart Pro');
}

// Get counts for display (optional)
$total_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'] ?? 0;
$total_teachers = $conn->query("SELECT COUNT(*) as total FROM teachers")->fetch_assoc()['total'] ?? 0;
$total_courses  = $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'] ?? 0;
$total_jobs     = $conn->query("SELECT COUNT(*) as total FROM jobs")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> – Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ai-widget.css">
    <style>
        /* Hero section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }
        .hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }
        .hero p {
            font-size: 1.2em;
            max-width: 800px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        .hero-buttons a {
            display: inline-block;
            margin: 10px;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .hero-buttons .btn-primary {
            background: white;
            color: #667eea;
        }
        .hero-buttons .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        .hero-buttons a:hover {
            transform: translateY(-3px);
        }

        /* Stats section */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            padding: 60px 20px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        .stat-item {
            background: white;
            padding: 30px 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            font-size: 1.1em;
        }

        /* Features section */
        .features {
            background: #f9f9f9;
            padding: 60px 20px;
        }
        .features h2 {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2em;
            color: #333;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .feature-card i {
            font-size: 3em;
            color: #667eea;
            margin-bottom: 15px;
        }
        .feature-card h3 {
            margin-bottom: 10px;
            color: #333;
        }
        .feature-card p {
            color: #666;
        }

        /* CTA section */
        .cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }
        .cta h2 {
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        .cta a {
            display: inline-block;
            margin-top: 20px;
            padding: 15px 40px;
            background: white;
            color: #667eea;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
        }
        .cta a:hover {
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.2em; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Welcome to <?php echo SITE_NAME; ?></h1>
        <p>Your all‑in‑one platform for learning, teaching, career opportunities, and shopping.</p>
        <div class="hero-buttons">
            <a href="products.php" class="btn-primary"><i class="fas fa-shopping-cart"></i> Shop Now</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="auth/login.php" class="btn-secondary"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="auth/register.php" class="btn-secondary"><i class="fas fa-user-plus"></i> Register</a>
            <?php else: ?>
                <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn-secondary"><i class="fas fa-tachometer-alt"></i> Go to Dashboard</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Statistics Section (optional) -->
    <section class="stats">
        <div class="stat-item">
            <div class="stat-number"><?php echo $total_students; ?></div>
            <div class="stat-label">Active Students</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $total_teachers; ?></div>
            <div class="stat-label">Qualified Teachers</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $total_courses; ?></div>
            <div class="stat-label">Courses Available</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $total_jobs; ?></div>
            <div class="stat-label">Job Opportunities</div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <h2>Why Choose <?php echo SITE_NAME; ?></h2>
        <div class="feature-grid">
            <div class="feature-card">
                <i class="fas fa-graduation-cap"></i>
                <h3>Online Learning</h3>
                <p>Access high‑quality courses, video lessons, and quizzes anytime, anywhere.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-briefcase"></i>
                <h3>Job Portal</h3>
                <p>Find the perfect job, post vacancies, and connect with top employers.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-shopping-cart"></i>
                <h3>E‑Commerce</h3>
                <p>Shop for educational materials, merchandise, and more.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-robot"></i>
                <h3>AI Assistant</h3>
                <p>Get instant help and insights from our intelligent AI assistant.</p>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta">
        <h2>Ready to get started?</h2>
        <p>Join thousands of learners, teachers, and employers today.</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="auth/register.php">Create Free Account</a>
        <?php else: ?>
            <a href="<?php echo $_SESSION['role']; ?>/dashboard.php">Go to Dashboard</a>
        <?php endif; ?>
    </section>

    <?php include 'includes/footer.php'; ?>
    <!-- AI Assistant Widget (already included in footer) -->
</body>
</html>s