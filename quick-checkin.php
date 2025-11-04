<?php
// quick-checkin.php
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
$success_message = '';
$error_message = '';

// Check if room tables exist, if not create them
require_once 'create_user_room_tables.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_phone = trim($_POST['guest_phone'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $room_id = intval($_POST['room_id'] ?? 0);
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $adults = intval($_POST['adults'] ?? 1);
    $children = intval($_POST['children'] ?? 0);
    $room_rate = floatval($_POST['room_rate'] ?? 0);
    $advance_paid = floatval($_POST['advance_paid'] ?? 0);
    $special_requests = trim($_POST['special_requests'] ?? '');
    
    // Validate required fields
    if (empty($guest_name) || empty($guest_phone) || empty($room_id) || empty($check_in_date) || empty($check_out_date)) {
        $error_message = "Please fill all required fields";
    } else {
        try {
            // Generate booking reference
            $booking_reference = 'BK' . date('Ymd') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            
            // Calculate total nights and amount
            $check_in = new DateTime($check_in_date);
            $check_out = new DateTime($check_out_date);
            $total_nights = $check_out->diff($check_in)->days;
            $subtotal = $room_rate * $total_nights;
            $total_amount = $subtotal; // You can add tax and other charges here
            
            // Start transaction
            $conn->begin_transaction();
            
            // Insert booking
            $booking_sql = "INSERT INTO bookings_$user_id (
                booking_reference, guest_name, guest_phone, guest_email, 
                room_id, check_in_date, check_out_date, adults, children,
                total_nights, room_rate, subtotal, total_amount, advance_paid,
                payment_status, status, special_requests
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'checked_in', ?)";
            
            $stmt = $conn->prepare($booking_sql);
            $stmt->bind_param(
                "ssssissiiidddds",
                $booking_reference, $guest_name, $guest_phone, $guest_email,
                $room_id, $check_in_date, $check_out_date, $adults, $children,
                $total_nights, $room_rate, $subtotal, $total_amount, $advance_paid,
                $special_requests
            );
            
            if ($stmt->execute()) {
                $booking_id = $stmt->insert_id;
                
                // Update room status to occupied
                $update_room_sql = "UPDATE rooms_$user_id SET status = 'occupied' WHERE id = ?";
                $update_stmt = $conn->prepare($update_room_sql);
                $update_stmt->bind_param("i", $room_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Add guest to guests table if not exists
                $guest_check_sql = "SELECT id FROM guests_$user_id WHERE phone = ?";
                $guest_stmt = $conn->prepare($guest_check_sql);
                $guest_stmt->bind_param("s", $guest_phone);
                $guest_stmt->execute();
                $guest_result = $guest_stmt->get_result();
                
                if ($guest_result->num_rows == 0) {
                    $insert_guest_sql = "INSERT INTO guests_$user_id (name, phone, email) VALUES (?, ?, ?)";
                    $insert_guest_stmt = $conn->prepare($insert_guest_sql);
                    $insert_guest_stmt->bind_param("sss", $guest_name, $guest_phone, $guest_email);
                    $insert_guest_stmt->execute();
                    $insert_guest_stmt->close();
                }
                $guest_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Check-in successful! Booking Reference: $booking_reference";
                
                // Clear form
                $_POST = [];
                
            } else {
                throw new Exception("Failed to create booking: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error during check-in: " . $e->getMessage();
        }
    }
}

// Get available rooms for dropdown
$available_rooms = [];
$rooms_sql = "SELECT r.id, r.room_number, rt.name as room_type, r.rate_per_night 
              FROM rooms_$user_id r 
              LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
              WHERE r.status = 'available' 
              ORDER BY r.room_number";
$rooms_result = $conn->query($rooms_sql);
if ($rooms_result) {
    $available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Quick Check-In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .guest-photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            border: 3px solid #dee2e6;
        }
        .room-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .room-card.selected {
            border: 3px solid #28a745;
            background: #f8fff9;
        }
        .amount-breakdown {
            background: #e9f7fe;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #17a2b8;
        }
        .quick-action-btn {
            margin: 5px;
        }
        @media (max-width: 768px) {
            .guest-photo-preview {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Page Header -->
                        <div class="page-title-box">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h4 class="page-title mb-0">
                                        <iconify-icon icon="mdi:login" class="me-2"></iconify-icon>
                                        Quick Check-In
                                    </h4>
                                    <nav aria-label="breadcrumb">
                                        <ol class="breadcrumb">
                                            <li class="breadcrumb-item"><a href="room-dashboard.php">Room Management</a></li>
                                            <li class="breadcrumb-item active">Quick Check-In</li>
                                        </ol>
                                    </nav>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="bookings.php" class="btn btn-secondary quick-action-btn">
                                        <iconify-icon icon="mdi:bookmark-multiple"></iconify-icon>
                                        View All Bookings
                                    </a>
                                    <a href="room-dashboard.php" class="btn btn-outline-primary quick-action-btn">
                                        <iconify-icon icon="mdi:arrow-left"></iconify-icon>
                                        Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <iconify-icon icon="mdi:check-circle" class="me-2"></iconify-icon>
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <iconify-icon icon="mdi:alert-circle" class="me-2"></iconify-icon>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <iconify-icon icon="mdi:account-arrow-right" class="me-2"></iconify-icon>
                                    Guest Check-In Form
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="checkinForm" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <!-- Guest Information -->
                                        <div class="col-md-6">
                                            <div class="form-section">
                                                <h6 class="border-bottom pb-2 mb-3">
                                                    <iconify-icon icon="mdi:account-details" class="me-2"></iconify-icon>
                                                    Guest Information
                                                </h6>
                                                
                                                <div class="row">
                                                    <div class="col-md-12 mb-3">
                                                        <label for="guest_name" class="form-label">Full Name *</label>
                                                        <input type="text" class="form-control" id="guest_name" name="guest_name" 
                                                               value="<?php echo $_POST['guest_name'] ?? ''; ?>" required>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="guest_phone" class="form-label">Phone Number *</label>
                                                        <input type="tel" class="form-control" id="guest_phone" name="guest_phone" 
                                                               value="<?php echo $_POST['guest_phone'] ?? ''; ?>" required>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="guest_email" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="guest_email" name="guest_email" 
                                                               value="<?php echo $_POST['guest_email'] ?? ''; ?>">
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="adults" class="form-label">Adults *</label>
                                                        <select class="form-control" id="adults" name="adults" required>
                                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                                <option value="<?php echo $i; ?>" <?php echo ($_POST['adults'] ?? 1) == $i ? 'selected' : ''; ?>>
                                                                    <?php echo $i; ?> Adult<?php echo $i > 1 ? 's' : ''; ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="children" class="form-label">Children</label>
                                                        <select class="form-control" id="children" name="children">
                                                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                                                <option value="<?php echo $i; ?>" <?php echo ($_POST['children'] ?? 0) == $i ? 'selected' : ''; ?>>
                                                                    <?php echo $i; ?> Child<?php echo $i > 1 ? 'ren' : ''; ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Booking Details -->
                                        <div class="col-md-6">
                                            <div class="form-section">
                                                <h6 class="border-bottom pb-2 mb-3">
                                                    <iconify-icon icon="mdi:calendar-check" class="me-2"></iconify-icon>
                                                    Booking Details
                                                </h6>
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="check_in_date" class="form-label">Check-In Date *</label>
                                                        <input type="date" class="form-control" id="check_in_date" name="check_in_date" 
                                                               value="<?php echo $_POST['check_in_date'] ?? date('Y-m-d'); ?>" required>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="check_out_date" class="form-label">Check-Out Date *</label>
                                                        <input type="date" class="form-control" id="check_out_date" name="check_out_date" 
                                                               value="<?php echo $_POST['check_out_date'] ?? date('Y-m-d', strtotime('+1 day')); ?>" required>
                                                    </div>
                                                    
                                                    <div class="col-12 mb-3">
                                                        <label for="room_id" class="form-label">Select Room *</label>
                                                        <select class="form-control" id="room_id" name="room_id" required>
                                                            <option value="">-- Select Available Room --</option>
                                                            <?php foreach ($available_rooms as $room): ?>
                                                                <option value="<?php echo $room['id']; ?>" 
                                                                        data-rate="<?php echo $room['rate_per_night']; ?>"
                                                                        <?php echo ($_POST['room_id'] ?? '') == $room['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo $room['room_number']; ?> - <?php echo $room['room_type']; ?> 
                                                                    (₹<?php echo number_format($room['rate_per_night']); ?>/night)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="room_rate" class="form-label">Room Rate (₹) *</label>
                                                        <input type="number" class="form-control" id="room_rate" name="room_rate" 
                                                               step="0.01" value="<?php echo $_POST['room_rate'] ?? ''; ?>" required readonly>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="advance_paid" class="form-label">Advance Paid (₹)</label>
                                                        <input type="number" class="form-control" id="advance_paid" name="advance_paid" 
                                                               step="0.01" value="<?php echo $_POST['advance_paid'] ?? '0'; ?>">
                                                    </div>
                                                    
                                                    <div class="col-12 mb-3">
                                                        <label for="special_requests" class="form-label">Special Requests</label>
                                                        <textarea class="form-control" id="special_requests" name="special_requests" 
                                                                  rows="3" placeholder="Any special requests or notes..."><?php echo $_POST['special_requests'] ?? ''; ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Amount Breakdown -->
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="amount-breakdown">
                                                <h6 class="mb-3">
                                                    <iconify-icon icon="mdi:calculator" class="me-2"></iconify-icon>
                                                    Amount Breakdown
                                                </h6>
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <strong>Total Nights:</strong>
                                                        <span id="total_nights_display">0</span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Room Rate:</strong>
                                                        ₹<span id="room_rate_display">0</span>/night
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Subtotal:</strong>
                                                        ₹<span id="subtotal_display">0</span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Total Amount:</strong>
                                                        ₹<span id="total_amount_display">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="row mt-4">
                                        <div class="col-12 text-center">
                                            <button type="submit" class="btn btn-success btn-lg px-5">
                                                <iconify-icon icon="mdi:check-circle-outline" class="me-2"></iconify-icon>
                                                Complete Check-In
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary btn-lg px-5 ms-2">
                                                <iconify-icon icon="mdi:refresh" class="me-2"></iconify-icon>
                                                Reset Form
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Available Rooms Preview -->
                        <?php if (!empty($available_rooms)): ?>
                        <div class="card mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <iconify-icon icon="mdi:bed-empty" class="me-2"></iconify-icon>
                                    Available Rooms (<?php echo count($available_rooms); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($available_rooms as $room): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card room-card" data-room-id="<?php echo $room['id']; ?>" data-room-rate="<?php echo $room['rate_per_night']; ?>">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title"><?php echo $room['room_number']; ?></h6>
                                                    <p class="card-text text-muted"><?php echo $room['room_type']; ?></p>
                                                    <p class="card-text">
                                                        <strong>₹<?php echo number_format($room['rate_per_night']); ?></strong> / night
                                                    </p>
                                                    <button type="button" class="btn btn-outline-primary btn-sm select-room-btn">
                                                        Select Room
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mt-4">
                            <iconify-icon icon="mdi:alert" class="me-2"></iconify-icon>
                            No available rooms found. Please add rooms or check room status.
                            <a href="manage-rooms.php" class="alert-link">Manage Rooms</a>
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
        // Room selection functionality
        $('.room-card').click(function() {
            $('.room-card').removeClass('selected');
            $(this).addClass('selected');
            
            const roomId = $(this).data('room-id');
            const roomRate = $(this).data('room-rate');
            
            $('#room_id').val(roomId);
            $('#room_rate').val(roomRate);
            
            calculateAmount();
        });

        $('.select-room-btn').click(function(e) {
            e.stopPropagation();
            $(this).closest('.room-card').click();
        });

        // Calculate amount when dates or room rate changes
        $('#check_in_date, #check_out_date, #room_rate').change(function() {
            calculateAmount();
        });

        // Auto-fill room rate when room is selected
        $('#room_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const roomRate = selectedOption.data('rate');
            
            if (roomRate) {
                $('#room_rate').val(roomRate);
                calculateAmount();
                
                // Highlight corresponding room card
                $('.room-card').removeClass('selected');
                $(`.room-card[data-room-id="${$(this).val()}"]`).addClass('selected');
            }
        });

        // Calculate amount breakdown
        function calculateAmount() {
            const checkInDate = new Date($('#check_in_date').val());
            const checkOutDate = new Date($('#check_out_date').val());
            const roomRate = parseFloat($('#room_rate').val()) || 0;
            
            if (checkInDate && checkOutDate && checkOutDate > checkInDate) {
                const timeDiff = checkOutDate.getTime() - checkInDate.getTime();
                const totalNights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                
                const subtotal = roomRate * totalNights;
                const totalAmount = subtotal; // Add tax if needed
                
                $('#total_nights_display').text(totalNights);
                $('#room_rate_display').text(roomRate.toLocaleString());
                $('#subtotal_display').text(subtotal.toLocaleString());
                $('#total_amount_display').text(totalAmount.toLocaleString());
            } else {
                $('#total_nights_display').text('0');
                $('#room_rate_display').text('0');
                $('#subtotal_display').text('0');
                $('#total_amount_display').text('0');
            }
        }

        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        $('#check_in_date').attr('min', today);
        $('#check_out_date').attr('min', today);

        // Auto-set checkout date to tomorrow if not set
        if (!$('#check_out_date').val()) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            $('#check_out_date').val(tomorrow.toISOString().split('T')[0]);
        }

        // Form validation
        $('#checkinForm').validate({
            rules: {
                guest_name: {
                    required: true,
                    minlength: 2
                },
                guest_phone: {
                    required: true,
                    minlength: 10
                },
                guest_email: {
                    email: true
                },
                room_id: {
                    required: true
                },
                check_in_date: {
                    required: true,
                    date: true
                },
                check_out_date: {
                    required: true,
                    date: true,
                    greaterThan: "#check_in_date"
                },
                room_rate: {
                    required: true,
                    min: 1
                }
            },
            messages: {
                guest_name: {
                    required: "Please enter guest name",
                    minlength: "Name must be at least 2 characters long"
                },
                guest_phone: {
                    required: "Please enter phone number",
                    minlength: "Phone number must be at least 10 digits"
                },
                guest_email: {
                    email: "Please enter a valid email address"
                },
                room_id: {
                    required: "Please select a room"
                },
                check_in_date: {
                    required: "Please select check-in date",
                    date: "Please enter a valid date"
                },
                check_out_date: {
                    required: "Please select check-out date",
                    date: "Please enter a valid date",
                    greaterThan: "Check-out date must be after check-in date"
                },
                room_rate: {
                    required: "Room rate is required",
                    min: "Room rate must be greater than 0"
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
            },
            submitHandler: function(form) {
                // Show loading state
                const submitBtn = $(form).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<iconify-icon icon="mdi:loading" class="spin me-2"></iconify-icon>Processing...').prop('disabled', true);
                
                // Submit form
                form.submit();
            }
        });

        // Custom validation method for date comparison
        $.validator.addMethod("greaterThan", function(value, element, param) {
            const startDate = new Date($(param).val());
            const endDate = new Date(value);
            return this.optional(element) || endDate > startDate;
        }, "Check-out date must be after check-in date");

        // Initialize amount calculation
        calculateAmount();

        // Android session protection
        if (typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            // Update cookies on form interaction
            $('#checkinForm').on('focusin change', function() {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                }, 1000);
            });
        }
    });

    // CSS for loading spinner
    const style = document.createElement('style');
    style.textContent = `
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>