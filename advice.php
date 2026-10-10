<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && $other_user_id) {
    $message = trim($_POST['message']);
    if ($message) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iis", $current_user_id, $other_user_id, $message);
        $stmt->execute();
    }
    header("Location: chat.php?user=$other_user_id");
    exit();
}

// Get list of users the current user has chatted with – corrected GROUP BY
$conversations = $conn->query("
    SELECT 
        CASE 
            WHEN sender_id = $current_user_id THEN receiver_id 
            ELSE sender_id 
        END as user_id,
        u.full_name,
        MAX(m.sent_at) as last_message_time
    FROM messages m
    JOIN users u ON u.id = CASE 
                            WHEN sender_id = $current_user_id THEN receiver_id 
                            ELSE sender_id 
                          END
    WHERE sender_id = $current_user_id OR receiver_id = $current_user_id
    GROUP BY user_id, u.full_name
    ORDER BY last_message_time DESC
");

// If a specific user is selected, load messages
if ($other_user_id) {
    // Mark messages as read
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $other_user_id AND receiver_id = $current_user_id");
    $messages = $conn->query("SELECT * FROM messages WHERE (sender_id = $current_user_id AND receiver_id = $other_user_id) OR (sender_id = $other_user_id AND receiver_id = $current_user_id) ORDER BY sent_at ASC");
    $other_user = $conn->query("SELECT full_name FROM users WHERE id = $other_user_id")->fetch_assoc();
}

// For starting a new chat – search users
$search = $_GET['search'] ?? '';
$users = [];
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $users = $conn->query("SELECT id, full_name, role FROM users WHERE full_name LIKE '%$search_esc%' OR email LIKE '%$search_esc%' LIMIT 10");
}

// Helper function for time ago (if not already in functions.php)
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        if ($diff < 60) return $diff . ' seconds ago';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
        return 'on ' . date('M d, Y', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-container { display: flex; gap: 20px; height: 70vh; }
        .sidebar-chat { width: 30%; background: white; border-radius: 10px; padding: 15px; overflow-y: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .chat-area { width: 70%; background: white; border-radius: 10px; padding: 20px; display: flex; flex-direction: column; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .message-list { flex: 1; overflow-y: auto; border: 1px solid #eee; padding: 10px; margin-bottom: 10px; border-radius: 8px; }
        .message { margin-bottom: 10px; }
        .message.sent { text-align: right; }
        .message-bubble { display: inline-block; padding: 8px 12px; border-radius: 18px; max-width: 70%; word-wrap: break-word; }
        .sent .message-bubble { background: #667eea; color: white; }
        .received .message-bubble { background: #f1f1f1; color: #333; }
        .message-time { font-size: 0.7em; color: #999; margin-top: 2px; }
        .conversation-item { display: block; padding: 10px; margin: 5px 0; background: #f9f9f9; border-radius: 5px; text-decoration: none; color: #333; border-left: 3px solid transparent; }
        .conversation-item.active { background: #667eea; color: white; border-left-color: #f72585; }
        .conversation-item small { color: #666; }
        .conversation-item.active small { color: #ddd; }
        .unread-badge { background: #f72585; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.7em; margin-left: 5px; }
        .search-box { margin-bottom: 15px; }
        .search-box input { width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 5px; }
        .message-input { display: flex; gap: 10px; }
        .message-input input { flex: 1; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; }
        .btn-send { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn-send:hover { background: #5a67d8; }
        h1 { margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="main-content">
        <h1>Messages</h1>

        <div class="chat-container">
            <!-- Left sidebar: conversations + search -->
            <div class="sidebar-chat">
                <div class="search-box">
                    <form method="GET">
                        <input type="text" name="search" placeholder="Search users by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>
                <?php if ($search && $users && $users->num_rows > 0): ?>
                    <h4>Search Results</h4>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <a href="chat.php?user=<?php echo $u['id']; ?>" class="conversation-item">
                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo ucfirst($u['role']); ?>)
                        </a>
                    <?php endwhile; ?>
                    <hr>
                <?php endif; ?>

                <h4>Conversations</h4>
                <?php if ($conversations && $conversations->num_rows == 0): ?>
                    <p>No conversations yet. Search for a user above to start chatting.</p>
                <?php elseif ($conversations): while($conv = $conversations->fetch_assoc()): ?>
                    <?php
                    // Count unread messages from this user
                    $unread = $conn->query("SELECT COUNT(*) as total FROM messages WHERE sender_id = {$conv['user_id']} AND receiver_id = $current_user_id AND is_read = 0")->fetch_assoc()['total'];
                    ?>
                    <a href="chat.php?user=<?php echo $conv['user_id']; ?>" class="conversation-item <?php echo ($other_user_id == $conv['user_id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($conv['full_name']); ?>
                        <?php if ($unread > 0): ?>
                            <span class="unread-badge"><?php echo $unread; ?></span>
                        <?php endif; ?>
                        <br><small><?php echo timeAgo($conv['last_message_time']); ?></small>
                    </a>
                <?php endwhile; endif; ?>
            </div>

            <!-- Right chat area -->
            <div class="chat-area">
                <?php if ($other_user_id && isset($messages) && $other_user): ?>
                    <h2>Chat with <?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                    <div class="message-list" id="messageList">
                        <?php if ($messages->num_rows == 0): ?>
                            <p style="text-align:center; color:#999;">No messages yet. Say hello!</p>
                        <?php else: while($msg = $messages->fetch_assoc()): ?>
                            <div class="message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>">
                                <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="message-time"><?php echo date('M d, H:i', strtotime($msg['sent_at'])); ?></div>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>
                    <form method="POST" class="message-input">
                        <input type="text" name="message" placeholder="Type your message..." autocomplete="off" required>
                        <button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                <?php else: ?>
                    <p style="text-align:center; color:#999; margin-top:40px;">Select a conversation or search for a user to start messaging.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        const messageList = document.getElementById('messageList');
        if (messageList) messageList.scrollTop = messageList.scrollHeight;
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>