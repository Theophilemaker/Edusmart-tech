<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
$role = $_SESSION['role'];
header("Location: $role/profile.php");
exit();