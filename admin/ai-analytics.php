<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$log_file = '../logs/ai_chat.log';
$conversations = [];

if (file_exists($log_file)) {
    $lines = file($log_file);
    foreach ($lines as $line) {
        if (preg_match('/^(.+?) \| User: (\d+) \| Q: (.+?) \| A: (.+)$/', trim($line), $matches)) {
            $conversations[] = [
                'time' => $matches[1],
                'user_id' => $matches[2],
                'question' => $matches[3],
                'answer' => $matches[4]
            ];
        }
    }
    $conversations = array_reverse($conversations);
}

$total_queries = count($conversations);
$unique_users = count(array_unique(array_column($conversations, 'user_id')));

// Get usernames for display
$user_names = [];
if ($total_queries > 0) {
    $user_ids = array_unique(array_column($conversations, 'user_id'));
    $ids_str = implode(',', $user_ids);
    $result = $conn->query("SELECT id, username, full_name FROM users WHERE id IN ($ids_str)");
    while ($row = $result->fetch_assoc()) {
        $user_names[$row['id']] = $row['full_name'] ?: $row['username'];
    }
}

// Average queries per day
$avg_per_day = 0;
if ($total_queries > 1) {
    $first = strtotime($conversations[array_key_last($conversations)]['time']);
    $last = strtotime($conversations[0]['time']);
    $days = max(1, ($last - $first) / 86400);
    $avg_per_day = round($total_queries / $days, 1);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AI Analytics</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:white; padding:20px; border-radius:10px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
        .stat-number { font-size:2.5em; font-weight:bold; color:#667eea; }
        .filter-bar { background:white; padding:20px; border-radius:10px; margin-bottom:20px; display:flex; gap:15px; align-items:center; flex-wrap:wrap; }
        .log-table { background:white; border-radius:10px; padding:20px; }
        .log-entry { border-bottom:1px solid #eee; padding:15px 0; }
        .log-entry:last-child { border-bottom:none; }
        .log-question { font-weight:bold; color:#333; }
        .log-answer { color:#666; margin-top:5px; }
        .log-meta { font-size:0.85em; color:#999; margin-top:5px; display:flex; gap:15px; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>AI Analytics</h1>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total_queries; ?></div>Total Queries</div>
            <div class="stat-card"><div class="stat-number"><?php echo $unique_users; ?></div>Unique Users</div>
            <div class="stat-card"><div class="stat-number"><?php echo $avg_per_day; ?></div>Avg Queries/Day</div>
        </div>
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="Search questions/answers...">
            <select id="userFilter">
                <option value="">All Users</option>
                <?php foreach ($user_names as $uid => $name): ?>
                    <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="applyFilters()" class="btn-action view">Filter</button>
            <button onclick="resetFilters()" class="btn-action edit">Reset</button>
        </div>
        <div class="log-table" id="logContainer">
            <?php if (empty($conversations)): ?><p>No conversations logged yet.</p>
            <?php else: foreach ($conversations as $conv): ?>
                <div class="log-entry" data-user="<?php echo $conv['user_id']; ?>" data-question="<?php echo htmlspecialchars($conv['question']); ?>" data-answer="<?php echo htmlspecialchars($conv['answer']); ?>">
                    <div class="log-question">Q: <?php echo htmlspecialchars($conv['question']); ?></div>
                    <div class="log-answer">A: <?php echo htmlspecialchars($conv['answer']); ?></div>
                    <div class="log-meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($user_names[$conv['user_id']] ?? 'User '.$conv['user_id']); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo $conv['time']; ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <script>
        function applyFilters() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const user = document.getElementById('userFilter').value;
            document.querySelectorAll('.log-entry').forEach(e => {
                const q = e.dataset.question.toLowerCase();
                const a = e.dataset.answer.toLowerCase();
                const match = (search==='' || q.includes(search) || a.includes(search)) && (user==='' || e.dataset.user===user);
                e.style.display = match ? 'block' : 'none';
            });
        }
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('userFilter').value = '';
            applyFilters();
        }
    </script>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>