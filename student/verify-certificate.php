<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$certificate = null;
$error = '';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    try {
        $query = "SELECT c.*, co.title as course_title, u.full_name as student_name,
                         co.description, c.issue_date
                  FROM certificates c
                  JOIN courses co ON c.course_id = co.id
                  JOIN students s ON c.student_id = s.id
                  JOIN users u ON s.user_id = u.id
                  WHERE c.certificate_no = ? AND c.status = 'issued'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $certificate = $result->fetch_assoc();
        } else {
            $error = "Invalid or not found certificate number.";
        }
    } catch (Exception $e) {
        $error = "Error verifying certificate.";
        error_log("Verification error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
        }
        .verification-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        .search-box input {
            flex: 1;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
        }
        .search-box button {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1em;
        }
        .certificate-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }
        .certificate-info.valid {
            border-left: 4px solid #4cc9f0;
        }
        .certificate-info.invalid {
            border-left: 4px solid #f72585;
        }
        .student-name {
            font-size: 2em;
            color: #333;
            margin: 15px 0;
        }
        .course-title {
            font-size: 1.5em;
            color: #667eea;
            margin: 10px 0;
        }
        .badge {
            display: inline-block;
            padding: 8px 20px;
            background: #4cc9f0;
            color: white;
            border-radius: 20px;
            margin: 10px 0;
        }
        .qr-code {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            display: inline-block;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-card">
            <div class="header">
                <h1><i class="fas fa-certificate"></i> Certificate Verification</h1>
                <p>Enter certificate number to verify authenticity</p>
            </div>
            
            <form method="GET" class="search-box">
                <input type="text" name="code" placeholder="Enter certificate number (e.g., CERT-2024-XXXX...)" value="<?php echo htmlspecialchars($_GET['code'] ?? ''); ?>" required>
                <button type="submit"><i class="fas fa-search"></i> Verify</button>
            </form>
            
            <?php if ($certificate): ?>
                <div class="certificate-info valid">
                    <i class="fas fa-check-circle" style="font-size: 3em; color: #4cc9f0; margin-bottom: 15px;"></i>
                    <h2>✓ Valid Certificate</h2>
                    <p>This certificate has been verified and is authentic.</p>
                    
                    <div style="margin: 30px 0;">
                        <p>Presented to</p>
                        <div class="student-name"><?php echo htmlspecialchars($certificate['student_name']); ?></div>
                        
                        <p>for successfully completing</p>
                        <div class="course-title"><?php echo htmlspecialchars($certificate['course_title']); ?></div>
                        
                        <div style="margin: 20px 0;">
                            <span class="badge"><i class="fas fa-calendar"></i> Issued: <?php echo date('F d, Y', strtotime($certificate['issue_date'])); ?></span>
                        </div>
                        
                        <div class="qr-code">
                            <img src="<?php echo $certificate['qr_code']; ?>" alt="QR Code" style="width: 100px;">
                            <p style="margin-top: 10px;">Certificate ID: <?php echo $certificate['certificate_no']; ?></p>
                        </div>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="certificate-info invalid">
                    <i class="fas fa-exclamation-circle" style="font-size: 3em; color: #f72585; margin-bottom: 15px;"></i>
                    <h2>✗ Invalid Certificate</h2>
                    <p><?php echo $error; ?></p>
                    <p style="margin-top: 20px;">Please check the certificate number and try again.</p>
                </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>