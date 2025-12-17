<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db_connection.php';

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

// Initialize variables
$errors = [];
$success = '';
$total_imported = 0;
$user_id = $_SESSION['user_id'];

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Validate file upload
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file. Please try again.";
    } else {
        // Check file extension
        $file_name = $_FILES['csv_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($file_ext !== 'csv') {
            $errors[] = "Only CSV files are allowed.";
        } else {
            // Process CSV file
            $tmp_name = $_FILES['csv_file']['tmp_name'];
            
            // Open the CSV file
            if (($handle = fopen($tmp_name, "r")) !== FALSE) {
                // Skip header row if exists
                $header = fgetcsv($handle);
                
                // Check if header matches expected format
                $expected_header = ['product_name', 'description', 'image_url', 'status'];
                if ($header !== $expected_header) {
                    $errors[] = "CSV header doesn't match expected format. Required columns: " . implode(', ', $expected_header);
                } else {
                    // Prepare insert statement
                    $insert_sql = "INSERT INTO master_products 
                                  (user_id, product_name, description, image_path, status, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($insert_sql);
                    
                    if (!$stmt) {
                        $errors[] = "Database error: " . $conn->error;
                    } else {
                        // Begin transaction
                        $conn->begin_transaction();
                        
                        try {
                            $row_count = 1; // Start counting from 1 (after header)
                            
                            while (($data = fgetcsv($handle)) !== FALSE) {
                                $row_count++;
                                
                                // Validate row data
                                if (count($data) < 4) {
                                    $errors[] = "Row $row_count: Insufficient columns";
                                    continue;
                                }
                                
                                // Sanitize data
                                $product_name = trim($data[0]);
                                $description = trim($data[1]);
                                $image_url = trim($data[2]);
                                $status = strtolower(trim($data[3]));
                                
                                // Validate required fields
                                if (empty($product_name)) {
                                    $errors[] = "Row $row_count: Product name is required";
                                    continue;
                                }
                                
                                if (empty($description)) {
                                    $errors[] = "Row $row_count: Description is required";
                                    continue;
                                }
                                
                                // Validate status
                                if (!in_array($status, ['active', 'inactive'])) {
                                    $status = 'active'; // Default to active if invalid
                                }
                                
                                // Bind parameters and execute
                                $stmt->bind_param("issss", $user_id, $product_name, $description, $image_url, $status);
                                
                                if (!$stmt->execute()) {
                                    $errors[] = "Row $row_count: " . $stmt->error;
                                } else {
                                    $total_imported++;
                                }
                            }
                            
                            // Commit transaction if no errors
                            if (empty($errors)) {
                                $conn->commit();
                                $success = "Successfully imported $total_imported products";
                            } else {
                                $conn->rollback();
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $errors[] = "Error during import: " . $e->getMessage();
                        }
                        
                        $stmt->close();
                    }
                }
                
                fclose($handle);
            } else {
                $errors[] = "Could not open the uploaded file.";
            }
        }
    }
}

// Fetch user name for display
$user_name = '';
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
    <title>Bulk Import Master Products | Admin</title>
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
    <style>
        .sample-csv {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .instructions {
            margin-bottom: 20px;
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
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Bulk Import Products</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <h5>Import completed with errors:</h5>
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($total_imported > 0): ?>
                                            <p class="mt-2 mb-0">Successfully imported <?= $total_imported ?> products despite errors.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!empty($success)): ?>
                                    <div class="alert alert-success">
                                        <?= htmlspecialchars($success) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="instructions">
                                    <h5>Instructions:</h5>
                                    <ol>
                                        <li>Prepare a CSV file with the following columns in order: <strong>product_name, description, image_url, status</strong></li>
                                        <li>Status should be either "active" or "inactive" (defaults to active if invalid)</li>
                                        <li>The first row should be the header row with column names</li>
                                        <li>Maximum file size: 2MB</li>
                                    </ol>
                                </div>

                                <form method="POST" action="bulk_master_products.php" enctype="multipart/form-data">
                                    <div class="form-group mb-3">
                                        <label for="csv_file">CSV File</label>
                                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                        <small class="text-muted">Only .csv files allowed</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload me-1"></i> Import Products
                                    </button>
                                    <a href="master_products.php" class="btn btn-secondary">Back to Products</a>
                                </form>

                                <div class="sample-csv">
                                    <h5>Sample CSV Format:</h5>
                                    <pre>product_name,description,image_url,status
"Product 1","Description for product 1","http://example.com/image1.jpg","active"
"Product 2","Description for product 2","http://example.com/image2.jpg","inactive"
"Product 3","Description for product 3","","active"</pre>
                                    <a href="data:text/csv;charset=utf-8,product_name,description,image_url,status%0A%22Product%201%22,%22Description%20for%20product%201%22,%22http://example.com/image1.jpg%22,%22active%22%0A%22Product%202%22,%22Description%20for%20product%202%22,%22http://example.com/image2.jpg%22,%22inactive%22%0A%22Product%203%22,%22Description%20for%20product%203%22,%22%22,%22active%22" 
                                       download="sample_products.csv" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="fas fa-download me-1"></i> Download Sample CSV
                                    </a>
                                </div>
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