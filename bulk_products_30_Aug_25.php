<?php
// Error reporting at the very top
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Verify db_connection.php exists
if (!file_exists('db_connection.php')) {
    die("Database connection file missing");
}
require 'db_connection.php';

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Handle CSV file upload
if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File upload error: " . $file['error'];
    } else {
        // Check file extension
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($file_ext) !== 'csv') {
            $error_message = "Please upload a valid CSV file.";
        } else {
            // Process the CSV file
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                // Get header row to check for product_id
                $header = fgetcsv($handle);
                $has_product_id = in_array('product_id', array_map('strtolower', $header)) 
                                || in_array('id', array_map('strtolower', $header));
                
                // Reset file pointer if we checked the header
                if ($has_product_id) {
                    rewind($handle);
                    fgetcsv($handle); // Skip header again
                }
                
                $success_count = 0;
                $update_count = 0;
                $error_count = 0;
                $errors = [];
                
                // Prepare insert and update statements
                $insert_sql = "INSERT INTO products (
                    user_id, 
                    product_name, 
                    description, 
                    price, 
                    quantity, 
                    image_path,
                    tag_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $update_sql = "UPDATE products SET 
                    product_name = ?, 
                    description = ?, 
                    price = ?, 
                    quantity = ?, 
                    image_path = ?,
                    tag_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?";
                
                $insert_stmt = $conn->prepare($insert_sql);
                $update_stmt = $conn->prepare($update_sql);
                
                if (!$insert_stmt) {
                    $error_message = "Insert prepare failed: " . $conn->error;
                } elseif (!$update_stmt) {
                    $error_message = "Update prepare failed: " . $conn->error;
                } else {
                    while (($data = fgetcsv($handle)) !== false) {
                        // Skip empty rows
                        if (empty(array_filter($data))) {
                            continue;
                        }
                        
                        // Check if this is an update or insert operation
                        if ($has_product_id) {
                            // Update operation - CSV has product_id
                            if (count($data) < 7) {
                                $errors[] = "Row with data: " . implode(',', $data) . " - Insufficient columns for update (needs product_id and tag_id)";
                                $error_count++;
                                continue;
                            }
                            
                            $product_id = trim($data[0] ?? '');
                            $product_name = trim($data[1] ?? '');
                            $description = trim($data[2] ?? '');
                            $price = trim($data[3] ?? '');
                            $quantity = trim($data[4] ?? '');
                            $image_path = trim($data[5] ?? '');
                            $tag_id = trim($data[6] ?? '');
                        } else {
                            // Insert operation - no product_id in CSV
                            if (count($data) < 6) {
                                $errors[] = "Row with data: " . implode(',', $data) . " - Insufficient columns (needs tag_id)";
                                $error_count++;
                                continue;
                            }
                            
                            $product_id = null;
                            $product_name = trim($data[0] ?? '');
                            $description = trim($data[1] ?? '');
                            $price = trim($data[2] ?? '');
                            $quantity = trim($data[3] ?? '');
                            $image_path = trim($data[4] ?? '');
                            $tag_id = trim($data[5] ?? '');
                        }
                        
                        // Validate required fields
                        if (empty($product_name)) {
                            $errors[] = "Row with data: " . implode(',', $data) . " - Product name is required";
                            $error_count++;
                            continue;
                        }
                        
                        // Clean and validate price
                        $price = str_replace(['₹', '$', ',', ' '], '', $price);
                        if (!is_numeric($price)) {
                            $errors[] = "Row with data: " . implode(',', $data) . " - Invalid price format";
                            $error_count++;
                            continue;
                        }
                        $price = (float)$price;
                        if ($price <= 0) {
                            $errors[] = "Row with data: " . implode(',', $data) . " - Price must be greater than 0";
                            $error_count++;
                            continue;
                        }
                        
                        // Validate quantity
                        $quantity = str_replace(',', '', $quantity);
                        if (!is_numeric($quantity)) {
                            $errors[] = "Row with data: " . implode(',', $data) . " - Invalid quantity format";
                            $error_count++;
                            continue;
                        }
                        $quantity = (int)$quantity;
                        if ($quantity < 0) {
                            $errors[] = "Row with data: " . implode(',', $data) . " - Quantity cannot be negative";
                            $error_count++;
                            continue;
                        }
                        
                        // Validate tag_id
                        if (!empty($tag_id) && !is_numeric($tag_id)) {
                            $errors[] = "Row with data: " . implode(',', $data) . " - Tag ID must be numeric";
                            $error_count++;
                            continue;
                        }
                        $tag_id = !empty($tag_id) ? (int)$tag_id : null;
                        
                        // Process based on whether we have a product_id or not
                        if ($product_id) {
                            // Update existing product
                            $update_stmt->bind_param("ssdisiii", 
                                $product_name, 
                                $description, 
                                $price, 
                                $quantity, 
                                $image_path,
                                $tag_id,
                                $product_id,
                                $user_id
                            );
                            
                            if ($update_stmt->execute()) {
                                if ($update_stmt->affected_rows > 0) {
                                    $update_count++;
                                } else {
                                    $errors[] = "Row with product_id: $product_id - No changes made or product not found";
                                    $error_count++;
                                }
                            } else {
                                $errors[] = "Row with product_id: $product_id - Update error: " . $update_stmt->error;
                                $error_count++;
                            }
                        } else {
                            // Insert new product
                            $insert_stmt->bind_param("issdisi", 
                                $user_id, 
                                $product_name, 
                                $description, 
                                $price, 
                                $quantity, 
                                $image_path,
                                $tag_id
                            );
                            
                            if ($insert_stmt->execute()) {
                                $success_count++;
                            } else {
                                $errors[] = "Row with data: " . implode(',', $data) . " - Insert error: " . $insert_stmt->error;
                                $error_count++;
                            }
                        }
                    }
                    
                    $insert_stmt->close();
                    $update_stmt->close();
                }
                fclose($handle);
                
                // Set success/error message
                $message_parts = [];
                if ($success_count > 0) {
                    $message_parts[] = "Successfully added $success_count new products.";
                }
                if ($update_count > 0) {
                    $message_parts[] = "Successfully updated $update_count existing products.";
                }
                $success_message = implode(' ', $message_parts);
                
                if ($error_count > 0) {
                    $error_message = "$error_count rows had errors. First few errors: " . implode('<br>', array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $error_message .= "<br>... and " . (count($errors) - 5) . " more errors";
                    }
                }
            } else {
                $error_message = "Could not read the uploaded file.";
            }
        }
    }
}

