<?php
/**
 * Core Functions for EduSmart Pro
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Get current user role
if (!function_exists('getUserRole')) {
    function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
}

// Check if user has permission
if (!function_exists('hasPermission')) {
    function hasPermission($required_role) {
        if (!isLoggedIn()) {
            return false;
        }
        
        $user_role = getUserRole();
        
        // Admin has all permissions
        if ($user_role == 'admin' || $user_role == 'super_admin') {
            return true;
        }
        
        // Check specific role
        return ($user_role == $required_role);
    }
}

// Redirect if not logged in
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: ' . SITE_URL . 'auth/login.php');
            exit();
        }
    }
}

// Redirect if not authorized - THIS IS THE MISSING FUNCTION!
if (!function_exists('requireRole')) {
    function requireRole($required_role) {
        // First check if logged in
        if (!isLoggedIn()) {
            header('Location: ' . SITE_URL . 'auth/login.php');
            exit();
        }
        
        // Then check if has required role
        if (!hasPermission($required_role)) {
            // Redirect to appropriate dashboard based on actual role
            $actual_role = getUserRole();
            if ($actual_role == 'admin' || $actual_role == 'super_admin') {
                header('Location: ' . SITE_URL . 'admin/dashboard.php');
            } elseif ($actual_role == 'teacher') {
                header('Location: ' . SITE_URL . 'teacher/dashboard.php');
            } elseif ($actual_role == 'student') {
                header('Location: ' . SITE_URL . 'student/dashboard.php');
            } else {
                header('Location: ' . SITE_URL . 'index.php');
            }
            exit();
        }
        
        // If we get here, user has the required role
        return true;
    }
}

// Log activity
if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $description, $conn) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        try {
            $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issss", $user_id, $action, $description, $ip, $user_agent);
            $stmt->execute();
        } catch (Exception $e) {
            // Silently fail - don't break the app for logging
            error_log("Log activity error: " . $e->getMessage());
        }
    }
}

// Generate unique ID
if (!function_exists('generateUniqueId')) {
    function generateUniqueId($prefix = '') {
        return $prefix . uniqid() . rand(1000, 9999);
    }
}

// Generate admission number
if (!function_exists('generateAdmissionNumber')) {
    function generateAdmissionNumber($year, $student_id) {
        return 'STU' . $year . str_pad($student_id, 4, '0', STR_PAD_LEFT);
    }
}

// Generate employee ID
if (!function_exists('generateEmployeeId')) {
    function generateEmployeeId($year, $teacher_id) {
        return 'TCH' . $year . str_pad($teacher_id, 4, '0', STR_PAD_LEFT);
    }
}

// Generate certificate number
if (!function_exists('generateCertificateNumber')) {
    function generateCertificateNumber() {
        return 'CERT-' . date('Y') . '-' . strtoupper(uniqid());
    }
}

// Format date
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'M d, Y') {
        if (empty($date)) return 'N/A';
        return date($format, strtotime($date));
    }
}

// Time ago function
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (empty($datetime)) return 'Unknown';
        
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return $diff . ' seconds ago';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . ' days ago';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . ' months ago';
        } else {
            return floor($diff / 31536000) . ' years ago';
        }
    }
}

// Calculate grade from percentage
if (!function_exists('calculateGrade')) {
    function calculateGrade($percentage) {
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 60) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }
}

// Get student by user ID
if (!function_exists('getStudentByUserId')) {
    function getStudentByUserId($user_id, $conn) {
        try {
            $query = "SELECT s.*, u.full_name, u.email, u.phone 
                      FROM students s 
                      JOIN users u ON s.user_id = u.id 
                      WHERE u.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error in getStudentByUserId: " . $e->getMessage());
            return null;
        }
    }
}

// Get teacher by user ID
if (!function_exists('getTeacherByUserId')) {
    function getTeacherByUserId($user_id, $conn) {
        try {
            $query = "SELECT t.*, u.full_name, u.email, u.phone 
                      FROM teachers t 
                      JOIN users u ON t.user_id = u.id 
                      WHERE u.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error in getTeacherByUserId: " . $e->getMessage());
            return null;
        }
    }
}

// Check if student is enrolled in course
if (!function_exists('isEnrolled')) {
    function isEnrolled($student_id, $course_id, $conn) {
        try {
            $query = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $student_id, $course_id);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        } catch (Exception $e) {
            error_log("Error in isEnrolled: " . $e->getMessage());
            return false;
        }
    }
}

// Get course progress
if (!function_exists('getCourseProgress')) {
    function getCourseProgress($student_id, $course_id, $conn) {
        try {
            $query = "SELECT progress FROM enrollments WHERE student_id = ? AND course_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $student_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            return $row['progress'] ?? 0;
        } catch (Exception $e) {
            error_log("Error in getCourseProgress: " . $e->getMessage());
            return 0;
        }
    }
}

// Upload file
if (!function_exists('uploadFile')) {
    function uploadFile($file, $target_dir, $allowed_types = ['jpg','jpeg','png','pdf','doc','docx','ppt','pptx','txt','mp4']) {
        $target_file = $target_dir . basename($file['name']);
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check file size (max 10MB)
        if ($file['size'] > 10000000) {
            return ['success' => false, 'message' => 'File too large (max 10MB)'];
        }
        
        // Allow certain file formats
        if (!in_array($file_type, $allowed_types)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Create directory if not exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Generate unique filename
        $new_filename = uniqid() . '.' . $file_type;
        $target_path = $target_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            return ['success' => true, 'filename' => $new_filename, 'path' => $target_path];
        }
        
        return ['success' => false, 'message' => 'Upload failed'];
    }
}

// Generate QR Code URL
if (!function_exists('generateQRCode')) {
    function generateQRCode($data, $size = 150) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data);
    }
}

// Send email (basic)
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
}
// Award points to a student and update level
function awardPoints($student_id, $points, $conn) {
    $stmt = $conn->prepare("UPDATE students SET points = points + ? WHERE id = ?");
    $stmt->bind_param("ii", $points, $student_id);
    $stmt->execute();

    // Update level based on points (simple formula)
    $new_level = floor($points / 100) + 1;
    $conn->query("UPDATE students SET level = $new_level WHERE id = $student_id");
}

// Check and award badges based on criteria
function checkAndAwardBadges($student_id, $conn) {
    // Get student stats
    $stats = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE student_id = $student_id) as enrollments,
            (SELECT COUNT(*) FROM lesson_views WHERE student_id = $student_id) as lessons_viewed,
            (SELECT COUNT(*) FROM results WHERE student_id = $student_id AND percentage >= 90) as high_scores,
            (SELECT COUNT(*) FROM enrollments WHERE student_id = $student_id AND progress >= 100) as courses_completed
    ")->fetch_assoc();

    // Define badge criteria
    $badges = $conn->query("SELECT * FROM badges");
    while ($badge = $badges->fetch_assoc()) {
        $award = false;
        switch ($badge['criteria']) {
            case 'first_enrollment':
                $award = ($stats['enrollments'] >= 1);
                break;
            case 'lessons_completed_5':
                $award = ($stats['lessons_viewed'] >= 5);
                break;
            case 'exam_high_score':
                $award = ($stats['high_scores'] >= 1);
                break;
            case 'course_completed':
                $award = ($stats['courses_completed'] >= 1);
                break;
            // Add more as needed
        }
        if ($award) {
            // Check if already awarded
            $check = $conn->query("SELECT id FROM student_badges WHERE student_id = $student_id AND badge_id = {$badge['id']}");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO student_badges (student_id, badge_id) VALUES ($student_id, {$badge['id']})");
            }
        }
    }
}
?>