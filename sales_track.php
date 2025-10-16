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
    $restaurant_name       = trim($_POST['restaurant_name']);
    $contacted_person      = trim($_POST['contacted_person']);
    $phone                 = trim($_POST['phone']);
    $decision_maker_name   = trim($_POST['decision_maker_name'] ?? '');
    $decision_maker_phone  = trim($_POST['decision_maker_phone'] ?? '');
    $location              = trim($_POST['location']);
    $street                = trim($_POST['street'] ?? '');
    $city                  = trim($_POST['city'] ?? '');
    $state                 = trim($_POST['state'] ?? '');
    $postal_code           = trim($_POST['postal_code'] ?? '');
    $country               = trim($_POST['country'] ?? '');
    $follow_up_date        = !empty($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : null;
    $package_price         = isset($_POST['package_price']) && is_numeric($_POST['package_price']) 
                           ? floatval($_POST['package_price']) 
                           : 0.00;
    $remark                = trim($_POST['remark']);
    $owner_available       = isset($_POST['owner_available']) ? 1 : 0;
    $current_date          = date('Y-m-d');

    if (empty($restaurant_name) || empty($contacted_person) || empty($phone) || empty($location) || empty($remark)) {
        $error_message = "Restaurant Name, Contacted Person, Phone, Location, and Remark are required fields.";
    } else {
        $insert_sql = "INSERT INTO sales_track (
            user_id, user_name, `current_date`,
            restaurant_name, contacted_person, phone,
            decision_maker_name, decision_maker_phone,
            location, street, city, state,
            postal_code, country, follow_up_date,
            package_price, remark, owner_available
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($insert_sql);

        if ($stmt) {
            $params = [
                $user_id,
                $user_name,
                $current_date,
                $restaurant_name,
                $contacted_person,
                $phone,
                $decision_maker_name,
                $decision_maker_phone,
                $location,
                $street,
                $city,
                $state,
                $postal_code,
                $country,
                $follow_up_date,
                $package_price,
                $remark,
                $owner_available
            ];

            $types = 'issssssssssssssdsi';

            $bound = $stmt->bind_param($types, ...$params);

            if (!$bound) {
                $error_message = "Parameter binding failed: " . $stmt->error;
            } elseif ($stmt->execute()) {
                $success_message = "Sales track entry for '{$restaurant_name}' added successfully!";
                $_POST = array();
            } else {
                $error_message = "Error adding sales track entry: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $error_message = "Error preparing insert query: " . $conn->error;
        }
    }
}

// Handle adding new remark to existing record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_remark'])) {
    $record_id = (int)$_POST['record_id'];
    $new_remark = trim($_POST['new_remark']);
    
    if ($record_id > 0 && !empty($new_remark)) {
        // First get the existing remark
        $get_remark_sql = "SELECT remark FROM sales_track WHERE id = ?";
        $get_remark_stmt = $conn->prepare($get_remark_sql);
        $get_remark_stmt->bind_param("i", $record_id);
        $get_remark_stmt->execute();
        $get_remark_stmt->bind_result($existing_remark);
        $get_remark_stmt->fetch();
        $get_remark_stmt->close();
        
        // Combine with new remark (with proper line breaks)
        $updated_remark = $existing_remark;
        if (!empty($existing_remark)) {
            $updated_remark .= "\n\n"; // Add separation between remarks
        }
        $updated_remark .= date('Y-m-d') . " - " . $user_name . ": " . $new_remark;
        
        // Update the record
        $update_sql = "UPDATE sales_track SET remark = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if ($update_stmt) {
            $update_stmt->bind_param("si", $updated_remark, $record_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Remark added successfully!";
                // Refresh the page to show the updated remark
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error adding remark: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $error_message = "Error preparing update statement: " . $conn->error;
        }
    } else {
        $error_message = "Record ID and remark text are required.";
    }
}

