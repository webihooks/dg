<?php
// Start the session to access user data
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require 'db_connection.php';

// Date parsing helper function
function parseCustomDate($dateString) {
    if (empty($dateString)) return null;
    
    // Take only the date part if time is present
    $dateString = trim(preg_split('/\s+/', $dateString)[0]);
    
    $formats = [
        'd-M-Y' => '/^(\d{1,2})-([a-zA-Z]{3})-(\d{4})$/i',
        'm/d/Y' => '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        'd.m.Y' => '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/',
        'Y-m-d' => '/^(\d{4})-(\d{1,2})-(\d{1,2})$/',
    ];
    
    foreach ($formats as $format => $pattern) {
        if (preg_match($pattern, $dateString)) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format('Y-m-d') !== '1970-01-01') {
                return $date->format('Y-m-d');
            }
        }
    }
    throw new Exception("Invalid date format: '".htmlspecialchars($dateString ?? '')."'");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$role = '';
$user_name = 'User'; // Default user name
$import_errors = [];

// Fetch user info for the logged-in user (needed for menu and possibly for default values if CSV is missing them)
$user_info_sql = "SELECT name, role FROM users WHERE id = ?";
$user_info_stmt = $conn->prepare($user_info_sql);
if ($user_info_stmt) {
    $user_info_stmt->bind_param("i", $user_id);
    $user_info_stmt->execute();
    $user_info_stmt->bind_result($user_name, $role);
    $user_info_stmt->fetch();
    $user_info_stmt->close();
}

