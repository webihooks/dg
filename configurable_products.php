<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Create user-specific configurable products table if it doesn't exist
$config_products_table = "config_products_" . $user_id;
$create_table_sql = "CREATE TABLE IF NOT EXISTS $config_products_table (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    half_price DECIMAL(10,2) DEFAULT 0,
    full_price DECIMAL(10,2) DEFAULT 0,
    small_price DECIMAL(10,2) DEFAULT 0,
    medium_price DECIMAL(10,2) DEFAULT 0,
    large_price DECIMAL(10,2) DEFAULT 0,
    8_inch_price DECIMAL(10,2) DEFAULT 0,
    10_inch_price DECIMAL(10,2) DEFAULT 0,
    12_inch_price DECIMAL(10,2) DEFAULT 0,
    16_inch_price DECIMAL(10,2) DEFAULT 0,
    quantity INT(11) NOT NULL DEFAULT 0,
    image_path VARCHAR(500),
    tag_id INT(11),
    toppings_group_id INT(11),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($create_table_sql)) {
    $error_message = "Error creating configurable products table: " . $conn->error;
}

// Check if all required columns exist, if not add them
$required_columns = [
    'half_price', 'full_price', 'small_price', 'medium_price', 'large_price',
    '8_inch_price', '10_inch_price', '12_inch_price', '16_inch_price',
    'toppings_group_id'
];

foreach ($required_columns as $column) {
    $check_column_sql = "SHOW COLUMNS FROM $config_products_table LIKE '$column'";
    $result = $conn->query($check_column_sql);
    if ($result->num_rows == 0) {
        $add_column_sql = "ALTER TABLE $config_products_table ADD COLUMN $column DECIMAL(10,2) DEFAULT 0";
        if ($column === 'toppings_group_id') {
            $add_column_sql = "ALTER TABLE $config_products_table ADD COLUMN $column INT(11)";
        }
        
        if (!$conn->query($add_column_sql)) {
            $error_message = "Error adding $column column: " . $conn->error;
        }
    }
}

// Create toppings group table if it doesn't exist
$toppings_group_table = "toppings_group_" . $user_id;
$create_toppings_group_sql = "CREATE TABLE IF NOT EXISTS $toppings_group_table (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    toppings_group_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($create_toppings_group_sql)) {
    $error_message = "Error creating toppings group table: " . $conn->error;
}

// Create toppings table if it doesn't exist
$toppings_table = "toppings_" . $user_id;
$create_toppings_sql = "CREATE TABLE IF NOT EXISTS $toppings_table (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    toppings_group_id INT(11) NOT NULL,
    toppings_name VARCHAR(255) NOT NULL,
    toppings_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (toppings_group_id) REFERENCES $toppings_group_table(id) ON DELETE CASCADE
)";

if (!$conn->query($create_toppings_sql)) {
    $error_message = "Error creating toppings table: " . $conn->error;
}

// Handle toggle active status
if (isset($_GET['toggle_status'])) {
    $product_id = $_GET['toggle_status'];
    
    // Get current status
    $sql = "SELECT is_active FROM $config_products_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($current_status);
    $stmt->fetch();
    $stmt->close();
    
    // Toggle status
    $new_status = $current_status ? 0 : 1;
    
    $sql = "UPDATE $config_products_table SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_status, $product_id);
    
    if ($stmt->execute()) {
        $success_message = "Product status updated successfully!";
    } else {
        $error_message = "Error updating product status: " . $conn->error;
    }
    $stmt->close();
    
    // Redirect to avoid resubmission
    header("Location: configurable_products.php");
    exit();
}

// Check user's subscription and product limits
$max_products = 0;
$current_product_count = 0;
$subscription_active = false;
$package_name = '';

// Get user's active subscription
$sql = "SELECT s.package_id, p.name as package_name 
        FROM subscriptions s
        JOIN packages p ON s.package_id = p.id
        WHERE s.user_id = ? AND s.status = 'active' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();
$stmt->close();

if ($subscription) {
    $subscription_active = true;
    $package_name = $subscription['package_name'];
    switch ($subscription['package_id']) {
        case 1:
            $max_products = 1000;
            break;
        case 2:
            $max_products = 1000;
            break;
        case 3:
            $max_products = 1000;
            break;
        default:
            $max_products = 0;
    }
}