// Fetch existing entries based on user role
$sales_track_list = [];
if ($role === 'admin') {
    // Admin can see all records
    $fetch_sales_sql = "SELECT 
        id, user_id, user_name, current_date, time_stamp, 
        restaurant_name, contacted_person, phone, 
        decision_maker_name, decision_maker_phone, 
        location, street, city, state, 
        postal_code, country, follow_up_date, 
        package_price, remark, owner_available 
        FROM sales_track 
        ORDER BY current_date DESC, time_stamp DESC";
    $fetch_sales_stmt = $conn->prepare($fetch_sales_sql);
} else {
    // Sales person can only see their own records
    $fetch_sales_sql = "SELECT 
        id, user_id, user_name, current_date, time_stamp, 
        restaurant_name, contacted_person, phone, 
        decision_maker_name, decision_maker_phone, 
        location, street, city, state, 
        postal_code, country, follow_up_date, 
        package_price, remark, owner_available 
        FROM sales_track 
        WHERE user_id = ?
        ORDER BY current_date DESC, time_stamp DESC";
    $fetch_sales_stmt = $conn->prepare($fetch_sales_sql);
    $fetch_sales_stmt->bind_param("i", $user_id);
}

if ($fetch_sales_stmt) {
    $fetch_sales_stmt->execute();
    $result = $fetch_sales_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sales_track_list[] = $row;
    }
    $fetch_sales_stmt->close();
} else {
    $error_message = "Error preparing fetch query: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Track</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/app.min.css" rel="stylesheet">
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
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
            include 'menu.php'; // default menu for other roles
        }
        ?>
        
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Sales Track Management</h4>
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
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Add New Sales Track Record</h5>
                                            </div>
                                            <div class="card-body">
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
                                                    <div class="row" style="display:none;">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Date</label>
                                                            <input type="date" class="form-control" value="<?= date('Y-m-d') ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Restaurant Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" name="restaurant_name" value="<?= htmlspecialchars($_POST['restaurant_name'] ?? '') ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Package Price<span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" class="form-control" name="package_price" value="<?= htmlspecialchars($_POST['package_price'] ?? '') ?>" required>
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
                                                            <small class="text-muted">Click "Detect" to auto-fill your current location with street details</small>
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
                                                        <textarea class="form-control" name="remark" id="remark" rows="3" required minlength="5"></textarea>
                                                        <div class="invalid-feedback">Please enter a remark (at least 5 characters).</div>
                                                    </div>
                                                    <div class="mb-3 form-check">
                                                        <label class="form-check-label">
                                                        <input type="checkbox" class="form-check-input" name="owner_available" value="1" <?= isset($_POST['owner_available']) ? 'checked' : '' ?>>
                                                        Decision Maker Available</label>
                                                    </div>

                                                    <button type="submit" name="add_sales_track" class="btn btn-primary">Add Record</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                
