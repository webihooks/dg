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
$message_type = 'success';

// Fetch user details including country
$sql = "SELECT name, email, phone, address, role, country FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role, $country);
$stmt->fetch();
$stmt->close();

// Determine tax label based on country
$tax_label = ($country == 'UAE') ? 'VAT' : 'GST';
$tax_label_lower = strtolower($tax_label);

// Fetch existing tax percentage if it exists
$current_tax = 0;
$sql = "SELECT gst_percent FROM gst_charge WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($current_tax);
$stmt->fetch();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tax_charge'])) {
    $tax_percent = (float)$_POST['tax_percent'];
    
    // Validate input
    if ($tax_percent < 0 || $tax_percent > 100) {
        $message = "$tax_label percentage must be between 0 and 100.";
        $message_type = "danger";
    } else {
        // First check if record exists for this user
        $record_exists = false;
        $sql_check = "SELECT id FROM gst_charge WHERE user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        $record_exists = ($stmt_check->num_rows > 0);
        $stmt_check->close();
        
        if ($record_exists) {
            // Update existing record
            $sql = "UPDATE gst_charge SET gst_percent = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $tax_percent, $user_id);
        } else {
            // Insert new record
            $sql = "INSERT INTO gst_charge (user_id, gst_percent, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("id", $user_id, $tax_percent);
        }
        
        if ($stmt->execute()) {
            $message = "$tax_label percentage saved successfully!";
            $current_tax = $tax_percent;
        } else {
            $message = "Error saving $tax_label percentage: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title><?php echo $tax_label; ?> Management</title>
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
    <script src="assets/js/jquery.validate.min.js"></script>
</head>


<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php'; // default menu for other roles
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo $tax_label; ?> Management</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="gst_charge.php">
                                    <div class="col-md-6 mb-3">
                                        <label for="tax_percent" class="form-label"><?php echo $tax_label; ?> Percentage (%)</label>
                                        <input type="number" class="form-control" id="tax_percent" 
                                               name="tax_percent" step="0.01" min="0" max="100"
                                               value="<?php echo htmlspecialchars($current_tax); ?>" required>
                                        <div class="form-text">Enter the <?php echo $tax_label_lower; ?> percentage (0-100).</div>
                                    </div>
                                    <button type="submit" name="save_tax_charge" class="btn btn-primary">Save <?php echo $tax_label; ?> Percentage</button>
                                </form>
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
            $('form').validate({
                rules: {
                    tax_percent: {
                        required: true,
                        min: 0,
                        max: 100
                    }
                },
                messages: {
                    tax_percent: {
                        required: "Please enter a <?php echo $tax_label; ?> percentage",
                        min: "<?php echo $tax_label; ?> percentage cannot be negative",
                        max: "<?php echo $tax_label; ?> percentage cannot exceed 100%"
                    }
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.mb-3').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid').removeClass('is-valid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                }
            });
        });
    </script>
</body>
</html>