// Get current product count from user's specific table
$sql = "SELECT COUNT(*) as count FROM $config_products_table";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$count_data = $result->fetch_assoc();
$current_product_count = $count_data['count'];
$stmt->close();

// Fetch all tags for the current user
$sql = "SELECT id, tag FROM tags WHERE user_id = ? ORDER BY tag";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all toppings groups for the current user
$sql = "SELECT id, toppings_group_name FROM $toppings_group_table ORDER BY toppings_group_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$toppings_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle remove image request
if (isset($_GET['remove_image'])) {
    $product_id = $_GET['remove_image'];
    
    // First get the image path to delete the file
    $sql = "SELECT image_path FROM $config_products_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($image_path);
    $stmt->fetch();
    $stmt->close();
    
    if (!empty($image_path)) {
        // Delete the image file if it exists
        if (file_exists($image_path)) {
            if (unlink($image_path)) {
                // Update the database to remove the image path
                $sql = "UPDATE $config_products_table SET image_path = NULL WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $product_id);
                
                if ($stmt->execute()) {
                    $success_message = "Image removed successfully!";
                } else {
                    $error_message = "Error updating database: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error deleting image file.";
            }
        } else {
            // File doesn't exist, but still update the database
            $sql = "UPDATE $config_products_table SET image_path = NULL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                $success_message = "Image reference removed successfully!";
            } else {
                $error_message = "Error updating database: " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $error_message = "No image found for this product.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: configurable_products.php");
    exit();
}

// Handle form submission for add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $half_price = trim($_POST['half_price']);
    $full_price = trim($_POST['full_price']);
    $small_price = trim($_POST['small_price']);
    $medium_price = trim($_POST['medium_price']);
    $large_price = trim($_POST['large_price']);
    $eight_inch_price = trim($_POST['8_inch_price']);
    $ten_inch_price = trim($_POST['10_inch_price']);
    $twelve_inch_price = trim($_POST['12_inch_price']);
    $sixteen_inch_price = trim($_POST['16_inch_price']);
    $quantity = trim($_POST['quantity']);
    $tag_id = isset($_POST['tag_id']) && !empty($_POST['tag_id']) ? $_POST['tag_id'] : null;
    $toppings_group_id = isset($_POST['toppings_group_id']) && !empty($_POST['toppings_group_id']) ? $_POST['toppings_group_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    if (empty($product_name) || empty($quantity)) {
        $error_message = "Product Name and quantity are required fields.";
    } else {
        // Check product limit for new products only (not for updates)
        if (!$product_id && $subscription_active && $current_product_count >= $max_products) {
            $error_message = "You have reached the maximum number of products ($max_products) allowed by your $package_name plan.";
        } elseif (!$product_id && !$subscription_active) {
            $error_message = "You need an active subscription to add products. Please subscribe first.";
        } else {
            






// Handle image upload
$image_path = '';
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/products/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $error_message = "Failed to create upload directory. Please check permissions.";
            error_log("Failed to create directory: " . $upload_dir);
        }
    }
    
    // Check if directory is writable
    if (file_exists($upload_dir) && !is_writable($upload_dir)) {
        $error_message = "Upload directory is not writable. Please check permissions.";
        error_log("Directory not writable: " . $upload_dir);
    }
    
    if (empty($error_message)) {
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        
        // Validate image file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        } elseif ($_FILES['product_image']['size'] > 5000000) {
            $error_message = "File size must be less than 5MB.";
        } elseif (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
            error_log("File uploaded successfully to: " . $image_path);
            
            // Delete old image if updating
            if ($product_id && !empty($_POST['existing_image'])) {
                if (file_exists($_POST['existing_image'])) {
                    unlink($_POST['existing_image']);
                }
            }
        } else {
            $error_message = "Error uploading image. Please check file permissions.";
            error_log("Failed to move uploaded file to: " . $target_path);
        }
    }
} elseif ($product_id && !empty($_POST['existing_image'])) {
    // Keep existing image when updating
    $image_path = $_POST['existing_image'];
    error_log("Using existing image: " . $image_path);
}








            // Check if user wants to remove the existing image
            if ($product_id && isset($_POST['remove_existing_image']) && $_POST['remove_existing_image'] == '1') {
                if (!empty($_POST['existing_image']) && file_exists($_POST['existing_image'])) {
                    unlink($_POST['existing_image']);
                }
                $image_path = '';
            }
            
            



