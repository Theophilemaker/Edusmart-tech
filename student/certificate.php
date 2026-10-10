<?php

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check role
if ($_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$notify = new NotificationSystem($conn);
$message = '';
$message_type = '';

// Get student database ID
$student_db_id = 0;
try {
    $query = "SELECT id FROM students WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $student_db_id = $row['id'];
    }
} catch (Exception $e) {
    error_log("Error getting student ID: " . $e->getMessage());
}

// Generate certificate for completed course
if (isset($_GET['generate']) && isset($_GET['course']) && $student_db_id > 0) {
    $course_id = (int)$_GET['course'];
    
    try {
        // Check if course is completed (progress >= 100)
        $query = "SELECT * FROM enrollments 
                  WHERE student_id = ? AND course_id = ? AND progress >= 100";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $student_db_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Check if certificate already exists
            $check = $conn->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
            $check->bind_param("ii", $student_db_id, $course_id);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                // Generate unique certificate number
                $cert_no = 'CERT-' . date('Y') . '-' . strtoupper(uniqid());
                
                // Generate QR code data
                $qr_data = SITE_URL . 'verify-certificate.php?code=' . $cert_no;
                $qr_code = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qr_data);
                
                // Get student and course details
                $student_query = "SELECT u.full_name, u.email, u.phone 
                                 FROM students s
                                 JOIN users u ON s.user_id = u.id
                                 WHERE s.id = ?";
                $stmt = $conn->prepare($student_query);
                $stmt->bind_param("i", $student_db_id);
                $stmt->execute();
                $student_info = $stmt->get_result()->fetch_assoc();
                
                $course_query = "SELECT title, description FROM courses WHERE id = ?";
                $stmt = $conn->prepare($course_query);
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $course_info = $stmt->get_result()->fetch_assoc();
                
                // Insert certificate
                $insert = "INSERT INTO certificates (student_id, course_id, certificate_no, qr_code, issue_date, status) 
                          VALUES (?, ?, ?, ?, NOW(), 'issued')";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("iiss", $student_db_id, $course_id, $cert_no, $qr_code);
                
                if ($stmt->execute()) {
                    $cert_id = $conn->insert_id;
                    
                    // Send notification
                    if ($student_info && $course_info) {
                        $notify->sendCertificateNotification(
                            $student_info['email'],
                            $student_info['phone'],
                            $course_info['title'],
                            $cert_no
                        );
                    }
                    
                    $message = "Congratulations! Your certificate has been generated successfully.";
                    $message_type = 'success';
                    
                    // Redirect to view the certificate
                    header("Location: certificate.php?view=" . $cert_id);
                    exit();
                }
            } else {
                $message = "Certificate already exists for this course.";
                $message_type = 'info';
            }
        } else {
            $message = "You need to complete the course first (100% progress required).";
            $message_type = 'warning';
        }
    } catch (Exception $e) {
        $message = "Error generating certificate: " . $e->getMessage();
        $message_type = 'error';
        error_log("Certificate generation error: " . $e->getMessage());
    }
}

// Download certificate
if (isset($_GET['download']) && isset($_GET['id'])) {
    $cert_id = (int)$_GET['id'];
    
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
        
        if ($cert) {
            // Generate HTML certificate
            $html = generateCertificateHTML($cert);
            
            // Save to file
            $filename = 'certificate_' . $cert['certificate_no'] . '.html';
            $filepath = '../uploads/certificates/' . $filename;
            
            file_put_contents($filepath, $html);
            
            // Force download
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit();
        }
    } catch (Exception $e) {
        error_log("Download error: " . $e->getMessage());
    }
}

// View certificate
if (isset($_GET['view']) && isset($_GET['id'])) {
    $cert_id = (int)$_GET['id'];
    
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
        $view_cert = $stmt->get_result()->fetch_assoc();
    } catch (Exception $e) {
        error_log("View error: " . $e->getMessage());
    }
}

