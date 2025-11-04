<?php
// upcoming-bookings.php
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
$user_name = $_SESSION['name'] ?? '';
$role = $_SESSION['role'] ?? 'user';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'bookings_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? 'all';
$filter_room_type = $_GET['room_type'] ?? 'all';

// Build WHERE conditions
$where_conditions = ["b.check_in_date >= ?"];
$params = [date('Y-m-d')];
$param_types = "s";

// Status filter
if ($filter_status !== 'all') {
    $where_conditions[] = "b.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

// Room type filter
if ($filter_room_type !== 'all') {
    $where_conditions[] = "r.room_type_id = ?";
    $params[] = $filter_room_type;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get upcoming bookings
$bookings_sql = "SELECT 
                    b.id, b.booking_reference, b.guest_name, b.guest_phone, b.guest_email,
                    r.room_number, r.floor,
                    rt.name as room_type, rt.base_rate,
                    b.check_in_date, b.check_out_date, b.total_nights,
                    b.total_amount, b.advance_paid, b.payment_status, b.status,
                    b.special_requests, b.created_at
                 FROM bookings_$user_id b
                 LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                 LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                 WHERE $where_clause
                 ORDER BY b.check_in_date ASC, b.created_at DESC";

$stmt = $conn->prepare($bookings_sql);

if ($stmt) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $bookings_result = $stmt->get_result();
    $upcoming_bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $upcoming_bookings = [];
    error_log("Error preparing statement: " . $conn->error);
}

// Get room types for filter
$room_types_sql = "SELECT id, name FROM room_types_$user_id WHERE is_active = 1 ORDER BY name";
$room_types_result = $conn->query($room_types_sql);
$room_types = $room_types_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_upcoming,
                SUM(CASE WHEN b.check_in_date = ? THEN 1 ELSE 0 END) as today_checkins,
                SUM(CASE WHEN b.status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN b.status = 'checked_in' THEN 1 ELSE 0 END) as checked_in
              FROM bookings_$user_id b
              WHERE b.check_in_date >= ?";

$stmt = $conn->prepare($stats_sql);
$today = date('Y-m-d');
$stmt->bind_param("ss", $today, $today);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Upcoming Bookings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .booking-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .booking-card.checked_in {
            border-left-color: #28a745;
        }
        
        .booking-card.reserved {
            border-left-color: #ffc107;
        }
        
        .booking-card.cancelled {
            border-left-color: #dc3545;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-reserved { background-color: #ffc107; color: #000; }
        .status-checked_in { background-color: #28a745; color: white; }
        .status-checked_out { background-color: #6c757d; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
        .status-no_show { background-color: #fd7e14; color: white; }
        
        .payment-status-paid { background-color: #28a745; color: white; }
        .payment-status-pending { background-color: #ffc107; color: #000; }
        .payment-status-partial { background-color: #17a2b8; color: white; }
        .payment-status-refunded { background-color: #6c757d; color: white; }
        
        .guest-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .action-buttons .btn {
            margin: 2px;
            font-size: 12px;
        }
        
        .stats-card {
            text-align: center;
            padding: 15px;
        }
        
        .stats-number {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        
        .stats-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .action-buttons .btn {
                font-size: 11px;
                padding: 4px 8px;
            }
            
            .booking-card .card-body {
                padding: 10px;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
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
                                    <li class="breadcrumb-item active">Upcoming Bookings</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Upcoming Bookings</h4>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <span class="stats-number"><?php echo $stats['total_upcoming'] ?? 0; ?></span>
                                <span class="stats-label">Total Upcoming</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <span class="stats-number"><?php echo $stats['today_checkins'] ?? 0; ?></span>
                                <span class="stats-label">Today Check-ins</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-warning text-dark">
                            <div class="card-body">
                                <span class="stats-number"><?php echo $stats['reserved'] ?? 0; ?></span>
                                <span class="stats-label">Reserved</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <span class="stats-number"><?php echo $stats['checked_in'] ?? 0; ?></span>
                                <span class="stats-label">Checked In</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="filter-section">
                            <form method="GET" action="upcoming-bookings.php" class="row g-3">
                                <div class="col-md-3">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date" 
                                           value="<?php echo htmlspecialchars($filter_date); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="reserved" <?php echo $filter_status === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                        <option value="checked_in" <?php echo $filter_status === 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="room_type" class="form-label">Room Type</label>
                                    <select class="form-select" id="room_type" name="room_type">
                                        <option value="all" <?php echo $filter_room_type === 'all' ? 'selected' : ''; ?>>All Room Types</option>
                                        <?php foreach ($room_types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" 
                                                    <?php echo $filter_room_type == $type['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                        <a href="upcoming-bookings.php" class="btn btn-secondary">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bookings List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Upcoming Bookings List</h4>
                                <div class="btn-group">
                                    <a href="add-booking.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i> New Booking
                                    </a>
                                    <a href="bookings.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-list"></i> All Bookings
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($upcoming_bookings)): ?>
                                    <div class="row">
                                        <?php foreach ($upcoming_bookings as $booking): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card booking-card <?php echo $booking['status']; ?>">
                                                    <div class="card-body">
                                                        <!-- Header -->
                                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                                            <div>
                                                                <h6 class="card-title mb-1">
                                                                    #<?php echo htmlspecialchars($booking['booking_reference']); ?>
                                                                </h6>
                                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                                </span>
                                                            </div>
                                                            <span class="payment-status payment-status-<?php echo $booking['payment_status']; ?>">
                                                                <?php echo ucfirst($booking['payment_status']); ?>
                                                            </span>
                                                        </div>

                                                        <!-- Guest Information -->
                                                        <div class="guest-info">
                                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong><br>
                                                            <small class="text-muted">
                                                                📞 <?php echo htmlspecialchars($booking['guest_phone']); ?><br>
                                                                <?php if ($booking['guest_email']): ?>
                                                                    ✉️ <?php echo htmlspecialchars($booking['guest_email']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>

                                                        <!-- Room Information -->
                                                        <div class="mb-2">
                                                            <strong>Room <?php echo htmlspecialchars($booking['room_number']); ?></strong>
                                                            <?php if ($booking['floor']): ?>
                                                                <small class="text-muted">(Floor <?php echo htmlspecialchars($booking['floor']); ?>)</small>
                                                            <?php endif; ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($booking['room_type']); ?></small>
                                                        </div>

                                                        <!-- Dates -->
                                                        <div class="row mb-2">
                                                            <div class="col-6">
                                                                <small><strong>Check-in:</strong></small><br>
                                                                <small><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></small>
                                                            </div>
                                                            <div class="col-6">
                                                                <small><strong>Check-out:</strong></small><br>
                                                                <small><?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?></small>
                                                            </div>
                                                        </div>

                                                        <!-- Booking Details -->
                                                        <div class="row mb-3">
                                                            <div class="col-6">
                                                                <small><strong>Nights:</strong> <?php echo $booking['total_nights']; ?></small>
                                                            </div>
                                                            <div class="col-6 text-end">
                                                                <small><strong>Amount:</strong> ₹<?php echo number_format($booking['total_amount']); ?></small>
                                                            </div>
                                                        </div>

                                                        <!-- Special Requests -->
                                                        <?php if ($booking['special_requests']): ?>
                                                            <div class="alert alert-info py-2 mb-3">
                                                                <small><strong>Special Requests:</strong><br>
                                                                <?php echo htmlspecialchars($booking['special_requests']); ?></small>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Action Buttons -->
                                                        <div class="action-buttons text-center">
                                                            <?php if ($booking['status'] === 'reserved'): ?>
                                                                <button class="btn btn-success btn-sm checkin-btn" 
                                                                        data-booking-id="<?php echo $booking['id']; ?>"
                                                                        data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference']); ?>">
                                                                    <i class="fas fa-sign-in-alt"></i> Check In
                                                                </button>
                                                            <?php elseif ($booking['status'] === 'checked_in'): ?>
                                                                <button class="btn btn-warning btn-sm checkout-btn" 
                                                                        data-booking-id="<?php echo $booking['id']; ?>"
                                                                        data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference']); ?>">
                                                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <a href="view-booking.php?id=<?php echo $booking['id']; ?>" 
                                                               class="btn btn-info btn-sm">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            
                                                            <button class="btn btn-primary btn-sm send-reminder-btn"
                                                                    data-booking-id="<?php echo $booking['id']; ?>"
                                                                    data-guest-phone="<?php echo htmlspecialchars($booking['guest_phone']); ?>"
                                                                    data-guest-name="<?php echo htmlspecialchars($booking['guest_name']); ?>">
                                                                <i class="fas fa-bell"></i> Reminder
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <h4>No Upcoming Bookings</h4>
                                        <p>There are no upcoming bookings matching your criteria.</p>
                                        <a href="add-booking.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Create New Booking
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Check-in Modal -->
    <div class="modal fade" id="checkinModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Check In Guest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Check in guest for booking <strong id="checkinBookingRef"></strong>?</p>
                    <div class="mb-3">
                        <label for="actualCheckin" class="form-label">Actual Check-in Time</label>
                        <input type="datetime-local" class="form-control" id="actualCheckin" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmCheckin">Check In</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Check-out Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Check Out Guest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Check out guest for booking <strong id="checkoutBookingRef"></strong>?</p>
                    <div class="mb-3">
                        <label for="actualCheckout" class="form-label">Actual Check-out Time</label>
                        <input type="datetime-local" class="form-control" id="actualCheckout" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="finalAmount" class="form-label">Final Amount (if different)</label>
                        <input type="number" class="form-control" id="finalAmount" step="0.01" placeholder="Leave empty to use booked amount">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmCheckout">Check Out</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    $(document).ready(function() {
        let currentBookingId = null;

        // Check-in functionality
        $('.checkin-btn').click(function() {
            currentBookingId = $(this).data('booking-id');
            const bookingRef = $(this).data('booking-ref');
            
            $('#checkinBookingRef').text(bookingRef);
            $('#checkinModal').modal('show');
        });

        $('#confirmCheckin').click(function() {
            if (!currentBookingId) return;

            const actualCheckin = $('#actualCheckin').val();
            
            $.post('update_booking_status.php', {
                booking_id: currentBookingId,
                status: 'checked_in',
                actual_checkin: actualCheckin,
                action: 'checkin'
            }, function(response) {
                if (response.success) {
                    showToast('Guest checked in successfully!', 'success');
                    $('#checkinModal').modal('hide');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + response.message, 'danger');
                }
            }).fail(function() {
                showToast('Network error occurred', 'danger');
            });
        });

        // Check-out functionality
        $('.checkout-btn').click(function() {
            currentBookingId = $(this).data('booking-id');
            const bookingRef = $(this).data('booking-ref');
            
            $('#checkoutBookingRef').text(bookingRef);
            $('#checkoutModal').modal('show');
        });

        $('#confirmCheckout').click(function() {
            if (!currentBookingId) return;

            const actualCheckout = $('#actualCheckout').val();
            const finalAmount = $('#finalAmount').val();

            $.post('update_booking_status.php', {
                booking_id: currentBookingId,
                status: 'checked_out',
                actual_checkout: actualCheckout,
                final_amount: finalAmount,
                action: 'checkout'
            }, function(response) {
                if (response.success) {
                    showToast('Guest checked out successfully!', 'success');
                    $('#checkoutModal').modal('hide');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + response.message, 'danger');
                }
            }).fail(function() {
                showToast('Network error occurred', 'danger');
            });
        });

        // Send reminder functionality
        $('.send-reminder-btn').click(function() {
            const bookingId = $(this).data('booking-id');
            const guestPhone = $(this).data('guest-phone');
            const guestName = $(this).data('guest-name');

            $.post('send_booking_reminder.php', {
                booking_id: bookingId,
                guest_phone: guestPhone,
                guest_name: guestName
            }, function(response) {
                if (response.success) {
                    showToast('Reminder sent successfully!', 'success');
                } else {
                    showToast('Error sending reminder: ' + response.message, 'danger');
                }
            }).fail(function() {
                showToast('Network error occurred', 'danger');
            });
        });

        // Toast notification function
        function showToast(message, type) {
            const toast = $(`<div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`);
            
            $('body').append(toast);
            
            setTimeout(() => {
                toast.alert('close');
            }, 5000);
        }

        // Auto-refresh every 2 minutes
        setInterval(() => {
            $.get('check_booking_updates.php', function(data) {
                if (data.updated) {
                    showToast('Bookings updated', 'info');
                    location.reload();
                }
            });
        }, 120000);
    });
    </script>
</body>
</html>