if (empty($error_message)) {
    if ($product_id) {
        // Update existing product in user's table
        if (!empty($image_path)) {
            $sql = "UPDATE $config_products_table SET 
                    product_name = ?, description = ?, price = ?, half_price = ?, full_price = ?,
                    small_price = ?, medium_price = ?, large_price = ?, 8_inch_price = ?,
                    10_inch_price = ?, 12_inch_price = ?, 16_inch_price = ?, quantity = ?, 
                    image_path = ?, tag_id = ?, toppings_group_id = ?, is_active = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddddddddddissiii", 
                $product_name, $description, $price, $half_price, $full_price,
                $small_price, $medium_price, $large_price, $eight_inch_price,
                $ten_inch_price, $twelve_inch_price, $sixteen_inch_price, $quantity,
                $image_path, $tag_id, $toppings_group_id, $is_active, $product_id);
        } else {
            $sql = "UPDATE $config_products_table SET 
                    product_name = ?, description = ?, price = ?, half_price = ?, full_price = ?,
                    small_price = ?, medium_price = ?, large_price = ?, 8_inch_price = ?,
                    10_inch_price = ?, 12_inch_price = ?, 16_inch_price = ?, quantity = ?, 
                    tag_id = ?, toppings_group_id = ?, is_active = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddddddddddiiiii", 
                $product_name, $description, $price, $half_price, $full_price,
                $small_price, $medium_price, $large_price, $eight_inch_price,
                $ten_inch_price, $twelve_inch_price, $sixteen_inch_price, $quantity,
                $tag_id, $toppings_group_id, $is_active, $product_id);
        }
    } else {
        // Add new product to user's table
        if (!empty($image_path)) {
            $sql = "INSERT INTO $config_products_table 
                    (product_name, description, price, half_price, full_price,
                    small_price, medium_price, large_price, 8_inch_price,
                    10_inch_price, 12_inch_price, 16_inch_price, quantity, 
                    image_path, tag_id, toppings_group_id, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddddddddddissii", 
                $product_name, $description, $price, $half_price, $full_price,
                $small_price, $medium_price, $large_price, $eight_inch_price,
                $ten_inch_price, $twelve_inch_price, $sixteen_inch_price, $quantity,
                $image_path, $tag_id, $toppings_group_id, $is_active);
        } else {
            $sql = "INSERT INTO $config_products_table 
                    (product_name, description, price, half_price, full_price,
                    small_price, medium_price, large_price, 8_inch_price,
                    10_inch_price, 12_inch_price, 16_inch_price, quantity, 
                    tag_id, toppings_group_id, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddddddddddiiii", 
                $product_name, $description, $price, $half_price, $full_price,
                $small_price, $medium_price, $large_price, $eight_inch_price,
                $ten_inch_price, $twelve_inch_price, $sixteen_inch_price, $quantity,
                $tag_id, $toppings_group_id, $is_active);
        }
    }

    if ($stmt->execute()) {
        $success_message = $product_id ? "Product updated successfully!" : "Product added successfully!";
        // Update product count after successful addition
        if (!$product_id) {
            $current_product_count++;
        }
    } else {
        $error_message = "Error saving product: " . $conn->error;
        // Debug: Log the error
        error_log("Database error: " . $conn->error);
        error_log("SQL: " . $sql);
    }
    $stmt->close();
}








        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $product_id = $_GET['edit'];
    $sql = "SELECT p.*, t.tag as tag_name, tg.toppings_group_name
            FROM $config_products_table p
            LEFT JOIN tags t ON p.tag_id = t.id
            LEFT JOIN $toppings_group_table tg ON p.toppings_group_id = tg.id
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
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
    $sql = "SELECT image_path FROM $config_products_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($image_path);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the product from user's table
    $sql = "DELETE FROM $config_products_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        // Delete the image file if it exists
        if (!empty($image_path) && file_exists($image_path)) {
            unlink($image_path);
        }
        $success_message = "Product deleted successfully!";
        // Update product count after successful deletion
        $current_product_count--;
    } else {
        $error_message = "Error deleting product: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all products for the current user with their tag names, ordered by id in ascending order
$sql = "SELECT p.*, t.tag as tag_name, tg.toppings_group_name
        FROM $config_products_table p
        LEFT JOIN tags t ON p.tag_id = t.id
        LEFT JOIN $toppings_group_table tg ON p.toppings_group_id = tg.id
        ORDER BY p.id ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Generate CSRF token for security if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle search functionality
$search_query = '';
$where_conditions = [];
$params = [];
$param_types = "";

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $where_conditions[] = "(p.product_name LIKE ? OR p.description LIKE ? OR t.tag LIKE ? OR tg.toppings_group_name LIKE ?)";
    
    $search_param = "%" . $search_query . "%";
    // Add 4 parameters for the search
    for ($i = 0; $i < 4; $i++) {
        $params[] = $search_param;
        $param_types .= "s";
    }
}

