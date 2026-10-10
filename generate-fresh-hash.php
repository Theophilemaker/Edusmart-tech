<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password<br>";
echo "New Hash: $hash<br>";
echo "<br>Copy this SQL and run in phpMyAdmin:<br>";
echo "<code>UPDATE users SET password = '$hash' WHERE email = 'admin@edusmart.com';</code>";
?>