// Handle sample CSV download
if (isset($_GET['download_sample'])) {
    $type = isset($_GET['type']) ? $_GET['type'] : 'new';
    
    header('Content-Type: text/csv');
    
    if ($type === 'update') {
        header('Content-Disposition: attachment; filename="products_update_sample.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Product ID', 'Product Name', 'Description', 'Price', 'Quantity', 'Image Path', 'Tag ID']);
        fputcsv($output, ['3647', 'Chicken Manchow Soup', 'Updated description', '400.00', '200', '', '101']);
        fputcsv($output, ['3648', 'Veg Fried Rice', 'Updated description', '350.00', '150', '', '102']);
    } else {
        header('Content-Disposition: attachment; filename="products_new_sample.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Product Name', 'Description', 'Price', 'Quantity', 'Image Path', 'Tag ID']);
        fputcsv($output, ['New Product 1', 'Description for product 1', '200.00', '100', '', '101']);
        fputcsv($output, ['New Product 2', 'Description for product 2', '300.00', '50', '', '102']);
    }
    
    fclose($output);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Bulk Products Import</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>
<body>

<div class="wrapper">
    <?php 
    // Verify includes exist before including
    if (file_exists('toolbar.php')) {
        include 'toolbar.php'; 
    } else {
        die("Toolbar file missing");
    }
    
    if (file_exists('menu.php')) {
        include 'menu.php';
    } else {
        die("Menu file missing");
    }
    ?>

    <div class="page-content">
        <div class="container">
            <div class="row">
                <div class="col-xl-9">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="header-title mb-4">Bulk Products Import/Update</h4>

                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success"><?php echo $success_message; ?></div>
                            <?php endif; ?>

                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                            <?php endif; ?>

                            <form action="" method="post" enctype="multipart/form-data">
                                <div class="form-group mb-3">
                                    <label for="csv_file">CSV File</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                    <small class="form-text text-muted">
                                        For new products: Product Name, Description, Price, Quantity, Image Path, Tag ID<br>
                                        For updates: Product ID, Product Name, Description, Price, Quantity, Image Path, Tag ID<br>
                                        Note: Price can be in any format (₹200, $5.99, 150.00)
                                    </small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="import_csv" class="btn btn-primary">Import/Update Products</button>
                                    <a href="?download_sample=1" class="btn btn-outline-secondary">Download New Products CSV</a>
                                    <a href="?download_sample=1&type=update" class="btn btn-outline-secondary">Download Update CSV</a>
                                </div>
                            </form>

                            <div class="mt-4">
                                <h5>CSV File Formats:</h5>
                                
                                <div class="mb-4">
                                    <h6>For New Products:</h6>
                                    <pre>
Product Name,Description,Price,Quantity,Image Path,Tag ID
"Chicken Manchow Soup","Delicious soup with chicken",200.00,100,"images/soup.jpg",101
"Veg Fried Rice","Vegetable fried rice with spices",150.00,50,"images/rice.jpg",102
                                    </pre>
                                </div>
                                
                                <div>
                                    <h6>For Updating Existing Products:</h6>
                                    <pre>
Product ID,Product Name,Description,Price,Quantity,Image Path,Tag ID
3647,"Chicken Manchow Soup","Updated description",400.00,200,"images/soup_new.jpg",101
3648,"Veg Fried Rice","Updated description",350.00,150,"images/rice_new.jpg",102
                                    </pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php 
            if (file_exists('footer.php')) {
                include 'footer.php';
            } else {
                die("Footer file missing");
            }
            ?>
        </div>
    </div>
</div>

<script src="assets/js/vendor.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(document).ready(function() {
    $('form').validate({
        rules: {
            csv_file: {
                required: true,
                extension: "csv"
            }
        },
        messages: {
            csv_file: {
                required: "Please select a CSV file",
                extension: "Please upload a valid CSV file"
            }
        }
    });
});
</script>
</body>
</html>