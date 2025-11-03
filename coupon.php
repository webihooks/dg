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

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Process form submission for creating a new coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_coupon'])) {
    $coupon_code = trim($_POST['coupon_code']);
    $discount_type = $_POST['discount_type'];
    $discount_value = (float)$_POST['discount_value'];
    $min_cart_value = isset($_POST['min_cart_value']) ? (float)$_POST['min_cart_value'] : 0;
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $usage_limit = isset($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate input
    $errors = [];
    
    if (empty($coupon_code)) {
        $errors[] = "Coupon code is required.";
    }
    
    if ($discount_value <= 0) {
        $errors[] = "Discount value must be greater than 0.";
    }
    
    if ($discount_type === 'percentage' && ($discount_value < 1 || $discount_value > 100)) {
        $errors[] = "Percentage discount must be between 1 and 100.";
    }
    
    if ($min_cart_value < 0) {
        $errors[] = "Minimum cart value cannot be negative.";
    }
    
    if (!empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
        $errors[] = "End date must be after start date.";
    }
    
    if ($usage_limit < 0) {
        $errors[] = "Usage limit cannot be negative.";
    }
    
    if (empty($errors)) {
        // Check if coupon code already exists for this user
        $sql_check = "SELECT id FROM coupons WHERE coupon_code = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $coupon_code, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $message = "Coupon code already exists for your account. Please choose a different one.";
            $message_type = "danger";
        } else {
            // Insert new coupon
            $sql = "INSERT INTO coupons (
                user_id, 
                coupon_code, 
                discount_type, 
                discount_value, 
                min_cart_value, 
                start_date, 
                end_date, 
                usage_limit,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issddssii", 
                $user_id,
                $coupon_code,
                $discount_type,
                $discount_value,
                $min_cart_value,
                $start_date,
                $end_date,
                $usage_limit,
                $is_active
            );
            
            if ($stmt->execute()) {
                $message = "Coupon created successfully!";
            } else {
                $message = "Error creating coupon: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
        $stmt_check->close();
    } else {
        $message = implode("<br>", $errors);
        $message_type = "danger";
    }
}

// Process coupon status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    
    // Get current status
    $sql = "SELECT is_active FROM coupons WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $coupon_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($current_status);
    $stmt->fetch();
    $stmt->close();
    
    if (isset($current_status)) {
        $new_status = $current_status ? 0 : 1;
        
        $sql = "UPDATE coupons SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $new_status, $coupon_id);
        
        if ($stmt->execute()) {
            $message = "Coupon status updated successfully!";
        } else {
            $message = "Error updating coupon status: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Coupon not found or you don't have permission to modify it.";
        $message_type = "danger";
    }
}

// Process coupon deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    
    $sql_check = "SELECT id FROM coupons WHERE id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $coupon_id, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $sql = "DELETE FROM coupons WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $coupon_id);
        
        if ($stmt->execute()) {
            $message = "Coupon deleted successfully!";
        } else {
            $message = "Error deleting coupon: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Coupon not found or you don't have permission to delete it.";
        $message_type = "danger";
    }
    $stmt_check->close();
}