// Handle CSV download
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_sales_track_import.csv"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'user_id', 'current_date', 'time_stamp', 'restaurant_name', 
        'contacted_person', 'phone', 'owner_available', 'decision_maker_name', 
        'decision_maker_phone', 'location', 'street', 'city', 'state', 
        'postal_code', 'country', 'follow_up_date', 'package_price', 'remark'
    ]);
    
    // Sample data rows
    // For sample, we can use the logged-in user's ID as a placeholder
    fputcsv($output, [
        $user_id, date('Y-m-d'), date('Y-m-d H:i:s'), 'Burger King', 
        'John Smith', '555-123-4567', '1', 'Sarah Johnson', 
        '555-987-6543', 'Downtown', '123 Main St', 'New York', 'NY', 
        '10001', 'USA', date('Y-m-d', strtotime('+1 week')), '1999.99', 'Owner available M-F 9-5'
    ]);
    
    fputcsv($output, [
        $user_id + 1, date('Y-m-d', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('-1 day')), 'Pizza Hut', 
        'Mike Brown', '555-234-5678', 'Not on weekends', 'Emily Davis', 
        '555-876-5432', 'Uptown', '456 Oak Ave', 'Chicago', 'IL', 
        '60601', 'USA', date('Y-m-d', strtotime('+2 weeks')), '1499.99', 'Manager available after 7:30pm'
    ]);
    
    fclose($output);
    exit();
}

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    try {
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error code: " . $_FILES['csv_file']['error']);
        }
        
        if (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
            throw new Exception("Only CSV files are allowed.");
        }
        
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) throw new Exception("Could not open the uploaded file.");
        
        fgetcsv($handle); // Skip header
        
        $imported_rows = 0;
        $skipped_rows = 0;
        $current_date = date('Y-m-d');
        $current_timestamp = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO sales_track (
            user_id, user_name, `current_date`, `time_stamp`, restaurant_name, 
            contacted_person, phone, decision_maker_name, decision_maker_phone, 
            location, street, city, state, postal_code, country, 
            follow_up_date, package_price, remark, owner_available
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database error: " . $conn->error);
        
        $row_number = 1; // Start row number for error reporting (after header)
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            $row_errors = [];
            
            // Skip entirely empty rows
            if (empty(array_filter($data))) {
                $skipped_rows++;
                $import_errors[] = "Row $row_number: Empty row skipped";
                continue;
            }
            
            try {
                // *** MODIFIED LINE ***
                // Strictly use the user_id from the CSV. If it's empty or invalid, it will be 0 or cause an error.
                // We'll let the DB handle any foreign key constraints if user_id is crucial.
                $csv_user_id = !empty($data[0]) ? intval($data[0]) : null; // Set to null if empty, so DB can use default or throw error
                
                // Fetch user_name for the csv_user_id
                $csv_user_name = '';
                if ($csv_user_id !== null) {
                    $user_name_sql = "SELECT name FROM users WHERE id = ?";
                    $user_name_stmt = $conn->prepare($user_name_sql);
                    if ($user_name_stmt) {
                        $user_name_stmt->bind_param("i", $csv_user_id);
                        $user_name_stmt->execute();
                        $user_name_stmt->bind_result($fetched_user_name);
                        $user_name_stmt->fetch();
                        $user_name_stmt->close();
                        $csv_user_name = $fetched_user_name ?? 'Unknown User'; // Use fetched name or 'Unknown User'
                    } else {
                        $csv_user_name = 'Unknown User'; // Fallback if statement fails
                    }
                } else {
                    $csv_user_name = 'No User ID Provided';
                    $row_errors[] = "User ID in CSV is empty. Record will be associated with ID 0 or fail.";
                }

                $csv_current_date = $current_date; // Default to current date if CSV field is empty
                $csv_time_stamp = $current_timestamp; // Default to current timestamp if CSV field is empty
                try {
                    // Parse current_date from CSV or use default
                    $csv_current_date = !empty($data[1]) ? parseCustomDate($data[1]) : $current_date;
                    
                    // Parse time_stamp from CSV or use default
                    if (!empty($data[2])) {
                        $parsed_timestamp = strtotime($data[2]);
                        $csv_time_stamp = $parsed_timestamp ? date('Y-m-d H:i:s', $parsed_timestamp) : $current_timestamp;
                    }
                } catch (Exception $e) {
                    $row_errors[] = $e->getMessage();
                }

                $restaurant_name      = $data[3] ?? '';
                $contacted_person     = $data[4] ?? '';
                $phone                = $data[5] ?? '';
                $owner_available_input = $data[6] ?? ''; 
                $decision_maker_name  = $data[7] ?? '';
                $decision_maker_phone = $data[8] ?? '';
                $location             = $data[9] ?? '';
                $street               = $data[10] ?? '';
                $city                 = $data[11] ?? '';
                $state                = $data[12] ?? '';
                $postal_code          = $data[13] ?? '';
                $country              = $data[14] ?? '';
                
                $follow_up_date = null;
                if (!empty($data[15])) {
                    try {
                        $follow_up_date = parseCustomDate($data[15]);
                        if ($follow_up_date && $follow_up_date < date('Y-m-d')) {
                            $row_errors[] = "Follow-up date cannot be in the past";
                        }
                    } catch (Exception $e) {
                        $row_errors[] = "Invalid follow-up date: " . $e->getMessage();
                    }
                }
                
                $package_price = !empty($data[16]) ? floatval($data[16]) : 0;
                $remark = $data[17] ?? '';
                
                // Handle owner_available: '1' or '0' as int, or move to remark
                if (is_numeric($owner_available_input) && ($owner_available_input == '1' || $owner_available_input == '0')) {
                    $owner_available = (int)$owner_available_input;
                } else {
                    if (!empty($owner_available_input)) {
                        $remark .= (!empty($remark) ? ". " : "") . "Owner availability: " . $owner_available_input;
                    }
                    $owner_available = 0; // Default to 0 (no) if not a valid '1' or '0'
                }

                // Ensure owner_available is strictly 0 or 1 for database BOOLEAN/TINYINT(1)
                $owner_available = $owner_available ? 1 : 0; 
                
                if (!empty($row_errors)) {
                    throw new Exception(implode("; ", $row_errors));
                }
                
                // Bind parameters with the CSV's user_id and corresponding user_name
                $stmt->bind_param(
                    "isssssssssssssssdsi", 
                    $csv_user_id, $csv_user_name, $csv_current_date, $csv_time_stamp,
                    $restaurant_name, $contacted_person, $phone, 
                    $decision_maker_name, $decision_maker_phone,
                    $location, $street, $city, $state, $postal_code, $country,
                    $follow_up_date, $package_price, $remark, $owner_available
                );
                
                if ($stmt->execute()) {
                    $imported_rows++;
                } else {
                    throw new Exception("Database error: ".$stmt->error);
                }
                
            } catch (Exception $e) {
                $skipped_rows++;
                $import_errors[] = "Row $row_number: " . $e->getMessage();
                continue;
            }
        }
        
        fclose($handle);
        if (isset($stmt)) $stmt->close();
        
        $success_message = "Successfully imported $imported_rows records.";
        if ($skipped_rows > 0) {
            $success_message .= " $skipped_rows records were skipped due to errors.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bulk Import Sales Track</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/app.min.css" rel="stylesheet">
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .error-details { max-height: 300px; overflow-y: auto; }
        .error-item { padding: 5px; border-bottom: 1px solid #eee; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php 
        // Include navigation/toolbar based on roles and file existence
        if (file_exists('toolbar.php')) include 'toolbar.php'; 
        if ($role === 'admin' && file_exists('admin_menu.php')) include 'admin_menu.php';
        elseif ($role === 'sales_person' && file_exists('sales_menu.php')) include 'sales_menu.php';
        elseif (file_exists('menu.php')) include 'menu.php';
        ?>
        
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header"><h4 class="card-title">Bulk Import Sales Track</h4></div>
                            <div class="card-body">
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success">
                                        <?php echo htmlspecialchars($success_message ?? ''); ?>
                                        <?php if (!empty($import_errors)): ?>
                                            <button type="button" class="btn btn-sm btn-warning mt-2" data-toggle="collapse" data-target="#importErrors">
                                                Show Error Details (<?php echo count($import_errors); ?>)
                                            </button>
                                            <div id="importErrors" class="collapse mt-2">
                                                <div class="card card-body bg-light error-details">
                                                    <h6>First 50 Errors:</h6>
                                                    <div class="mb-0">
                                                        <?php foreach(array_slice($import_errors, 0, 50) as $error): ?>
                                                            <div class="error-item"><?php echo htmlspecialchars($error ?? ''); ?></div>
                                                        <?php endforeach; ?>
                                                        <?php if (count($import_errors) > 50): ?>
                                                            <div class="error-item">... and <?php echo count($import_errors) - 50; ?> more errors</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message ?? ''); ?></div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="form-group">
                                                <label for="csv_file">CSV File</label>
                                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                                <small class="form-text text-muted">
                                                    CSV columns order:<br>
                                                    user_id, current_date, time_stamp, restaurant_name, contacted_person, phone, <strong>owner_available</strong>, 
                                                    decision_maker_name, decision_maker_phone, location, street, city, state, 
                                                    postal_code, country, follow_up_date, package_price, remark<br><br>
                                                    <strong>Special Notes:</strong><br>
                                                    - <strong>user_id</strong> will be taken directly from the CSV. Ensure it exists in your 'users' table.<br>
                                                    - <strong>owner_available</strong> should be 1 (yes) or 0 (no)<br>
                                                    - Any text in <strong>owner_available</strong> will be moved to remarks with "Owner availability:" prefix<br>
                                                    - Supported Date formats: YYYY-MM-DD, DD-Mon-YYYY, MM/DD/YYYY, DD.MM.YYYY<br>
                                                    - Follow-up date is optional (leave empty if no follow-up needed)
                                                </small>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Import</button>
                                            <a href="?download_sample=1" class="btn btn-secondary ml-2">Download Sample CSV</a>
                                        </form>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h5 class="card-title">Instructions</h5>
                                                <ol>
                                                    <li>Download the sample CSV file.</li>
                                                    <li>Add your data, ensuring the columns are in the correct order.</li>
                                                    <li>For the <strong>owner_available</strong> column, use '1' for yes or '0' for no.</li>
                                                    <li>Any non-numeric text in that column will be added to the remarks.</li>
                                                    <li>Save your file as a CSV and upload it using the form.</li>
                                                </ol>
                                                <p class="text-danger">The first row (headers) in the CSV will be skipped during import.</p>
                                                <p class="text-info">The <strong>follow_up_date</strong> is optional and can be left blank.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (file_exists('footer.php')) include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            $('form').submit(function() {
                var file = $('#csv_file').val();
                if (!file) { alert('Please select a file.'); return false; }
                var ext = file.split('.').pop().toLowerCase();
                if (ext !== 'csv') { alert('Only CSV files are allowed.'); return false; }
                return true;
            });
            $('[data-toggle="collapse"]').click(function() {
                $(this).text(function(i, text) {
                    return text.includes("Show") ? text.replace("Show", "Hide") : text.replace("Hide", "Show");
                });
            });
        });
    </script>
</body>
</html>
<?php
// Close the database connection if it's open
if (isset($conn) && $conn) $conn->close();
?>