<?php
// today-checkouts.php
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

// Get user details
$user_sql = "SELECT name, role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role);
$user_stmt->fetch();
$user_stmt->close();

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Get today's date
$today = date('Y-m-d');

// Handle checkout action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_booking_id'])) {
    $booking_id = intval($_POST['checkout_booking_id']);
    $final_amount = floatval($_POST['final_amount']);
    $payment_method = $_POST['payment_method'];
    $additional_charges = floatval($_POST['additional_charges'] ?? 0);
    $additional_notes = $_POST['additional_notes'] ?? '';
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Update booking status to checked_out
        $update_booking_sql = "UPDATE bookings_$user_id 
                              SET status = 'checked_out', 
                                  total_amount = ?,
                                  payment_status = 'paid',
                                  additional_charges = ?,
                                  additional_notes = ?,
                                  updated_at = NOW()
                              WHERE id = ? AND status = 'checked_in'";
        $stmt = $conn->prepare($update_booking_sql);
        $stmt->bind_param("ddsi", $final_amount, $additional_charges, $additional_notes, $booking_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Update room status to available
            $get_room_sql = "SELECT room_id FROM bookings_$user_id WHERE id = ?";
            $stmt2 = $conn->prepare($get_room_sql);
            $stmt2->bind_param("i", $booking_id);
            $stmt2->execute();
            $stmt2->bind_result($room_id);
            $stmt2->fetch();
            $stmt2->close();
            
            if ($room_id) {
                $update_room_sql = "UPDATE rooms_$user_id SET status = 'available', updated_at = NOW() WHERE id = ?";
                $stmt3 = $conn->prepare($update_room_sql);
                $stmt3->bind_param("i", $room_id);
                $stmt3->execute();
                $stmt3->close();
            }
            
            $conn->commit();
            $success_message = "Check-out completed successfully!";
        } else {
            throw new Exception("Failed to update booking status");
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error during check-out: " . $e->getMessage();
    }
}

// Get today's checkouts
$today_checkouts_sql = "SELECT 
                        b.id, b.booking_reference, b.guest_name, b.guest_phone,
                        r.room_number, rt.name as room_type,
                        b.check_in_date, b.check_out_date,
                        b.total_nights, b.total_amount,
                        b.adults, b.children,
                        b.special_requests
                      FROM bookings_$user_id b
                      LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                      LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                      WHERE DATE(b.check_out_date) = ? 
                      AND b.status = 'checked_in'
                      ORDER BY b.check_out_date ASC, r.room_number ASC";
                      
