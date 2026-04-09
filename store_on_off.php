<?php
session_start();
require 'db_connection.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Fetch user details including role
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch user's current subscription package
$package_id = 0;
$package_type = '';
$sql_package = "SELECT p.id, p.name FROM subscriptions s 
               JOIN packages p ON s.package_id = p.id 
               WHERE s.user_id = ? AND s.status = 'active'";
$stmt_package = $conn->prepare($sql_package);
$stmt_package->bind_param("i", $user_id);
$stmt_package->execute();
$stmt_package->bind_result($package_id, $package_type);
$stmt_package->fetch();
$stmt_package->close();

// Initialize variables
$table_count = 0;
$services_active = 0;
$dining_active = 0;
$delivery_active = 0;
$record_exists = false; // Initialize this variable

// Get table count if not Delivery Package (package_id 1)
if ($package_id != 1) { // This means package is 2 (Dining) or 3 (Premium) or 5 (Vegetable Seller)
    $stmt = $conn->prepare("SELECT id, table_count FROM dining_tables WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($record_id, $table_count);
    $record_exists = $stmt->fetch();
    $stmt->close();
}

// Get current service status
$stmt = $conn->prepare("SELECT dining_active, delivery_active FROM dining_and_delivery WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($dining_active, $delivery_active);
    $stmt->fetch();
    $stmt->close();
    $services_active = ($dining_active || $delivery_active) ? 1 : 0;
} else {
    $dining_active = 0;
    $delivery_active = 0;
    $services_active = 0;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Process table count if not Delivery Package (package_id 1)
    if ($package_id != 1) {
        $new_table_count = filter_var($_POST['table_count'], FILTER_SANITIZE_NUMBER_INT);
        if ($new_table_count < 0 || $new_table_count > 50) {
            $message = "Table count must be between 0 and 50.";
            $message_type = 'danger';
        }
    } else {
        $new_table_count = 0;
    }
    
    // Get service status (ON/OFF for both)
    $services_active = isset($_POST['services_active']) ? 1 : 0;
    
    // Reset both services first
    $dining_active = 0;
    $delivery_active = 0;
    
    // Only activate services if switch is ON
    if ($services_active) {
        // Activate services according to package type
        switch ($package_id) {
            case 1: // Delivery Package
                $dining_active = 1;
                $delivery_active = 1;
                break;
            case 2: // Dining Package
                $dining_active = 1;
                $delivery_active = 1;
                break;
            case 3: // Premium Package
                $dining_active = 1;
                $delivery_active = 1;
                break;
            default:
                $dining_active = 0;
                $delivery_active = 0;
        }
    }

    if (empty($message)) {
        $conn->begin_transaction();
        
        try {
            // Handle dining tables (if not Delivery Package - package_id 1)
            if ($package_id != 1) {
                if ($record_exists) {
                    $update = $conn->prepare("UPDATE dining_tables SET table_count = ?, updated_at = NOW() WHERE user_id = ?");
                    $update->bind_param("ii", $new_table_count, $user_id);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $conn->prepare("INSERT INTO dining_tables (user_id, table_count, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                    $insert->bind_param("ii", $user_id, $new_table_count);
                    $insert->execute();
                    $insert->close();
                }
                $table_count = $new_table_count;
            }
            
            // Handle dining and delivery settings
            $check = $conn->prepare("SELECT id FROM dining_and_delivery WHERE user_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();
            $record_exists_dd = $check->fetch();
            $check->close();
            
            if ($record_exists_dd) {
                $update = $conn->prepare("UPDATE dining_and_delivery SET dining_active = ?, delivery_active = ?, updated_at = NOW() WHERE user_id = ?");
                $update->bind_param("iii", $dining_active, $delivery_active, $user_id);
                $update->execute();
                $update->close();
            } else {
                $insert = $conn->prepare("INSERT INTO dining_and_delivery (user_id, dining_active, delivery_active, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $insert->bind_param("iii", $user_id, $dining_active, $delivery_active);
                $insert->execute();
                $insert->close();
            }
            
            $conn->commit();
            $message = "Settings saved successfully.";
            $message_type = 'success';
            $services_active = ($dining_active || $delivery_active) ? 1 : 0;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error saving settings: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Store ON/OFF</title>
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
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        // Include appropriate menu based on user role
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'vegetable_seller') {
            include 'vegetable_seller_menu.php';
        } else {
            include 'menu.php';
        }
        ?>
        
        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Store ON/OFF</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" id="serviceSettingsForm">
                                    <?php if ($package_id != 1): // Show for Dining (2), Premium (3), and Vegetable Seller (5) packages ?>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="table_count" class="form-label">Number of Tables</label>
                                            <input type="number" name="table_count" id="table_count" class="form-control"
                                                value="<?php echo isset($table_count) ? htmlspecialchars($table_count) : ''; ?>" 
                                                min="0" max="50" required />
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="services_active" id="services_active" 
                                                    <?php echo $services_active ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="services_active">
                                                    Store ON/OFF <span style="color:red; font-weight: bold;">(Emergency Store Open/Close)</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="submit" class="btn btn-primary">Save Settings</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $("#serviceSettingsForm").validate({
                rules: {
                    <?php if ($package_id != 1): ?>
                    table_count: {
                        required: true,
                        number: true,
                        min: 0,
                        max: 50
                    }
                    <?php endif; ?>
                },
                messages: {
                    <?php if ($package_id != 1): ?>
                    table_count: {
                        required: "Please enter number of tables.",
                        number: "Please enter a valid number.",
                        min: "Minimum 0 tables required.",
                        max: "Maximum 50 tables allowed."
                    }
                    <?php endif; ?>
                }
            });
        });
    </script>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>