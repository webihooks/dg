<?php
// walkin-customers.php
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
$user_sql = "SELECT name, role, is_trial, trial_end FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role, $is_trial, $trial_end);
$user_stmt->fetch();
$user_stmt->close();

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle form submission for new walk-in customer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name = trim($_POST['guest_name']);
    $guest_phone = trim($_POST['guest_phone']);
    $guest_email = trim($_POST['guest_email']);
    $guest_address = trim($_POST['guest_address']);
    $id_proof_type = trim($_POST['id_proof_type']);
    $id_proof_number = trim($_POST['id_proof_number']);
    $room_id = intval($_POST['room_id']);
    $check_in_date = $_POST['check_in_date'];
    $check_out_date = $_POST['check_out_date'];
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $special_requests = trim($_POST['special_requests']);
    
    // Validate required fields
    if (empty($guest_name) || empty($guest_phone) || empty($room_id)) {
        $error_message = "Please fill in all required fields (Name, Phone, and Room)";
    } elseif ($check_in_date >= $check_out_date) {
        $error_message = "Check-out date must be after check-in date";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Check if room is available
            $room_check_sql = "SELECT status, rate_per_night FROM rooms_$user_id WHERE id = ?";
            $room_stmt = $conn->prepare($room_check_sql);
            $room_stmt->bind_param("i", $room_id);
            $room_stmt->execute();
            $room_result = $room_stmt->get_result();
            
            if ($room_result->num_rows === 0) {
                throw new Exception("Selected room not found");
            }
            
            $room = $room_result->fetch_assoc();
            $room_stmt->close();
            
            if ($room['status'] !== 'available') {
                throw new Exception("Selected room is not available. Current status: " . $room['status']);
            }
            
            // Calculate total amount
            $check_in = new DateTime($check_in_date);
            $check_out = new DateTime($check_out_date);
            $total_nights = $check_out->diff($check_in)->days;
            $room_rate = $room['rate_per_night'];
            $subtotal = $total_nights * $room_rate;
            $tax_amount = $subtotal * 0.12; // 12% GST
            $total_amount = $subtotal + $tax_amount;
            
            // Generate booking reference
            $booking_reference = 'WALKIN-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            // Insert into bookings table
            $booking_sql = "INSERT INTO bookings_$user_id (
                booking_reference, guest_name, guest_phone, guest_email, guest_address,
                room_id, check_in_date, check_out_date, adults, children, total_nights,
                room_rate, subtotal, tax_amount, total_amount, status, special_requests
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'checked_in', ?)";
            
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param(
                "sssssisssiidddds",
                $booking_reference,
                $guest_name,
                $guest_phone,
                $guest_email,
                $guest_address,
                $room_id,
                $check_in_date,
                $check_out_date,
                $adults,
                $children,
                $total_nights,
                $room_rate,
                $subtotal,
                $tax_amount,
                $total_amount,
                $special_requests
            );
            
            if (!$booking_stmt->execute()) {
                throw new Exception("Failed to create booking: " . $booking_stmt->error);
            }
            $booking_id = $booking_stmt->insert_id;
            $booking_stmt->close();
            
            // Update room status to occupied
            $update_room_sql = "UPDATE rooms_$user_id SET status = 'occupied' WHERE id = ?";
            $update_stmt = $conn->prepare($update_room_sql);
            $update_stmt->bind_param("i", $room_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update room status: " . $update_stmt->error);
            }
            $update_stmt->close();
            
            // Add/Update guest in guests table
            $guest_check_sql = "SELECT id FROM guests_$user_id WHERE phone = ?";
            $guest_check_stmt = $conn->prepare($guest_check_sql);
            $guest_check_stmt->bind_param("s", $guest_phone);
            $guest_check_stmt->execute();
            $guest_result = $guest_check_stmt->get_result();
            
            if ($guest_result->num_rows > 0) {
                // Update existing guest
                $guest = $guest_result->fetch_assoc();
                $guest_id = $guest['id'];
                $update_guest_sql = "UPDATE guests_$user_id SET 
                    name = ?, email = ?, address = ?, id_proof_type = ?, id_proof_number = ?
                    WHERE id = ?";
                $update_guest_stmt = $conn->prepare($update_guest_sql);
                $update_guest_stmt->bind_param(
                    "sssssi",
                    $guest_name,
                    $guest_email,
                    $guest_address,
                    $id_proof_type,
                    $id_proof_number,
                    $guest_id
                );
                $update_guest_stmt->execute();
                $update_guest_stmt->close();
            } else {
                // Insert new guest
                $insert_guest_sql = "INSERT INTO guests_$user_id (
                    name, phone, email, address, id_proof_type, id_proof_number
                ) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_guest_stmt = $conn->prepare($insert_guest_sql);
                $insert_guest_stmt->bind_param(
                    "ssssss",
                    $guest_name,
                    $guest_phone,
                    $guest_email,
                    $guest_address,
                    $id_proof_type,
                    $id_proof_number
                );
                $insert_guest_stmt->execute();
                $insert_guest_stmt->close();
            }
            $guest_check_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Walk-in customer checked in successfully! Booking Reference: " . $booking_reference;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get available rooms for dropdown
