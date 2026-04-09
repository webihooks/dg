<?php
session_start();
require 'db_connection.php';

// First, check and modify the status column if needed
$check_status_sql = "SHOW COLUMNS FROM master_products LIKE 'status'";
$result = $conn->query($check_status_sql);
if ($result->num_rows > 0) {
    $column = $result->fetch_assoc();
    if (strpos($column['Type'], 'varchar') === false || $column['Type'] == "varchar(7)") {
        $alter_sql = "ALTER TABLE master_products MODIFY COLUMN status VARCHAR(10) DEFAULT 'active'";
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

// Initialize product data
$product = [
    'id' => '',
    'user_id' => $_SESSION['user_id'],
    'product_name' => '',
    'description' => '',
    'image_path' => '',
    'status' => 'active'
];

$action = 'add';
$errors = [];

// Handle edit action
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $action = 'edit';
    
    $sql = "SELECT * FROM master_products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        // Ensure status is valid
        $product['status'] = in_array($product['status'], ['active', 'inactive']) ? $product['status'] : 'active';
    } else {
        $_SESSION['error'] = "Product not found";
        header("Location: master_products.php");
        exit();
    }
    $stmt->close();
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    $delete_sql = "DELETE FROM master_products WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Product deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting product";
    }
    $delete_stmt->close();
    
    header("Location: master_products.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $product['product_name'] = trim($_POST['product_name']);
    $product['description'] = trim($_POST['description']);
    $product['status'] = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';
    
    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/master_products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Get original filename and extension
        $originalName = basename($_FILES['image']['name']);
        $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileNameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Clean the filename (remove special characters)
        $cleanName = preg_replace("/[^a-zA-Z0-9]/", "", $fileNameWithoutExt);
        $cleanName = strtolower($cleanName);
        
        // Generate final filename
        $finalFileName = $cleanName . '.' . $fileExtension;
        $counter = 1;
        
        // Ensure filename is unique
        while (file_exists($uploadDir . $finalFileName)) {
            $finalFileName = $cleanName . '_' . $counter . '.' . $fileExtension;
            $counter++;
        }
        
        $targetPath = $uploadDir . $finalFileName;
        
        // Validate and move uploaded file
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileExtension), $allowedExtensions)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $product['image_path'] = 'http://' . $_SERVER['HTTP_HOST'] . '/deegeecard/' . $targetPath;
            } else {
                $errors['image'] = "Error uploading image";
            }
        } else {
            $errors['image'] = "Only JPG, JPEG, PNG & GIF files are allowed";
        }
    } elseif ($action === 'edit' && empty($_FILES['image']['name'])) {
        // Keep existing image if not uploading new one in edit mode
        $product['image_path'] = $_POST['existing_image'];
    } else {
        if ($action === 'add') {
            $errors['image'] = "Product image is required";
        }
    }
    
    // Validation
    if (empty($product['product_name'])) {
        $errors['product_name'] = "Product name is required";
    }
    
    if (empty($product['description'])) {
        $errors['description'] = "Description is required";
    }
    
    // Process if no errors
    if (empty($errors)) {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO master_products (user_id, product_name, description, image_path, status) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $product['user_id'], $product['product_name'], 
                             $product['description'], $product['image_path'], $product['status']);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Product added successfully";
                header("Location: master_products.php");
                exit();
            } else {
                $errors[] = "Error adding product: " . $stmt->error;
            }
        } elseif ($_POST['action'] === 'edit') {
            $product['id'] = (int)$_POST['product_id'];
            
            $sql = "UPDATE master_products SET 
                    product_name = ?, description = ?, image_path = ?, 
                    status = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $product['product_name'], $product['description'], 
                              $product['image_path'], $product['status'], $product['id']);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Product updated successfully";
                header("Location: master_products.php");
                exit();
            } else {
                $errors[] = "Error updating product: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Fetch all products
$sql = "SELECT mp.*, u.name as user_name FROM master_products mp 
        JOIN users u ON mp.user_id = u.id 
        ORDER BY mp.product_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>Master Products | Admin</title>
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
                                <h4 class="card-title"><?= ucfirst($action) ?> Product</h4>
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

                                <form method="POST" action="master_products.php" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="<?= $action ?>">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <?php if ($action === 'edit'): ?>
                                        <input type="hidden" name="existing_image" value="<?= $product['image_path'] ?>">
                                    <?php endif; ?>
                                    
                                    <div class="form-group mb-3">
                                        <label for="product_name">Product Name</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" 
                                               value="<?= htmlspecialchars($product['product_name']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3" required><?= htmlspecialchars($product['description']) ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="image">Product Image</label>
                                                <input type="file" class="form-control" id="image" name="image" <?= $action === 'add' ? 'required' : '' ?>>
                                                <?php if ($action === 'edit' && !empty($product['image_path'])): ?>
                                                    <small class="text-muted">Current image: <?= basename($product['image_path']) ?></small>
                                                    <div class="mt-2">
                                                        <img src="<?= $product['image_path'] ?>" alt="Product Image" style="max-width: 200px; max-height: 200px;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="status">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <?= $action === 'add' ? 'Add Product' : 'Update Product' ?>
                                    </button>
                                    <a href="master_products.php" class="btn btn-secondary">Cancel</a>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">All Products</h4>
                                <a href="download_products_csv.php" class="fr link-btn">Download All Products CSV</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($products)): ?>
                                    <p>No products found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th width="50">Sr. No.</th>
                                                    <th>Product Name</th>
                                                    <th>Description</th>
                                                    <th>Image</th>
                                                    <th>Added By</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $counter = 1;
                                                foreach ($products as $prod): ?>
                                                    <tr>
                                                        <td><?= $counter++ ?></td>
                                                        <td><?= htmlspecialchars($prod['product_name']) ?></td>
                                                        <td><?= htmlspecialchars(substr($prod['description'], 0, 50)) ?>...</td>
                                                        <td>
                                                            <?php if (!empty($prod['image_path'])): ?>
                                                                <img src="<?= $prod['image_path'] ?>" alt="Product Image" style="max-width: 50px; max-height: 50px;">
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($prod['user_name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $prod['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($prod['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="master_products.php?edit_id=<?= $prod['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                                            <a href="master_products.php?delete_id=<?= $prod['id'] ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
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