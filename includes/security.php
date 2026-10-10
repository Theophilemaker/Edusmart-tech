<?php
/**
 * Security Functions for EduSmart Pro
 * This file contains ONLY security-specific functions
 * (No duplicate functions from functions.php)
 */

// Prevent direct access
if (!defined('SITE_URL') && !isset($_SESSION)) {
    exit('Direct access not permitted');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    
    return $ipaddress;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (basic)
 */
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s]+$/', $phone);
}

/**
 * Check password strength
 */
function checkPasswordStrength($password) {
    $strength = 0;
    $messages = [];
    
    if (strlen($password) < 6) {
        $messages[] = "Password must be at least 6 characters";
    } else {
        $strength++;
    }
    
    if (preg_match('/[A-Z]/', $password)) {
        $strength++;
    } else {
        $messages[] = "Add at least one uppercase letter";
    }
    
    if (preg_match('/[a-z]/', $password)) {
        $strength++;
    } else {
        $messages[] = "Add at least one lowercase letter";
    }
    
    if (preg_match('/[0-9]/', $password)) {
        $strength++;
    } else {
        $messages[] = "Add at least one number";
    }
    
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        $strength++;
    } else {
        $messages[] = "Add at least one special character";
    }
    
    return [
        'strength' => $strength,
        'messages' => $messages,
        'score' => min(100, $strength * 20)
    ];
}

/**
 * Rate limiting
 */
function checkRateLimit($key, $limit = 60, $time = 60) {
    $session_key = 'rate_limit_' . $key;
    
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [
            'count' => 1,
            'first_request' => time()
        ];
        return true;
    }
    
    $data = $_SESSION[$session_key];
    
    if (time() - $data['first_request'] > $time) {
        // Reset after time period
        $_SESSION[$session_key] = [
            'count' => 1,
            'first_request' => time()
        ];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$session_key]['count']++;
    return true;
}

/**
 * Encrypt data (simple - for educational purposes)
 */
function encryptData($data, $key) {
    $iv = random_bytes(16);
    $cipher = "aes-256-gcm";
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv, $tag);
    return base64_encode($iv . $tag . $encrypted);
}

/**
 * Decrypt data
 */
function decryptData($data, $key) {
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $tag = substr($data, 16, 16);
    $encrypted = substr($data, 32);
    $cipher = "aes-256-gcm";
    return openssl_decrypt($encrypted, $cipher, $key, 0, $iv, $tag);
}
?>