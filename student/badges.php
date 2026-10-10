<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) die("Student record not found.");
$student_id = $student['id'];

// Check if awarded_at column exists in student_badges
$has_awarded_at = false;
$col_check = $conn->query("SHOW COLUMNS FROM student_badges LIKE 'awarded_at'");
if ($col_check && $col_check->num_rows > 0) {
    $has_awarded_at = true;
}

// Fetch earned badges – adapt query based on column existence
if ($has_awarded_at) {
    $earned_query = "
        SELECT b.*, sb.awarded_at 
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = $student_id
        ORDER BY sb.awarded_at DESC
    ";
} else {
    // Fallback: use sb.id as a rough order (newest first)
    $earned_query = "
        SELECT b.*, NULL as awarded_at
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = $student_id
        ORDER BY sb.id DESC
    ";
}
$earned = $conn->query($earned_query);

// Fetch all badges (for locked status)
$all_badges = $conn->query("SELECT * FROM badges");

// Build array of earned badge IDs
$earned_ids = [];
while ($e = $earned->fetch_assoc()) {
    $earned_ids[] = $e['id'];
}
$earned->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Badges</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <style>
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .badge-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .badge-card:hover {
            transform: translateY(-5px);
        }
        .badge-card.earned {
            border: 2px solid gold;
            background: #fff9e6;
        }
        .badge-card.locked {
            opacity: 0.7;
            filter: grayscale(0.5);
        }
        .badge-icon {
            font-size: 3.5em;
            margin-bottom: 10px;
        }
        .badge-icon.earned { color: gold; }
        .badge-icon.locked { color: #aaa; }
        .badge-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .badge-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
        .badge-date {
            font-size: 0.8rem;
            color: #888;
            margin-top: 8px;
        }
        @media (max-width: 768px) {
            .badges-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        @media (max-width: 480px) {
            .badges-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>My Badges</h1>
        </div>

        <div class="badges-grid">
            <?php $all_badges->data_seek(0); while ($badge = $all_badges->fetch_assoc()): 
                $is_earned = in_array($badge['id'], $earned_ids);
            ?>
            <div class="badge-card <?php echo $is_earned ? 'earned' : 'locked'; ?>">
                <div class="badge-icon <?php echo $is_earned ? 'earned' : 'locked'; ?>">
                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                </div>
                <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                <div class="badge-description"><?php echo htmlspecialchars($badge['description']); ?></div>
                <?php if ($is_earned): 
                    // Find award date
                    $earned->data_seek(0);
                    $awarded = '';
                    while ($e = $earned->fetch_assoc()) {
                        if ($e['id'] == $badge['id']) {
                            $awarded = $e['awarded_at'] ?? '';
                            break;
                        }
                    }
                ?>
                    <?php if (!empty($awarded)): ?>
                        <div class="badge-date">🎉 Earned <?php echo date('M d, Y', strtotime($awarded)); ?></div>
                    <?php else: ?>
                        <div class="badge-date">✅ Earned</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="badge-date">🔒 Locked</div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>