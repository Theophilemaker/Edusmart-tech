<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/ai-widget.css">
    <meta name="user-role" content="admin">
</head>
<body>
    <h1>AI Assistant Test</h1>
    <script src="assets/js/ai-widget.js"></script>
</body>
</html>