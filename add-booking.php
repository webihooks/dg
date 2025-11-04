<?php
// add-booking.php
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

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Get available rooms
$rooms_sql = "SELECT r.id, r.room_number, r.floor, rt.name as room_type, r.rate_per_night 
              FROM rooms_$user_id r 
              LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
              WHERE r.status = 'available' 
              ORDER BY r.room_number";
$rooms_result = $conn->query($rooms_sql);
$available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name = $_POST['guest_name'] ?? '';
    $guest_phone = $_POST['guest_phone'] ?? '';
    $guest_email = $_POST['guest_email'] ?? '';
    $guest_address = $_POST['guest_address'] ?? '';
    $room_id = $_POST['room_id'] ?? '';
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $adults = $_POST['adults'] ?? 1;
    $children = $_POST['children'] ?? 0;
    $special_requests = $_POST['special_requests'] ?? '';
    $advance_paid = $_POST['advance_paid'] ?? 0;
    
    // Validate required fields
    if (empty($guest_name) || empty($guest_phone) || empty($room_id) || empty($check_in_date) || empty($check_out_date)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Calculate total amount
        $room_sql = "SELECT rate_per_night FROM rooms_$user_id WHERE id = ?";
        $room_stmt = $conn->prepare($room_sql);
        $room_stmt->bind_param("i", $room_id);
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        $room_data = $room_result->fetch_assoc();
        $room_stmt->close();
        
        $rate_per_night = $room_data['rate_per_night'];
        $check_in = new DateTime($check_in_date);
        $check_out = new DateTime($check_out_date);
        $total_nights = $check_in->diff($check_out)->days;
        $subtotal = $rate_per_night * $total_nights;
        $total_amount = $subtotal; // Add tax and other charges if needed
        
        // Generate booking reference
        $booking_reference = 'BK' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert booking
        $insert_sql = "INSERT INTO bookings_$user_id (
            booking_reference, guest_name, guest_phone, guest_email, guest_address,
            room_id, check_in_date, check_out_date, adults, children, total_nights,
            room_rate, subtotal, total_amount, advance_paid, special_requests
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            "sssssisiiiiddds", 
            $booking_reference, $guest_name, $guest_phone, $guest_email, $guest_address,
            $room_id, $check_in_date, $check_out_date, $adults, $children, $total_nights,
            $rate_per_night, $subtotal, $total_amount, $advance_paid, $special_requests
        );
        
        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id;
            
            // Update room status
            $update_room_sql = "UPDATE rooms_$user_id SET status = 'reserved' WHERE id = ?";
            $update_stmt = $conn->prepare($update_room_sql);
            $update_stmt->bind_param("i", $room_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $success_message = "Booking created successfully! Booking Reference: $booking_reference";
            
            // Clear form
            $_POST = [];
        } else {
            $error_message = "Error creating booking: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Add New Booking</title>
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
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="page-title-box">
                            <h4 class="page-title">Add New Booking</h4>
                            <div class="page-title-right">
                                <a href="bookings.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Bookings
                                </a>
                            </div>
                        </div>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-body">
                                <form method="POST" id="bookingForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Guest Information</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Guest Name *</label>
                                                <input type="text" class="form-control" name="guest_name" value="<?php echo $_POST['guest_name'] ?? ''; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number *</label>
                                                <input type="tel" class="form-control" name="guest_phone" value="<?php echo $_POST['guest_phone'] ?? ''; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="guest_email" value="<?php echo $_POST['guest_email'] ?? ''; ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="guest_address" rows="3"><?php echo $_POST['guest_address'] ?? ''; ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h5>Booking Details</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Room *</label>
                                                <select class="form-select" name="room_id" required>
                                                    <option value="">Select Room</option>
                                                    <?php foreach ($available_rooms as $room): ?>
                                                        <option value="<?php echo $room['id']; ?>" 
                                                            <?php echo ($_POST['room_id'] ?? '') == $room['id'] ? 'selected' : ''; ?>>
                                                            <?php echo $room['room_number']; ?> - <?php echo $room['room_type']; ?> (₹<?php echo $room['rate_per_night']; ?>/night)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Check-in Date *</label>
                                                        <input type="date" class="form-control" name="check_in_date" value="<?php echo $_POST['check_in_date'] ?? ''; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Check-out Date *</label>
                                                        <input type="date" class="form-control" name="check_out_date" value="<?php echo $_POST['check_out_date'] ?? ''; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Adults</label>
                                                        <input type="number" class="form-control" name="adults" value="<?php echo $_POST['adults'] ?? 1; ?>" min="1" max="10">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Children</label>
                                                        <input type="number" class="form-control" name="children" value="<?php echo $_POST['children'] ?? 0; ?>" min="0" max="10">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Advance Paid (₹)</label>
                                                <input type="number" class="form-control" name="advance_paid" value="<?php echo $_POST['advance_paid'] ?? 0; ?>" min="0" step="0.01">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Special Requests</label>
                                                <textarea class="form-control" name="special_requests" rows="3"><?php echo $_POST['special_requests'] ?? ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-save"></i> Create Booking
                                        </button>
                                        <a href="bookings.php" class="btn btn-secondary btn-lg">Cancel</a>
                                    </div>
                                </form>
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
        // Calculate total nights and amount when dates change
        $('input[name="check_in_date"], input[name="check_out_date"]').change(function() {
            calculateBookingDetails();
        });
        
        $('select[name="room_id"]').change(function() {
            calculateBookingDetails();
        });
        
        function calculateBookingDetails() {
            var checkIn = $('input[name="check_in_date"]').val();
            var checkOut = $('input[name="check_out_date"]').val();
            var roomSelect = $('select[name="room_id"]');
            var selectedOption = roomSelect.find('option:selected');
            
            if (checkIn && checkOut && selectedOption.val()) {
                var checkInDate = new Date(checkIn);
                var checkOutDate = new Date(checkOut);
                var timeDiff = checkOutDate - checkInDate;
                var totalNights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                
                if (totalNights > 0) {
                    // Extract rate from option text (assuming format: "Room 101 - Deluxe (₹2500/night)")
                    var optionText = selectedOption.text();
                    var rateMatch = optionText.match(/₹(\d+)/);
                    if (rateMatch) {
                        var ratePerNight = parseFloat(rateMatch[1]);
                        var totalAmount = ratePerNight * totalNights;
                        
                        // Show calculation (you can display this in a div)
                        console.log('Total Nights:', totalNights);
                        console.log('Total Amount: ₹', totalAmount);
                    }
                }
            }
        }
    });
    </script>
</body>
</html>