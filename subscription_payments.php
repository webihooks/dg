<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata'); // for Indian Standard Time
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT role, name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($role, $user_name);
$user_stmt->fetch();
$user_stmt->close();

if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_balance'])) {
        // Handle balance payment update
        $payment_id = $_POST['payment_id'];
        $balance_payment = $_POST['balance_payment'];
        $balance_payment_date = !empty($_POST['balance_payment_date']) ? $_POST['balance_payment_date'] : null;
        $qr_card_qty = $_POST['qr_card_qty'] ?? 0;
        $qr_card_price = $_POST['qr_card_price'] ?? 0;
        $notes = $_POST['notes'] ?? '';
        
        $update_sql = "UPDATE subscription_payments 
              SET balance_payment = ?, balance_payment_date = ?, qr_card_qty = ?, qr_card_price = ?, notes = ?
              WHERE payment_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dsddsi", $balance_payment, $balance_payment_date, $qr_card_qty, $qr_card_price, $notes, $payment_id);
        
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Balance payment updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating balance payment: " . $conn->error;
        }
        
        $update_stmt->close();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } 
    elseif (isset($_POST['mark_paid'])) {
        // Handle marking balance as paid
        $payment_id = $_POST['payment_id'];
        
        $update_sql = "UPDATE subscription_payments 
                      SET balance_payment = 0, balance_payment_date = NULL
                      WHERE payment_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $payment_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Balance payment marked as paid successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating payment: " . $conn->error;
        }
        
        $update_stmt->close();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    else {
        // Handle new payment record
        $user_id = $_POST['user_id'];
        $package_price = $_POST['package_price'];
        $advance_payment = $_POST['advance_payment'] ?? 0;
        $balance_payment = $_POST['balance_payment'] ?? 0;
        $balance_payment_date = !empty($_POST['balance_payment_date']) ? $_POST['balance_payment_date'] : null;
        $qr_card_qty = $_POST['qr_card_qty'] ?? 0;
        $qr_card_price = $_POST['qr_card_price'] ?? 0;
        $payment_date = date('Y-m-d H:i:s');

        $insert_sql = "INSERT INTO subscription_payments 
                      (user_id, package_price, advance_payment, balance_payment, balance_payment_date, qr_card_qty, qr_card_price, payment_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        
        if ($insert_stmt->bind_param("idddsiss", $user_id, $package_price, $advance_payment, 
                                   $balance_payment, $balance_payment_date, $qr_card_qty, $qr_card_price, $payment_date)) {
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Payment record added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding payment record: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Error binding parameters: " . $conn->error;
        }
        
        $insert_stmt->close();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get filter value from GET parameter
$status_filter = $_GET['status'] ?? 'all';

// Fetch all users from subscriptions table who don't have a package price yet
$users_sql = "SELECT s.user_id, u.name 
              FROM subscriptions s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN subscription_payments sp ON s.user_id = sp.user_id
              WHERE sp.package_price IS NULL
              AND u.role != 'admin'
              GROUP BY s.user_id
              ORDER BY u.name ASC";
$users_result = $conn->query($users_sql);

// Build payment records query based on filter
$payments_sql = "SELECT sp.payment_id, sp.user_id, sp.package_price, sp.advance_payment, 
                 sp.balance_payment, sp.qr_card_qty, sp.qr_card_price, sp.notes, sp.payment_date, sp.balance_payment_date,
                 u.name as user_name 
                 FROM subscription_payments sp
                 JOIN users u ON sp.user_id = u.id";

// Add WHERE clause based on filter
if ($status_filter === 'paid') {
    $payments_sql .= " WHERE sp.balance_payment = 0";
} elseif ($status_filter === 'pending') {
    $payments_sql .= " WHERE sp.balance_payment > 0";
} elseif ($status_filter === 'overdue') {
    $payments_sql .= " WHERE sp.balance_payment > 0 AND (sp.balance_payment_date IS NOT NULL AND sp.balance_payment_date < CURDATE())";
}

$payments_sql .= " ORDER BY sp.payment_date DESC";

$payments_result = $conn->query($payments_sql);

if (!$payments_result) {
    die("Query failed: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Subscription Payments | Admin</title>
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
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="assets/js/config.js"></script>
    <style>
        .warning {
          color: #fff;
          background-color: orange !important;
          box-shadow: 0 0 0 1px orange !important;
        }
        .modal-dialog {
            max-width: 500px;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .mr-1 {
            margin-right: 0.25rem !important;
        }
        .status-filter {
            margin-bottom: 20px;
        }
        .status-filter .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .status-filter .btn {
            border-radius: 4px !important;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Add Subscription Payment</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form method="post" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="user_id">User Name <span class="text-danger">*</span></label>
                                                <select class="form-control" id="user_id" name="user_id" required>
                                                    <option value="">Select User</option>
                                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="package_price">Package Price (₹) <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="package_price" name="package_price" 
                                                       step="0.01" min="0" required placeholder="Enter package price">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="advance_payment">Advance Payment (₹)</label>
                                                <input type="number" class="form-control" id="advance_payment" name="advance_payment" 
                                                       step="0.01" min="0" placeholder="Enter advance amount">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="balance_payment">Balance Payment (₹)</label>
                                                <input type="number" class="form-control" id="balance_payment" name="balance_payment" 
                                                       step="0.01" min="0" placeholder="Enter balance amount">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="balance_payment_date">Balance Payment Due Date</label>
                                                <input type="text" class="form-control datepicker" id="balance_payment_date" 
                                                       name="balance_payment_date" placeholder="Select due date (optional)"
                                                       value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
        <div class="form-group">
            <label for="qr_card_qty">QR Card Quantity</label>
            <input type="number" class="form-control" id="qr_card_qty" name="qr_card_qty" 
                   min="0" placeholder="Enter quantity" value="1000">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="qr_card_price">QR Card Price (₹)</label>
            <input type="number" class="form-control" id="qr_card_price" name="qr_card_price" 
                   step="0.01" min="0" placeholder="Enter QR card price" value="2000">
        </div>
    </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary">Submit Payment</button>
                                            <button type="reset" class="btn btn-secondary ml-2">Reset Form</button>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <small class="text-muted"><span class="text-danger">*</span> indicates required field</small>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Records Table -->
                <div class="row mt-4">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title">Payment Records</h4>
                                <div class="status-filter">
                                    <div class="btn-group" role="group">
                                        <a href="?status=all" class="btn btn-sm <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                                        <a href="?status=paid" class="btn btn-sm <?php echo $status_filter === 'paid' ? 'btn-primary' : 'btn-outline-primary'; ?>">Paid</a>
                                        <a href="?status=pending" class="btn btn-sm <?php echo $status_filter === 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pending</a>
                                        <a href="?status=overdue" class="btn btn-sm <?php echo $status_filter === 'overdue' ? 'btn-primary' : 'btn-outline-primary'; ?>">Overdue</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User Name</th>
                                                <th>Package</th>
                                                <th>Advance</th>
                                                <th>Balance</th>
                                                <th>QR Qty</th>
                                                <th>QR Price</th>
                                                <th>Pay. Date</th>
                                                <th>Bal. Due</th>
                                                <th>Status</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
    <?php if ($payments_result->num_rows > 0): ?>
        <?php while ($payment = $payments_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $payment['payment_id']; ?></td>
                <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                <td>₹<?php echo number_format($payment['package_price'], 2); ?></td>
                <td>₹<?php echo number_format($payment['advance_payment'], 2); ?></td>
                <td>₹<?php echo number_format($payment['balance_payment'], 2); ?></td>
                <td><?php echo $payment['qr_card_qty']; ?></td>
                <td>₹<?php echo number_format($payment['qr_card_price'], 2); ?></td>
                <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                <td>
                    <?php 
                    if (!empty($payment['balance_payment_date'])) {
                        echo date('d M Y', strtotime($payment['balance_payment_date']));
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </td>
                <td>
                    <?php 
                    if ($payment['balance_payment'] > 0) {
                        $due_date = !empty($payment['balance_payment_date']) ? strtotime($payment['balance_payment_date']) : 0;
                        $today = time();
                        if ($due_date > 0 && $due_date < $today) {
                            echo '<span class="badge badge-danger">Overdue</span>';
                        } else {
                            echo '<span class="badge badge-warning warning">Pending</span>';
                        }
                    } else {
                        echo '<span class="badge badge-success">Paid</span>';
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                <td>
                    <?php if ($payment['balance_payment'] > 0): ?>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary update-balance-btn mr-1" 
                                    data-id="<?php echo $payment['payment_id']; ?>"
                                    data-balance="<?php echo $payment['balance_payment']; ?>"
                                    data-date="<?php echo !empty($payment['balance_payment_date']) ? $payment['balance_payment_date'] : ''; ?>"
                                    data-qrqty="<?php echo $payment['qr_card_qty']; ?>"
                                    data-qrprice="<?php echo $payment['qr_card_price']; ?>"
                                    data-notes="<?php echo htmlspecialchars($payment['notes'] ?? ''); ?>">
                                Update
                            </button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                                <input type="hidden" name="mark_paid" value="1">
                                <button type="submit" class="btn btn-sm btn-success" 
                                        onclick="return confirm('Mark this balance payment as received?')">
                                    Mark Paid
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="12" class="text-center">No payment records found</td>
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

            <?php include 'footer.php'; ?>
        </div>
    </div>


<!-- Update Balance Payment Modal -->
<div class="modal fade" id="updateBalanceModal" tabindex="-1" aria-labelledby="updateBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateBalanceModalLabel">Update Balance Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateBalanceForm" method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="modal_payment_id">
                    <input type="hidden" name="update_balance" value="1">
                    
                    <div class="form-group mb-3">
                        <label for="modal_balance_payment" class="form-label">Balance Payment (₹)</label>
                        <input type="number" class="form-control" id="modal_balance_payment" name="balance_payment" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="modal_balance_payment_date" class="form-label">Balance Payment Due Date</label>
                        <input type="text" class="form-control" id="modal_balance_payment_date" 
                               name="balance_payment_date" placeholder="Select due date (optional)">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="modal_qr_card_qty" class="form-label">QR Card Quantity</label>
                        <input type="number" class="form-control" id="modal_qr_card_qty" name="qr_card_qty" 
                               min="0" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="modal_qr_card_price" class="form-label">QR Card Price (₹)</label>
                        <input type="number" class="form-control" id="modal_qr_card_price" name="qr_card_price" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="modal_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Add jQuery before Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        $(document).ready(function() {

            var updateBalanceModal = new bootstrap.Modal(document.getElementById('updateBalanceModal'));
            // Main form datepicker
            flatpickr("#balance_payment_date", {
                dateFormat: "Y-m-d",
                minDate: "today",
                allowInput: true
            });

            // Initialize modal datepicker (will be configured when modal opens)
            let modalDatepicker = null;

            // Handle update balance button clicks
            $(document).on('click', '.update-balance-btn', function() {
                const paymentId = $(this).data('id');
                const balance = $(this).data('balance');
                const date = $(this).data('date');
                const qrQty = $(this).data('qrqty');
                const qrPrice = $(this).data('qrprice');
                const notes = $(this).data('notes');
                
                $('#modal_payment_id').val(paymentId);
                $('#modal_balance_payment').val(balance);
                $('#modal_qr_card_qty').val(qrQty);
                $('#modal_qr_card_price').val(qrPrice);
                $('#modal_notes').val(notes);
                
                // Initialize or reinitialize the datepicker
                if (modalDatepicker) {
                    modalDatepicker.destroy();
                }
                
                $('#modal_balance_payment_date').val(date || '');
                
                modalDatepicker = flatpickr("#modal_balance_payment_date", {
                    dateFormat: "Y-m-d",
                    minDate: "today",
                    allowInput: true,
                    defaultDate: date || ''
                });
                
                $('#updateBalanceModal').modal('show');
            });

            // Auto-calculate balance fields
            const packagePriceInput = document.getElementById('package_price');
            const advancePaymentInput = document.getElementById('advance_payment');
            const balancePaymentInput = document.getElementById('balance_payment');
            const balancePaymentDateInput = document.getElementById('balance_payment_date');
            
            function updateBalanceFields() {
                const packagePrice = parseFloat(packagePriceInput.value) || 0;
                const advancePayment = parseFloat(advancePaymentInput.value) || 0;
                const balance = packagePrice - advancePayment;
                
                if (packagePrice === advancePayment) {
                    // Full payment - disable balance fields and clear values
                    balancePaymentInput.value = '';
                    balancePaymentInput.disabled = true;
                    if (balancePaymentDateInput._flatpickr) {
                        balancePaymentDateInput._flatpickr.clear();
                    } else {
                        balancePaymentDateInput.value = '';
                    }
                    balancePaymentDateInput.disabled = true;
                } else {
                    // Partial payment - enable fields and calculate balance
                    balancePaymentInput.disabled = false;
                    balancePaymentDateInput.disabled = false;
                    
                    if (balance > 0) {
                        balancePaymentInput.value = balance.toFixed(2);
                        
                        // Auto-set due date to 30 days from now if not already set
                        if (!balancePaymentDateInput.value && balancePaymentDateInput._flatpickr) {
                            const today = new Date();
                            const dueDate = new Date(today.setDate(today.getDate() + 30));
                            balancePaymentDateInput._flatpickr.setDate(dueDate);
                        }
                    } else {
                        balancePaymentInput.value = '';
                        if (balancePaymentDateInput._flatpickr) {
                            balancePaymentDateInput._flatpickr.clear();
                        } else {
                            balancePaymentDateInput.value = '';
                        }
                    }
                }
            }
            
            packagePriceInput.addEventListener('change', updateBalanceFields);
            advancePaymentInput.addEventListener('change', updateBalanceFields);
            
            // Initialize the fields on page load
            updateBalanceFields();
        });
    </script>
</body>
</html>