<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user name and role
$sql_name = "SELECT name, role FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name, $user_role);
$stmt_name->fetch();
$stmt_name->close();

// Create user-specific vegetable products table if not exists
$user_products_table = "vegetable_products_" . $user_id;
$create_table_sql = "CREATE TABLE IF NOT EXISTS `$user_products_table` (
    `id` int NOT NULL AUTO_INCREMENT,
    `master_id` int NOT NULL COMMENT 'Reference to master_vegetable_products.id',
    `product_name_en` varchar(255) NOT NULL,
    `product_name_hi` varchar(255) NOT NULL,
    `price` decimal(10,2) NOT NULL DEFAULT '0.00',
    `weight` varchar(50) NOT NULL DEFAULT '1kg',
    `unit` enum('kg','pcs') NOT NULL DEFAULT 'kg',
    `quantity` int NOT NULL DEFAULT '0',
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `portion_type` enum('weight','pieces','units','litre') DEFAULT 'weight',
    `base_unit` varchar(20) DEFAULT 'kg',
    `is_custom` tinyint(1) DEFAULT '0',
    `image_path` varchar(500) DEFAULT NULL,
    `tag_id` int DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_master_id` (`master_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";

if (!$conn->query($create_table_sql)) {
    $error = "Error creating user products table: " . $conn->error;
}

// Handle AJAX request to add product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'add_product') {
        $master_id = intval($_POST['master_id']);
        $price = floatval($_POST['price']);
        $unit = $_POST['unit'];
        $weight = $_POST['weight'];
        $quantity = intval($_POST['quantity']);
        
        // Get master product details
        $master_sql = "SELECT product_name_en, product_name_hi, image_path FROM master_vegetable_products WHERE id = ?";
        $master_stmt = $conn->prepare($master_sql);
        $master_stmt->bind_param("i", $master_id);
        $master_stmt->execute();
        $master_result = $master_stmt->get_result();
        
        if ($master_result->num_rows > 0) {
            $master_data = $master_result->fetch_assoc();
            
            // Check if product already exists for this user
            $check_sql = "SELECT id FROM `$user_products_table` WHERE master_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $master_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing product
                $update_sql = "UPDATE `$user_products_table` SET price = ?, unit = ?, weight = ?, quantity = ?, updated_at = NOW() WHERE master_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("dssii", $price, $unit, $weight, $quantity, $master_id);
                
                if ($update_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
                }
                $update_stmt->close();
            } else {
                // Insert new product
                $insert_sql = "INSERT INTO `$user_products_table` (master_id, product_name_en, product_name_hi, price, unit, weight, quantity, image_path, is_active) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("issdssis", $master_id, $master_data['product_name_en'], $master_data['product_name_hi'], 
                                        $price, $unit, $weight, $quantity, $master_data['image_path']);
                
                if ($insert_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Product added successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error adding product: ' . $conn->error]);
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Master product not found!']);
        }
        $master_stmt->close();
        exit();
    }
    
    // Handle AJAX request to remove product
    if ($_POST['action'] == 'remove_product') {
        $master_id = intval($_POST['master_id']);
        
        $delete_sql = "DELETE FROM `$user_products_table` WHERE master_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $master_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product removed successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing product: ' . $conn->error]);
        }
        $delete_stmt->close();
        exit();
    }
    
    // Handle AJAX request to get user products
    if ($_POST['action'] == 'get_user_products') {
        $products = [];
        $select_sql = "SELECT * FROM `$user_products_table` ORDER BY created_at DESC";
        $result = $conn->query($select_sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        }
        echo json_encode(['success' => true, 'products' => $products]);
        exit();
    }
    
    // Handle AJAX request to update product details
    if ($_POST['action'] == 'update_product') {
        $master_id = intval($_POST['master_id']);
        $price = floatval($_POST['price']);
        $unit = $_POST['unit'];
        $weight = $_POST['weight'];
        $quantity = intval($_POST['quantity']);
        $is_active = intval($_POST['is_active']);
        
        $update_sql = "UPDATE `$user_products_table` SET price = ?, unit = ?, weight = ?, quantity = ?, is_active = ?, updated_at = NOW() WHERE master_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dssiii", $price, $unit, $weight, $quantity, $is_active, $master_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
        }
        $update_stmt->close();
        exit();
    }
}

// Fetch master vegetable products (alphabetical order by English name)
$master_products = [];
$master_sql = "SELECT id, product_name_en, product_name_hi, image_path, unit, status 
               FROM master_vegetable_products 
               ORDER BY product_name_en ASC, product_name_hi ASC";
