<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_advice'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message']);

    $errors = [];
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($message)) $errors[] = "Message is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO advice_requests (name, email, phone, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $phone, $message);
        if ($stmt->execute()) {
            $_SESSION['advice_success'] = "Your request has been submitted. We'll get back to you soon.";
        } else {
            $_SESSION['advice_error'] = "Database error. Please try again.";
        }
    } else {
        $_SESSION['advice_error'] = implode("<br>", $errors);
    }
    header("Location: products.php");
    exit();
}