// Fetch all coupons for this user
$coupons = [];
$sql = "SELECT 
            id, 
            coupon_code, 
            discount_type, 
            discount_value, 
            min_cart_value, 
            start_date, 
            end_date, 
            usage_limit,
            is_active,
            created_at
        FROM coupons 
        WHERE user_id = ? 
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $coupons[] = $row;
}
$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Coupon Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Coupon Management</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Create New Coupon</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="coupon.php" id="couponForm">
                                                    <div class="mb-3">
                                                        <label for="coupon_code" class="form-label">Coupon Code *</label>
                                                        <input type="text" class="form-control" id="coupon_code" name="coupon_code" required>
                                                        <small class="text-muted">Unique code for the coupon</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="discount_type" class="form-label">Discount Type *</label>
                                                        <select class="form-select" id="discount_type" name="discount_type" required>
                                                            <option value="percentage">Percentage</option>
                                                            <option value="fixed">Fixed Amount</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="discount_value" class="form-label">Discount Value *</label>
                                                        <input type="number" class="form-control" id="discount_value" name="discount_value" step="0.01" min="0.01" required>
                                                        <small class="text-muted">Percentage (1-100) or fixed amount</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="min_cart_value" class="form-label">Minimum Cart Value</label>
                                                        <input type="number" class="form-control" id="min_cart_value" name="min_cart_value" step="0.01" min="0" value="0">
                                                        <small class="text-muted">Minimum order amount to apply coupon (0 for no minimum)</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="start_date" class="form-label">Start Date *</label>
                                                        <input type="text" class="form-control datepicker" id="start_date" name="start_date" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="end_date" class="form-label">End Date (Optional)</label>
                                                        <input type="text" class="form-control datepicker" id="end_date" name="end_date">
                                                        <small class="text-muted">Leave blank for no expiration</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="usage_limit" class="form-label">Usage Limit</label>
                                                        <input type="number" class="form-control" id="usage_limit" name="usage_limit" min="0" value="0">
                                                        <small class="text-muted">0 for unlimited uses</small>
                                                    </div>
                                                    
                                                    <div class="mb-3 form-check">
                                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                                        <label class="form-check-label" for="is_active">Active</label>
                                                    </div>
                                                    
                                                    <button type="submit" name="create_coupon" class="btn btn-primary">Create Coupon</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Your Coupons</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($coupons)): ?>
                                                    <p>No coupons found.</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Code</th>
                                                                    <th>Discount</th>
                                                                    <th>Min. Cart</th>
                                                                    <th>Valid From</th>
                                                                    <th>Valid To</th>
                                                                    <th>Uses Left</th>
                                                                    <th>Status</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($coupons as $coupon): 
                                                                    $today = date('Y-m-d');
                                                                    $status = $coupon['is_active'];
                                                                    
                                                                    if ($status === 0) {
                                                                        $badge_class = 'bg-secondary';
                                                                        $status_text = 'Inactive';
                                                                    } elseif (!empty($coupon['end_date']) && $today > $coupon['end_date']) {
                                                                        $badge_class = 'bg-danger';
                                                                        $status_text = 'Expired';
                                                                    } elseif ($today < $coupon['start_date']) {
                                                                        $badge_class = 'bg-info';
                                                                        $status_text = 'Upcoming';
                                                                    } else {
                                                                        $badge_class = 'bg-success';
                                                                        $status_text = 'Active';
                                                                    }
                                                                    
                                                                    $uses_left = ($coupon['usage_limit'] == 0) ? '∞' : $coupon['usage_limit'];
                                                                ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                                                                        <td>
                                                                            <?php 
                                                                                echo htmlspecialchars($coupon['discount_value']);
                                                                                echo ($coupon['discount_type'] === 'percentage') ? '%' : '';
                                                                            ?>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($coupon['min_cart_value']); ?></td>
                                                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($coupon['start_date']))); ?></td>
                                                                        <td><?php echo !empty($coupon['end_date']) ? htmlspecialchars(date('M d, Y', strtotime($coupon['end_date']))) : 'No expiry'; ?></td>
                                                                        <td><?php echo $uses_left; ?></td>
                                                                        <td>
                                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                                <?php echo $status_text; ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <form method="POST" action="coupon.php" style="display:inline;">
                                                                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $coupon['is_active'] ? 'warning' : 'success'; ?>">
                                                                                    <?php echo $coupon['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                                </button>
                                                                            </form>
                                                                            <form method="POST" action="coupon.php" style="display:inline;" class="ms-1">
                                                                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                                                <button type="submit" name="delete_coupon" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this coupon?');">
                                                                                    Delete
                                                                                </button>
                                                                            </form>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
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
        $(document).ready(function() {
            // Initialize date pickers
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // Form validation
            $("#couponForm").validate({
                rules: {
                    coupon_code: {
                        required: true,
                        minlength: 4
                    },
                    discount_value: {
                        required: true,
                        min: 0.01
                    },
                    min_cart_value: {
                        min: 0
                    },
                    start_date: {
                        required: true,
                        date: true
                    },
                    end_date: {
                        date: true,
                        greaterThan: "#start_date"
                    },
                    usage_limit: {
                        min: 0
                    }
                },
                messages: {
                    coupon_code: {
                        required: "Please enter a coupon code",
                        minlength: "Coupon code must be at least 4 characters long"
                    },
                    discount_value: {
                        required: "Please enter a discount value",
                        min: "Discount must be greater than 0"
                    },
                    min_cart_value: {
                        min: "Minimum cart value cannot be negative"
                    },
                    start_date: {
                        required: "Please select a start date",
                        date: "Please enter a valid date"
                    },
                    end_date: {
                        date: "Please enter a valid date",
                        greaterThan: "End date must be after start date"
                    },
                    usage_limit: {
                        min: "Usage limit cannot be negative"
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

            // Custom validation method for end date
            $.validator.addMethod("greaterThan", function(value, element, param) {
                if (!value) return true; // Optional field
                var startDate = $(param).val();
                return Date.parse(value) > Date.parse(startDate);
            }, "End date must be after start date");

            // Toggle discount value hint based on discount type
            $('#discount_type').change(function() {
                if ($(this).val() === 'percentage') {
                    $('#discount_value').attr('min', '1').attr('max', '100');
                    $('label[for="discount_value"] small').text('Percentage between 1 and 100');
                } else {
                    $('#discount_value').removeAttr('max');
                    $('label[for="discount_value"] small').text('Fixed amount (minimum 0.01)');
                }
            });
        });
    </script>
</body>
</html>