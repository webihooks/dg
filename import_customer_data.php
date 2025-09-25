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
$message = '';
$message_type = '';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Process CSV file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload error: ' . $file['error'];
        $message_type = 'danger';
    } else {
        // Check file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            $message = 'Only CSV files are allowed.';
            $message_type = 'danger';
        } else {
            // Process the CSV file
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle); // Get header row
            
            // Validate CSV structure
            $expected_headers = ['customer_name', 'customer_phone', 'delivery_address'];
            $header_diff = array_diff($expected_headers, $header);
            
            if (!empty($header_diff)) {
                $message = 'Invalid CSV format. Required columns: ' . implode(', ', $expected_headers);
                $message_type = 'danger';
            } else {
                $success_count = 0;
                $error_count = 0;
                $duplicate_count = 0;
                $error_details = [];
                
                // Prepare SQL statement
                $insert_sql = "INSERT INTO customer_data 
                              (user_id, customer_name, customer_phone, delivery_address) 
                              VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE 
                              customer_name = VALUES(customer_name), 
                              delivery_address = VALUES(delivery_address)";
                
                $stmt = $conn->prepare($insert_sql);
                
                // Process each row
                $row_number = 1; // Start with header row + 1
                while (($row = fgetcsv($handle)) !== false) {
                    $row_number++;
                    
                    if (count($row) !== count($header)) {
                        $error_count++;
                        $error_details[] = "Row $row_number: Malformed row (column count doesn't match header)";
                        continue;
                    }
                    
                    $data = array_combine($header, $row);
                    
                    // Validate required field (only phone is required)
                    $validation_errors = [];
                    
                    if (empty(trim($data['customer_phone']))) {
                        $validation_errors[] = "Customer phone is required";
                    } else {
                        // Clean phone number
                        $phone = preg_replace('/[^0-9]/', '', $data['customer_phone']);
                        if (empty($phone)) {
                            $validation_errors[] = "Customer phone contains no valid digits";
                        }
                    }
                    
                    // Skip row if there are validation errors
                    if (!empty($validation_errors)) {
                        $error_count++;
                        $error_details[] = "Row $row_number: " . implode(", ", $validation_errors);
                        continue;
                    }
                    
                    try {
                        $phone = preg_replace('/[^0-9]/', '', $data['customer_phone']);
                        
                        // Set default values for empty fields
                        $customer_name = !empty(trim($data['customer_name'])) ? trim($data['customer_name']) : "No Name";
                        $delivery_address = !empty(trim($data['delivery_address'])) ? trim($data['delivery_address']) : "";
                        
                        $stmt->bind_param("isss", $user_id, $customer_name, $phone, $delivery_address);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows > 0) {
                            $success_count++;
                        } else {
                            $duplicate_count++;
                        }
                    } catch (Exception $e) {
                        $error_count++;
                        $error_details[] = "Row $row_number: Database error - " . $e->getMessage();
                    }
                }
                
                fclose($handle);
                $stmt->close();
                
                $message = "Import completed: $success_count records imported successfully. ";
                if ($duplicate_count > 0) {
                    $message .= "$duplicate_count duplicates skipped. ";
                }
                if ($error_count > 0) {
                    $message .= "$error_count records failed to import.";
                    // Add error details if there were errors
                    if (!empty($error_details)) {
                        $message .= "<br><br>Error details:<br>" . implode("<br>", array_slice($error_details, 0, 10));
                        if (count($error_details) > 10) {
                            $message .= "<br>... and " . (count($error_details) - 10) . " more errors";
                        }
                    }
                }
                $message_type = $error_count > 0 ? 'warning' : 'success';
            }

        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Import Customer Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php';
        } ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Import Customer Data</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-8 offset-md-2">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Upload CSV File</h5>
                                                <p class="card-text">
                                                    Please upload a CSV file with the following columns:<br>
                                                    <strong>customer_name, customer_phone, delivery_address</strong>
                                                </p>
                                                
                                                <form method="POST" enctype="multipart/form-data">
                                                    <div class="mb-3">
                                                        <label for="csv_file" class="form-label">CSV File</label>
                                                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                                    </div>
                                                    
                                                    <div class="text-center">
                                                        <button type="submit" class="btn btn-primary">Import Data</button>
                                                        <a href="customer_data.php" class="btn btn-secondary">View Customer Data</a>
                                                        <button type="button" id="download-template" class="btn btn-info">Download Example CSV</button>
                                                    </div>
                                                </form>
                                            </div>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Simple file validation
        $(document).ready(function() {
            $('form').on('submit', function(e) {
                const fileInput = $('#csv_file')[0];
                if (fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please select a CSV file to upload.');
                    return false;
                }
                
                const file = fileInput.files[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                if (fileExt !== 'csv') {
                    e.preventDefault();
                    alert('Only CSV files are allowed.');
                    return false;
                }
            });
            
            // Download CSV template
            $('#download-template').on('click', function() {
                // Create CSV content
                const csvContent = "customer_name,customer_phone,delivery_address\n" +
                                   "John Doe,5551234567,123 Main St\n" +
                                   "Jane Smith,5559876543,456 Oak Ave\n" +
                                   "Robert Johnson,5554567890,789 Pine Rd";
                
                // Create a Blob object with the CSV content
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                
                // Create a download link
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                
                link.setAttribute('href', url);
                link.setAttribute('download', 'customer_data_template.csv');
                link.style.visibility = 'hidden';
                
                // Add to document, trigger click, then remove
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>
</html>