<div class="col-md-12 mt-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Existing Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr.No.</th>
                            <th style="display:none;">ID</th>
                            <?php if ($role === 'admin'): ?>
                                <th>Sales Person</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Restaurant</th>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Owner</th>
                            <th>D.M.</th>
                            <th>D.M. Phone</th>
                            <th>Location Details</th>
                            <th>Follow Up</th>
                            <th>Price</th>
                            <th>Remark</th>
                            <th>Add Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sales_track_list)): ?>
                            <?php foreach ($sales_track_list as $index => $entry): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td style="display:none;"><?= htmlspecialchars($entry['id']) ?></td>
                                    <?php if ($role === 'admin'): ?>
                                        <td><?= htmlspecialchars($entry['user_name']) ?></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($entry['current_date']) ?></td>
                                    <td><?= date('h:i A', strtotime($entry['time_stamp'])) ?></td>
                                    <td><?= htmlspecialchars($entry['restaurant_name']) ?></td>
                                    <td><?= htmlspecialchars($entry['contacted_person']) ?></td>
                                    <td><?= htmlspecialchars($entry['phone']) ?></td>
                                    <td><?= $entry['owner_available'] ? 'Yes' : 'No' ?></td>
                                    <td><?= htmlspecialchars($entry['decision_maker_name']) ?></td>
                                    <td><?= htmlspecialchars($entry['decision_maker_phone']) ?></td>
                                    
                                    <td>
                                        <?php
                                            $fullAddress = '';
                                            if (!empty($entry['street'])) {
                                                $fullAddress .= htmlspecialchars($entry['street']) . '<br>';
                                            }
                                            if (!empty($entry['city'])) {
                                                $fullAddress .= htmlspecialchars($entry['city']);
                                            }
                                            if (!empty($entry['state'])) {
                                                $fullAddress .= ', ' . htmlspecialchars($entry['state']);
                                            }
                                        ?>
                                        <div class="address">
                                            <?= $fullAddress ?>
                                        </div>
                                    </td>

                                    <td><?= htmlspecialchars($entry['follow_up_date']) ?></td>
                                    <td><?= number_format($entry['package_price']) ?></td>
                                    
                                    <td>
                                        <?php if (!empty($entry['remark'])): ?>
                                            <div class="remark-container">
                                                <?php 
                                                    $remarks = explode("\n\n", $entry['remark']);
                                                    foreach ($remarks as $remark): 
                                                        if (!empty(trim($remark))):
                                                            $parts = explode(" - ", $remark, 2);
                                                ?>
                                                            <div class="remark-entry">
                                                                <?php if (count($parts) > 1): ?>
                                                                    <div class="remark-date"><?= htmlspecialchars($parts[0]) ?></div>
                                                                    <div class="remark-content"><?= htmlspecialchars($parts[1]) ?></div>
                                                                <?php else: ?>
                                                                    <div class="remark-content"><?= htmlspecialchars($remark) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                <?php 
                                                        endif;
                                                    endforeach; 
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary add-remark-btn" 
                                                data-record-id="<?= $entry['id'] ?>"
                                                data-restaurant-name="<?= htmlspecialchars($entry['restaurant_name']) ?>">
                                            <i class="fas fa-plus"></i> Add Remark
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($role === 'admin') ? '16' : '15' ?>" class="text-center">No records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