// Get student's certificates
$certificates = [];
if ($student_db_id > 0) {
    try {
        $query = "SELECT c.*, co.title as course_title, co.description,
                         u.full_name as student_name
                  FROM certificates c
                  JOIN courses co ON c.course_id = co.id
                  JOIN students s ON c.student_id = s.id
                  JOIN users u ON s.user_id = u.id
                  WHERE c.student_id = ? AND c.status = 'issued'
                  ORDER BY c.issue_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $certificates[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting certificates: " . $e->getMessage());
    }
}

// Get completed courses without certificates
$completed_courses = [];
if ($student_db_id > 0) {
    try {
        $query = "SELECT c.id, c.title, c.description,
                         e.progress
                  FROM courses c
                  JOIN enrollments e ON c.id = e.course_id
                  WHERE e.student_id = ? AND e.progress >= 100
                  AND c.id NOT IN (SELECT course_id FROM certificates WHERE student_id = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $student_db_id, $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $completed_courses[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting completed courses: " . $e->getMessage());
    }
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/protect-design.css">
    <style>
        .certificate-card {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .certificate-card::before {
            content: '✦';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 150px;
            opacity: 0.2;
            transform: rotate(15deg);
        }
        .certificate-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .certificate-number {
            font-family: monospace;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.9em;
        }
        .qr-code-small {
            width: 60px;
            height: 60px;
            background: white;
            padding: 5px;
            border-radius: 5px;
        }
        .badge-achievement {
            background: gold;
            color: #333;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        .completed-course-card {
            background: linear-gradient(135deg, #fff9c4 0%, #fff59d 100%);
            color: #333;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .completed-course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        .view-certificate-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        .view-certificate-content {
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></h3>
                <p>Student</p>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="courses.php" class="menu-item">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
                <a href="learning.php" class="menu-item">
                    <i class="fas fa-play-circle"></i>
                    <span>Learning</span>
                </a>
                <a href="exams.php" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Exams</span>
                </a>
                <a href="certificate.php" class="menu-item active">
                    <i class="fas fa-certificate"></i>
                    <span>Certificate</span>
                </a>
                <a href="../auth/logout.php" class="menu-item" style="margin-top: 50px;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <h1>My Certificates</h1>
                    <p>View and download your certificates</p>
                </div>
                
                <div class="user-profile">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($completed_courses); ?></span>
                    </div>
                    
                    <div class="profile-dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'Student'); ?>&background=4361ee&color=fff&size=45" class="profile-img">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>" style="background: <?php 
                    echo $message_type == 'success' ? '#d4edda' : 
                        ($message_type == 'error' ? '#f8d7da' : '#fff3cd'); 
                ?>; color: <?php 
                    echo $message_type == 'success' ? '#155724' : 
                        ($message_type == 'error' ? '#721c24' : '#856404'); 
                ?>; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid <?php 
                    echo $message_type == 'success' ? '#4cc9f0' : 
                        ($message_type == 'error' ? '#f72585' : '#ffc107'); 
                ?>;">
                    <i class="fas <?php 
                        echo $message_type == 'success' ? 'fa-check-circle' : 
                            ($message_type == 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); 
                    ?>"></i> 
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- View Certificate Modal -->
            <?php if (isset($view_cert)): ?>
            <div class="view-certificate-modal" id="viewModal">
                <div class="view-certificate-content">
                    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                        <button onclick="closeViewModal()" style="background: none; border: none; font-size: 1.5em; cursor: pointer;">&times;</button>
                    </div>
                    <?php echo generateCertificateHTML($view_cert); ?>
                    <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                        <a href="?download&id=<?php echo $view_cert['id']; ?>" class="btn-enroll">
                            <i class="fas fa-download"></i> Download PDF
                        </a>
                        <button onclick="window.print()" class="btn-enroll" style="background: var(--info);">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>
            <script>
                document.getElementById('viewModal').style.display = 'flex';
                function closeViewModal() {
                    document.getElementById('viewModal').style.display = 'none';
                    window.location.href = 'certificate.php';
                }
            </script>
            <?php endif; ?>
            
            <!-- Completed Courses Without Certificates -->
            <?php if (!empty($completed_courses)): ?>
            <div class="table-container" style="margin-bottom: 30px;">
                <div class="table-header">
                    <h3><i class="fas fa-trophy" style="color: gold;"></i> Courses Ready for Certification</h3>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach($completed_courses as $course): ?>
                    <div class="completed-course-card">
                        <i class="fas fa-certificate" style="font-size: 3em; color: #f57c00; margin-bottom: 15px;"></i>
                        <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                        <p style="color: #666; margin: 10px 0;">Progress: <?php echo $course['progress']; ?>%</p>
                        <p style="color: #666; margin: 10px 0;">You've completed this course!</p>
                        <a href="?generate&course=<?php echo $course['id']; ?>" class="btn-enroll" style="margin-top: 15px; display: inline-block; background: var(--success);">
                            <i class="fas fa-award"></i> Generate Certificate
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Certificates Grid -->
            <?php if (empty($certificates) && empty($completed_courses)): ?>
                <div class="table-container" style="text-align: center; padding: 80px;">
                    <i class="fas fa-certificate" style="font-size: 5em; color: #ccc; margin-bottom: 20px;"></i>
                    <h3>No Certificates Yet</h3>
                    <p style="color: #666; margin: 20px 0;">Complete courses with 100% progress to earn certificates</p>
                    <a href="courses.php" class="btn-enroll" style="display: inline-block;">
                        Browse Courses
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($certificates)): ?>
                <div class="table-container">
                    <h3><i class="fas fa-certificate"></i> My Certificates (<?php echo count($certificates); ?>)</h3>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-top: 20px;">
                    <?php foreach($certificates as $cert): ?>
                    <div class="certificate-card">
                        <div style="position: relative; z-index: 1;">
                            <!-- Certificate Header -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                                <div>
                                    <i class="fas fa-award" style="font-size: 2.5em;"></i>
                                </div>
                                <div>
                                    <span class="badge-achievement">
                                        <i class="fas fa-star"></i> ACHIEVEMENT
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Certificate Body -->
                            <div style="text-align: center;">
                                <h2 style="font-size: 2.2em; margin-bottom: 10px;">CERTIFICATE OF COMPLETION</h2>
                                
                                <p style="opacity: 0.9; margin: 20px 0;">This is to certify that</p>
                                
                                <h3 style="font-size: 2.5em; margin: 15px 0; font-family: 'Playfair Display', serif; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                                    <?php echo htmlspecialchars($cert['student_name']); ?>
                                </h3>
                                
                                <p style="opacity: 0.9;">has successfully completed the course</p>
                                
                                <h4 style="font-size: 1.8em; margin: 20px 0; background: rgba(255,255,255,0.2); padding: 15px; border-radius: 50px; display: inline-block;">
                                    <?php echo htmlspecialchars($cert['course_title']); ?>
                                </h4>
                            </div>
                            
                            <!-- Certificate Footer -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px;">
                                <div>
                                    <p style="font-size: 0.9em; opacity: 0.8;">Issue Date</p>
                                    <p style="font-weight: bold;"><?php echo date('F d, Y', strtotime($cert['issue_date'])); ?></p>
                                </div>
                                
                                <div>
                                    <img src="<?php echo $cert['qr_code']; ?>" alt="QR Code" class="qr-code-small">
                                </div>
                                
                                <div>
                                    <p style="font-size: 0.9em; opacity: 0.8;">Certificate ID</p>
                                    <p class="certificate-number"><?php echo $cert['certificate_no']; ?></p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 10px; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px;">
                                <a href="?download&id=<?php echo $cert['id']; ?>" class="btn-enroll" style="background: white; color: #43e97b; flex: 1;">
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <a href="?view&id=<?php echo $cert['id']; ?>" class="btn-enroll" style="background: rgba(255,255,255,0.2); flex: 1;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn-enroll" style="background: rgba(255,255,255,0.2); flex: 1;" onclick="shareCertificate('<?php echo $cert['certificate_no']; ?>', '<?php echo addslashes($cert['course_title']); ?>')">
                                    <i class="fas fa-share-alt"></i> Share
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Certificate Stats -->
            <?php if (!empty($certificates)): ?>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px;">
                <div class="stat-card" style="text-align: center;">
                    <i class="fas fa-certificate" style="font-size: 2em; color: var(--primary);"></i>
                    <h3 style="margin: 10px 0;"><?php echo count($certificates); ?></h3>
                    <p>Total Certificates</p>
                </div>
                <div class="stat-card" style="text-align: center;">
                    <i class="fas fa-calendar-alt" style="font-size: 2em; color: var(--success);"></i>
                    <?php
                    $latest = !empty($certificates) ? $certificates[0]['issue_date'] : null;
                    ?>
                    <h3 style="margin: 10px 0;"><?php echo $latest ? date('M Y', strtotime($latest)) : 'N/A'; ?></h3>
                    <p>Latest Certificate</p>
                </div>
                <div class="stat-card" style="text-align: center;">
                    <i class="fas fa-star" style="font-size: 2em; color: gold;"></i>
                    <h3 style="margin: 10px 0;">Achievement</h3>
                    <p>Certified Learner</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function shareCertificate(certNo, courseTitle) {
            const shareText = `I've earned my certificate for completing ${courseTitle} at EduSmart Pro!`;
            const shareUrl = window.location.origin + '/edusmart-pro/verify-certificate.php?code=' + certNo;
            
            if (navigator.share) {
                navigator.share({
                    title: 'My Course Certificate',
                    text: shareText,
                    url: shareUrl
                }).catch(console.error);
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Certificate link copied to clipboard! You can now share it.');
                }).catch(() => {
                    prompt('Copy this link to share your certificate:', shareUrl);
                });
            }
        }
    </script>
    
    <script src="../assets/js/hover-effects.js"></script>
</body>
</html>