$master_result = $conn->query($master_sql);
if ($master_result && $master_result->num_rows > 0) {
    while ($row = $master_result->fetch_assoc()) {
        $master_products[] = $row;
    }
}

// Fetch user's saved products (latest first)
$user_products = [];
$user_products_sql = "SELECT * FROM `$user_products_table` ORDER BY created_at DESC";
$user_products_result = $conn->query($user_products_sql);
if ($user_products_result && $user_products_result->num_rows > 0) {
    while ($row = $user_products_result->fetch_assoc()) {
        $user_products[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Vegetable Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    
    <style>
    /* Minified CSS */
    *{box-sizing:border-box}body{overflow-x:hidden}.products-container{display:flex;flex-direction:column;gap:20px}@media(min-width:992px){.products-container{flex-direction:row}.master-section,.my-products-section{flex:1;width:50%}}.master-section,.my-products-section{background:#fff;border-radius:12px;padding:15px;box-shadow:0 1px 3px rgba(0,0,0,.1)}.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #f0f0f0;flex-wrap:wrap;gap:10px}.section-title{font-size:1.1rem;font-weight:600;margin:0;display:flex;align-items:center;gap:8px}.section-title i{color:#fb5b29;font-size:1.2rem}.badge-count{background:#fb5b29;color:#fff;padding:2px 8px;border-radius:20px;font-size:.75rem}.search-box{position:relative;margin-bottom:15px}.search-box input{padding:8px 12px 8px 35px;border:1px solid #e0e0e0;border-radius:20px;width:100%;font-size:.85rem}.search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#999}.master-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:5px}@media(max-width:992px){.master-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:768px){.master-grid{grid-template-columns:repeat(2,1fr)}}.master-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:12px;transition:all .2s ease;cursor:pointer}.master-card.hide{display:none}.master-card:hover{border-color:#fb5b29;box-shadow:0 2px 8px rgba(251,91,41,.1)}.master-card.active{border-color:#fb5b29;background:#fff8f5;border-left:3px solid #fb5b29}.master-image{width:100%;height:120px;margin-bottom:10px}.master-image img{width:100%;height:100%;object-fit:cover;border-radius:10px}.master-image-placeholder{width:100%;height:120px;background:#f5f5f5;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#999}.master-info{text-align:center}.master-name{font-weight:600;font-size:.9rem;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.master-name-hi{font-size:.7rem;color:#666;margin-bottom:6px}.master-unit{display:inline-block;background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:12px;font-size:.7rem;font-weight:500;margin-bottom:10px}.master-action{text-align:center}.products-grid{display:flex;flex-direction:column;gap:12px;padding:5px;max-height:70vh;overflow-y:auto}.product-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:12px;transition:all .2s ease}.product-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.1)}.product-content{display:flex;gap:12px;align-items:center}.product-image{width:60px;height:60px;flex-shrink:0}.product-image img{width:100%;height:100%;object-fit:cover;border-radius:10px}.product-image-placeholder{width:60px;height:60px;background:#f5f5f5;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fb5b29}.product-details{flex:1;min-width:0}.product-title{font-weight:600;font-size:.95rem;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.product-price{font-weight:bold;color:#fb5b29;font-size:1rem;margin-bottom:4px}.product-meta{font-size:.7rem;color:#666;display:flex;flex-wrap:wrap;gap:8px;align-items:center}.stock-badge{background:#e3f2fd;color:#1976d2;padding:2px 6px;border-radius:10px}.status-active{color:#4caf50}.status-inactive{color:#f44336}.product-actions{display:flex;gap:6px;flex-shrink:0}.product-actions button{padding:6px 10px;font-size:.75rem}.empty-state{text-align:center;padding:40px 20px;color:#999}.empty-state i{font-size:3rem;margin-bottom:15px;opacity:.5}.no-results{text-align:center;padding:40px 20px;color:#999;display:none}.no-results i{font-size:3rem;margin-bottom:15px;opacity:.5}@media(max-width:576px){.card-body {padding: 10px;}.modal-dialog{margin:.5rem}.modal-content{border-radius:16px}.modal-body{padding:20px 15px}.form-label{font-size:.85rem}.form-control,.form-select{font-size:.9rem;padding:8px 12px}.product-content{flex-wrap:wrap}.product-actions{width:100%;justify-content:flex-end;margin-top:8px}.master-section,.my-products-section{max-height:50vh;overflow-y:auto}}.back-button{margin-top:20px;padding-top:20px;border-top:1px solid #e0e0e0}.master-grid::-webkit-scrollbar,.products-grid::-webkit-scrollbar{width:4px}.master-grid::-webkit-scrollbar-track,.products-grid::-webkit-scrollbar-track{background:#f1f1f1;border-radius:10px}.master-grid::-webkit-scrollbar-thumb,.products-grid::-webkit-scrollbar-thumb{background:#fb5b29;border-radius:10px}.loading-spinner{display:inline-block;width:16px;height:16px;border:2px solid #f3f3f3;border-top:2px solid #fb5b29;border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.toast-notification{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:10px 20px;border-radius:8px;font-size:.85rem;z-index:9999;animation:slideUp .3s ease}@keyframes slideUp{from{transform:translateX(-50%) translateY(100px);opacity:0}to{transform:translateX(-50%) translateY(0);opacity:1}}
    </style>
</head>
<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($user_role === 'room') {
            include 'room_management_menu.php';
        } elseif ($user_role === 'vegetable_seller') {
            include 'vegetable_seller_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-carrot me-2"></i>Vegetable Products Management
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['message'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Two Column Responsive Layout -->
                                <div class="products-container">
                                    <!-- Left Column: Master Products -->
                                    <div class="master-section">
                                        <div class="section-header">
                                            <div class="section-title">
                                                <i class="fas fa-database"></i>
                                                <span>Master Products</span>
                                            </div>
                                            <span class="badge-count" id="masterCount">
                                                <i class="fas fa-boxes me-1"></i><?php echo count($master_products); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Search Box -->
                                        <div class="search-box">
                                            <i class="fas fa-search"></i>
                                            <input type="text" id="searchMaster" class="form-control" placeholder="Search by product name...">
                                        </div>
                                        
                                        <div class="master-grid" id="masterGrid">
                                            <?php if (count($master_products) > 0): ?>
                                                <?php foreach ($master_products as $product): ?>
                                                    <?php 
                                                    $is_added = false;
                                                    foreach ($user_products as $up) {
                                                        if ($up['master_id'] == $product['id']) {
                                                            $is_added = true;
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    <div class="master-card" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo strtolower(htmlspecialchars($product['product_name_en'])); ?>">
                                                        <div class="master-image">
                                                            <?php if (!empty($product['image_path'])): ?>
                                                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name_en']); ?>">
                                                            <?php else: ?>
                                                                <div class="master-image-placeholder">
                                                                    <i class="fas fa-seedling fa-2x"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="master-info">
                                                            <div class="master-name"><?php echo htmlspecialchars($product['product_name_en']); ?></div>
                                                            <div class="master-name-hi"><?php echo htmlspecialchars($product['product_name_hi']); ?></div>
                                                        </div>
                                                        <div class="master-action">
                                                            <?php if ($is_added): ?>
                                                                <button class="btn btn-sm btn-success btn-edit-master" 
                                                                        data-master-id="<?php echo $product['id']; ?>" 
                                                                        data-name="<?php echo htmlspecialchars($product['product_name_en']); ?>" 
                                                                        data-unit="<?php echo $product['unit']; ?>">
                                                                    <i class="fas fa-pen"></i> <span class="d-none d-sm-inline">Edit</span>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-primary btn-add-product" 
                                                                        data-master-id="<?php echo $product['id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($product['product_name_en']); ?>"
                                                                        data-unit="<?php echo $product['unit']; ?>">
                                                                    <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">Add</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <i class="fas fa-box-open"></i>
                                                    <p>No master products found.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="no-results" id="noResults">
                                            <i class="fas fa-search"></i>
                                            <p>No products found matching your search.</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column: My Products (Latest First) -->
                                    <div class="my-products-section">
                                        <div class="section-header">
                                            <div class="section-title">
                                                <i class="fas fa-store"></i>
                                                <span>My Products</span>
                                            </div>
                                            <span class="badge-count" id="productCount">
                                                <i class="fas fa-tag me-1"></i><?php echo count($user_products); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="products-grid" id="savedProductsList">
                                            <?php if (count($user_products) > 0): ?>
                                                <?php foreach ($user_products as $product): ?>
                                                    <div class="product-card" data-master-id="<?php echo $product['master_id']; ?>">
                                                        <div class="product-content">
                                                            <div class="product-image">
                                                                <?php if (!empty($product['image_path'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name_en']); ?>">
                                                                <?php else: ?>
                                                                    <div class="product-image-placeholder">
                                                                        <i class="fas fa-carrot fa-2x"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="product-details">
                                                                <div class="product-title"><?php echo htmlspecialchars($product['product_name_en']); ?></div>
                                                                <div class="product-price">₹<?php echo round($product['price']); ?> / <?php echo $product['weight']; ?></div>
                                                                <div class="product-meta">
                                                                    <span class="stock-badge">
                                                                        <i class="fas fa-boxes me-1"></i>Stock: <?php echo $product['quantity']; ?> <?php echo $product['unit']; ?>
                                                                    </span>
                                                                    <span class="<?php echo $product['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                                        <i class="fas <?php echo $product['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                                        <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="product-actions">
                                                                <button class="btn btn-sm btn-outline-primary btn-edit-product" 
                                                                        title="Edit Product"
                                                                        data-master-id="<?php echo $product['master_id']; ?>"
                                                                        data-price="<?php echo $product['price']; ?>"
                                                                        data-unit="<?php echo $product['unit']; ?>"
                                                                        data-weight="<?php echo htmlspecialchars($product['weight']); ?>"
                                                                        data-quantity="<?php echo $product['quantity']; ?>"
                                                                        data-active="<?php echo $product['is_active']; ?>">
                                                                    <i class="fas fa-pen"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger btn-remove-product" 
                                                                        title="Remove Product"
                                                                        data-master-id="<?php echo $product['master_id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($product['product_name_en']); ?>">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <i class="fas fa-box-open"></i>
                                                    <p>No products added yet.</p>
                                                    <p class="small text-muted">Click "Add" button on the left to add products.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-plus-circle me-2"></i>Add Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm">
                        <input type="hidden" id="action_type" name="action_type" value="add">
                        <input type="hidden" id="master_id" name="master_id">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-tag me-1"></i>Product Name
                            </label>
                            <input type="text" class="form-control bg-light" id="product_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-rupee-sign me-1"></i>Price (₹)
                            </label>
                            <input type="number" step="1" class="form-control" id="price" name="price" placeholder="Enter price" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-weight-hanging me-1"></i>Weight/Unit
                                </label>
                                <select class="form-select" id="weight" name="weight">
                                    <option value="250g">250g</option>
                                    <option value="500g">500g</option>
                                    <option value="1kg" selected>1kg</option>
                                    <option value="2kg">2kg</option>
                                    <option value="5kg">5kg</option>
                                    <option value="piece">Piece</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-cubes me-1"></i>Unit Type
                                </label>
                                <select class="form-select" id="unit" name="unit">
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-boxes me-1"></i>Stock Quantity
                            </label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="0" placeholder="Enter stock quantity" required>
                        </div>
                        
                        <div class="mb-3" id="status_div" style="display: none;">
                            <label class="form-label">
                                <i class="fas fa-toggle-on me-1"></i>Status
                            </label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveProductBtn">
                        <i class="fas fa-save me-1"></i>Save Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        var modal = new bootstrap.Modal(document.getElementById('productModal'));
        
        function showToast(message, isError){
            var toast=$('<div class="toast-notification"><i class="fas '+(isError?'fa-exclamation-triangle':'fa-check-circle')+' me-2"></i>'+message+'</div>');
            $('body').append(toast);
            setTimeout(function(){toast.fadeOut(function(){$(this).remove();});},3000);
        }
        
        // Search functionality for master products
        $('#searchMaster').on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            var visibleCount = 0;
            
            $('.master-card').each(function() {
                var productName = $(this).data('product-name');
                if (productName.indexOf(searchTerm) > -1) {
                    $(this).removeClass('hide').show();
                    visibleCount++;
                } else {
                    $(this).addClass('hide').hide();
                }
            });
            
            // Update count display
            $('#masterCount').html('<i class="fas fa-boxes me-1"></i>' + visibleCount);
            
            // Show/hide no results message
            if (visibleCount === 0) {
                $('#noResults').show();
                $('.master-grid').hide();
            } else {
                $('#noResults').hide();
                $('.master-grid').show();
            }
        });
        
        $(document).on('click','.btn-add-product',function(e){
            e.stopPropagation();
            $('#modalTitle').html('<i class="fas fa-plus-circle me-2"></i>Add Product');
            $('#action_type').val('add');
            $('#master_id').val($(this).data('master-id'));
            $('#product_name').val($(this).data('name'));
            $('#price').val('');
            $('#weight').val('1kg');
            $('#unit').val($(this).data('unit'));
            $('#quantity').val(0);
            $('#status_div').hide();
            modal.show();
        });
        
        $(document).on('click','.btn-edit-master',function(e){
            e.stopPropagation();
            var masterId=$(this).data('master-id');
            var productName=$(this).data('name');
            var btn=$(this),originalHtml=btn.html();
            btn.html('<span class="loading-spinner"></span>').prop('disabled',true);
            $.ajax({
                url:window.location.href,type:'POST',data:{action:'get_user_products'},dataType:'json',
                success:function(r){
                    if(r.success&&r.products){
                        var product=r.products.find(p=>p.master_id==masterId);
                        if(product){
                            $('#modalTitle').html('<i class="fas fa-edit me-2"></i>Edit Product');
                            $('#action_type').val('edit');
                            $('#master_id').val(masterId);
                            $('#product_name').val(productName);
                            $('#price').val(product.price);
                            $('#weight').val(product.weight);
                            $('#unit').val(product.unit);
                            $('#quantity').val(product.quantity);
                            $('#is_active').val(product.is_active);
                            $('#status_div').show();
                            modal.show();
                        }else showToast('Product not found!',true);
                    }else showToast('Error loading product details!',true);
                },error:function(){showToast('Error loading product details!',true);},
                complete:function(){btn.html(originalHtml).prop('disabled',false);}
            });
        });
        
        $(document).on('click','.btn-edit-product',function(e){
            e.stopPropagation();
            $('#modalTitle').html('<i class="fas fa-edit me-2"></i>Edit Product');
            $('#action_type').val('edit');
            $('#master_id').val($(this).data('master-id'));
            $('#product_name').val($(this).closest('.product-card').find('.product-title').text());
            $('#price').val($(this).data('price'));
            $('#weight').val($(this).data('weight'));
            $('#unit').val($(this).data('unit'));
            $('#quantity').val($(this).data('quantity'));
            $('#is_active').val($(this).data('active'));
            $('#status_div').show();
            modal.show();
        });
        
        $('#saveProductBtn').on('click',function(){
            var actionType=$('#action_type').val(),masterId=$('#master_id').val(),price=$('#price').val(),unit=$('#unit').val(),weight=$('#weight').val(),quantity=$('#quantity').val();
            if(!price||price<=0){showToast('Please enter a valid price',true);$('#price').focus();return;}
            if(quantity<0){showToast('Please enter a valid quantity',true);$('#quantity').focus();return;}
            var postData={action:'add_product',master_id:masterId,price:price,unit:unit,weight:weight,quantity:quantity};
            if(actionType==='edit'){postData.action='update_product';postData.is_active=$('#is_active').val();}
            var saveBtn=$(this),originalHtml=saveBtn.html();
            saveBtn.html('<span class="loading-spinner"></span> Saving...').prop('disabled',true);
            $.ajax({
                url:window.location.href,type:'POST',data:postData,dataType:'json',
                success:function(r){
                    if(r.success){modal.hide();showToast(r.message);setTimeout(function(){location.reload();},500);}
                    else{showToast(r.message,true);saveBtn.html(originalHtml).prop('disabled',false);}
                },error:function(){showToast('Error saving product. Please try again.',true);saveBtn.html(originalHtml).prop('disabled',false);}
            });
        });
        
        $(document).on('click','.btn-remove-product',function(e){
            e.stopPropagation();
            var masterId=$(this).data('master-id'),productName=$(this).data('name');
            if(confirm('Are you sure you want to remove "'+productName+'" from your products?')){
                var btn=$(this),originalHtml=btn.html();
                btn.html('<span class="loading-spinner"></span>').prop('disabled',true);
                $.ajax({
                    url:window.location.href,type:'POST',data:{action:'remove_product',master_id:masterId},dataType:'json',
                    success:function(r){
                        if(r.success){showToast(r.message);setTimeout(function(){location.reload();},500);}
                        else{showToast(r.message,true);btn.html(originalHtml).prop('disabled',false);}
                    },error:function(){showToast('Error removing product. Please try again.',true);btn.html(originalHtml).prop('disabled',false);}
                });
            }
        });
        
        $(document).on('click','.master-card',function(e){
            if(!$(e.target).is('button')&&!$(e.target).closest('button').length){
                $('.master-card').removeClass('active');
                $(this).addClass('active');
            }
        });
    });
    </script>
</body>
</html>