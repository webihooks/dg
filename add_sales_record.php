<?php
// Start the session to access user data
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user's name and role
$role = '';
$user_name = 'User';
$user_info_sql = "SELECT name, role FROM users WHERE id = ?";
$user_info_stmt = $conn->prepare($user_info_sql);

if ($user_info_stmt) {
    $user_info_stmt->bind_param("i", $user_id);
    $user_info_stmt->execute();
    $user_info_stmt->bind_result($user_name, $role);
    $user_info_stmt->fetch();
    $user_info_stmt->close();
}

// Handle form submission for new sales track
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sales_track'])) {
    $restaurant_name        = trim($_POST['restaurant_name']);
    $contacted_person      = trim($_POST['contacted_person']);
    $phone                  = trim($_POST['phone']);
    $decision_maker_name   = trim($_POST['decision_maker_name'] ?? '');
    $decision_maker_phone  = trim($_POST['decision_maker_phone'] ?? '');
    $location              = trim($_POST['location']);
    $street                 = trim($_POST['street'] ?? '');
    $city                   = trim($_POST['city'] ?? '');
    $state                  = trim($_POST['state'] ?? '');
    $postal_code            = trim($_POST['postal_code'] ?? '');
    $country                = trim($_POST['country'] ?? '');
    $follow_up_date        = !empty($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : null;
    
    // Package price validation with limits
    $package_price = isset($_POST['package_price']) && is_numeric($_POST['package_price']) 
                    ? floatval($_POST['package_price']) 
                    : 0.00;
    $max_package_price = 999999.99; // Matches DECIMAL(10,2) in database
    
    date_default_timezone_set('Asia/Kolkata');
    $raw_remark = trim($_POST['remark']);
    $timestamp = date('Y-m-d h:i A');
    $remark = "$timestamp - $user_name: $raw_remark";

    $owner_available        = isset($_POST['owner_available']) ? 1 : 0;
    $record_date            = date('Y-m-d');

    // Validate required fields
    if (empty($restaurant_name) || empty($contacted_person) || empty($phone) || empty($location) || empty($raw_remark)) {
        $error_message = "Restaurant Name, Contacted Person, Phone, Location, and Remark are required fields.";
    } 
    // Validate package price range
    elseif ($package_price > $max_package_price) {
        $error_message = "Package price cannot exceed " . number_format($max_package_price, 2);
    }
    elseif ($package_price < 0) {
        $error_message = "Package price cannot be negative";
    }
    else {
        // In your form handling section:

$insert_sql = "INSERT INTO sales_track (
    user_id, user_name, record_date,
    restaurant_name, contacted_person, phone,
    decision_maker_name, decision_maker_phone,
    location, street, city, state,
    postal_code, country, follow_up_date,
    package_price, remark, owner_available
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($insert_sql);

if ($stmt) {
    $params = [
        $user_id,             // i (integer)
        $user_name,           // s (string)
        $record_date,         // s (string)
        $restaurant_name,     // s (string)
        $contacted_person,    // s (string)
        $phone,               // s (string)
        $decision_maker_name, // s (string)
        $decision_maker_phone,// s (string)
        $location,            // s (string)
        $street,              // s (string)
        $city,                // s (string)
        $state,               // s (string)
        $postal_code,         // s (string)
        $country,             // s (string)
        $follow_up_date,      // s (string)
        $package_price,       // d (double/float)
        $remark,              // s (string)
        $owner_available      // i (integer)
    ];

    // Corrected type definition string (18 characters)
    $types = 'issssssssssssssdsi';
    /* Breakdown:
        i - user_id (integer)
        s - user_name (string)
        s - record_date (string)
        s - restaurant_name (string)
        s - contacted_person (string)
        s - phone (string)
        s - decision_maker_name (string)
        s - decision_maker_phone (string)
        s - location (string)
        s - street (string)
        s - city (string)
        s - state (string)
        s - postal_code (string)
        s - country (string)
        s - follow_up_date (string)
        d - package_price (double)
        s - remark (string)
        i - owner_available (integer)
    */

    // Debug output to verify counts
    echo "<script>console.log('Type length: " . strlen($types) . "')</script>";
    echo "<script>console.log('Params count: " . count($params) . "')</script>";

    if (strlen($types) !== count($params)) {
        die("Mismatch detected: Types length (" . strlen($types) . ") 
            doesn't match params count (" . count($params) . ")");
    }

    $bound = $stmt->bind_param($types, ...$params);

    if (!$bound) {
        $error_message = "Parameter binding failed: " . $stmt->error;
    } elseif ($stmt->execute()) {
        $success_message = "Record added successfully!";
        $_POST = array(); // Clear form
    } else {
        $error_message = "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    $error_message = "Prepare failed: " . $conn->error;
}
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Add Sales Record</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/app.min.css" rel="stylesheet">
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>    
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'sales_person') {
            include 'sales_menu.php';
        } else {
            include 'menu.php';
        }
        ?>
        
        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Add New Sales Record</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-12">
                                        <form id="salesTrackForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                            <div class="row" style="display:none;">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">User ID</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_id) ?>" readonly>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Sales Person</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_name) ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Restaurant Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="restaurant_name" value="<?= htmlspecialchars($_POST['restaurant_name'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Package Price<span class="text-danger">*</span></label>
                                                    <input type="number" step="0.01" class="form-control" name="package_price" 
                                                            value="<?= htmlspecialchars($_POST['package_price'] ?? '') ?>" 
                                                            min="0" max="999999" required>
                                                    <small class="text-muted">Maximum value: 999,999.99</small>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="contacted_person" value="<?= htmlspecialchars($_POST['contacted_person'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Decision Maker's Name</label>
                                                    <input type="text" class="form-control" name="decision_maker_name" value="<?= htmlspecialchars($_POST['decision_maker_name'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Decision Maker's Phone</label>
                                                    <input type="text" class="form-control" name="decision_maker_phone" value="<?= htmlspecialchars($_POST['decision_maker_phone'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Location <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="location" id="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required readonly>
                                                        <button type="button" class="btn btn-secondary" id="detectLocation">
                                                            <i class="fas fa-location-arrow"></i> Detect
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Click "Detect" to auto-fill your current location</small>
                                                    <div id="locationStatus" class="mt-2"></div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-md-6 mb-2">
                                                            <label class="form-label">Street</label>
                                                            <input type="text" class="form-control" name="street" id="street" value="<?= htmlspecialchars($_POST['street'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-2" style="display:none;">
                                                            <label class="form-label">City</label>
                                                            <input type="text" class="form-control" name="city" id="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-2" style="display:none;">
                                                            <label class="form-label">State/Region</label>
                                                            <input type="text" class="form-control" name="state" id="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-2" style="display:none;">
                                                            <label class="form-label">Postal Code</label>
                                                            <input type="text" class="form-control" name="postal_code" id="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-2" style="display:none;">
                                                            <label class="form-label">Country</label>
                                                            <input type="text" class="form-control" name="country" id="country" value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Follow Up Date</label>
                                                            <input type="date" class="form-control" name="follow_up_date" value="<?= htmlspecialchars($_POST['follow_up_date'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Remark <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="remark" id="remark" rows="3" required minlength="5"><?= htmlspecialchars($_POST['remark'] ?? '') ?></textarea>
                                            </div>

                                            <div class="mb-3 form-check">
                                                <label class="form-check-label">
                                                    <input type="checkbox" class="form-check-input" name="owner_available" value="1" <?= isset($_POST['owner_available']) ? 'checked' : '' ?>>
                                                    Decision Maker Available
                                                </label>
                                            </div>

                                            <button type="submit" name="add_sales_track" class="btn btn-primary">Add Record</button>
                                        </form>
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

    <script>
        $(document).ready(function() {
            // Location detection
            $('#detectLocation').click(function() {
                $('#locationStatus').html('<div class="alert alert-info">Detecting your location...</div>');
                
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const latitude = position.coords.latitude;
                            const longitude = position.coords.longitude;
                            
                            $.get(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`, 
                                function(data) {
                                    const address = data.address || {};
                                    let locationText = '';
                                    if (address.road) locationText += address.road;
                                    if (address.road && address.city) locationText += ', ';
                                    if (address.city) locationText += address.city;
                                    
                                    $('#location').val(locationText || 'Current Location');
                                    $('#street').val(address.road || '');
                                    $('#city').val(address.city || address.town || address.village || '');
                                    $('#state').val(address.state || '');
                                    $('#postal_code').val(address.postcode || '');
                                    $('#country').val(address.country || '');
                                    
                                    $('#locationStatus').html('<div class="alert alert-success">Location detected successfully!</div>');
                                }
                            ).fail(function() {
                                $('#locationStatus').html('<div class="alert alert-warning">Location detected but address details could not be retrieved.</div>');
                                $('#location').val('Current Location (' + latitude + ', ' + longitude + ')');
                            });
                        },
                        function(error) {
                            let errorMessage = 'Error detecting location: ';
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage += "User denied the request for Geolocation.";
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage += "Location information is unavailable.";
                                    break;
                                case error.TIMEOUT:
                                    errorMessage += "The request to get user location timed out.";
                                    break;
                                case error.UNKNOWN_ERROR:
                                    errorMessage += "An unknown error occurred.";
                                    break;
                            }
                            $('#locationStatus').html('<div class="alert alert-danger">' + errorMessage + '</div>');
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                } else {
                    $('#locationStatus').html('<div class="alert alert-danger">Geolocation is not supported by this browser.</div>');
                }
            });

            // Form validation
            $("#salesTrackForm").validate({
                rules: {
                    restaurant_name: {
                        required: true,
                        minlength: 2
                    },
                    contacted_person: {
                        required: true,
                        minlength: 2
                    },
                    phone: {
                        required: true,
                        minlength: 6
                    },
                    location: {
                        required: true,
                        minlength: 3
                    },
                    remark: {
                        required: true,
                        minlength: 5
                    },
                    package_price: {
                        required: true,
                        number: true,
                        min: 0,
                        max: 999999
                    }
                },
                messages: {
                    restaurant_name: {
                        required: "Please enter restaurant name",
                        minlength: "Restaurant name should be at least 2 characters long"
                    },
                    contacted_person: {
                        required: "Please enter contact person name",
                        minlength: "Name should be at least 2 characters long"
                    },
                    phone: {
                        required: "Please enter phone number",
                        minlength: "Phone number should be at least 6 characters long"
                    },
                    location: {
                        required: "Please enter location",
                        minlength: "Location should be at least 3 characters long"
                    },
                    remark: {
                        required: "Please enter a remark",
                        minlength: "Remark should be at least 5 characters long"
                    },
                    package_price: {
                        required: "Please enter package price",
                        number: "Please enter a valid number",
                        min: "Price cannot be negative",
                        max: "Price cannot exceed 999,999"
                    }
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-group').append(error);
                },
                highlight: function(element) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element) {
                    $(element).removeClass('is-invalid');
                }
            });
        });
    </script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>    
</body>
</html>