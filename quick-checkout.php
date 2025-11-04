<?php
// quick-checkout.php
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

// Handle checkout process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $booking_id = $_POST['booking_id'];
    $final_amount = $_POST['final_amount'];
    $payment_method = $_POST['payment_method'];
    $additional_charges = $_POST['additional_charges'] ?? 0;
    $discount_amount = $_POST['discount_amount'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    try {
        $conn->begin_transaction();

        // Get booking details
        $booking_sql = "SELECT b.*, r.room_number, r.room_type_id 
                       FROM bookings_$user_id b 
                       LEFT JOIN rooms_$user_id r ON b.room_id = r.id 
                       WHERE b.id = ? AND b.status = 'checked_in'";
        $booking_stmt = $conn->prepare($booking_sql);
        $booking_stmt->bind_param("i", $booking_id);
        $booking_stmt->execute();
        $booking_result = $booking_stmt->get_result();
        
        if ($booking_result->num_rows === 0) {
            throw new Exception("Booking not found or already checked out");
        }
        
        $booking = $booking_result->fetch_assoc();
        $booking_stmt->close();

        // Update booking status to checked_out
        $update_booking_sql = "UPDATE bookings_$user_id 
                              SET status = 'checked_out', 
                                  total_amount = ?,
                                  additional_charges = ?,
                                  discount_amount = ?,
                                  payment_method = ?,
                                  payment_status = 'paid',
                                  checkout_notes = ?,
                                  actual_check_out = NOW()
                              WHERE id = ?";
        $update_stmt = $conn->prepare($update_booking_sql);
        $update_stmt->bind_param("dddess", $final_amount, $additional_charges, $discount_amount, $payment_method, $notes, $booking_id);
        $update_stmt->execute();
        $update_stmt->close();

        // Update room status to available
        $update_room_sql = "UPDATE rooms_$user_id SET status = 'available' WHERE id = ?";
        $room_stmt = $conn->prepare($update_room_sql);
        $room_stmt->bind_param("i", $booking['room_id']);
        $room_stmt->execute();
        $room_stmt->close();

        // Create payment record
        $payment_sql = "INSERT INTO payments_$user_id 
                       (booking_id, amount, payment_method, transaction_id, status, notes)
                       VALUES (?, ?, ?, ?, 'completed', ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $transaction_id = 'TXN' . time() . $booking_id;
        $payment_stmt->bind_param("idsss", $booking_id, $final_amount, $payment_method, $transaction_id, $notes);
        $payment_stmt->execute();
        $payment_stmt->close();

        $conn->commit();
        $success_message = "Checkout completed successfully! Room " . $booking['room_number'] . " is now available.";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Checkout failed: " . $e->getMessage();
    }
}

// Get today's checkouts and active bookings
$today_checkouts_sql = "SELECT b.*, r.room_number, rt.name as room_type, 
                       g.phone as guest_phone, g.email as guest_email
                       FROM bookings_$user_id b
                       LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                       LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                       LEFT JOIN guests_$user_id g ON b.guest_phone = g.phone
                       WHERE b.status = 'checked_in' 
                       AND DATE(b.check_out_date) <= DATE(NOW())
                       ORDER BY b.check_out_date ASC";
$today_checkouts_result = $conn->query($today_checkouts_sql);
$active_bookings = $today_checkouts_result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Quick Checkout - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .checkout-card {
            transition: all 0.3s ease;
            border-left: 4px solid #ffc107;
        }
        .checkout-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .checkout-card.urgent {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .checkout-card.completed {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .room-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .payment-methods {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .payment-method-btn {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        .amount-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .guest-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Page Header -->
                        <div class="page-title-box">
                            <h4 class="page-title">
                                <iconify-icon icon="mdi:logout" class="me-2"></iconify-icon>
                                Quick Checkout
                            </h4>
                            <p class="text-muted mb-4">Process guest checkouts and manage room availability</p>
                        </div>

                        <!-- Notifications -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Active Bookings for Checkout -->
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <iconify-icon icon="mdi:calendar-clock" class="me-2"></iconify-icon>
                                            Active Bookings Ready for Checkout
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($active_bookings)): ?>
                                            <div class="text-center py-4">
                                                <iconify-icon icon="mdi:check-circle-outline" style="font-size: 48px; color: #28a745;"></iconify-icon>
                                                <h5 class="mt-3">No Active Bookings</h5>
                                                <p class="text-muted">All guests are checked out or no active bookings found.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Room</th>
                                                            <th>Guest</th>
                                                            <th>Check-out</th>
                                                            <th>Amount</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($active_bookings as $booking): ?>
                                                            <?php
                                                            $checkout_date = new DateTime($booking['check_out_date']);
                                                            $today = new DateTime();
                                                            $is_urgent = $checkout_date <= $today;
                                                            ?>
                                                            <tr class="<?php echo $is_urgent ? 'table-warning' : ''; ?>">
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($booking['room_number']); ?></strong>
                                                                    <br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['room_type']); ?></small>
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                                                    <br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?>
                                                                    <br>
                                                                    <small class="<?php echo $is_urgent ? 'text-danger' : 'text-muted'; ?>">
                                                                        <?php echo $is_urgent ? 'Due Today' : 'Upcoming'; ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <strong>₹<?php echo number_format($booking['total_amount']); ?></strong>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-primary btn-sm" 
                                                                            onclick="openCheckoutModal(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                                                        Checkout
                                                                    </button>
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

                            <!-- Quick Stats Sidebar -->
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Today's Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span>Total Checkouts Due:</span>
                                            <strong class="text-primary"><?php echo count($active_bookings); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span>Urgent Checkouts:</span>
                                            <strong class="text-danger">
                                                <?php
                                                $urgent_count = 0;
                                                foreach ($active_bookings as $booking) {
                                                    $checkout_date = new DateTime($booking['check_out_date']);
                                                    $today = new DateTime();
                                                    if ($checkout_date <= $today) {
                                                        $urgent_count++;
                                                    }
                                                }
                                                echo $urgent_count;
                                                ?>
                                            </strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Expected Revenue:</span>
                                            <strong class="text-success">
                                                ₹<?php
                                                $total_revenue = 0;
                                                foreach ($active_bookings as $booking) {
                                                    $total_revenue += $booking['total_amount'];
                                                }
                                                echo number_format($total_revenue);
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <a href="bookings.php" class="btn btn-outline-primary">
                                                <iconify-icon icon="mdi:bookmark-multiple" class="me-2"></iconify-icon>
                                                View All Bookings
                                            </a>
                                            <a href="manage-rooms.php" class="btn btn-outline-info">
                                                <iconify-icon icon="mdi:bed" class="me-2"></iconify-icon>
                                                Manage Rooms
                                            </a>
                                            <a href="room-dashboard.php" class="btn btn-outline-secondary">
                                                <iconify-icon icon="mdi:view-dashboard" class="me-2"></iconify-icon>
                                                Room Dashboard
                                            </a>
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

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="checkoutForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Process Checkout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="checkoutDetails">
                            <!-- Dynamic content will be loaded here -->
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Additional Charges</label>
                                    <input type="number" class="form-control" name="additional_charges" value="0" min="0" step="0.01" id="additionalCharges">
                                    <small class="text-muted">Extra charges (damages, amenities, etc.)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Discount Amount</label>
                                    <input type="number" class="form-control" name="discount_amount" value="0" min="0" step="0.01" id="discountAmount">
                                    <small class="text-muted">Any discounts applied</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Final Amount</label>
                            <div class="amount-display" id="finalAmountDisplay">₹0.00</div>
                            <input type="hidden" name="final_amount" id="finalAmount">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <div class="payment-methods">
                                <label class="payment-method-btn btn btn-outline-primary">
                                    <input type="radio" name="payment_method" value="cash" checked> Cash
                                </label>
                                <label class="payment-method-btn btn btn-outline-primary">
                                    <input type="radio" name="payment_method" value="card"> Card
                                </label>
                                <label class="payment-method-btn btn btn-outline-primary">
                                    <input type="radio" name="payment_method" value="upi"> UPI
                                </label>
                                <label class="payment-method-btn btn btn-outline-primary">
                                    <input type="radio" name="payment_method" value="bank_transfer"> Bank Transfer
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any special notes about this checkout..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="checkout" class="btn btn-success">
                            <iconify-icon icon="mdi:check-circle" class="me-2"></iconify-icon>
                            Complete Checkout
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    let currentBooking = null;

    function openCheckoutModal(booking) {
        currentBooking = booking;
        
        // Populate modal content
        const detailsHtml = `
            <div class="guest-info">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Guest Information</h6>
                        <p class="mb-1"><strong>Name:</strong> ${booking.guest_name}</p>
                        <p class="mb-1"><strong>Phone:</strong> ${booking.guest_phone}</p>
                        ${booking.guest_email ? `<p class="mb-1"><strong>Email:</strong> ${booking.guest_email}</p>` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6>Booking Details</h6>
                        <p class="mb-1"><strong>Room:</strong> ${booking.room_number} (${booking.room_type})</p>
                        <p class="mb-1"><strong>Check-in:</strong> ${new Date(booking.check_in_date).toLocaleDateString()}</p>
                        <p class="mb-1"><strong>Check-out:</strong> ${new Date(booking.check_out_date).toLocaleDateString()}</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Original Amount</label>
                        <div class="form-control">₹${parseFloat(booking.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Nights Stayed</label>
                        <div class="form-control">${calculateNightsStayed(booking.check_in_date, booking.check_out_date)} nights</div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('checkoutDetails').innerHTML = detailsHtml;
        document.getElementById('finalAmountDisplay').textContent = '₹' + parseFloat(booking.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('finalAmount').value = booking.total_amount;
        
        // Add hidden input for booking ID
        if (!document.querySelector('input[name="booking_id"]')) {
            const bookingIdInput = document.createElement('input');
            bookingIdInput.type = 'hidden';
            bookingIdInput.name = 'booking_id';
            bookingIdInput.value = booking.id;
            document.getElementById('checkoutForm').appendChild(bookingIdInput);
        } else {
            document.querySelector('input[name="booking_id"]').value = booking.id;
        }
        
        // Show modal
        new bootstrap.Modal(document.getElementById('checkoutModal')).show();
    }

    function calculateNightsStayed(checkIn, checkOut) {
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        const timeDiff = checkOutDate.getTime() - checkInDate.getTime();
        return Math.ceil(timeDiff / (1000 * 3600 * 24));
    }

    // Calculate final amount when charges or discounts change
    document.addEventListener('DOMContentLoaded', function() {
        const additionalCharges = document.getElementById('additionalCharges');
        const discountAmount = document.getElementById('discountAmount');
        
        function updateFinalAmount() {
            if (!currentBooking) return;
            
            const baseAmount = parseFloat(currentBooking.total_amount);
            const additional = parseFloat(additionalCharges.value) || 0;
            const discount = parseFloat(discountAmount.value) || 0;
            
            const finalAmount = baseAmount + additional - discount;
            
            document.getElementById('finalAmountDisplay').textContent = '₹' + finalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('finalAmount').value = finalAmount;
        }
        
        additionalCharges.addEventListener('input', updateFinalAmount);
        discountAmount.addEventListener('input', updateFinalAmount);
        
        // Style payment method buttons
        document.querySelectorAll('.payment-method-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.payment-method-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.remove('btn-primary');
                    b.classList.add('btn-outline-primary');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                this.classList.add('btn-primary');
                this.classList.remove('btn-outline-primary');
                
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
    });

    // Form submission handling
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const finalAmount = parseFloat(document.getElementById('finalAmount').value);
        if (finalAmount < 0) {
            e.preventDefault();
            alert('Final amount cannot be negative. Please adjust charges and discounts.');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<iconify-icon icon="mdi:loading" class="me-2"></iconify-icon> Processing...';
    });
    </script>
</body>
</html>