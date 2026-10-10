<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check login (optional – if you want only logged-in users)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Define SITE_NAME if not already defined
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'EduSmart Pro');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Assistant - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="<?php echo $_SESSION['role'] ?? 'user'; ?>">
    <style>
        .assistant-page {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
        }
        .assistant-page h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .assistant-page .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .assistant-page .robot-icon {
            font-size: 5em;
            color: #667eea;
            margin-bottom: 20px;
        }
        .assistant-page .note {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            text-align: left;
            margin-top: 30px;
        }
        .assistant-page .note i {
            color: #667eea;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <div class="page-title">
                <h1>AI Assistant</h1>
                <p>Your intelligent helper</p>
            </div>
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Admin'); ?>&background=667eea&color=fff&size=45" class="profile-img">
            </div>
        </div>

        <div class="assistant-page">
            <div class="robot-icon">
                <i class="fas fa-robot"></i>
            </div>
            <h1>How can I help you today?</h1>
            <p class="subtitle">Click the chat button at the bottom right to start a conversation.</p>

            <div class="note">
                <i class="fas fa-info-circle"></i> 
                <strong>Tip:</strong> You can ask me about:
                <ul style="margin-top:10px; margin-left:20px;">
                    <li>System statistics (students, teachers, courses)</li>
                    <li>User management</li>
                    <li>Reports and analytics</li>
                    <li>General help</li>
                </ul>
            </div>

            <div class="note" style="border-left-color: #4cc9f0;">
                <i class="fas fa-robot" style="color:#4cc9f0;"></i>
                <strong>The assistant is context‑aware:</strong> It adapts to your role (admin, teacher, student).
            </div>
        </div>
    </div>

    <!-- The floating widget will appear automatically because we include the CSS/JS -->
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>