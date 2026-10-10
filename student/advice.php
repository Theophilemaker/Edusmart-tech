<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

session_start();
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

// Get list of conversations (distinct users the employer has chatted with)
$conversations = $conn->query("SELECT DISTINCT 
                                    CASE 
                                        WHEN sender_id = $current_user_id THEN receiver_id 
                                        ELSE sender_id 
                                    END as user_id,
                                    u.full_name
                                FROM messages m
                                JOIN users u ON u.id = CASE 
                                                        WHEN sender_id = $current_user_id THEN receiver_id 
                                                        ELSE sender_id 
                                                    END
                                WHERE sender_id = $current_user_id OR receiver_id = $current_user_id
                                ORDER BY MAX(m.sent_at) DESC
                                GROUP BY user_id");

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
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <h1>Messages</h1>
            <div class="chat-container">
                <div class="conversation-list">
                    <h3>Conversations</h3>
                    <?php while($conv = $conversations->fetch_assoc()): ?>
                        <a href="chat.php?user=<?php echo $conv['user_id']; ?>" class="conv-item <?php if($other_user_id == $conv['user_id']) echo 'active'; ?>">
                            <?php echo htmlspecialchars($conv['full_name']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
                <div class="chat-area">
                    <?php if ($other_user_id && isset($messages)): ?>
                        <h2>Chat with <?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                        <div class="message-list">
                            <?php while($msg = $messages->fetch_assoc()): ?>
                                <div class="message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>">
                                    <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    <div class="message-time"><?php echo date('M d, H:i', strtotime($msg['sent_at'])); ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <form method="POST" class="message-input">
                            <input type="hidden" name="other_user" value="<?php echo $other_user_id; ?>">
                            <input type="text" name="message" placeholder="Type your message..." required>
                            <button type="submit">Send</button>
                        </form>
                    <?php else: ?>
                        <p>Select a conversation to start chatting.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>