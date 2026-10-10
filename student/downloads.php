<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student database ID
$student_db_id = 0;
try {
    $query = "SELECT id FROM students WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_db_id = $result->fetch_assoc()['id'] ?? 0;
} catch (Exception $e) {
    error_log("Error getting student ID: " . $e->getMessage());
}

// Get enrolled courses with materials
$courses = [];
if ($student_db_id > 0) {
    try {
        $query = "SELECT c.*, 
                         (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_files,
                         (SELECT SUM(file_size) FROM lessons WHERE course_id = c.id) as total_size
                  FROM courses c
                  JOIN enrollments e ON c.id = e.course_id
                  WHERE e.student_id = ?
                  ORDER BY c.title";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting courses: " . $e->getMessage());
    }
}

// Get all materials for a specific course
$course_materials = [];
if (isset($_GET['course'])) {
    $course_id = intval($_GET['course']);
    try {
        $query = "SELECT l.*, c.title as course_title 
                  FROM lessons l
                  JOIN courses c ON l.course_id = c.id
                  JOIN enrollments e ON c.id = e.course_id
                  WHERE l.course_id = ? AND e.student_id = ?
                  ORDER BY l.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $course_id, $student_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $course_materials[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting materials: " . $e->getMessage());
    }
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes === null) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Get file icon
function getFileIcon($file_type) {
    switch($file_type) {
        case 'video': return 'fa-video';
        case 'image': return 'fa-image';
        case 'presentation': return 'fa-file-powerpoint';
        case 'document': return 'fa-file-pdf';
        case 'audio': return 'fa-music';
        case 'archive': return 'fa-file-archive';
        default: return 'fa-file';
    }
}

// Get file color
function getFileColor($file_type) {
    switch($file_type) {
        case 'video': return '#4cc9f0';
        case 'image': return '#f59e0b';
        case 'presentation': return '#f72585';
        case 'document': return '#10b981';
        case 'audio': return '#8b5cf6';
        case 'archive': return '#6c757d';
        default: return '#667eea';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Materials - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 2em;
        }

        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .course-card h3 {
            margin: 0 0 10px;
            color: #333;
        }

        .course-stats {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .course-progress {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            width: 0%;
        }

        .materials-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .back-btn {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .material-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .material-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .material-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: white;
        }

        .material-info {
            flex: 1;
        }

        .material-info h4 {
            margin: 0 0 5px;
            color: #333;
        }

        .material-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.85em;
            margin-bottom: 5px;
        }

        .material-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4em;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-download"></i> Course Materials</h1>
            <p>Access and download all your learning resources</p>
        </div>

        <?php if (isset($_GET['course']) && !empty($course_materials)): ?>
            <!-- Materials for specific course -->
            <div class="materials-header">
                <h2><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($course_materials[0]['course_title']); ?></h2>
                <a href="downloads.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Courses</a>
            </div>

            <div class="materials-grid">
                <?php foreach($course_materials as $material): ?>
                <div class="material-card">
                    <div class="material-icon" style="background: <?php echo getFileColor($material['file_type']); ?>">
                        <i class="fas <?php echo getFileIcon($material['file_type']); ?>"></i>
                    </div>
                    <div class="material-info">
                        <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                        <div class="material-meta">
                            <span><i class="fas fa-tag"></i> <?php echo ucfirst($material['file_type']); ?></span>
                            <span><i class="fas fa-database"></i> <?php echo formatFileSize($material['file_size']); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($material['created_at'])); ?></span>
                        </div>
                        <?php if (!empty($material['description'])): ?>
                        <p style="color: #666; font-size: 0.9em;"><?php echo htmlspecialchars($material['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="material-actions">
                        <a href="../<?php echo $material['file_path']; ?>" target="_blank" class="btn btn-primary" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="../<?php echo $material['file_path']; ?>" download class="btn btn-success" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Course list -->
            <h2 style="margin-bottom: 20px;">Your Enrolled Courses</h2>
            
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Courses Yet</h3>
                    <p>You haven't enrolled in any courses yet.</p>
                    <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                </div>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach($courses as $course): ?>
                    <a href="?course=<?php echo $course['id']; ?>" class="course-card">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <div class="course-stats">
                            <span><i class="fas fa-file"></i> <?php echo $course['total_files']; ?> files</span>
                            <span><i class="fas fa-database"></i> <?php echo formatFileSize($course['total_size']); ?></span>
                        </div>
                        <div class="course-progress">
                            <div class="progress-fill" style="width: <?php echo rand(30, 100); ?>%"></div>
                        </div>
                        <p style="color: #666; margin-top: 10px;">
                            <i class="fas fa-arrow-right"></i> Click to view materials
                        </p>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($_GET['course']) && empty($course_materials)): ?>
                <div class="empty-state" style="margin-top: 30px;">
                    <i class="fas fa-file"></i>
                    <h3>No Materials Yet</h3>
                    <p>The instructor hasn't uploaded any materials for this course yet.</p>
                    <a href="downloads.php" class="btn btn-primary">Back to Courses</a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>