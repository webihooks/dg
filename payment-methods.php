<?php
// payment-methods.php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
date_default_timezone_set('Asia/Kolkata');

require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

require_once 'session_check.php';
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Check if payment methods table exists, if not create it
$check_table_sql = "SHOW TABLES LIKE 'payment_methods_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    // Create payment methods table
    $create_table_sql = "
        CREATE TABLE `payment_methods_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `method_name` VARCHAR(100) NOT NULL,
            `method_type` ENUM('cash', 'card', 'upi', 'bank_transfer', 'wallet', 'other') NOT NULL,
            `account_details` TEXT,
            `qr_code_image` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `display_order` INT(3) DEFAULT 0,
            `processing_fee` DECIMAL(5,2) DEFAULT 0.00,
            `min_amount` DECIMAL(10,2) DEFAULT 0.00,
            `max_amount` DECIMAL(10,2) DEFAULT 0.00,
            `additional_info` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `method_name` (`method_name`),
            KEY `method_type` (`method_type`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) === FALSE) {
        $error_message = "Error creating payment methods table: " . $conn->error;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_payment_method'])) {
        // Add new payment method
        $method_name = trim($_POST['method_name']);
        $method_type = $_POST['method_type'];
        $account_details = trim($_POST['account_details']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order']);
        $processing_fee = floatval($_POST['processing_fee']);
        $min_amount = floatval($_POST['min_amount']);
        $max_amount = floatval($_POST['max_amount']);
        $additional_info = trim($_POST['additional_info']);
        
        // Handle QR code upload
        $qr_code_image = '';
        if (isset($_FILES['qr_code_image']) && $_FILES['qr_code_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'assets/uploads/payment_qr/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['qr_code_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'qr_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['qr_code_image']['tmp_name'], $file_path)) {
                $qr_code_image = $file_path;
            }
        }
        
        $insert_sql = "INSERT INTO payment_methods_$user_id 
                      (method_name, method_type, account_details, qr_code_image, is_active, display_order, processing_fee, min_amount, max_amount, additional_info) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssiiddds", $method_name, $method_type, $account_details, $qr_code_image, $is_active, $display_order, $processing_fee, $min_amount, $max_amount, $additional_info);
        
        if ($stmt->execute()) {
            $success_message = "Payment method added successfully!";
        } else {
            $error_message = "Error adding payment method: " . $stmt->error;
        }
        $stmt->close();
        
    } elseif (isset($_POST['update_payment_method'])) {
        // Update payment method
        $method_id = intval($_POST['method_id']);
        $method_name = trim($_POST['method_name']);
        $method_type = $_POST['method_type'];
        $account_details = trim($_POST['account_details']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order']);
        $processing_fee = floatval($_POST['processing_fee']);
        $min_amount = floatval($_POST['min_amount']);
        $max_amount = floatval($_POST['max_amount']);
        $additional_info = trim($_POST['additional_info']);
        
        // Handle QR code upload
        $qr_code_image = $_POST['existing_qr_code'];
        if (isset($_FILES['qr_code_image']) && $_FILES['qr_code_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'assets/uploads/payment_qr/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Delete old QR code if exists
            if (!empty($qr_code_image) && file_exists($qr_code_image)) {
                unlink($qr_code_image);
            }
            
            $file_extension = pathinfo($_FILES['qr_code_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'qr_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['qr_code_image']['tmp_name'], $file_path)) {
                $qr_code_image = $file_path;
            }
        }
        
        $update_sql = "UPDATE payment_methods_$user_id 
                      SET method_name = ?, method_type = ?, account_details = ?, qr_code_image = ?, is_active = ?, display_order = ?, processing_fee = ?, min_amount = ?, max_amount = ?, additional_info = ?
                      WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssiidddsi", $method_name, $method_type, $account_details, $qr_code_image, $is_active, $display_order, $processing_fee, $min_amount, $max_amount, $additional_info, $method_id);
        
        if ($stmt->execute()) {
            $success_message = "Payment method updated successfully!";
        } else {
            $error_message = "Error updating payment method: " . $stmt->error;
        }
        $stmt->close();
        
    } elseif (isset($_POST['delete_payment_method'])) {
        // Delete payment method
        $method_id = intval($_POST['method_id']);
        
        // Get QR code path to delete file
        $get_qr_sql = "SELECT qr_code_image FROM payment_methods_$user_id WHERE id = ?";
        $stmt = $conn->prepare($get_qr_sql);
        $stmt->bind_param("i", $method_id);
        $stmt->execute();
        $stmt->bind_result($qr_code_image);
        $stmt->fetch();
        $stmt->close();
        
        // Delete QR code file if exists
        if (!empty($qr_code_image) && file_exists($qr_code_image)) {
            unlink($qr_code_image);
        }
        
        $delete_sql = "DELETE FROM payment_methods_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $method_id);
        
        if ($stmt->execute()) {
            $success_message = "Payment method deleted successfully!";
        } else {
            $error_message = "Error deleting payment method: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all payment methods
$payment_methods = [];
$methods_sql = "SELECT * FROM payment_methods_$user_id ORDER BY display_order, method_name";
$result = $conn->query($methods_sql);
if ($result) {
    $payment_methods = $result->fetch_all(MYSQLI_ASSOC);
}

// Get method for editing
$edit_method = null;
if (isset($_GET['edit'])) {
    $method_id = intval($_GET['edit']);
    $edit_sql = "SELECT * FROM payment_methods_$user_id WHERE id = ?";
    $stmt = $conn->prepare($edit_sql);
    $stmt->bind_param("i", $method_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_method = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Payment Methods</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .payment-method-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .payment-method-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .payment-method-card.cash { border-left-color: #28a745; }
        .payment-method-card.card { border-left-color: #6f42c1; }
        .payment-method-card.upi { border-left-color: #fd7e14; }
        .payment-method-card.bank_transfer { border-left-color: #20c997; }
        .payment-method-card.wallet { border-left-color: #e83e8c; }
        .payment-method-card.other { border-left-color: #6c757d; }
        
        .method-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .qr-code-preview {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px;
        }
        
        .payment-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        // Include appropriate menu based on user role and subscription
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'room_management_menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Page Header -->
                        <div class="page-title-box">
                            <h4 class="page-title">Payment Methods Management</h4>
                            <p class="text-muted mb-4">Manage your accepted payment methods and settings</p>
                        </div>

                        <!-- Notifications -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Payment Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="payment-stats text-center">
                                    <h3 class="stats-number"><?php echo count($payment_methods); ?></h3>
                                    <p class="stats-label">Total Methods</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="payment-stats text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <h3 class="stats-number">
                                        <?php echo count(array_filter($payment_methods, function($method) { return $method['is_active'] == 1; })); ?>
                                    </h3>
                                    <p class="stats-label">Active Methods</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="payment-stats text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <h3 class="stats-number">
                                        <?php echo count(array_filter($payment_methods, function($method) { return $method['method_type'] === 'upi'; })); ?>
                                    </h3>
                                    <p class="stats-label">UPI Methods</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="payment-stats text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                    <h3 class="stats-number">
                                        <?php echo count(array_filter($payment_methods, function($method) { return $method['method_type'] === 'cash'; })); ?>
                                    </h3>
                                    <p class="stats-label">Cash Methods</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Add/Edit Payment Method Form -->
                            <div class="col-lg-5">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            <?php echo $edit_method ? 'Edit Payment Method' : 'Add New Payment Method'; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data" id="paymentMethodForm">
                                            <?php if ($edit_method): ?>
                                                <input type="hidden" name="method_id" value="<?php echo $edit_method['id']; ?>">
                                                <input type="hidden" name="existing_qr_code" value="<?php echo $edit_method['qr_code_image'] ?? ''; ?>">
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="method_name" class="form-label">Method Name *</label>
                                                <input type="text" class="form-control" id="method_name" name="method_name" 
                                                       value="<?php echo $edit_method ? htmlspecialchars($edit_method['method_name']) : ''; ?>" 
                                                       required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="method_type" class="form-label">Method Type *</label>
                                                <select class="form-control" id="method_type" name="method_type" required>
                                                    <option value="">Select Type</option>
                                                    <option value="cash" <?php echo ($edit_method && $edit_method['method_type'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                                                    <option value="card" <?php echo ($edit_method && $edit_method['method_type'] === 'card') ? 'selected' : ''; ?>>Card</option>
                                                    <option value="upi" <?php echo ($edit_method && $edit_method['method_type'] === 'upi') ? 'selected' : ''; ?>>UPI</option>
                                                    <option value="bank_transfer" <?php echo ($edit_method && $edit_method['method_type'] === 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                                    <option value="wallet" <?php echo ($edit_method && $edit_method['method_type'] === 'wallet') ? 'selected' : ''; ?>>Wallet</option>
                                                    <option value="other" <?php echo ($edit_method && $edit_method['method_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="account_details" class="form-label">Account Details</label>
                                                <textarea class="form-control" id="account_details" name="account_details" rows="3" 
                                                          placeholder="Bank account number, UPI ID, wallet details, etc."><?php echo $edit_method ? htmlspecialchars($edit_method['account_details']) : ''; ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="qr_code_image" class="form-label">QR Code Image</label>
                                                <input type="file" class="form-control" id="qr_code_image" name="qr_code_image" 
                                                       accept="image/*">
                                                <?php if ($edit_method && !empty($edit_method['qr_code_image'])): ?>
                                                    <div class="mt-2">
                                                        <img src="<?php echo $edit_method['qr_code_image']; ?>" alt="QR Code" class="qr-code-preview">
                                                        <p class="text-muted small mt-1">Current QR Code</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="processing_fee" class="form-label">Processing Fee (%)</label>
                                                        <input type="number" class="form-control" id="processing_fee" name="processing_fee" 
                                                               step="0.01" min="0" max="10" 
                                                               value="<?php echo $edit_method ? $edit_method['processing_fee'] : '0.00'; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="display_order" class="form-label">Display Order</label>
                                                        <input type="number" class="form-control" id="display_order" name="display_order" 
                                                               min="0" max="999" 
                                                               value="<?php echo $edit_method ? $edit_method['display_order'] : '0'; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="min_amount" class="form-label">Minimum Amount (₹)</label>
                                                        <input type="number" class="form-control" id="min_amount" name="min_amount" 
                                                               step="0.01" min="0" 
                                                               value="<?php echo $edit_method ? $edit_method['min_amount'] : '0.00'; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="max_amount" class="form-label">Maximum Amount (₹)</label>
                                                        <input type="number" class="form-control" id="max_amount" name="max_amount" 
                                                               step="0.01" min="0" 
                                                               value="<?php echo $edit_method ? $edit_method['max_amount'] : '0.00'; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="additional_info" class="form-label">Additional Information</label>
                                                <textarea class="form-control" id="additional_info" name="additional_info" rows="2" 
                                                          placeholder="Any additional instructions or notes"><?php echo $edit_method ? htmlspecialchars($edit_method['additional_info']) : ''; ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                                       <?php echo ($edit_method && $edit_method['is_active'] == 1) || !$edit_method ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_active">Active (Accept payments with this method)</label>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <?php if ($edit_method): ?>
                                                    <button type="submit" name="update_payment_method" class="btn btn-primary">Update Payment Method</button>
                                                    <a href="payment-methods.php" class="btn btn-secondary">Cancel</a>
                                                <?php else: ?>
                                                    <button type="submit" name="add_payment_method" class="btn btn-success">Add Payment Method</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Methods List -->
                            <div class="col-lg-7">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Your Payment Methods</h5>
                                        <p class="text-muted mb-0">Manage all your accepted payment methods</p>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($payment_methods)): ?>
                                            <div class="text-center py-4">
                                                <div class="mb-3">
                                                    <i class="fas fa-credit-card fa-3x text-muted"></i>
                                                </div>
                                                <h5>No Payment Methods Added</h5>
                                                <p class="text-muted">Start by adding your first payment method using the form on the left.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Method</th>
                                                            <th>Type</th>
                                                            <th>Status</th>
                                                            <th>Order</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payment_methods as $method): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($method['method_name']); ?></strong>
                                                                    <?php if (!empty($method['account_details'])): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($method['account_details'], 0, 30)) . '...'; ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge method-badge bg-<?php 
                                                                        switch($method['method_type']) {
                                                                            case 'cash': echo 'success'; break;
                                                                            case 'card': echo 'primary'; break;
                                                                            case 'upi': echo 'warning'; break;
                                                                            case 'bank_transfer': echo 'info'; break;
                                                                            case 'wallet': echo 'danger'; break;
                                                                            default: echo 'secondary';
                                                                        }
                                                                    ?>">
                                                                        <?php echo ucfirst(str_replace('_', ' ', $method['method_type'])); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge <?php echo $method['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                                        <?php echo $method['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $method['display_order']; ?></td>
                                                                <td>
                                                                    <div class="btn-group btn-group-sm">
                                                                        <a href="payment-methods.php?edit=<?php echo $method['id']; ?>" class="btn btn-outline-primary">Edit</a>
                                                                        <button type="button" class="btn btn-outline-danger" 
                                                                                onclick="confirmDelete(<?php echo $method['id']; ?>, '<?php echo htmlspecialchars($method['method_name']); ?>')">
                                                                            Delete
                                                                        </button>
                                                                    </div>
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

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete payment method: <strong id="deleteMethodName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="method_id" id="deleteMethodId">
                        <button type="submit" name="delete_payment_method" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    // Enhanced Android Session Protection
    function setupPaymentSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('💳 Payment Methods: Setting up Android session protection');
        
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 1000);
        
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && WTN.forceUpdateCookies) {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                }, 500);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupPaymentSessionProtection();
        
        // Form validation
        $('#paymentMethodForm').validate({
            rules: {
                method_name: {
                    required: true,
                    minlength: 2
                },
                method_type: {
                    required: true
                }
            },
            messages: {
                method_name: {
                    required: "Please enter payment method name",
                    minlength: "Method name must be at least 2 characters long"
                },
                method_type: {
                    required: "Please select payment method type"
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

        // QR code preview
        $('#qr_code_image').change(function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if ($('#qrPreview').length === 0) {
                        $('#qr_code_image').after('<div class="mt-2"><img id="qrPreview" src="' + e.target.result + '" alt="QR Code Preview" class="qr-code-preview"><p class="text-muted small mt-1">New QR Code Preview</p></div>');
                    } else {
                        $('#qrPreview').attr('src', e.target.result);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    });

    function confirmDelete(methodId, methodName) {
        $('#deleteMethodId').val(methodId);
        $('#deleteMethodName').text(methodName);
        $('#deleteModal').modal('show');
    }

    // Enhanced session monitoring for payment methods
    if (typeof WTN !== 'undefined') {
        setInterval(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 45000);
    }
    </script>
</body>
</html>