<?php
require_once 'config/database.php';
require_once 'includes/notifications.php';

$notify = new NotificationSystem($conn);

// Test email
$to = "admin@edusmart.com"; // Change to your email for testing
$subject = "Test Email from EduSmart Pro";
$message = "
<h3>Email Test</h3>
<p>This is a test email to verify that the email system is working.</p>
<p>If you received this, email is configured correctly!</p>
";

$result = $notify->sendEmail($to, $subject, $message);

if ($result) {
    echo "✅ Test email sent successfully to $to<br>";
    echo "Check your inbox (and spam folder)";
} else {
    echo "❌ Failed to send email. Check your PHP mail configuration.";
}
?>