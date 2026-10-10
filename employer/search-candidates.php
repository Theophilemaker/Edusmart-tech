<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
    header("Location: ../auth/login.php");
    exit();
}

// Get actual columns of students table
$columns_result = $conn->query("SHOW COLUMNS FROM students");
$student_columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $student_columns[] = $col['Field'];
}

$search = $_GET['search'] ?? '';

// Build SELECT clause dynamically
$select = "u.id, u.full_name, u.email, s.admission_number";
if (in_array('class', $student_columns)) {
    $select .= ", s.class";
}
if (in_array('address', $student_columns)) {
    $select .= ", s.address";
}

$query = "SELECT $select FROM users u JOIN students s ON u.id = s.user_id WHERE u.role = 'student'";
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $query .= " AND (u.full_name LIKE '%$search_esc%' OR u.email LIKE '%$search_esc%')";
}
$query .= " ORDER BY u.full_name";
$candidates = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Search Candidates</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ai-widget.css">
    <meta name="user-role" content="employer">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Search Candidates</h1>
        </div>
        <form method="GET" class="filter-bar" style="background:white; padding:20px; border-radius:10px; margin-bottom:20px;">
            <input type="text" name="search" placeholder="Name or email" value="<?php echo htmlspecialchars($search); ?>" style="flex:2; padding:10px;">
            <button type="submit" class="btn-action view">Search</button>
            <a href="search-candidates.php" class="btn-action edit">Reset</a>
        </form>

        <?php if ($candidates->num_rows == 0): ?>
            <p>No candidates found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Admission No.</th>
                        <?php if (in_array('class', $student_columns)): ?>
                            <th>Class</th>
                        <?php endif; ?>
                        <?php if (in_array('address', $student_columns)): ?>
                            <th>Location</th>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($cand = $candidates->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cand['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($cand['email']); ?></td>
                        <td><?php echo $cand['admission_number']; ?></td>
                        <?php if (in_array('class', $student_columns)): ?>
                            <td><?php echo $cand['class'] ?? '—'; ?></td>
                        <?php endif; ?>
                        <?php if (in_array('address', $student_columns)): ?>
                            <td><?php echo htmlspecialchars($cand['address'] ?? '—'); ?></td>
                        <?php endif; ?>
                        <td>
                            <a href="chat.php?user=<?php echo $cand['id']; ?>" class="btn-action view">Message</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>