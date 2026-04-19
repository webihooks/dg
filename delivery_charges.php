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

// Fetch user details including country and role
$sql = "SELECT name, email, phone, address, role, country FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role, $user_country);
$stmt->fetch();
$stmt->close();

// Function to get currency symbol based on country
function getCurrencySymbol($country) {
    $currencySymbols = [
        'India' => '₹',
        'UAE' => 'AED',
        'UK' => '£',
        'USA' => '$'
    ];
    return isset($currencySymbols[$country]) ? $currencySymbols[$country] : '₹';
}

$currencySymbol = getCurrencySymbol($user_country);

// Fetch existing delivery settings
$current_charge = 0;
$current_free_delivery_min = 0;
$current_min_order = 0;

$sql = "SELECT delivery_charge, free_delivery_minimum, minimum_order_amount FROM delivery_charges WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($current_charge, $current_free_delivery_min, $current_min_order);
$stmt->fetch();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_delivery_charge'])) {
    $delivery_charge = (float)$_POST['delivery_charge'];
    $free_delivery_min = (float)$_POST['free_delivery_min'];
    $min_order_amount = (float)$_POST['min_order_amount'];
    
    // Validate inputs
    if ($delivery_charge < 0) {
        $message = "Delivery charge cannot be negative.";
        $message_type = "danger";
    } elseif ($free_delivery_min < 0) {
        $message = "Free delivery minimum cannot be negative.";
        $message_type = "danger";
    } elseif ($min_order_amount < 0) {
        $message = "Minimum order amount cannot be negative.";
        $message_type = "danger";
    } else {
        // Check if record exists
        $record_exists = false;
        $sql_check = "SELECT id FROM delivery_charges WHERE user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        $record_exists = ($stmt_check->num_rows > 0);
        $stmt_check->close();
        
        if ($record_exists) {
            // Update existing record
            $sql = "UPDATE delivery_charges 
                    SET delivery_charge = ?, 
                        free_delivery_minimum = ?, 
                        minimum_order_amount = ?,
                        updated_at = NOW() 
                    WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dddi", $delivery_charge, $free_delivery_min, $min_order_amount, $user_id);
        } else {
            // Insert new record
            $sql = "INSERT INTO delivery_charges (user_id, delivery_charge, free_delivery_minimum, minimum_order_amount, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iddd", $user_id, $delivery_charge, $free_delivery_min, $min_order_amount);
        }
        
        if ($stmt->execute()) {
            $message = "Delivery settings saved successfully!";
            $current_charge = $delivery_charge;
            $current_free_delivery_min = $free_delivery_min;
            $current_min_order = $min_order_amount;
        } else {
            $message = "Error saving delivery settings: " . $conn->error;
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
    <title>Delivery Charges</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
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
                                <h4 class="card-title">Delivery & Order Settings</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="delivery_charges.php">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="delivery_charge" class="form-label">
                                                Delivery Charge (<?php echo $currencySymbol; ?>)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo $currencySymbol; ?></span>
                                                <input type="number" class="form-control" id="delivery_charge" 
                                                       name="delivery_charge" step="0.01" min="0" 
                                                       value="<?php echo htmlspecialchars($current_charge); ?>" required>
                                            </div>
                                            <div class="form-text">Standard delivery charge amount.</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="free_delivery_min" class="form-label">
                                                Free Delivery Minimum (<?php echo $currencySymbol; ?>)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo $currencySymbol; ?></span>
                                                <input type="number" class="form-control" id="free_delivery_min" 
                                                       name="free_delivery_min" step="0.01" min="0" 
                                                       value="<?php echo htmlspecialchars($current_free_delivery_min); ?>" required>
                                            </div>
                                            <div class="form-text">Order amount needed for free delivery.</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="min_order_amount" class="form-label">
                                                Minimum Order Amount (<?php echo $currencySymbol; ?>)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo $currencySymbol; ?></span>
                                                <input type="number" class="form-control" id="min_order_amount" 
                                                       name="min_order_amount" step="0.01" min="0" 
                                                       value="<?php echo htmlspecialchars($current_min_order); ?>" required>
                                            </div>
                                            <div class="form-text">
                                                Customers cannot place an order below this amount. 
                                                Set 0 to disable minimum order restriction.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="save_delivery_charge" class="btn btn-primary">
                                        Save Settings
                                    </button>
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
            const currencySymbol = '<?php echo $currencySymbol; ?>';
            
            $('form').validate({
                rules: {
                    delivery_charge: { required: true, min: 0, number: true },
                    free_delivery_min: { required: true, min: 0, number: true },
                    min_order_amount: { required: true, min: 0, number: true }
                },
                messages: {
                    delivery_charge: {
                        required: "Please enter a delivery charge",
                        min: "Delivery charge cannot be negative",
                        number: "Please enter a valid number"
                    },
                    free_delivery_min: {
                        required: "Please enter free delivery minimum",
                        min: "Free delivery minimum cannot be negative",
                        number: "Please enter a valid number"
                    },
                    min_order_amount: {
                        required: "Please enter minimum order amount",
                        min: "Minimum order amount cannot be negative",
                        number: "Please enter a valid number"
                    }
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.mb-3').append(error);
                },
                highlight: function(element) {
                    $(element).addClass('is-invalid').removeClass('is-valid');
                },
                unhighlight: function(element) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                }
            });
        });
    </script>
</body>
</html>