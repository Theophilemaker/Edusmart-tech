<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: ../auth/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Send message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && $other_user_id) {
    $message = trim($_POST['message']);
    if ($message) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user_id, $other_user_id, $message);
        $stmt->execute();
    }
    header("Location: chat.php?user=$other_user_id");
    exit();
}

// Get list of conversations (users the employer has chatted with) – corrected for ONLY_FULL_GROUP_BY
$query = "
    SELECT 
        CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END as user_id,
        u.full_name,
        MAX(m.sent_at) as last_message_time
    FROM messages m
    JOIN users u ON u.id = CASE 
                            WHEN sender_id = ? THEN receiver_id 
                            ELSE sender_id 
                          END
    WHERE sender_id = ? OR receiver_id = ?
    GROUP BY user_id, u.full_name
    ORDER BY last_message_time DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$conversations = $stmt->get_result();

// If a specific user is selected, load messages
if ($other_user_id) {
    // Mark messages as read
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $other_user_id AND receiver_id = $current_user_id");
    $messages = $conn->query("SELECT * FROM messages WHERE (sender_id = $current_user_id AND receiver_id = $other_user_id) OR (sender_id = $other_user_id AND receiver_id = $current_user_id) ORDER BY sent_at ASC");
    $other_user = $conn->query("SELECT full_name FROM users WHERE id = $other_user_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Messages</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
    <style>
        .chat-container { display: flex; gap: 20px; }
        .conversation-list { width: 30%; background: white; border-radius: 10px; padding: 15px; }
        .conversation-list a { display: block; padding: 10px; margin: 5px 0; background: #f0f0f0; border-radius: 5px; text-decoration: none; color: #333; }
        .conversation-list a.active { background: #8b5cf6; color: white; }
        .chat-area { width: 70%; background: white; border-radius: 10px; padding: 20px; }
        .message-list { height: 400px; overflow-y: auto; border: 1px solid #eee; padding: 10px; margin-bottom: 10px; }
        .message { margin-bottom: 10px; }
        .message.sent { text-align: right; }
        .message-bubble { display: inline-block; padding: 8px 12px; border-radius: 18px; max-width: 70%; }
        .sent .message-bubble { background: #8b5cf6; color: white; }
        .received .message-bubble { background: #e9ecef; color: #333; }
        .message-time { font-size: 0.7em; color: #999; margin-top: 2px; }
        .message-input { display: flex; gap: 10px; }
        .message-input input { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 20px; }
        .message-input button { background: #8b5cf6; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Messages</h1>
        </div>

        <div class="chat-container">
            <div class="conversation-list">
                <h3>Conversations</h3>
                <?php if ($conversations && $conversations->num_rows > 0): ?>
                    <?php while($conv = $conversations->fetch_assoc()): ?>
                        <a href="chat.php?user=<?php echo $conv['user_id']; ?>" class="<?php if($other_user_id == $conv['user_id']) echo 'active'; ?>">
                            <?php echo htmlspecialchars($conv['full_name']); ?>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No conversations yet.</p>
                <?php endif; ?>
            </div>

            <div class="chat-area">
                <?php if ($other_user_id && isset($messages)): ?>
                    <h2>Chat with <?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                    <div class="message-list" id="messageList">
                        <?php while($msg = $messages->fetch_assoc()): ?>
                            <div class="message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>">
                                <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="message-time"><?php echo date('M d, H:i', strtotime($msg['sent_at'])); ?></div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <form method="POST" class="message-input">
                        <input type="text" name="message" placeholder="Type your message..." required>
                        <button type="submit">Send</button>
                    </form>
                <?php else: ?>
                    <p>Select a conversation to start chatting.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        const messageList = document.getElementById('messageList');
        if (messageList) messageList.scrollTop = messageList.scrollHeight;
    </script>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>