$stmt = $conn->prepare($today_checkouts_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_checkouts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get completed checkouts today (for history)
$completed_checkouts_sql = "SELECT 
                            b.id, b.booking_reference, b.guest_name,
                            r.room_number, b.check_out_date,
                            b.total_amount, b.payment_method,
                            b.additional_charges, b.additional_notes
                          FROM bookings_$user_id b
                          LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                          WHERE DATE(b.updated_at) = ? 
                          AND b.status = 'checked_out'
                          ORDER BY b.updated_at DESC
                          LIMIT 20";
                          
$stmt = $conn->prepare($completed_checkouts_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$completed_checkouts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Today's Check-outs - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .checkout-card {
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
        }
        .checkout-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .checkout-completed {
            border-left: 4px solid #28a745;
            background-color: #f8fff9;
        }
        .urgent-checkout {
            border-left: 4px solid #dc3545;
            animation: pulseWarning 2s infinite;
        }
        .room-badge {
            font-size: 12px;
            padding: 4px 8px;
        }
        @keyframes pulseWarning {
            0% { border-left-color: #dc3545; }
            50% { border-left-color: #ffc107; }
            100% { border-left-color: #dc3545; }
        }
        .guest-info {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        .amount-display {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'room_management_menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="room-dashboard.php">Room Management</a></li>
                                    <li class="breadcrumb-item active">Today's Check-outs</li>
                                </ol>
                            </div>
                            <h4 class="page-title">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                Today's Check-outs - <?php echo date('F j, Y'); ?>
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="fas fa-bed widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0">Pending Check-outs</h5>
                                <h3 class="mt-3 mb-3"><?php echo count($today_checkouts); ?></h3>
                                <p class="mb-0 text-muted">
                                    <span class="text-success me-2"><i class="fas fa-clock"></i> Today</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="fas fa-check-circle widget-icon text-success"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0">Completed Today</h5>
                                <h3 class="mt-3 mb-3"><?php echo count($completed_checkouts); ?></h3>
                                <p class="mb-0 text-muted">
                                    <span class="text-success me-2"><i class="fas fa-calendar-check"></i> Processed</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="fas fa-rupee-sign widget-icon text-primary"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0">Today's Revenue</h5>
                                <h3 class="mt-3 mb-3">
                                    ₹<?php 
                                    $total_revenue = 0;
                                    foreach ($completed_checkouts as $checkout) {
                                        $total_revenue += $checkout['total_amount'];
                                    }
                                    echo number_format($total_revenue);
                                    ?>
                                </h3>
                                <p class="mb-0 text-muted">
                                    <span class="text-success me-2"><i class="fas fa-chart-line"></i> Collected</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Pending Check-outs -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="fas fa-list-ul me-2"></i>
                                    Pending Check-outs
                                    <span class="badge bg-warning ms-2"><?php echo count($today_checkouts); ?></span>
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($today_checkouts)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-centered table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Room</th>
                                                    <th>Guest</th>
                                                    <th>Check-in/out</th>
                                                    <th>Nights</th>
                                                    <th>Amount</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($today_checkouts as $checkout): 
                                                    $is_urgent = strtotime($checkout['check_out_date']) < strtotime('+3 hours');
                                                ?>
                                                    <tr class="<?php echo $is_urgent ? 'table-warning' : ''; ?>">
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="me-3">
                                                                    <span class="badge room-badge bg-primary"><?php echo $checkout['room_number']; ?></span>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <small class="text-muted"><?php echo $checkout['room_type']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($checkout['guest_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $checkout['guest_phone']; ?></small>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <strong>In:</strong> <?php echo date('M j', strtotime($checkout['check_in_date'])); ?><br>
                                                                <strong>Out:</strong> <?php echo date('M j', strtotime($checkout['check_out_date'])); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $checkout['total_nights']; ?> nights</span>
                                                        </td>
                                                        <td>
                                                            <strong class="amount-display">₹<?php echo number_format($checkout['total_amount']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-success btn-sm" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#checkoutModal"
                                                                    data-booking-id="<?php echo $checkout['id']; ?>"
                                                                    data-guest-name="<?php echo htmlspecialchars($checkout['guest_name']); ?>"
                                                                    data-room-number="<?php echo $checkout['room_number']; ?>"
                                                                    data-total-amount="<?php echo $checkout['total_amount']; ?>">
                                                                <i class="fas fa-sign-out-alt me-1"></i> Check-out
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h5>No Pending Check-outs Today</h5>
                                        <p class="text-muted">All check-outs for today have been processed.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Completed Check-outs & Quick Actions -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="fas fa-bolt me-2"></i>
                                    Quick Actions
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="quick-checkout.php" class="btn btn-primary">
                                        <i class="fas fa-fast-forward me-2"></i>Quick Check-out
                                    </a>
                                    <a href="bookings.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list me-2"></i>All Bookings
                                    </a>
                                    <a href="manage-rooms.php" class="btn btn-outline-info">
                                        <i class="fas fa-bed me-2"></i>Manage Rooms
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Completed Check-outs -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="fas fa-history me-2"></i>
                                    Recently Completed
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($completed_checkouts)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($completed_checkouts as $completed): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($completed['guest_name']); ?></h6>
                                                        <p class="mb-1 text-muted">
                                                            Room <?php echo $completed['room_number']; ?> 
                                                            • ₹<?php echo number_format($completed['total_amount']); ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <?php echo date('h:i A', strtotime($completed['check_out_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <span class="badge bg-success">Completed</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No completed check-outs today</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Check-out Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="checkoutModalLabel">
                            <i class="fas fa-sign-out-alt me-2"></i>Process Check-out
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="guest-info">
                                    <h6>Guest Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> <span id="modalGuestName"></span></p>
                                    <p class="mb-1"><strong>Room:</strong> <span id="modalRoomNumber"></span></p>
                                    <p class="mb-0"><strong>Original Amount:</strong> ₹<span id="modalTotalAmount"></span></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="final_amount" class="form-label">Final Amount ₹</label>
                                    <input type="number" step="0.01" class="form-control" id="final_amount" name="final_amount" required>
                                </div>
                                <div class="mb-3">
                                    <label for="additional_charges" class="form-label">Additional Charges ₹</label>
                                    <input type="number" step="0.01" class="form-control" id="additional_charges" name="additional_charges" value="0">
                                </div>
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="upi">UPI</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="wallet">Wallet</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="additional_notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" placeholder="Any special notes..."></textarea>
                        </div>
                        <input type="hidden" id="checkout_booking_id" name="checkout_booking_id">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i> Complete Check-out
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    $(document).ready(function() {
        // Check-out Modal Handler
        $('#checkoutModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var bookingId = button.data('booking-id');
            var guestName = button.data('guest-name');
            var roomNumber = button.data('room-number');
            var totalAmount = button.data('total-amount');
            
            var modal = $(this);
            modal.find('#modalGuestName').text(guestName);
            modal.find('#modalRoomNumber').text(roomNumber);
            modal.find('#modalTotalAmount').text(totalAmount);
            modal.find('#final_amount').val(totalAmount);
            modal.find('#checkout_booking_id').val(bookingId);
        });

        // Auto-calculate final amount when additional charges change
        $('#additional_charges').on('input', function() {
            var originalAmount = parseFloat($('#modalTotalAmount').text());
            var additionalCharges = parseFloat($(this).val()) || 0;
            $('#final_amount').val(originalAmount + additionalCharges);
        });

        // Auto-refresh page every 2 minutes to get updated data
        setInterval(function() {
            location.reload();
        }, 120000);

        // Print receipt function (to be implemented)
        function printReceipt(bookingId) {
            window.open('print-receipt.php?booking_id=' + bookingId, '_blank');
        }
    });
    </script>

</body>
</html>