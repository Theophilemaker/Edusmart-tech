<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login and role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Create backups directory if not exists
$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'download') {
        // Find latest backup
        $files = glob($backup_dir . '*.sql');
        if (!empty($files)) {
            $latest = array_reduce($files, function($a, $b) {
                return filemtime($a) > filemtime($b) ? $a : $b;
            });
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($latest) . '"');
            header('Content-Length: ' . filesize($latest));
            readfile($latest);
            exit();
        } else {
            // Create new backup if none exists
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_dir . $filename;
            
            // Get database credentials from config
            $config = include '../config/database.php';
            
            // Create backup using mysqldump (if available)
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );
            
            system($command, $output);
            
            if (file_exists($filepath)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit();
            } else {
                echo "Backup creation failed. Please check server configuration.";
            }
        }
    } elseif ($_GET['action'] == 'create') {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Fallback: Generate backup using PHP
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $output = "-- EduSmart Pro Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            // Get create table syntax
            $result = $conn->query("SHOW CREATE TABLE $table");
            $row = $result->fetch_row();
            $output .= "\n\n" . $row[1] . ";\n\n";
            
            // Get table data
            $result = $conn->query("SELECT * FROM $table");
            while ($row = $result->fetch_assoc()) {
                $values = array_map(function($value) use ($conn) {
                    return "'" . $conn->real_escape_string($value) . "'";
                }, array_values($row));
                $output .= "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n";
            }
        }
        
        file_put_contents($filepath, $output);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }
}

header("Location: settings.php");
exit();
?>