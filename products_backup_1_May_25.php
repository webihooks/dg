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
$is_edit_mode = false;
$product_data = null;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Handle form submission for add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $quantity = trim($_POST['quantity']);

    // Validate inputs
    if (empty($product_name) || empty($price) || empty($quantity)) {
        $error_message = "Product Name, price and quantity are required fields.";
    } else {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            // Validate image file
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($file_extension), $allowed_types)) {
                $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
            } elseif ($_FILES['product_image']['size'] > 5000000) { // 5MB limit
                $error_message = "File size must be less than 5MB.";
            } elseif (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
                
                // Delete old image if updating
                if ($product_id && !empty($_POST['existing_image'])) {
                    if (file_exists($_POST['existing_image'])) {
                        unlink($_POST['existing_image']);
                    }
                }
            } else {
                $error_message = "Error uploading image.";
            }
        } elseif ($product_id && !empty($_POST['existing_image'])) {
            $image_path = $_POST['existing_image'];
        }
        
        if (empty($error_message)) {
            if ($product_id) {
                // Update existing product
                if (!empty($image_path)) {
                    $sql = "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ?, image_path = ? WHERE id = ? AND user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdssii", $product_name, $description, $price, $quantity, $image_path, $product_id, $user_id);
                } else {
                    $sql = "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ? AND user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdiii", $product_name, $description, $price, $quantity, $product_id, $user_id);
                }
            } else {
                // Add new product
                $sql = "INSERT INTO products (user_id, product_name, description, price, quantity, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issdis", $user_id, $product_name, $description, $price, $quantity, $image_path);
            }

            if ($stmt->execute()) {
                $success_message = $product_id ? "Product updated successfully!" : "Product added successfully!";
            } else {
                $error_message = "Error saving product: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $product_id = $_GET['edit'];
    $sql = "SELECT * FROM products WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($product_data) {
        $is_edit_mode = true;
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // First get the image path to delete the file
    $sql = "SELECT image_path FROM products WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($image_path);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the product
    $sql = "DELETE FROM products WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $user_id);
    
    if ($stmt->execute()) {
        // Delete the image file if it exists
        if (!empty($image_path) && file_exists($image_path)) {
            unlink($image_path);
        }
        $success_message = "Product deleted successfully!";
    } else {
        $error_message = "Error deleting product: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all products for the current user
$sql = "SELECT * FROM products WHERE user_id = ? ORDER BY product_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Products Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Products</h4>
                            </div>
                            <div class="card-body">
                                <h4 class="card-title"><?php echo $is_edit_mode ? 'Edit Product' : 'Add New Product'; ?></h4>
                                <form id="productForm" method="POST" action="products.php" enctype="multipart/form-data">
                                    <input type="hidden" name="product_id" value="<?php echo $is_edit_mode ? $product_data['id'] : ''; ?>">
                                    <input type="hidden" name="existing_image" value="<?php echo $is_edit_mode && !empty($product_data['image_path']) ? $product_data['image_path'] : ''; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="product_name" class="form-label">Product Name *</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" required 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($product_data['product_name']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                            echo $is_edit_mode ? htmlspecialchars($product_data['description']) : ''; ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Price *</label>
                                            <input type="number" step="0.01" class="form-control" id="price" name="price" required 
                                                value="<?php echo $is_edit_mode ? $product_data['price'] : ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="quantity" class="form-label">Quantity *</label>
                                            <input type="number" class="form-control" id="quantity" name="quantity" required 
                                                value="<?php echo $is_edit_mode ? $product_data['quantity'] : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="product_image" class="form-label">Product Image</label>
                                        <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                        <?php if ($is_edit_mode && !empty($product_data['image_path'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo $product_data['image_path']; ?>" alt="Product Image" style="max-width: 200px; max-height: 200px;">
                                                <?php if (!empty($product_data['image_path'])): ?>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                                        <label class="form-check-label" for="remove_image">Remove current image</label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Product</button>
                                    <?php if ($is_edit_mode): ?>
                                        <a href="products.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">Your Products</h4>
                            </div>
                            <div class="card-body">
                                <a href="export_products.php" class="fr link-btn">Download All Products CSV</a>
                                <br>

                                <?php if (empty($products)): ?>
                                    <p>No products found. Add your first product above.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Image</th>
                                                    <th>Name</th>
                                                    <th>Description</th>
                                                    <th>Price</th>
                                                    <th>Quantity</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if (!empty($product['image_path'])): ?>
                                                                <img src="<?php echo $product['image_path']; ?>" alt="Product Image" style="max-width: 50px; max-height: 50px;">
                                                            <?php else: ?>
                                                                <span class="text-muted">No image</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($product['description']); ?></td>
                                                        <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                                        <td><?php echo $product['quantity']; ?></td>
                                                        <td>
                                                            <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                            <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" 
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
    <script>
        $(document).ready(function() {
            // Form validation
            $("#productForm").validate({
                rules: {
                    product_name: "required",
                    price: {
                        required: true,
                        number: true,
                        min: 0.01
                    },
                    quantity: {
                        required: true,
                        digits: true,
                        min: 1
                    },
                    product_image: {
                        accept: "image/*",
                        filesize: 5000000 // 5MB
                    }
                },
                messages: {
                    product_name: "Please enter product name",
                    price: {
                        required: "Please enter price",
                        number: "Please enter a valid number",
                        min: "Price must be at least 0.01"
                    },
                    quantity: {
                        required: "Please enter quantity",
                        digits: "Please enter a whole number",
                        min: "Quantity must be at least 1"
                    },
                    product_image: {
                        accept: "Please upload a valid image file (JPG, PNG, GIF)",
                        filesize: "File size must be less than 5MB"
                    }
                }
            });
            
            // Custom validation for file size
            $.validator.addMethod('filesize', function(value, element, param) {
                return this.optional(element) || (element.files[0].size <= param);
            }, 'File size must be less than {0} bytes');
        });
    </script>
</body>
</html>