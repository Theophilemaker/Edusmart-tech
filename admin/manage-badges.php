<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle add/edit/delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM badges WHERE id = $id");
    header("Location: manage-badges.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_badge'])) {
        $id = (int)($_POST['badge_id'] ?? 0);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $criteria = trim($_POST['criteria']);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE badges SET name=?, description=?, icon=?, criteria=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $description, $icon, $criteria, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO badges (name, description, icon, criteria) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $description, $icon, $criteria);
        }
        if ($stmt->execute()) {
            $message = "Badge saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $message_type = 'error';
        }
    }
}

$badges = $conn->query("SELECT * FROM badges ORDER BY id DESC");
$edit_badge = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_badge = $conn->query("SELECT * FROM badges WHERE id = $id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Badges</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .badge-form { max-width: 500px; margin-bottom: 30px; }
        .badge-table { width: 100%; }
        .icon-preview { font-size: 2em; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h1>Manage Badges</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="badge-form">
            <h2><?php echo $edit_badge ? 'Edit Badge' : 'Add New Badge'; ?></h2>
            <form method="POST">
                <?php if ($edit_badge): ?>
                    <input type="hidden" name="badge_id" value="<?php echo $edit_badge['id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Badge Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $edit_badge['name'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo $edit_badge['description'] ?? ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>Icon (FontAwesome class, e.g., fa-star)</label>
                    <input type="text" name="icon" class="form-control" value="<?php echo $edit_badge['icon'] ?? 'fa-medal'; ?>" required>
                    <small>Preview: <i class="fas <?php echo $edit_badge['icon'] ?? 'fa-medal'; ?>"></i></small>
                </div>
                <div class="form-group">
                    <label>Criteria (internal code, e.g., first_enrollment)</label>
                    <input type="text" name="criteria" class="form-control" value="<?php echo $edit_badge['criteria'] ?? ''; ?>" required>
                </div>
                <button type="submit" name="save_badge" class="btn-primary">Save Badge</button>
                <?php if ($edit_badge): ?>
                    <a href="manage-badges.php" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Existing Badges</h2>
        <table class="table badge-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Criteria</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($badge = $badges->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $badge['id']; ?></td>
                    <td><i class="fas <?php echo $badge['icon']; ?>"></i></td>
                    <td><?php echo htmlspecialchars($badge['name']); ?></td>
                    <td><?php echo htmlspecialchars($badge['description']); ?></td>
                    <td><?php echo $badge['criteria']; ?></td>
                    <td>
                        <a href="?edit=<?php echo $badge['id']; ?>" class="btn-action edit">Edit</a>
                        <a href="?delete=<?php echo $badge['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this badge?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script src="../assets/js/ai-widget.js"></script>
</body>
</html>