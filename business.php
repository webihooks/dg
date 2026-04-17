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
$success_message = '';
$error_message = '';
$is_edit_mode = false;
$business_data = null;

// Fetch user name and role
$sql = "SELECT name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_role);
$stmt->fetch();
$stmt->close();

// Check if we're editing an existing business
if (isset($_GET['edit'])) {
    $business_id = (int)$_GET['edit'];
    
    // Verify the business belongs to the current user
    $sql = "SELECT * FROM business_info WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $business_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $business_data = $result->fetch_assoc();
        $is_edit_mode = true;
    } else {
        $error_message = "Business not found or you don't have permission to edit it.";
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim all inputs
    $business_name = trim(htmlspecialchars($_POST['business_name'] ?? ''));
    $business_description = trim(htmlspecialchars($_POST['business_description'] ?? ''));
    $business_address = trim(htmlspecialchars($_POST['business_address'] ?? ''));
    $building = trim(htmlspecialchars($_POST['building'] ?? ''));
    $floor = trim(htmlspecialchars($_POST['floor'] ?? ''));
    $flat_unit = trim(htmlspecialchars($_POST['flat_unit'] ?? ''));
    $google_direction = trim(htmlspecialchars($_POST['google_direction'] ?? ''));
    $designation = trim(htmlspecialchars($_POST['designation'] ?? ''));
    $website = trim(htmlspecialchars($_POST['website'] ?? ''));
    $business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : null;

    // Validate inputs
    $errors = [];

    // Business name validation
    if (empty($business_name)) {
        $errors[] = "Business Name is required.";
    } elseif (strlen($business_name) < 3) {
        $errors[] = "Business Name must be at least 3 characters.";
    }

    // Business address validation (no line breaks)
    if (empty($business_address)) {
        $errors[] = "Business Address is required.";
    } elseif (preg_match('/[\r\n]/', $business_address)) {
        $errors[] = "Line breaks are not allowed in the address.";
    } elseif (strlen($business_address) < 5) {
        $errors[] = "Address must be at least 5 characters.";
    }

    // Google direction URL validation
    if (!empty($google_direction) && !filter_var($google_direction, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid Google Maps URL.";
    }

    // Website URL validation - allow URLs without http/https
    if (!empty($website)) {
        // Add http:// prefix if missing for validation
        $website_to_validate = $website;
        if (!preg_match("~^(?:f|ht)tps?://~i", $website_to_validate)) {
            $website_to_validate = "http://" . $website_to_validate;
        }
        
        if (!filter_var($website_to_validate, FILTER_VALIDATE_URL)) {
            $errors[] = "Please enter a valid website URL.";
        }
    }

    // If no errors, proceed with database operation
    if (empty($errors)) {
        // Remove any remaining line breaks from address
        $business_address = str_replace(["\r", "\n"], ' ', $business_address);
        
        if ($is_edit_mode && $business_id) {
            // Update existing business with new fields
            $sql = "UPDATE business_info SET 
                    business_name = ?, 
                    business_description = ?, 
                    business_address = ?,
                    building = ?,
                    floor = ?,
                    flat_unit = ?,
                    google_direction = ?,
                    designation = ?,
                    website = ?,
                    updated_at = NOW()
                    WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssii", 
                $business_name, 
                $business_description, 
                $business_address,
                $building,
                $floor,
                $flat_unit,
                $google_direction, 
                $designation, 
                $website, 
                $business_id, 
                $user_id
            );
            
            if ($stmt->execute()) {
                $success_message = "Business information updated successfully!";
                // Refresh business data
                $business_data = [
                    'business_name' => $business_name,
                    'business_description' => $business_description,
                    'business_address' => $business_address,
                    'building' => $building,
                    'floor' => $floor,
                    'flat_unit' => $flat_unit,
                    'google_direction' => $google_direction,
                    'designation' => $designation,
                    'website' => $website,
                    'id' => $business_id
                ];
            } else {
                $error_message = "Error updating business information.";
                error_log("Business update error: " . $stmt->error);
            }
        } else {
            // Insert new business with new fields
            $sql = "INSERT INTO business_info (user_id, business_name, business_description, business_address, building, floor, flat_unit, google_direction, designation, website) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssss", 
                $user_id, 
                $business_name, 
                $business_description, 
                $business_address,
                $building,
                $floor,
                $flat_unit,
                $google_direction, 
                $designation, 
                $website
            );
            
            if ($stmt->execute()) {
                $success_message = "Business information added successfully!";
                // Clear form fields
                $_POST = array();
                $business_data = null;
                $is_edit_mode = false;
            } else {
                $error_message = "Error adding business information.";
                error_log("Business insert error: " . $stmt->error);
            }
        }
        
        $stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch all businesses for the current user (including new fields)
$businesses = [];
$sql = "SELECT id, business_name, business_address, building, floor, flat_unit, designation, website FROM business_info WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Business</title>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <style>
        .business-form .form-control:focus{border-color:#007bff;box-shadow:0 0 0 0.2rem rgba(0,123,255,.25)}
        .business-table tr:hover{background-color:#f8f9fa}
        .action-btn{margin:2px}
        .table-responsive{overflow-x:auto}
        @media (max-width:768px){.action-btn{display:block;width:100%;margin-bottom:5px}}
        /* New address components row styling */
        .address-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .address-row .form-group {
            flex: 1;
            min-width: 150px;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php 
        // Updated menu inclusion to support vegetable seller role
        if ($user_role === 'room') {
            include 'room_management_menu.php';
        } elseif ($user_role === 'vegetable_seller') {
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
                                <h4 class="card-title">Business</h4>
                            </div>
                            <div class="card-body business-form">
                                <h4 class="header-title mb-3"><?php echo $is_edit_mode ? 'Edit' : 'Add'; ?> Business Information</h4>
                                
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="business.php<?php echo $is_edit_mode ? '?edit=' . $business_data['id'] : ''; ?>">
                                    <?php if ($is_edit_mode): ?>
                                        <input type="hidden" name="business_id" value="<?php echo $business_data['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="business_name" class="form-label">Business Name *</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name" 
                                               value="<?php echo htmlspecialchars($business_data['business_name'] ?? $_POST['business_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="designation" class="form-label">Designation</label>
                                        <input type="text" class="form-control" id="designation" name="designation" 
                                               value="<?php echo htmlspecialchars($business_data['designation'] ?? $_POST['designation'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="business_description" class="form-label">Business Description</label>
                                        <textarea class="form-control" id="business_description" name="business_description" 
onkeydown="if(event.keyCode === 13) { return false; }"
                                                  rows="3"><?php echo htmlspecialchars($business_data['business_description'] ?? $_POST['business_description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <!-- NEW ADDRESS COMPONENTS ROW -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Address Details</label>
                                        <div class="address-row">
                                            <div class="form-group">
                                                <label for="building" class="form-label">Building Name</label>
                                                <input type="text" class="form-control" id="building" name="building" 
                                                       value="<?php echo htmlspecialchars($business_data['building'] ?? $_POST['building'] ?? ''); ?>"
                                                       placeholder="e.g., Sunshine Tower">
                                            </div>
                                            <div class="form-group">
                                                <label for="floor" class="form-label">Floor</label>
                                                <input type="text" class="form-control" id="floor" name="floor" 
                                                       value="<?php echo htmlspecialchars($business_data['floor'] ?? $_POST['floor'] ?? ''); ?>"
                                                       placeholder="e.g., 3rd Floor">
                                            </div>
                                            <div class="form-group">
                                                <label for="flat_unit" class="form-label">Flat / Unit No.</label>
                                                <input type="text" class="form-control" id="flat_unit" name="flat_unit" 
                                                       value="<?php echo htmlspecialchars($business_data['flat_unit'] ?? $_POST['flat_unit'] ?? ''); ?>"
                                                       placeholder="e.g., 304, Shop No.5">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="business_address" class="form-label">Street / Area / Locality *</label>
                                        <textarea class="form-control" id="business_address" name="business_address" 
onkeydown="if(event.keyCode === 13) { return false; }"
                                                  rows="3" required><?php echo htmlspecialchars($business_data['business_address'] ?? $_POST['business_address'] ?? ''); ?></textarea>
                                        <small class="text-muted">Enter the street name, area, colony, or landmark (without building/floor/unit).</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="text" class="form-control" id="website" name="website" 
                                               value="<?php echo htmlspecialchars($business_data['website'] ?? $_POST['website'] ?? ''); ?>">
                                        <small class="text-muted">Example: www.example.com or https://www.example.com</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="google_direction" class="form-label">Google Maps Direction Link</label>
                                        <input type="text" class="form-control" id="google_direction" name="google_direction" 
                                               value="<?php echo htmlspecialchars($business_data['google_direction'] ?? $_POST['google_direction'] ?? ''); ?>">
                                        <small class="text-muted">Example: https://goo.gl/maps/...</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary"><?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Business Information</button>
                                    
                                    <?php if ($is_edit_mode): ?>
                                        <a href="business.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <?php if (!empty($businesses)): ?>
                        <div class="card mt-4">
                            <div class="card-body">
                                <h4 class="header-title mb-3">Your Businesses</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover business-table">
                                        <thead>
                                            <tr>
                                                <th>Business Name</th>
                                                <th>Designation</th>
                                                <th>Address</th>
                                                <th>Website</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($businesses as $business): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($business['business_name']); ?></td>
                                                <td><?php echo htmlspecialchars($business['designation']); ?></td>
                                                <td>
                                                    <?php 
                                                    // Build full address for display
                                                    $full_addr = [];
                                                    if (!empty($business['building'])) $full_addr[] = $business['building'];
                                                    if (!empty($business['floor'])) $full_addr[] = $business['floor'];
                                                    if (!empty($business['flat_unit'])) $full_addr[] = $business['flat_unit'];
                                                    if (!empty($business['business_address'])) $full_addr[] = $business['business_address'];
                                                    echo htmlspecialchars(implode(', ', $full_addr));
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($business['website'])): ?>
                                                        <?php
                                                        $website_url = $business['website'];
                                                        if (!preg_match("~^(?:f|ht)tps?://~i", $website_url)) {
                                                            $website_url = "http://" . $website_url;
                                                        }
                                                        ?>
                                                        <a href="<?php echo htmlspecialchars($website_url); ?>" target="_blank">Visit Website</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="business.php?edit=<?php echo $business['id']; ?>" class="btn btn-sm btn-primary action-btn">Edit</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
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
                    business_name: {
                        required: true,
                        minlength: 3
                    },
                    business_address: {
                        required: true,
                        minlength: 5,
                        noLineBreaks: true
                    },
                    website: {
                        pattern: /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/
                    },
                    google_direction: {
                        url: true
                    }
                },
                messages: {
                    business_name: {
                        required: "Please enter your business name",
                        minlength: "Business name must be at least 3 characters"
                    },
                    business_address: {
                        required: "Please enter your business address",
                        minlength: "Address must be at least 5 characters",
                        noLineBreaks: "Line breaks are not allowed in the address"
                    },
                    website: {
                        pattern: "Please enter a valid website URL (with or without http://)"
                    },
                    google_direction: {
                        url: "Please enter a valid URL"
                    }
                },
                errorElement: "div",
                errorPlacement: function(error, element) {
                    error.addClass("invalid-feedback");
                    error.insertAfter(element);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                }
            });

            $.validator.addMethod("noLineBreaks", function(value, element) {
                return !/[\r\n]/.test(value);
            });

            // Prevent Enter key in address textarea
            $('#business_address').on('keydown', function(e) {
                if (e.keyCode === 13) {
                    return false;
                }
            });
        });
    </script>

</body>
</html>