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

// Fetch top 50 students (use backticks for `rank`)
$top_students = $conn->query("
    SELECT u.full_name, s.admission_number, s.points, s.level,
           (SELECT COUNT(*) + 1 FROM students WHERE points > s.points) as `rank`
    FROM students s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.points DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <style>
        .leaderboard-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        th {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 10px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .rank-1 { font-weight: bold; color: gold; }
        .rank-2 { font-weight: bold; color: silver; }
        .rank-3 { font-weight: bold; color: #cd7f32; }
        .rank-1 td:first-child { font-size: 1.2em; }
        .medal {
            margin-left: 5px;
        }
        @media (max-width: 768px) {
            .leaderboard-container {
                padding: 15px;
            }
            th, td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
        }
        @media (max-width: 480px) {
            .leaderboard-container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Leaderboard</h1>
        </div>

        <div class="leaderboard-container">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Name</th>
                            <th>Admission No.</th>
                            <th>Points</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_students->num_rows == 0): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px;">No students yet.</td></tr>
                        <?php else: while ($row = $top_students->fetch_assoc()): ?>
                            <tr class="<?php echo $row['rank'] <= 3 ? 'rank-'.$row['rank'] : ''; ?>">
                                <td>
                                    <strong>#<?php echo $row['rank']; ?></strong>
                                    <?php if ($row['rank'] == 1): ?>
                                        <span class="medal">🥇</span>
                                    <?php elseif ($row['rank'] == 2): ?>
                                        <span class="medal">🥈</span>
                                    <?php elseif ($row['rank'] == 3): ?>
                                        <span class="medal">🥉</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['admission_number']); ?></td>
                                <td><strong><?php echo $row['points']; ?></strong></td>
                                <td>Level <?php echo $row['level']; ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>