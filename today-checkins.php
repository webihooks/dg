<?php
// today-checkins.php
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
$user_name = $_SESSION['name'] ?? 'User';
$today = date('Y-m-d');
$success_message = '';
$error_message = '';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle check-in action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_booking'])) {
    $booking_id = $_POST['booking_id'];
    $room_id = $_POST['room_id'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Update booking status to checked_in
        $update_booking_sql = "UPDATE bookings_$user_id SET status = 'checked_in', updated_at = NOW() WHERE id = ? AND status = 'reserved'";
        $stmt = $conn->prepare($update_booking_sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Update room status to occupied
            $update_room_sql = "UPDATE rooms_$user_id SET status = 'occupied', updated_at = NOW() WHERE id = ?";
            $stmt2 = $conn->prepare($update_room_sql);
            $stmt2->bind_param("i", $room_id);
            $stmt2->execute();
            
            $conn->commit();
            $success_message = "Booking checked in successfully!";
        } else {
            $conn->rollback();
            $error_message = "Failed to check in booking. It may have been already processed.";
        }
        
        $stmt->close();
        if (isset($stmt2)) $stmt2->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error during check-in: " . $e->getMessage();
    }
}

// Get today's check-ins
$today_checkins_sql = "SELECT 
                        b.id, b.booking_reference, b.guest_name, b.guest_phone, b.guest_email,
                        r.room_number, rt.name as room_type,
                        b.check_in_date, b.check_out_date, b.total_nights,
                        b.adults, b.children, b.total_amount, b.special_requests,
                        b.created_at
                      FROM bookings_$user_id b
                      LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                      LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                      WHERE DATE(b.check_in_date) = ? 
                      AND b.status = 'checked_in'
                      ORDER BY b.check_in_date ASC, b.created_at ASC";
                      