<!-- Add Remark Modal -->
<div class="modal fade" id="addRemarkModal" tabindex="-1" role="dialog" aria-labelledby="addRemarkModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRemarkModalLabel">Add Remark</h5>
                <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button> -->
            </div>
            <form id="addRemarkForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                        <input type="hidden" name="record_id" id="modalRecordId">
                        <div class="form-group">
                            <label for="restaurantName">Restaurant</label>
                            <input type="text" class="form-control" id="modalRestaurantName" readonly>
                        </div>
                        </div>
                    </div>   
                    <div class="row">
                        <div class="col-md-12 mt-2">
                        <div class="form-group">
                            <label for="new_remark">New Remark</label>
                            <textarea class="form-control remark-textarea" name="new_remark" id="new_remark" required></textarea>
                            <div class="invalid-feedback">Please enter a remark (at least 5 characters).</div>
                        </div>
                        </div>
                    </div> 
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_remark" class="btn btn-primary">Save Remark</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Make sure these scripts are loaded in this order -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Add remark button click handler - this is the critical fix
    $(document).on('click', '.add-remark-btn', function(e) {
        e.preventDefault();
        
        var recordId = $(this).data('record-id');
        var restaurantName = $(this).data('restaurant-name');
        
        $('#modalRecordId').val(recordId);
        $('#modalRestaurantName').val(restaurantName);
        $('#new_remark').val('').focus();
        
        // Initialize and show the modal
        $('#addRemarkModal').modal('show');
    });

    // Form validation for add remark form
    $("#addRemarkForm").validate({
        rules: {
            new_remark: {
                required: true,
                minlength: 5
            }
        },
        messages: {
            new_remark: {
                required: "Please enter a remark",
                minlength: "Remark should be at least 5 characters long"
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

    // Handle modal hidden event to clear form
    $('#addRemarkModal').on('hidden.bs.modal', function() {
        $('#addRemarkForm')[0].reset();
        $('#addRemarkForm').validate().resetForm();
    });
});
</script>


<!-- Load jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Then load Bootstrap JS bundle (includes Popper.js) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Then your other scripts -->
<script src="assets/js/vendor.js"></script>
<script src="assets/js/app.js"></script>


    <script>
        $(document).ready(function() {
            // Location detection
            $('#detectLocation').click(function() {
                $('#locationStatus').html('<div class="alert alert-info">Detecting your location...</div>');
                
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Success callback
                            const latitude = position.coords.latitude;
                            const longitude = position.coords.longitude;
                            
                            // Use a geocoding service to get address details
                            $.get(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`, 
                                function(data) {
                                    // Parse the response and fill the form fields
                                    const address = data.address || {};
                                    
                                    // Set the main location field
                                    let locationText = '';
                                    if (address.road) locationText += address.road;
                                    if (address.road && address.city) locationText += ', ';
                                    if (address.city) locationText += address.city;
                                    
                                    $('#location').val(locationText || 'Current Location');
                                    
                                    // Set the detailed address fields
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
                            // Error callback
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

            // Form validation for main form
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
                    remark: {  // Add this for remark validation
                        required: true,
                        minlength: 5
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
                    remark: {  // Add this for remark validation messages
                        required: "Please enter a remark",
                        minlength: "Remark should be at least 5 characters long"
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

            // Replace your existing add remark button click handler with this:
            $(document).on('click', '.add-remark-btn', function(e) {
                e.preventDefault();
                
                var recordId = $(this).data('record-id');
                var restaurantName = $(this).data('restaurant-name');
                
                $('#modalRecordId').val(recordId);
                $('#modalRestaurantName').val(restaurantName);
                $('#new_remark').val('');
                
                // Clear any previous validation errors
                $('#addRemarkForm').validate().resetForm();
                $('#new_remark').removeClass('is-invalid');
                
                // Initialize and show the modal
                $('#addRemarkModal').modal({
                    backdrop: 'static',
                    keyboard: false
                }).modal('show');
            });

            // Handle modal hidden event to clear form
            $('#addRemarkModal').on('hidden.bs.modal', function () {
                $('#addRemarkForm')[0].reset();
                $('#addRemarkForm').validate().resetForm();
            });

            // Form validation for add remark form
            $("#addRemarkForm").validate({
                rules: {
                    new_remark: {
                        required: true,
                        minlength: 5
                    }
                },
                messages: {
                    new_remark: {
                        required: "Please enter a remark",
                        minlength: "Remark should be at least 5 characters long"
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
                },
                submitHandler: function(form) {
                    form.submit();
                }
            });

            // Toggle address details
            function toggleAddress(link) {
                const shortDiv = $(link).closest('td').find('.short-address');
                const fullDiv = $(link).closest('td').find('.full-address');
                if (shortDiv.is(':visible')) {
                    shortDiv.hide();
                    fullDiv.show();
                } else {
                    shortDiv.show();
                    fullDiv.hide();
                }
            }
            
            // Make toggleAddress function available globally
            window.toggleAddress = toggleAddress;
        });

        $(document).ready(function() {
            // Handle click on cancel button
            $(document).on('click', '#addRemarkModal .btn.btn-secondary', function(e) {
                e.preventDefault();
                
                // Hide the modal
                $('#addRemarkModal').modal('hide');
                
                // Or manually:
                // $('#addRemarkModal').removeClass('show');
                // $('#addRemarkModal').css('display', 'none');
                // $('body').removeClass('modal-open');
                // $('.modal-backdrop').remove();
            });
        });
    </script>


</body>
</html>