<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

$message = '';
$message_type = '';
$service_id = isset($_GET['service']) ? (int)$_GET['service'] : 0;
$service_name = '';

// If a service ID is provided, fetch its name
if ($service_id) {
    $stmt = $conn->prepare("SELECT name FROM services WHERE id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    if ($service) {
        $service_name = $service['name'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_inquiry'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid security token.";
        $message_type = 'error';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $inquiry = trim($_POST['inquiry']);

        $errors = [];
        if (empty($name)) $errors[] = "Your name is required.";
        if (empty($email)) $errors[] = "Email address is required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
        if (empty($subject)) $errors[] = "Subject is required.";
        if (empty($inquiry)) $errors[] = "Inquiry message is required.";

        if (empty($errors)) {
            // Store in database
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, service_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $name, $email, $subject, $inquiry, $service_id);
            if ($stmt->execute()) {
                $message = "Your inquiry has been sent. We'll get back to you soon.";
                $message_type = 'success';
            } else {
                $message = "Failed to save inquiry. Please try again later.";
                $message_type = 'error';
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = 'error';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/ai-widget.css">
    <style>
        .contact-container {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="contact-container">
            <h1>Contact Us</h1>
            <?php if ($service_name): ?>
                <p>You are inquiring about: <strong><?php echo htmlspecialchars($service_name); ?></strong></p>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label>Your Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Your Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" class="form-control" required value="<?php echo $service_name ? "Inquiry about " . htmlspecialchars($service_name) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="inquiry" class="form-control" rows="6" required></textarea>
                </div>

                <button type="submit" name="send_inquiry" class="btn-submit">Send Inquiry</button>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>