// Build the SQL query
$sql = "SELECT p.*, t.tag as tag_name, tg.toppings_group_name
        FROM $config_products_table p
        LEFT JOIN tags t ON p.tag_id = t.id
        LEFT JOIN $toppings_group_table tg ON p.toppings_group_id = tg.id";
        
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY p.id ASC";

// Fetch products with optional search filter
$stmt = $conn->prepare($sql);

// Dynamic binding based on search
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Configurable Products Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        select[multiple]{min-height:100px}.table th{background-color:#f8f9fa;font-weight:600}.product-table{width:100%;border-collapse:collapse}.product-table td,.product-table th{padding:12px;border:1px solid #dee2e6;vertical-align:middle}.product-img{max-width:60px;max-height:60px;border-radius:4px}.action-buttons{display:flex;gap:8px}.btn-sm{padding:5px 10px;font-size:12px}.search-container{display:flex;margin-bottom:20px;gap:10px}.search-container input{flex:1}.no-results{text-align:center;padding:20px;font-style:italic;color:#6c757d}.status-toggle{display:inline-block;width:80px;text-align:center}.form-check-input{margin-top:.3rem}.status-col{width:100px}.price-column{min-width:80px}@media (max-width:1200px){.card-header{flex-direction:column;align-items:flex-start!important}.card-header .input-group{width:100%;margin-top:15px}}@media (max-width:992px){.table-responsive{overflow-x:auto}.product-table{min-width:1000px}.action-buttons{flex-wrap:nowrap}.action-buttons .btn{white-space:nowrap}}@media (max-width:768px){.form-row,.search-container,.search-form{flex-direction:column}.col-md-4,.col-md-6{width:100%;margin-bottom:15px}.card-header h4{margin-bottom:15px}.btn-group-responsive{display:flex;flex-direction:column;gap:10px}.btn-group-responsive .btn{width:100%;margin-bottom:5px}.action-buttons{flex-direction:row;flex-wrap:wrap;justify-content:center}.search-form .btn{margin-top:10px;margin-left:0!important}}@media (max-width:576px){.container{padding-left:10px;padding-right:10px}.card-body{padding:15px}.product-table td,.product-table th{padding:8px}.btn{padding:8px 12px;font-size:14px}.action-buttons{flex-direction:row;flex-wrap:nowrap;justify-content:center}.action-buttons .btn{padding:6px 10px;font-size:12px}.modal-dialog{margin:10px}.status-toggle{width:70px;font-size:12px}}.mobile-product-card{display:none;border:1px solid #dee2e6;border-radius:5px;padding:15px;margin-bottom:15px;background:#fff}.mobile-product-field{display:flex;justify-content:space-between;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #f1f1f1}.mobile-product-field:last-child{border-bottom:none}.mobile-field-label{font-weight:700;color:#495057;min-width:100px}.mobile-field-value{flex:1;text-align:right}@media (max-width:992px){.product-table{display:none}.mobile-product-card{display:block}}.search-form{display:flex;width:100%}.mobile-actions{display:flex;justify-content:center;gap:8px;margin-top:15px;flex-wrap:wrap}.mobile-actions .btn{flex:1;min-width:80px;max-width:120px}
    </style>
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
                                <h4 class="card-title">Configurable Products</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($subscription_active): ?>
                                    <div class="alert alert-info">
                                        <strong><?php echo $package_name; ?> Plan:</strong> 
                                        You can add up to <?php echo $max_products; ?> products. 
                                        You currently have <?php echo $current_product_count; ?> products.
                                        <?php if ($current_product_count >= $max_products): ?>
                                            <br><strong>You've reached your limit!</strong> Upgrade your plan to add more products.
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <strong>No active subscription!</strong> You need to subscribe to add products.
                                        <a href="subscription.php" class="alert-link">View subscription plans</a>
                                    </div>
                                <?php endif; ?>
                                
                                <h4 class="card-title"><?php echo $is_edit_mode ? 'Edit Product' : 'Add New Product'; ?></h4>
                                <form id="productForm" method="POST" action="configurable_products.php" enctype="multipart/form-data">
                                    <input type="hidden" name="product_id" value="<?php echo $is_edit_mode ? $product_data['id'] : ''; ?>">
                                    <input type="hidden" name="existing_image" value="<?php echo $is_edit_mode && !empty($product_data['image_path']) ? $product_data['image_path'] : ''; ?>">
                                    
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="product_name" class="form-label">Product Name *</label>
                                                <input type="text" class="form-control" id="product_name" name="product_name" required 
                                                    value="<?php echo $is_edit_mode ? htmlspecialchars($product_data['product_name']) : ''; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="tag_id" class="form-label">Tag</label>
                                                <select class="form-select" id="tag_id" name="tag_id">
                                                    <option value="">-- No Tag --</option>
                                                    <?php foreach ($tags as $tag): ?>
                                                        <option value="<?php echo $tag['id']; ?>" 
                                                            <?php echo ($is_edit_mode && isset($product_data['tag_id']) && $product_data['tag_id'] == $tag['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($tag['tag']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                            echo $is_edit_mode ? htmlspecialchars($product_data['description']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="price" class="form-label">Regular Price</label>
                                            <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                                value="<?php echo $is_edit_mode ? $product_data['price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="half_price" class="form-label">Half Price</label>
                                            <input type="number" step="0.01" class="form-control" id="half_price" name="half_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['half_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="full_price" class="form-label">Full Price</label>
                                            <input type="number" step="0.01" class="form-control" id="full_price" name="full_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['full_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="toppings_group_id" class="form-label">Toppings Group</label>
                                            <select class="form-select" id="toppings_group_id" name="toppings_group_id">
                                                <option value="">-- No Toppings Group --</option>
                                                <?php foreach ($toppings_groups as $group): ?>
                                                    <option value="<?php echo $group['id']; ?>" 
                                                        <?php echo ($is_edit_mode && isset($product_data['toppings_group_id']) && $product_data['toppings_group_id'] == $group['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($group['toppings_group_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="small_price" class="form-label">Small Price</label>
                                            <input type="number" step="0.01" class="form-control" id="small_price" name="small_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['small_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="medium_price" class="form-label">Medium Price</label>
                                            <input type="number" step="0.01" class="form-control" id="medium_price" name="medium_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['medium_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="large_price" class="form-label">Large Price</label>
                                            <input type="number" step="0.01" class="form-control" id="large_price" name="large_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['large_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Status</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                    <?php echo ($is_edit_mode && isset($product_data['is_active']) && $product_data['is_active'] == 0) ? '' : 'checked'; ?>>
                                                <label class="form-check-label" for="is_active">Active</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="8_inch_price" class="form-label">8 Inch Price</label>
                                            <input type="number" step="0.01" class="form-control" id="8_inch_price" name="8_inch_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['8_inch_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="10_inch_price" class="form-label">10 Inch Price</label>
                                            <input type="number" step="0.01" class="form-control" id="10_inch_price" name="10_inch_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['10_inch_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="12_inch_price" class="form-label">12 Inch Price</label>
                                            <input type="number" step="0.01" class="form-control" id="12_inch_price" name="12_inch_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['12_inch_price'] : '0'; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="16_inch_price" class="form-label">16 Inch Price</label>
                                            <input type="number" step="0.01" class="form-control" id="16_inch_price" name="16_inch_price" 
                                                value="<?php echo $is_edit_mode ? $product_data['16_inch_price'] : '0'; ?>">
                                        </div>
                                    </div>





                                    
<div class="row mb-3">
    <div class="col-md-4">
        <label for="quantity" class="form-label">Quantity *</label>
        <input type="number" class="form-control" id="quantity" name="quantity" required 
            value="<?php echo $is_edit_mode ? $product_data['quantity'] : '0'; ?>">
    </div>
    
    <div class="col-md-8">
        <div class="mb-3">
            <label for="product_image" class="form-label">Product Image</label>
            <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
            <?php if ($is_edit_mode && !empty($product_data['image_path'])): ?>
                <div class="mt-2">
                    <img src="<?php echo $product_data['image_path']; ?>" alt="Product Image" style="max-width: 200px; max-height: 200px;">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_existing_image" name="remove_existing_image" value="1">
                        <label class="form-check-label" for="remove_existing_image">Remove current image</label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


                                    
                                    <div class="btn-group-responsive">
                                        <button type="submit" class="btn btn-primary" <?php echo (!$subscription_active && !$is_edit_mode) ? 'disabled' : ''; ?>>
                                            <?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Product
                                        </button>
                                        <?php if ($is_edit_mode): ?>
                                            <a href="configurable_products.php" class="btn btn-secondary">Cancel</a>
                                        <?php endif; ?>
                                        <a href="toppings.php" class="btn btn-info">Manage Toppings</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title mb-0">Your Configurable Products</h4>
                                <form method="GET" action="configurable_products.php" class="search-form">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Search products..." name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search_query)): ?>
                                            <a href="configurable_products.php" class="btn btn-outline-danger">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a href="export_products.php" class="btn btn-outline-primary">Download All Products CSV</a>
                                    <a href="toppings.php" class="btn btn-outline-info">Manage Toppings</a>
                                </div>

                                <?php if (empty($products)): ?>
                                    <div class="no-results">
                                        <?php if (!empty($search_query)): ?>
                                            <p>No products found matching "<?php echo htmlspecialchars($search_query); ?>".</p>
                                            <a href="configurable_products.php" class="btn btn-primary">View All Products</a>
                                        <?php else: ?>
                                            <p>No products found. <?php echo $subscription_active ? 'Add your first product above.' : 'Subscribe to add products.'; ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped product-table">
                                            <thead>
                                                <tr>
                                                    <th>Sr.No.</th>
                                                    <th>ID</th>
                                                    <th>Image</th>
                                                    <th>Name</th>
                                                    <th>Tag</th>
                                                    <th>Toppings Group</th>
                                                    <th>Regular</th>
                                                    <th>Half</th>
                                                    <th>Full</th>
                                                    <th>Small</th>
                                                    <th>Medium</th>
                                                    <th>Large</th>
                                                    <th>8"</th>
                                                    <th>10"</th>
                                                    <th>12"</th>
                                                    <th>16"</th>
                                                    <th>Qty</th>
                                                    <th class="status-col">Status</th>
                                                    <th width="140">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $counter = 1;
                                                foreach ($products as $product): 
                                                    // Highlight search terms if search is active
                                                    $highlighted_name = $product['product_name'];
                                                    $highlighted_tag = $product['tag_name'] ?? '';
                                                    $highlighted_toppings = $product['toppings_group_name'] ?? '';
                                                    
                                                    if (!empty($search_query)) {
                                                        $highlighted_name = preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<mark>$1</mark>", $product['product_name']);
                                                        $highlighted_tag = $product['tag_name'] ? preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<mark>$1</mark>", $product['tag_name']) : '';
                                                        $highlighted_toppings = $product['toppings_group_name'] ? preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<mark>$1</mark>", $product['toppings_group_name']) : '';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td><?php echo $product['id']; ?></td>
                                                        <td>
                                                            <?php if (!empty($product['image_path'])): ?>
                                                                <img src="<?php echo $product['image_path']; ?>" alt="Product Image" class="product-img">
                                                            <?php else: ?>
                                                                <span class="text-muted">No image</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $highlighted_name; ?></td>
                                                        <td><?php echo !empty($highlighted_tag) ? $highlighted_tag : '--'; ?></td>
                                                        <td><?php echo !empty($highlighted_toppings) ? $highlighted_toppings : '--'; ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['half_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['full_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['small_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['medium_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['large_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['8_inch_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['10_inch_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['12_inch_price']); ?></td>
                                                        <td class="price-column">₹<?php echo number_format($product['16_inch_price']); ?></td>
                                                        <td><?php echo $product['quantity']; ?></td>
                                                        <td>
                                                            <a href="configurable_products.php?toggle_status=<?php echo $product['id']; ?>" 
                                                               class="btn btn-sm status-toggle <?php echo $product['is_active'] ? 'btn-success' : 'btn-secondary'; ?>"
                                                               onclick="return confirm('Are you sure you want to <?php echo $product['is_active'] ? 'deactivate' : 'activate'; ?> this product?')">
                                                                <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <?php if (!empty($product['image_path'])): ?>
                                                                    <a href="configurable_products.php?remove_image=<?php echo $product['id']; ?>" 
                                                                       class="btn btn-sm btn-warning" 
                                                                       title="Remove Image"
                                                                       onclick="return confirm('Are you sure you want to remove the image for this product?')">
                                                                       <i class="fas fa-trash-alt"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <a href="configurable_products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="configurable_products.php?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this product?')">
                                                                    <i class="fas fa-times"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Mobile product cards (hidden on larger screens) -->
                                    <div class="mobile-products-list">
                                        <?php 
                                        $counter = 1;
                                        foreach ($products as $product): 
                                            // Highlight search terms if search is active
                                            $highlighted_name = $product['product_name'];
                                            $highlighted_tag = $product['tag_name'] ?? '';
                                            $highlighted_toppings = $product['toppings_group_name'] ?? '';
                                            
                                            if (!empty($search_query)) {
                                                $highlighted_name = preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<mark>$1</mark>", $product['product_name']);
                                                $highlighted_tag = $product['tag_name'] ? preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<mark>$1</mark>", $product['tag_name']) : '';
                                                $highlighted_toppings = $product['toppings_group_name'] ? preg_replace("/(" . preg_quote($search_query, '/') . ")/i", "<mark>$1</mark>", $product['toppings_group_name']) : '';
                                            }
                                        ?>
                                            <div class="mobile-product-card">
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Sr.No.</span>
                                                    <span class="mobile-field-value"><?php echo $counter++; ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">ID</span>
                                                    <span class="mobile-field-value"><?php echo $product['id']; ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Image</span>
                                                    <span class="mobile-field-value">
                                                        <?php if (!empty($product['image_path'])): ?>
                                                            <img src="<?php echo $product['image_path']; ?>" alt="Product Image" style="max-width: 60px; max-height: 60px; border-radius: 4px;">
                                                        <?php else: ?>
                                                            <span class="text-muted">No image</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Name</span>
                                                    <span class="mobile-field-value"><?php echo $highlighted_name; ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Tag</span>
                                                    <span class="mobile-field-value"><?php echo !empty($highlighted_tag) ? $highlighted_tag : '--'; ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Toppings Group</span>
                                                    <span class="mobile-field-value"><?php echo !empty($highlighted_toppings) ? $highlighted_toppings : '--'; ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Regular</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Half</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['half_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Full</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['full_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Small</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['small_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Medium</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['medium_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Large</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['large_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">8"</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['8_inch_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">10"</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['10_inch_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">12"</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['12_inch_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">16"</span>
                                                    <span class="mobile-field-value">₹<?php echo number_format($product['16_inch_price'], 2); ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Qty</span>
                                                    <span class="mobile-field-value"><?php echo $product['quantity']; ?></span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Status</span>
                                                    <span class="mobile-field-value">
                                                        <a href="configurable_products.php?toggle_status=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm status-toggle <?php echo $product['is_active'] ? 'btn-success' : 'btn-secondary'; ?>"
                                                           onclick="return confirm('Are you sure you want to <?php echo $product['is_active'] ? 'deactivate' : 'activate'; ?> this product?')">
                                                            <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </a>
                                                    </span>
                                                </div>
                                                <div class="mobile-product-field">
                                                    <span class="mobile-field-label">Actions</span>
                                                    <span class="mobile-field-value">
                                                        <div class="action-buttons">
                                                            <?php if (!empty($product['image_path'])): ?>
                                                                <a href="configurable_products.php?remove_image=<?php echo $product['id']; ?>" 
                                                                   class="btn btn-sm btn-warning" 
                                                                   title="Remove Image"
                                                                   onclick="return confirm('Are you sure you want to remove the image for this product?')">
                                                                   <i class="fas fa-trash-alt"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="configurable_products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="configurable_products.php?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this product?')">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </div>
                                                    </span>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {

            // Handle image selection and resize before upload
            $('#product_image').on('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                // Check if file is an image
                if (!file.type.match('image.*')) {
                    alert('Please select an image file (JPG, PNG, GIF)');
                    $(this).val('');
                    return;
                }
                
                // Check file size (max 5MB)
                if (file.size > 5000000) {
                    alert('File size must be less than 5MB');
                    $(this).val('');
                    return;
                }
                
                // Create a canvas for resizing
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const img = new Image();
                
                img.onload = function() {
                    // Calculate dimensions to maintain aspect ratio
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > height) {
                        if (width > 200) {
                            height *= 200 / width;
                            width = 200;
                        }
                    } else {
                        if (height > 200) {
                            width *= 200 / height;
                            height = 200;
                        }
                    }
                    
                    // Set canvas dimensions
                    canvas.width = 200;
                    canvas.height = 200;
                    
                    // Create a white background
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, 200, 200);
                    
                    // Center and draw the resized image
                    const xOffset = (200 - width) / 2;
                    const yOffset = (200 - height) / 2;
                    
                    ctx.drawImage(img, xOffset, yOffset, width, height);
                    
                    // Convert canvas to blob and create a new file
                    canvas.toBlob(function(blob) {
                        // Create a new file from the blob
                        const resizedFile = new File([blob], file.name, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        
                        // Create a new FileList and DataTransfer to set the file
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(resizedFile);
                        
                        // Replace the original file with the resized one
                        $('#product_image')[0].files = dataTransfer.files;
                        
                        // Preview the resized image
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // Remove any existing preview
                            $('.image-preview').remove();
                            
                            // Create preview
                            const preview = $('<div class="image-preview mt-2"><p>Resized preview (200×200px):</p><img src="' + e.target.result + '" alt="Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;"></div>');
                            $('#product_image').after(preview);
                        };
                        reader.readAsDataURL(resizedFile);
                        
                    }, 'image/jpeg', 0.9); // 0.9 quality
                };
                
                img.src = URL.createObjectURL(file);
            });
            
            // Remove preview when removing image
            $('#remove_image').on('change', function() {
                if (this.checked) {
                    $('.image-preview').hide();
                } else {
                    $('.image-preview').show();
                }
            });


            // Form validation
            $("#productForm").validate({
                rules: {
                    product_name: "required",
                    quantity: {
                        required: true,
                        digits: true,
                        min: 0
                    },
                    product_image: {
                        accept: "image/*",
                        filesize: 5000000 // 5MB
                    }
                },
                messages: {
                    product_name: "Please enter product name",
                    quantity: {
                        required: "Please enter quantity",
                        digits: "Please enter a whole number",
                        min: "Quantity must be at least 0"
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

            // Focus on search input when page loads if there's a search query
            <?php if (!empty($search_query)): ?>
                $('input[name="search"]').focus();
            <?php endif; ?>
            
            // Toggle between table and card view based on screen size
            function checkScreenSize() {
                if ($(window).width() < 992) {
                    $('.product-table').hide();
                    $('.mobile-products-list').show();
                } else {
                    $('.product-table').show();
                    $('.mobile-products-list').hide();
                }
            }
            
            // Check on load and resize
            checkScreenSize();
            $(window).resize(checkScreenSize);
        });
    </script>
</body>
</html>