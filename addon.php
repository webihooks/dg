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
$current_profile_url = '';

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// ==================== ADDON MANAGEMENT CODE ====================
$addons = [];
$edit_addon = null;

// Handle addon form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile URL handling (your existing code)
    if (isset($_POST['profile_url'])) {
        $profile_url = trim($_POST['profile_url']);
        
        // Basic validation
        if (empty($profile_url)) {
            $error_message = "Profile URL cannot be empty";
        } elseif (!preg_match('/^[a-zA-Z0-9-]+$/', $profile_url)) {
            $error_message = "Profile URL can only contain letters, numbers, and hyphens";
        } else {
            // Check if URL is available
            $check_sql = "SELECT user_id FROM profile_url_details WHERE profile_url = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $profile_url);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_user = $check_result->fetch_assoc();
                if ($existing_user['user_id'] != $user_id) {
                    $error_message = "This profile URL is already taken";
                }
            }
            $check_stmt->close();
            
            // If no errors, save the profile URL
            if (empty($error_message)) {
                // Check if user already has a profile URL
                $existing_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
                $existing_stmt = $conn->prepare($existing_sql);
                $existing_stmt->bind_param("i", $user_id);
                $existing_stmt->execute();
                $existing_result = $existing_stmt->get_result();
                
                if ($existing_result->num_rows > 0) {
                    // Update existing record
                    $update_sql = "UPDATE profile_url_details SET profile_url = ?, updated_at = NOW() WHERE user_id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("si", $profile_url, $user_id);
                } else {
                    // Insert new record
                    $insert_sql = "INSERT INTO profile_url_details (user_id, profile_url, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("is", $user_id, $profile_url);
                }
                
                if ($stmt->execute()) {
                    $success_message = "Profile URL saved successfully!";
                    $current_profile_url = $profile_url;
                } else {
                    $error_message = "Error saving profile URL: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    
    // Addon management handling
    if (isset($_POST['add_addon']) || isset($_POST['update_addon'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $special_price = !empty($_POST['special_price']) ? floatval($_POST['special_price']) : null;
        $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        $status = $_POST['status'] === 'active' ? 1 : 0;
        
        // Validation
        if (empty($name)) {
            $error_message = "Addon name cannot be empty";
        } elseif ($price < 0) {
            $error_message = "Price cannot be negative";
        } elseif ($special_price !== null && $special_price >= $price) {
            $error_message = "Special price must be lower than regular price";
        } else {
            // Handle file upload
            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/addons/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $file_name = uniqid('addon_') . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;
                
                // Validate image
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($file_ext, $allowed_types)) {
                    $error_message = "Only JPG, JPEG, PNG & GIF files are allowed";
                } elseif ($_FILES['image']['size'] > 5000000) { // 5MB limit
                    $error_message = "File size must be less than 5MB";
                } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = $target_path;
                } else {
                    $error_message = "Error uploading file";
                }
            }
            
            if (empty($error_message)) {
                if (isset($_POST['add_addon'])) {
                    // Insert new addon
                    $sql = "INSERT INTO addons (name, description, image, price, special_price, valid_until, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssddss", $name, $description, $image_path, $price, $special_price, $valid_until, $status);
                } elseif (isset($_POST['update_addon'])) {
                    // Update existing addon
                    $addon_id = intval($_POST['addon_id']);
                    
                    // Keep existing image if no new image uploaded
                    if ($image_path === null) {
                        $sql = "UPDATE addons SET name = ?, description = ?, price = ?, special_price = ?, 
                                valid_until = ?, status = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssddssi", $name, $description, $price, $special_price, $valid_until, $status, $addon_id);
                    } else {
                        // First get old image to delete it
                        $old_img_sql = "SELECT image FROM addons WHERE id = ?";
                        $old_img_stmt = $conn->prepare($old_img_sql);
                        $old_img_stmt->bind_param("i", $addon_id);
                        $old_img_stmt->execute();
                        $old_img_stmt->bind_result($old_image);
                        $old_img_stmt->fetch();
                        $old_img_stmt->close();
                        
                        if ($old_image && file_exists($old_image)) {
                            unlink($old_image);
                        }
                        
                        $sql = "UPDATE addons SET name = ?, description = ?, image = ?, price = ?, 
                                special_price = ?, valid_until = ?, status = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssddssi", $name, $description, $image_path, $price, $special_price, $valid_until, $status, $addon_id);
                    }
                }
                
                if ($stmt->execute()) {
                    $success_message = isset($_POST['add_addon']) ? "Addon added successfully!" : "Addon updated successfully!";
                } else {
                    $error_message = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $addon_id = intval($_GET['id']);
    
    // First get image path to delete file
    $img_sql = "SELECT image FROM addons WHERE id = ?";
    $img_stmt = $conn->prepare($img_sql);
    $img_stmt->bind_param("i", $addon_id);
    $img_stmt->execute();
    $img_stmt->bind_result($image_path);
    $img_stmt->fetch();
    $img_stmt->close();
    
    // Delete record
    $delete_sql = "DELETE FROM addons WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $addon_id);
    
    if ($stmt->execute()) {
        // Delete the image file if exists
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        $success_message = "Addon deleted successfully!";
    } else {
        $error_message = "Error deleting addon: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all addons
$sql = "SELECT * FROM addons ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $addons[] = $row;
    }
}

// Fetch addon details for editing
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $addon_id = intval($_GET['id']);
    
    $edit_sql = "SELECT * FROM addons WHERE id = ?";
    $stmt = $conn->prepare($edit_sql);
    $stmt->bind_param("i", $addon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $edit_addon = $result->fetch_assoc();
    }
    $stmt->close();
}
// ==================== END ADDON MANAGEMENT CODE ====================

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
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-header {
                padding: 15px;
            }
            
            .card-header .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 15px;
            }
            
            .card-header h4 {
                font-size: 1.25rem;
                margin-bottom: 0;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .table-responsive table {
                min-width: 700px; /* Allow horizontal scrolling */
            }
            
            .addon-card {
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                background: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .addon-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .addon-id {
                font-weight: 600;
                color: #333;
                font-size: 1.1rem;
            }
            
            .addon-status {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
            
            .addon-image {
                text-align: center;
                margin-bottom: 12px;
            }
            
            .addon-image img {
                max-height: 120px;
                width: auto;
                border-radius: 6px;
            }
            
            .addon-name {
                font-weight: 600;
                font-size: 1.1rem;
                color: #333;
                margin-bottom: 8px;
            }
            
            .addon-description {
                color: #666;
                font-size: 0.9rem;
                line-height: 1.4;
                margin-bottom: 12px;
            }
            
            .addon-pricing {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 15px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 6px;
            }
            
            .price-item {
                text-align: center;
            }
            
            .price-label {
                font-size: 0.8rem;
                color: #666;
                margin-bottom: 4px;
            }
            
            .price-value {
                font-weight: 600;
                font-size: 1rem;
                color: #333;
            }
            
            .regular-price {
                color: #dc3545;
            }
            
            .special-price {
                color: #28a745;
            }
            
            .valid-until {
                font-size: 0.75rem;
                color: #6c757d;
                margin-top: 4px;
            }
            
            .addon-actions {
                display: flex;
                gap: 8px;
                justify-content: flex-end;
            }
            
            .addon-actions .btn {
                font-size: 0.8rem;
                padding: 6px 12px;
                flex: 1;
            }
            
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #666;
            }
            
            .empty-state .mdi {
                font-size: 3rem;
                margin-bottom: 15px;
                color: #ccc;
            }
            
            .empty-state h5 {
                color: #666;
                margin-bottom: 10px;
            }
            
            .empty-state p {
                color: #888;
                margin-bottom: 20px;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
            }
            
            /* Form improvements for mobile */
            .form-control, .form-select {
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            .img-thumbnail {
                max-height: 80px;
                width: auto;
            }
        }
        
        @media (min-width: 769px) {
            .desktop-table {
                display: block;
            }
            
            .mobile-cards {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .addon-pricing {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .addon-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .addon-status {
                align-self: flex-start;
            }
            
            .addon-actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .card-header .btn {
                width: 100%;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-row .col-md-6 {
                width: 100%;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 400px) {
            .addon-card {
                padding: 12px;
            }
            
            .addon-image img {
                max-height: 100px;
            }
        }
        
        /* Common Styles */
        .img-thumbnail {
            max-height: 100px;
            width: auto;
        }
        
        .table img {
            max-height: 50px;
        }
        
        .badge-status-active { background-color: #28a745; }
        .badge-status-inactive { background-color: #dc3545; }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        /* Loading animation for cards */
        .addon-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .addon-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        /* Price styling */
        .price-strike {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }
        .scroll-to-top.show {
          bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <!-- Addon Management Section -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo isset($edit_addon) ? 'Edit Addon' : 'Add New Addon'; ?></h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="addonForm" enctype="multipart/form-data">
                                    <?php if (isset($edit_addon)): ?>
                                        <input type="hidden" name="addon_id" value="<?php echo $edit_addon['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Addon Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo isset($edit_addon) ? htmlspecialchars($edit_addon['name']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                            echo isset($edit_addon) ? htmlspecialchars($edit_addon['description']) : ''; 
                                        ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Image</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <?php if (isset($edit_addon) && !empty($edit_addon['image'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo $edit_addon['image']; ?>" alt="Current image" class="img-thumbnail">
                                                <p class="small text-muted">Current image (leave blank to keep this image)</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row form-row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Regular Price *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" 
                                                   value="<?php echo isset($edit_addon) ? $edit_addon['price'] : '0.00'; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="special_price" class="form-label">Special Price</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="special_price" name="special_price" 
                                                   value="<?php echo isset($edit_addon) ? $edit_addon['special_price'] : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="valid_until" class="form-label">Special Price Valid Until</label>
                                        <input type="date" class="form-control" id="valid_until" name="valid_until" 
                                               value="<?php echo isset($edit_addon) ? $edit_addon['valid_until'] : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo (isset($edit_addon) && $edit_addon['status'] == 1) ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo (isset($edit_addon) && $edit_addon['status'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <?php if (isset($edit_addon)): ?>
                                            <button type="submit" name="update_addon" class="btn btn-primary">Update Addon</button>
                                            <a href="addon.php" class="btn btn-secondary">Cancel</a>
                                        <?php else: ?>
                                            <button type="submit" name="add_addon" class="btn btn-primary">Add Addon</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title">All Addons</h4>
                                <a href="addon.php" class="btn btn-sm btn-success">Add New</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($addons)): ?>
                                    <div class="empty-state">
                                        <i class="mdi mdi-package-variant"></i>
                                        <h5>No Addons Found</h5>
                                        <p>You haven't created any addons yet.</p>
                                        <a href="addon.php" class="btn btn-primary">
                                            <i class="mdi mdi-plus"></i> Create Your First Addon
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <!-- Desktop Table View -->
                                    <div class="desktop-table">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Image</th>
                                                        <th>Name</th>
                                                        <th>Price</th>
                                                        <th>Special Price</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($addons as $addon): ?>
                                                        <tr>
                                                            <td><?php echo $addon['id']; ?></td>
                                                            <td>
                                                                <?php if (!empty($addon['image'])): ?>
                                                                    <img src="<?php echo $addon['image']; ?>" alt="Addon image" class="img-fluid">
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($addon['name']); ?></td>
                                                            <td>₹<?php echo number_format($addon['price']); ?></td>
                                                            <td>
                                                                <?php if ($addon['special_price'] !== null): ?>
                                                                    ₹<?php echo number_format($addon['special_price']); ?>
                                                                    <?php if ($addon['valid_until']): ?>
                                                                        <br><small class="text-muted">Until: <?php echo date('M d, Y', strtotime($addon['valid_until'])); ?></small>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-status-<?php echo $addon['status'] ? 'active' : 'inactive'; ?>">
                                                                    <?php echo $addon['status'] ? 'Active' : 'Inactive'; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="?action=edit&id=<?php echo $addon['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                                <a href="?action=delete&id=<?php echo $addon['id']; ?>" 
                                                                   class="btn btn-sm btn-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this addon?')">Delete</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile Card View -->
                                    <div class="mobile-cards">
                                        <?php foreach ($addons as $addon): ?>
                                            <div class="addon-card">
                                                <div class="addon-header">
                                                    <div class="addon-id">Addon #<?php echo $addon['id']; ?></div>
                                                    <span class="badge addon-status badge-status-<?php echo $addon['status'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $addon['status'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if (!empty($addon['image'])): ?>
                                                    <div class="addon-image">
                                                        <img src="<?php echo $addon['image']; ?>" alt="Addon image" class="img-fluid">
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="addon-name">
                                                    <?php echo htmlspecialchars($addon['name']); ?>
                                                </div>
                                                
                                                <?php if (!empty($addon['description'])): ?>
                                                    <div class="addon-description">
                                                        <?php echo htmlspecialchars($addon['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="addon-pricing">
                                                    <div class="price-item">
                                                        <div class="price-label">Regular Price</div>
                                                        <div class="price-value regular-price">₹<?php echo number_format($addon['price']); ?></div>
                                                    </div>
                                                    
                                                    <?php if ($addon['special_price'] !== null): ?>
                                                        <div class="price-item">
                                                            <div class="price-label">Special Price</div>
                                                            <div class="price-value special-price">₹<?php echo number_format($addon['special_price']); ?></div>
                                                            <?php if ($addon['valid_until']): ?>
                                                                <div class="valid-until">Until: <?php echo date('M d, Y', strtotime($addon['valid_until'])); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="price-item">
                                                            <div class="price-label">Special Price</div>
                                                            <div class="price-value text-muted">-</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="addon-actions">
                                                    <a href="?action=edit&id=<?php echo $addon['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="?action=delete&id=<?php echo $addon['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this addon?')">Delete</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="mdi mdi-chevron-up"></i>
    </button>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            // Scroll to top functionality
            const scrollToTopBtn = document.getElementById('scrollToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    scrollToTopBtn.classList.add('show');
                } else {
                    scrollToTopBtn.classList.remove('show');
                }
            });
            
            scrollToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Add animation to addon cards
            $('.addon-card').each(function(index) {
                $(this).css({
                    'opacity': '0',
                    'transform': 'translateY(20px)'
                });
                
                setTimeout(() => {
                    $(this).animate({
                        'opacity': '1',
                        'transform': 'translateY(0)'
                    }, 300);
                }, index * 100);
            });
            
            $("#addonForm").validate({
                rules: {
                    name: {
                        required: true,
                        minlength: 2
                    },
                    price: {
                        required: true,
                        min: 0
                    },
                    special_price: {
                        min: 0,
                        lessThanPrice: true
                    },
                    image: {
                        accept: "image/*"
                    }
                },
                messages: {
                    name: {
                        required: "Please enter addon name",
                        minlength: "Addon name must be at least 2 characters long"
                    },
                    price: {
                        required: "Please enter price",
                        min: "Price cannot be negative"
                    },
                    special_price: {
                        min: "Special price cannot be negative",
                        lessThanPrice: "Special price must be less than regular price"
                    },
                    image: {
                        accept: "Please upload a valid image file (JPG, PNG, GIF)"
                    }
                },
                errorElement: "div",
                errorClass: "invalid-feedback",
                highlight: function(element) {
                    $(element).addClass('is-invalid').removeClass('is-valid');
                },
                unhighlight: function(element) {
                    $(element).addClass('is-valid').removeClass('is-invalid');
                }
            });
            
            // Custom validation rule for special price
            $.validator.addMethod("lessThanPrice", function(value, element) {
                if (value === "") return true; // Skip if empty
                var price = parseFloat($("#price").val());
                return parseFloat(value) < price;
            }, "Special price must be less than regular price");
            
            // Set today's date as default for valid_until
            if (!$("#valid_until").val()) {
                var today = new Date();
                var dd = String(today.getDate()).padStart(2, '0');
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var yyyy = today.getFullYear();
                today = yyyy + '-' + mm + '-' + dd;
                $("#valid_until").val(today);
            }
        });
    </script>
</body>
</html>