$stmt = $conn->prepare($today_checkins_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get today's reserved bookings (pending check-ins)
$pending_checkins_sql = "SELECT 
                        b.id, b.booking_reference, b.guest_name, b.guest_phone, b.guest_email,
                        r.room_number, rt.name as room_type, r.id as room_id,
                        b.check_in_date, b.check_out_date, b.total_nights,
                        b.adults, b.children, b.total_amount, b.special_requests,
                        b.created_at
                      FROM bookings_$user_id b
                      LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                      LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                      WHERE DATE(b.check_in_date) = ? 
                      AND b.status = 'reserved'
                      ORDER BY b.check_in_date ASC, b.created_at ASC";
                      
$stmt = $conn->prepare($pending_checkins_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$pending_checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Today's Check-ins</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-checked_in { background: #28a745; color: white; }
        .status-reserved { background: #ffc107; color: #000; }
        .status-completed { background: #6c757d; color: white; }
        
        .guest-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .guest-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .guest-card.checked-in {
            border-left-color: #28a745;
        }
        
        .action-buttons .btn {
            margin: 2px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .action-buttons .btn {
                font-size: 12px;
                padding: 6px 10px;
            }
            .guest-info {
                font-size: 14px;
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
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="room-dashboard.php">Room Management</a></li>
                                    <li class="breadcrumb-item active">Today's Check-ins</li>
                                </ol>
                            </div>
                            <h4 class="page-title">
                                <i class="fas fa-sign-in-alt me-1"></i>
                                Today's Check-ins - <?php echo date('F j, Y'); ?>
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo count($today_checkins); ?></h3>
                                    <p class="mb-0">Checked In Today</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo count($pending_checkins); ?></h3>
                                    <p class="mb-0">Pending Check-ins</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo count($today_checkins) + count($pending_checkins); ?></h3>
                                    <p class="mb-0">Total Expected</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Pending Check-ins Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Pending Check-ins (<?php echo count($pending_checkins); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($pending_checkins)): ?>
                                    <div class="row">
                                        <?php foreach ($pending_checkins as $booking): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card guest-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                                            <h6 class="card-title mb-0">
                                                                <?php echo htmlspecialchars($booking['guest_name']); ?>
                                                            </h6>
                                                            <span class="status-badge status-reserved">Reserved</span>
                                                        </div>
                                                        
                                                        <div class="guest-info mb-3">
                                                            <div class="mb-2">
                                                                <small class="text-muted">Booking Ref:</small>
                                                                <strong><?php echo $booking['booking_reference']; ?></strong>
                                                            </div>
                                                            <div class="mb-2">
                                                                <small class="text-muted">Room:</small>
                                                                <strong><?php echo htmlspecialchars($booking['room_number']); ?> (<?php echo htmlspecialchars($booking['room_type']); ?>)</strong>
                                                            </div>
                                                            <div class="mb-2">
                                                                <small class="text-muted">Stay:</small>
                                                                <?php echo date('M j', strtotime($booking['check_in_date'])); ?> - 
                                                                <?php echo date('M j', strtotime($booking['check_out_date'])); ?>
                                                                (<?php echo $booking['total_nights']; ?> nights)
                                                            </div>
                                                            <div class="mb-2">
                                                                <small class="text-muted">Guests:</small>
                                                                <?php echo $booking['adults']; ?> Adult<?php echo $booking['adults'] > 1 ? 's' : ''; ?>
                                                                <?php echo $booking['children'] > 0 ? ', ' . $booking['children'] . ' Children' : ''; ?>
                                                            </div>
                                                            <div class="mb-2">
                                                                <small class="text-muted">Amount:</small>
                                                                <strong>₹<?php echo number_format($booking['total_amount']); ?></strong>
                                                            </div>
                                                            <?php if (!empty($booking['special_requests'])): ?>
                                                                <div class="mb-2">
                                                                    <small class="text-muted">Special Requests:</small>
                                                                    <div class="fst-italic"><?php echo htmlspecialchars($booking['special_requests']); ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="action-buttons">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="checkin_booking" value="1">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="room_id" value="<?php echo $booking['room_id']; ?>">
                                                                <button type="submit" class="btn btn-success btn-sm">
                                                                    <i class="fas fa-sign-in-alt me-1"></i> Check In
                                                                </button>
                                                            </form>
                                                            <a href="tel:<?php echo htmlspecialchars($booking['guest_phone']); ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="fas fa-phone me-1"></i> Call
                                                            </a>
                                                            <?php if (!empty($booking['guest_email'])): ?>
                                                                <a href="mailto:<?php echo htmlspecialchars($booking['guest_email']); ?>" 
                                                                   class="btn btn-info btn-sm">
                                                                    <i class="fas fa-envelope me-1"></i> Email
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-bed"></i>
                                        <h5>No Pending Check-ins</h5>
                                        <p class="text-muted">All guests for today have been checked in or there are no reservations for today.</p>
                                        <a href="add-booking.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i> Create New Booking
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Completed Check-ins Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Completed Check-ins (<?php echo count($today_checkins); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($today_checkins)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Guest Name</th>
                                                    <th>Room</th>
                                                    <th>Contact</th>
                                                    <th>Check-in Time</th>
                                                    <th>Stay Duration</th>
                                                    <th>Amount</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($today_checkins as $checkin): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($checkin['guest_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $checkin['booking_reference']; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($checkin['room_number']); ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($checkin['room_type']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($checkin['guest_phone']); ?>
                                                            <?php if (!empty($checkin['guest_email'])): ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($checkin['guest_email']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('h:i A', strtotime($checkin['created_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $checkin['total_nights']; ?> night<?php echo $checkin['total_nights'] > 1 ? 's' : ''; ?>
                                                            <br>
                                                            <small>Check-out: <?php echo date('M j', strtotime($checkin['check_out_date'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <strong>₹<?php echo number_format($checkin['total_amount']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <a href="view-booking.php?id=<?php echo $checkin['id']; ?>" 
                                                                   class="btn btn-info btn-sm">
                                                                    <i class="fas fa-eye me-1"></i> View
                                                                </a>
                                                                <a href="tel:<?php echo htmlspecialchars($checkin['guest_phone']); ?>" 
                                                                   class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-phone me-1"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h5>No Check-ins Today</h5>
                                        <p class="text-muted">No guests have been checked in yet today.</p>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    // Enhanced Android Session Protection
    function setupCheckinsSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('🏨 Today Check-ins: Setting up Android session protection');
        
        // Force immediate cookie update
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
                console.log('🔧 Today Check-ins: Initial cookie update completed');
            }
        }, 1000);
        
        // Additional protection for page transitions
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && WTN.forceUpdateCookies) {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                    console.log('🔧 Today Check-ins: Page restored from cache - cookies updated');
                }, 500);
            }
        });
    }

    // Initialize protection
    document.addEventListener('DOMContentLoaded', function() {
        setupCheckinsSessionProtection();
        
        // Auto-refresh every 2 minutes to get latest check-ins
        setInterval(() => {
            window.location.reload();
        }, 120000);
        
        // Add confirmation for check-in actions
        const checkinForms = document.querySelectorAll('form[method="POST"]');
        checkinForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
                button.disabled = true;
            });
        });
    });

    // Room Management Session Manager
    class CheckinsSessionManager {
        constructor() {
            this.isWebToNative = typeof WTN !== 'undefined';
            this.init();
        }

        init() {
            if (this.isWebToNative) {
                console.log('🏨 Check-ins Session Manager: WebToNative detected');
                this.startSessionMaintenance();
            }
        }

        startSessionMaintenance() {
            setInterval(() => {
                this.maintainSession();
            }, 30000);
        }

        maintainSession() {
            if (!this.isWebToNative) return;

            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }

            this.sendPing();
        }

        async sendPing() {
            try {
                await fetch('session-keepalive.php', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'X-Checkins-Ping': 'true',
                        'X-WebToNative': 'true'
                    }
                });
                console.log('🏨 Today Check-ins: Session ping sent');
            } catch (error) {
                console.log('🏨 Today Check-ins: Ping failed');
            }
        }
    }

    // Initialize session manager
    document.addEventListener('DOMContentLoaded', function() {
        window.checkinsSessionManager = new CheckinsSessionManager();
    });
    </script>
</body>
</html>