$available_rooms_sql = "SELECT r.id, r.room_number, rt.name as room_type, r.rate_per_night 
                       FROM rooms_$user_id r 
                       LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
                       WHERE r.status = 'available' 
                       ORDER BY r.room_number";
$available_rooms_result = $conn->query($available_rooms_sql);
$available_rooms = [];
while ($row = $available_rooms_result->fetch_assoc()) {
    $available_rooms[] = $row;
}

// Get today's walk-in customers
$today_walkins_sql = "SELECT b.*, r.room_number, rt.name as room_type 
                     FROM bookings_$user_id b 
                     LEFT JOIN rooms_$user_id r ON b.room_id = r.id 
                     LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
                     WHERE DATE(b.created_at) = CURDATE() 
                     AND b.booking_reference LIKE 'WALKIN-%'
                     ORDER BY b.created_at DESC";
$today_walkins_result = $conn->query($today_walkins_sql);
$today_walkins = [];
while ($row = $today_walkins_result->fetch_assoc()) {
    $today_walkins[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Walk-in Customers - Room Management</title>
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
        .card-summary {
            transition: all 0.3s ease;
        }
        .card-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .quick-stats {
            margin-bottom: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            color: white;
            margin-bottom: 15px;
        }
        .stat-card.available { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.occupied { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        .stat-card.today { background: linear-gradient(135deg, #007bff, #6f42c1); }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .room-option {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .room-option:hover {
            background-color: #f8f9fa;
            border-color: #007bff;
        }
        .room-option.selected {
            background-color: #e7f3ff;
            border-color: #007bff;
        }
        .booking-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
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
                            <h4 class="page-title">Walk-in Customers Management</h4>
                            <p class="text-muted mb-4">Manage direct walk-in customers and instant bookings</p>
                        </div>

                        <!-- Success/Error Messages -->
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

                        <!-- Quick Stats -->
                        <div class="row quick-stats">
                            <div class="col-md-4">
                                <div class="stat-card available">
                                    <div class="stat-number"><?php echo count($available_rooms); ?></div>
                                    <div class="stat-label">Available Rooms</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card occupied">
                                    <div class="stat-number"><?php echo count($today_walkins); ?></div>
                                    <div class="stat-label">Today's Walk-ins</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card today">
                                    <div class="stat-number">₹<?php 
                                        $today_revenue = 0;
                                        foreach ($today_walkins as $walkin) {
                                            $today_revenue += $walkin['total_amount'];
                                        }
                                        echo number_format($today_revenue);
                                    ?></div>
                                    <div class="stat-label">Today's Revenue</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- New Walk-in Form -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">New Walk-in Customer</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="walkinForm">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="guest_name" class="form-label">Guest Name *</label>
                                                        <input type="text" class="form-control" id="guest_name" name="guest_name" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="guest_phone" class="form-label">Phone Number *</label>
                                                        <input type="tel" class="form-control" id="guest_phone" name="guest_phone" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="guest_email" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="guest_email" name="guest_email">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="id_proof_type" class="form-label">ID Proof Type</label>
                                                        <select class="form-control" id="id_proof_type" name="id_proof_type">
                                                            <option value="">Select ID Proof</option>
                                                            <option value="Aadhar Card">Aadhar Card</option>
                                                            <option value="Passport">Passport</option>
                                                            <option value="Driving License">Driving License</option>
                                                            <option value="Voter ID">Voter ID</option>
                                                            <option value="PAN Card">PAN Card</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="guest_address" class="form-label">Address</label>
                                                <textarea class="form-control" id="guest_address" name="guest_address" rows="2"></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label for="id_proof_number" class="form-label">ID Proof Number</label>
                                                <input type="text" class="form-control" id="id_proof_number" name="id_proof_number">
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="check_in_date" class="form-label">Check-in Date *</label>
                                                        <input type="date" class="form-control" id="check_in_date" name="check_in_date" 
                                                               value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="check_out_date" class="form-label">Check-out Date *</label>
                                                        <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="adults" class="form-label">Adults *</label>
                                                        <select class="form-control" id="adults" name="adults" required>
                                                            <option value="1">1 Adult</option>
                                                            <option value="2">2 Adults</option>
                                                            <option value="3">3 Adults</option>
                                                            <option value="4">4 Adults</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="children" class="form-label">Children</label>
                                                        <select class="form-control" id="children" name="children">
                                                            <option value="0">0 Children</option>
                                                            <option value="1">1 Child</option>
                                                            <option value="2">2 Children</option>
                                                            <option value="3">3 Children</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="room_id" class="form-label">Select Room *</label>
                                                <select class="form-control" id="room_id" name="room_id" required>
                                                    <option value="">Select Available Room</option>
                                                    <?php foreach ($available_rooms as $room): ?>
                                                        <option value="<?php echo $room['id']; ?>" 
                                                                data-rate="<?php echo $room['rate_per_night']; ?>">
                                                            <?php echo $room['room_number'] . ' - ' . $room['room_type'] . ' (₹' . $room['rate_per_night'] . '/night)'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="special_requests" class="form-label">Special Requests</label>
                                                <textarea class="form-control" id="special_requests" name="special_requests" rows="2" 
                                                          placeholder="Any special requests or notes..."></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6 class="card-title">Booking Summary</h6>
                                                        <div id="bookingSummary">
                                                            <p class="text-muted mb-1">Select a room and dates to see booking details</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn btn-success btn-lg w-100">
                                                <i class="fas fa-user-check me-2"></i>Check-in Walk-in Customer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Today's Walk-ins -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Today's Walk-in Customers</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($today_walkins)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Ref No.</th>
                                                            <th>Guest</th>
                                                            <th>Room</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($today_walkins as $walkin): ?>
                                                            <tr>
                                                                <td>
                                                                    <small class="text-muted"><?php echo $walkin['booking_reference']; ?></small>
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($walkin['guest_name']); ?></strong><br>
                                                                    <small class="text-muted"><?php echo $walkin['guest_phone']; ?></small>
                                                                </td>
                                                                <td>
                                                                    <?php echo $walkin['room_number']; ?><br>
                                                                    <small class="text-muted"><?php echo $walkin['room_type']; ?></small>
                                                                </td>
                                                                <td>
                                                                    <strong>₹<?php echo number_format($walkin['total_amount']); ?></strong><br>
                                                                    <small class="text-muted"><?php echo $walkin['total_nights']; ?> night(s)</small>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-success">Checked In</span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No walk-in customers today</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h5 class="card-title">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <a href="manage-rooms.php" class="btn btn-outline-primary w-100">
                                                    <i class="fas fa-bed me-2"></i>Manage Rooms
                                                </a>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <a href="bookings.php" class="btn btn-outline-info w-100">
                                                    <i class="fas fa-list me-2"></i>All Bookings
                                                </a>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <a href="quick-checkout.php" class="btn btn-outline-warning w-100">
                                                    <i class="fas fa-sign-out-alt me-2"></i>Quick Check-out
                                                </a>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <a href="room-dashboard.php" class="btn btn-outline-secondary w-100">
                                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                                </a>
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
        // Calculate booking summary when dates or room change
        function calculateBookingSummary() {
            const checkInDate = new Date($('#check_in_date').val());
            const checkOutDate = new Date($('#check_out_date').val());
            const roomSelect = $('#room_id');
            const selectedOption = roomSelect.find('option:selected');
            const roomRate = parseFloat(selectedOption.data('rate')) || 0;
            
            if (checkInDate && checkOutDate && checkOutDate > checkInDate && roomRate > 0) {
                const timeDiff = checkOutDate.getTime() - checkInDate.getTime();
                const totalNights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                const subtotal = totalNights * roomRate;
                const taxAmount = subtotal * 0.12; // 12% GST
                const totalAmount = subtotal + taxAmount;
                
                $('#bookingSummary').html(`
                    <div class="row">
                        <div class="col-6">
                            <small>Total Nights:</small><br>
                            <strong>${totalNights}</strong>
                        </div>
                        <div class="col-6">
                            <small>Room Rate:</small><br>
                            <strong>₹${roomRate.toFixed(2)}/night</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small>Subtotal:</small><br>
                            <strong>₹${subtotal.toFixed(2)}</strong>
                        </div>
                        <div class="col-6">
                            <small>Tax (12%):</small><br>
                            <strong>₹${taxAmount.toFixed(2)}</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-12">
                            <small>Total Amount:</small><br>
                            <strong class="text-success">₹${totalAmount.toFixed(2)}</strong>
                        </div>
                    </div>
                `);
            } else {
                $('#bookingSummary').html('<p class="text-muted mb-1">Select a room and dates to see booking details</p>');
            }
        }

        // Event listeners for calculation
        $('#check_in_date, #check_out_date, #room_id').on('change', calculateBookingSummary);
        
        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        $('#check_in_date').attr('min', today);
        $('#check_out_date').attr('min', today);
        
        // Set check-out date to tomorrow by default
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        $('#check_out_date').val(tomorrow.toISOString().split('T')[0]);
        
        // Initialize booking summary
        calculateBookingSummary();

        // Form validation
        $('#walkinForm').on('submit', function(e) {
            const checkInDate = new Date($('#check_in_date').val());
            const checkOutDate = new Date($('#check_out_date').val());
            
            if (checkOutDate <= checkInDate) {
                e.preventDefault();
                alert('Check-out date must be after check-in date');
                return false;
            }
            
            if (!$('#room_id').val()) {
                e.preventDefault();
                alert('Please select a room');
                return false;
            }
        });

        // Android session protection
        if (typeof WTN !== 'undefined') {
            setInterval(() => {
                if (WTN.forceUpdateCookies) {
                    WTN.forceUpdateCookies();
                }
            }, 45000);
        }
    });
    </script>
</body>
</html>