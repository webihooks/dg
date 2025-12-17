<?php
// Start the session
session_start();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Handle form submission for new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($title) || empty($content)) {
        $error_message = "Title and content cannot be empty";
    } else {
        $insert_sql = "INSERT INTO announcements (title, content, created_by, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssii", $title, $content, $user_id, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Announcement created successfully!";
        } else {
            $error_message = "Error creating announcement: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle announcement deletion
if (isset($_GET['delete_id']) && $role === 'admin') {
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $success_message = "Announcement deleted successfully!";
    } else {
        $error_message = "Error deleting announcement: " . $conn->error;
    }
    $stmt->close();
}

// Handle announcement status change
if (isset($_GET['toggle_status'])) {
    $toggle_id = $_GET['toggle_status'];
    $update_sql = "UPDATE announcements SET is_active = NOT is_active WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $toggle_id);
    
    if ($stmt->execute()) {
        $success_message = "Announcement status updated!";
    } else {
        $error_message = "Error updating announcement status: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all announcements
$announcements = [];
$fetch_sql = "SELECT a.*, u.name as author_name FROM announcements a JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC";
$result = $conn->query($fetch_sql);
if ($result) {
    $announcements = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Announcements</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA Meta Tags -->
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Announcements</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>

                                <?php if ($role === 'admin'): ?>
                                <div class="mb-4">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                        Create New Announcement
                                    </button>
                                </div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Content</th>
                                                <th>Author</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <?php if ($role === 'admin'): ?>
                                                <th>Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($announcements as $announcement): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></td>
                                                <td><?php echo htmlspecialchars($announcement['author_name']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $announcement['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <?php if ($role === 'admin'): ?>
                                                <td>
                                                    <a href="announcement.php?toggle_status=<?php echo $announcement['id']; ?>" 
                                                       class="btn btn-sm btn-<?php echo $announcement['is_active'] ? 'warning' : 'success'; ?>">
                                                        <?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </a>
                                                    <a href="announcement.php?delete_id=<?php echo $announcement['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                        Delete
                                                    </a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAnnouncementModalLabel">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="announcement.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="create_announcement" class="btn btn-primary">Save Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>