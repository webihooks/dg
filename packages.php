<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
session_start();
require 'db_connection.php';

// First, check and modify the status column if needed
$check_status_sql = "SHOW COLUMNS FROM packages LIKE 'status'";
$result = $conn->query($check_status_sql);
if ($result->num_rows > 0) {
    $column = $result->fetch_assoc();
    if (strpos($column['Type'], 'varchar') === false || $column['Type'] == "varchar(7)") {
        $alter_sql = "ALTER TABLE packages MODIFY COLUMN status VARCHAR(10) DEFAULT 'active'";
        $conn->query($alter_sql);
    }
}

// Authentication and admin check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_sql = "SELECT role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if ($user['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Initialize package data
$package = [
    'id' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'duration' => '',
    'status' => 'active'
];

$action = 'add';
$errors = [];

// Handle edit action
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $action = 'edit';
    
    $sql = "SELECT * FROM packages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $package = $result->fetch_assoc();
        // Ensure status is valid
        $package['status'] = in_array($package['status'], ['active', 'inactive']) ? $package['status'] : 'active';
    } else {
        $_SESSION['error'] = "Package not found";
        header("Location: packages.php");
        exit();
    }
    $stmt->close();
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    $delete_sql = "DELETE FROM packages WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Package deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting package";
    }
    $delete_stmt->close();
    
    header("Location: packages.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $package['name'] = trim($_POST['name']);
    $package['description'] = trim($_POST['description']);
    $package['price'] = (float)$_POST['price'];
    $package['duration'] = (int)$_POST['duration'];
    $package['status'] = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';
    
    // Validation
    if (empty($package['name'])) {
        $errors['name'] = "Package name is required";
    }
    
    if (empty($package['description'])) {
        $errors['description'] = "Description is required";
    }
    
    if ($package['price'] <= 0) {
        $errors['price'] = "Price must be greater than 0";
    }
    
    if ($package['duration'] <= 0) {
        $errors['duration'] = "Duration must be at least 1 day";
    }
    
    // Process if no errors
    if (empty($errors)) {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO packages (name, description, price, duration, status) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdss", $package['name'], $package['description'], 
                             $package['price'], $package['duration'], $package['status']);

            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Package added successfully";
                header("Location: packages.php");
                exit();
            } else {
                $errors[] = "Error adding package: " . $stmt->error;
            }
        } elseif ($_POST['action'] === 'edit') {
            $package['id'] = (int)$_POST['package_id'];
            
            $sql = "UPDATE packages SET 
                    name = ?, description = ?, price = ?, duration = ?, 
                    status = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssi", $package['name'], $package['description'], 
                              $package['price'], $package['duration'], $package['status'], $package['id']);

            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Package updated successfully";
                header("Location: packages.php");
                exit();
            } else {
                $errors[] = "Error updating package: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Fetch all packages
$sql = "SELECT * FROM packages ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user name for display
$user_name = '';
$user_id = $_SESSION['user_id'];
$name_sql = "SELECT name FROM users WHERE id = ?";
$name_stmt = $conn->prepare($name_sql);
$name_stmt->bind_param("i", $user_id);
$name_stmt->execute();
$name_stmt->bind_result($user_name);
$name_stmt->fetch();
$name_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Package Management | Admin</title>
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
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?= ucfirst($action) ?> Package</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success">
                                        <?= htmlspecialchars($_SESSION['success']) ?>
                                        <?php unset($_SESSION['success']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger">
                                        <?= htmlspecialchars($_SESSION['error']) ?>
                                        <?php unset($_SESSION['error']); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="packages.php">
                                    <input type="hidden" name="action" value="<?= $action ?>">
                                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                    
                                    <div class="form-group mb-3">
                                        <label for="name">Package Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($package['name']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3" required><?= htmlspecialchars($package['description']) ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="price">Price (₹)</label>
                                                <input type="number" class="form-control" id="price" name="price" 
                                                       step="0.01" min="0.01" value="<?= $package['price'] ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="duration">Duration (days)</label>
                                                <input type="number" class="form-control" id="duration" name="duration" 
                                                       min="1" value="<?= $package['duration'] ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="status">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="active" <?= $package['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $package['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <?= $action === 'add' ? 'Add Package' : 'Update Package' ?>
                                    </button>
                                    <a href="packages.php" class="btn btn-secondary">Cancel</a>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">All Packages</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($packages)): ?>
                                    <p>No packages found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Description</th>
                                                    <th>Price</th>
                                                    <th>Duration</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($packages as $pkg): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($pkg['name']) ?></td>
                                                        <td><?= htmlspecialchars(substr($pkg['description'], 0, 50)) ?>...</td>
                                                        <td>₹<?= number_format($pkg['price'], 2) ?></td>
                                                        <td><?= $pkg['duration'] ?> days</td>
                                                        <td>
                                                            <span class="badge bg-<?= $pkg['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($pkg['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="packages.php?edit_id=<?= $pkg['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                                            <a href="packages.php?delete_id=<?= $pkg['id'] ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('Are you sure you want to delete this package?')">Delete</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>