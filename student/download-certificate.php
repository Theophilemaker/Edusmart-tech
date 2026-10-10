<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$cert_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($cert_id <= 0) {
    header("Location: certificate.php");
    exit();
}

// Get student database ID
try {
    $query = "SELECT id FROM students WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_db_id = $result->fetch_assoc()['id'] ?? 0;
} catch (Exception $e) {
    $student_db_id = 0;
}

// Get certificate
try {
    $query = "SELECT c.*, co.title as course_title, co.description,
                     u.full_name as student_name, u.email
              FROM certificates c
              JOIN courses co ON c.course_id = co.id
              JOIN students s ON c.student_id = s.id
              JOIN users u ON s.user_id = u.id
              WHERE c.id = ? AND c.student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $cert_id, $student_db_id);
    $stmt->execute();
    $cert = $stmt->get_result()->fetch_assoc();
    
    if (!$cert) {
        header("Location: certificate.php");
        exit();
    }
    
    // Generate PDF (simplified HTML version)
    $html = generateCertificateHTML($cert);
    
    // Set headers for download
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="certificate_' . $cert['certificate_no'] . '.html"');
    header('Content-Length: ' . strlen($html));
    
    echo $html;
    exit();
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    header("Location: certificate.php?error=download_failed");
    exit();
}

// Function to generate certificate HTML
function generateCertificateHTML($cert) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Certificate of Completion</title>
        <style>
            body {
                margin: 0;
                padding: 40px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: "Times New Roman", serif;
            }
            .certificate {
                max-width: 1000px;
                background: white;
                padding: 60px;
                border-radius: 30px;
                box-shadow: 0 30px 60px rgba(0,0,0,0.3);
                position: relative;
                border: 20px solid transparent;
                border-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-image-slice: 1;
            }
            .certificate::before {
                content: "✦";
                position: absolute;
                top: 20px;
                left: 20px;
                font-size: 40px;
                color: #667eea;
                opacity: 0.3;
            }
            .certificate::after {
                content: "✦";
                position: absolute;
                bottom: 20px;
                right: 20px;
                font-size: 40px;
                color: #764ba2;
                opacity: 0.3;
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
            }
            .header h1 {
                font-size: 3.5em;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin: 0;
                letter-spacing: 5px;
            }
            .header h2 {
                color: #666;
                font-weight: normal;
                margin-top: 10px;
            }
            .content {
                text-align: center;
                margin: 40px 0;
            }
            .content p {
                font-size: 1.3em;
                color: #666;
            }
            .student-name {
                font-size: 3em;
                font-weight: bold;
                color: #333;
                margin: 20px 0;
                border-bottom: 2px solid #667eea;
                display: inline-block;
                padding-bottom: 10px;
            }
            .course-title {
                font-size: 2em;
                color: #667eea;
                margin: 20px 0;
            }
            .description {
                font-style: italic;
                color: #888;
                margin: 20px 0;
                line-height: 1.6;
            }
            .footer {
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
                margin-top: 50px;
            }
            .signature {
                text-align: center;
            }
            .signature-line {
                width: 200px;
                height: 2px;
                background: #333;
                margin: 10px 0;
            }
            .qr-code {
                width: 100px;
                height: 100px;
            }
            .certificate-number {
                text-align: center;
                margin-top: 30px;
                color: #999;
                font-family: monospace;
                font-size: 0.9em;
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
                <h2>Certificate of Completion</h2>
            </div>
            
            <div class="content">
                <p>This is proudly presented to</p>
                <div class="student-name">' . htmlspecialchars($cert['student_name']) . '</div>
                <p>for successfully completing the course</p>
                <div class="course-title">' . htmlspecialchars($cert['course_title']) . '</div>
                <div class="description">"' . htmlspecialchars($cert['description'] ?? '') . '"</div>
            </div>
            
            <div class="footer">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Principal</div>
                </div>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Date: ' . date('F d, Y', strtotime($cert['issue_date'])) . '</div>
                </div>
                
                <img src="' . $cert['qr_code'] . '" class="qr-code" alt="QR Code">
            </div>
            
            <div class="certificate-number">
                Certificate ID: ' . $cert['certificate_no'] . '
            </div>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}
?>