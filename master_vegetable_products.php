<?php
session_start();
require 'db_connection.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$sql_role = "SELECT role FROM users WHERE id = ?";
$stmt_role = $conn->prepare($sql_role);
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$stmt_role->bind_result($role);
$stmt_role->fetch();
$stmt_role->close();
if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// Create master table if not exists (with image_path column)
$create_table_sql = "CREATE TABLE IF NOT EXISTS master_vegetable_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name_en VARCHAR(255) NOT NULL,
    product_name_hi VARCHAR(255) NOT NULL,
    image_path VARCHAR(500),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

$message = '';
$error = '';

// Create upload directory if not exists
$upload_dir = 'uploads/master_vegetables/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Helper function to upload image
function uploadProductImage($file, $upload_dir) {
    if ($file['error'] !== UPLOAD_ERR_OK) return '';
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return '';
    
    if ($file['size'] > 2 * 1024 * 1024) return ''; // 2MB max
    
    $new_name = uniqid() . '.' . $ext;
    $target = $upload_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $target;
    }
    return '';
}

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $product_name_en = trim($_POST['product_name_en'] ?? '');
    $product_name_hi = trim($_POST['product_name_hi'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $image_path = '';

    if ($action === 'add' && !empty($product_name_en) && !empty($product_name_hi)) {
        // Handle image upload for new product
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $image_path = uploadProductImage($_FILES['product_image'], $upload_dir);
        }
        
        $stmt = $conn->prepare("INSERT INTO master_vegetable_products (product_name_en, product_name_hi, image_path, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $product_name_en, $product_name_hi, $image_path, $status);
        if ($stmt->execute()) {
            $message = "Product added successfully.";
        } else {
            $error = "Error adding product: " . $stmt->error;
        }
        $stmt->close();
    } elseif ($action === 'edit' && $product_id > 0 && !empty($product_name_en) && !empty($product_name_hi)) {
        // Get current image path to delete old if new uploaded
        $old_image = '';
        $img_stmt = $conn->prepare("SELECT image_path FROM master_vegetable_products WHERE id = ?");
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $img_stmt->bind_result($old_image);
        $img_stmt->fetch();
        $img_stmt->close();
        
        // Handle new image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $new_image = uploadProductImage($_FILES['product_image'], $upload_dir);
            if ($new_image) {
                // Delete old image if exists
                if (!empty($old_image) && file_exists($old_image)) {
                    unlink($old_image);
                }
                $image_path = $new_image;
            } else {
                $error = "Invalid image upload.";
            }
        } else {
            // Keep existing image
            $image_path = $old_image;
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE master_vegetable_products SET product_name_en=?, product_name_hi=?, image_path=?, status=? WHERE id=?");
            $stmt->bind_param("ssssi", $product_name_en, $product_name_hi, $image_path, $status, $product_id);
            if ($stmt->execute()) {
                $message = "Product updated successfully.";
            } else {
                $error = "Error updating product: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'delete' && $product_id > 0) {
        // Get image path to delete file
        $img_stmt = $conn->prepare("SELECT image_path FROM master_vegetable_products WHERE id = ?");
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $img_stmt->bind_result($old_image);
        $img_stmt->fetch();
        $img_stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM master_vegetable_products WHERE id=?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            if (!empty($old_image) && file_exists($old_image)) {
                unlink($old_image);
            }
            $message = "Product deleted successfully.";
        } else {
            $error = "Error deleting product: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all products
$products = [];
$result = $conn->query("SELECT * FROM master_vegetable_products ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Master Vegetable Products | Admin</title>
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
    
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    <style>
        .product-img { max-width: 60px; max-height: 60px; object-fit: cover; border-radius: 4px; }
        .table-responsive { overflow-x: auto; }
        @media (max-width: 768px) { .btn-sm { margin: 2px; } }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>
        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Master Vegetable / Fruits Library</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($message): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>

                                <h5>Add New Product</h5>
                                <form method="POST" enctype="multipart/form-data" class="row g-3 mb-4">
                                    <input type="hidden" name="action" value="add">
                                    <div class="col-md-3">
                                        <label>Name (English)</label>
                                        <input type="text" name="product_name_en" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Name (Hindi)</label>
                                        <input type="text" name="product_name_hi" class="form-control" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Image</label>
                                        <input type="file" name="product_image" class="form-control" accept="image/*">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">Add Product</button>
                                    </div>
                                </form>

                                <h5>All Products</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr><th>ID</th><th>Image</th><th>English Name</th><th>Hindi Name</th><th>Status</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $p): ?>
                                            <tr>
                                                <td><?php echo $p['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($p['image_path']) && file_exists($p['image_path'])): ?>
                                                        <img src="<?php echo $p['image_path']; ?>" class="product-img" alt="Image">
                                                    <?php else: ?>
                                                        <span class="text-muted">No image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($p['product_name_en']); ?></td>
                                                <td><?php echo htmlspecialchars($p['product_name_hi']); ?></td>
                                                <td><span class="badge bg-<?php echo $p['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning edit-btn" 
                                                        data-id="<?php echo $p['id']; ?>" 
                                                        data-en="<?php echo htmlspecialchars($p['product_name_en']); ?>" 
                                                        data-hi="<?php echo htmlspecialchars($p['product_name_hi']); ?>" 
                                                        data-status="<?php echo $p['status']; ?>"
                                                        data-image="<?php echo htmlspecialchars($p['image_path']); ?>">
                                                        Edit
                                                    </button>
                                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')">Delete</a>
                                                </td>
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header"><h5>Edit Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="product_id" id="edit_id">
                        <div class="mb-3">
                            <label>Name (English)</label>
                            <input type="text" name="product_name_en" id="edit_en" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Name (Hindi)</label>
                            <input type="text" name="product_name_hi" id="edit_hi" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Current Image</label>
                            <div id="current_image_preview"></div>
                            <input type="file" name="product_image" class="form-control mt-2" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image.</small>
                        </div>
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            $('.edit-btn').click(function() {
                $('#edit_id').val($(this).data('id'));
                $('#edit_en').val($(this).data('en'));
                $('#edit_hi').val($(this).data('hi'));
                $('#edit_status').val($(this).data('status'));
                var imgPath = $(this).data('image');
                if (imgPath && imgPath !== '') {
                    $('#current_image_preview').html('<img src="' + imgPath + '" style="max-width:100px; max-height:100px; border:1px solid #ddd; padding:5px;">');
                } else {
                    $('#current_image_preview').html('<span class="text-muted">No image</span>');
                }
                $('#editModal').modal('show');
            });
        });
    </script>
</body>
</html>