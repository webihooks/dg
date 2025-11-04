<?php
// In your server configuration or PHP file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Start the session
session_start();
date_default_timezone_set('Asia/Kolkata');

// Include the enhanced session manager
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();

// Let the session manager handle Android session persistence
$sessionManager->validateAndroidSession();

require_once 'session_check.php';

require_once 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$current_profile_url = '';
$trial_notification = '';

// First, check if user has an active subscription
$subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
$subscription_stmt = $conn->prepare($subscription_sql);
$subscription_stmt->bind_param("i", $user_id);
$subscription_stmt->execute();
$subscription_stmt->store_result();
$has_active_subscription = ($subscription_stmt->num_rows > 0);
$subscription_stmt->close();

// Get user details including trial information
$user_sql = "SELECT name, role, is_trial, trial_end FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role, $is_trial, $trial_end);
$user_stmt->fetch();
$user_stmt->close();

// Check trial status and prepare notification only if user doesn't have active subscription
if (!$has_active_subscription && $is_trial) {
    $current_date = new DateTime();
    $trial_end_date = new DateTime($trial_end);
    $days_remaining = $current_date->diff($trial_end_date)->days;
    
    if ($current_date > $trial_end_date) {
        $trial_notification = '<div class="alert alert-danger">Your trial period has ended. <a href="subscription.php" class="alert-link">Subscribe now</a> to continue using our services.</div>';
    } else {
        $trial_notification = '<div class="alert alert-info">You have ' . $days_remaining . ' day(s) remaining in your free trial. <a href="subscription.php" class="alert-link">Subscribe now</a> for full access.</div>';
    }
}

// Get today's date
$today = date('Y-m-d');

// Include table creation function and create tables if they don't exist
require_once 'create_user_room_tables.php';
$tables_created = createUserRoomTables($conn, $user_id);

if (!$tables_created) {
    $error_message = "Error setting up room management system. Please try refreshing the page.";
}

// Room Management Summary Data with user-specific tables
$summary_sql = "SELECT 
                  COUNT(*) as total_rooms,
                  SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
                  SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
                  SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms,
                  SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning_rooms,
                  SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_rooms
                FROM rooms_$user_id";
                
$stmt = $conn->prepare($summary_sql);
$stmt->execute();
$summary_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Today's check-ins
$today_checkins_sql = "SELECT 
                        COUNT(*) as total_checkins,
                        SUM(total_amount) as today_revenue
                      FROM bookings_$user_id 
                      WHERE DATE(check_in_date) = ?
                      AND status = 'checked_in'";
                      
$stmt = $conn->prepare($today_checkins_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_checkins = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Today's check-outs
$today_checkouts_sql = "SELECT 
                        COUNT(*) as total_checkouts,
                        SUM(total_amount) as checkout_revenue
                      FROM bookings_$user_id 
                      WHERE DATE(check_out_date) = ?
                      AND status = 'checked_in'";
                      
$stmt = $conn->prepare($today_checkouts_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_checkouts = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Upcoming check-ins for next 3 days
$upcoming_checkins_sql = "SELECT 
                        COUNT(*) as upcoming_checkins
                      FROM bookings_$user_id 
                      WHERE check_in_date BETWEEN ? AND DATE_ADD(?, INTERVAL 3 DAY)
                      AND status = 'reserved'";
                      
$stmt = $conn->prepare($upcoming_checkins_sql);
$stmt->bind_param("ss", $today, $today);
$stmt->execute();
$upcoming_checkins = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Monthly revenue data for chart
$monthly_revenue_sql = "SELECT 
                        DATE_FORMAT(check_in_date, '%Y-%m') as month,
                        SUM(total_amount) as monthly_revenue,
                        COUNT(*) as monthly_bookings
                      FROM bookings_$user_id 
                      WHERE status IN ('checked_in', 'checked_out')
                      AND check_in_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
                      ORDER BY month ASC";
                      
$stmt = $conn->prepare($monthly_revenue_sql);
$stmt->execute();
$result = $stmt->get_result();
$revenue_data = [];
while ($row = $result->fetch_assoc()) {
    $revenue_data[] = $row;
}
$stmt->close();

// Recent bookings
$recent_bookings_sql = "SELECT 
                        b.id, b.booking_reference, b.guest_name, 
                        r.room_number, b.check_in_date, b.check_out_date, 
                        b.total_amount, b.status, 
                        rt.name as room_type
                      FROM bookings_$user_id b
                      LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                      LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                      ORDER BY b.created_at DESC 
                      LIMIT 5";
                      
$stmt = $conn->prepare($recent_bookings_sql);
$stmt->execute();
$recent_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Today's arrivals and departures - CORRECTED QUERIES
$today_arrivals_sql = "SELECT 
                        b.booking_reference, b.guest_name, r.room_number,
                        b.special_requests
                      FROM bookings_$user_id b
                      LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                      WHERE DATE(b.check_in_date) = ?
                      AND b.status = 'reserved'
                      ORDER BY b.check_in_date ASC
                      LIMIT 5";
                      
$stmt = $conn->prepare($today_arrivals_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_arrivals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$today_departures_sql = "SELECT 
                        b.booking_reference, b.guest_name, r.room_number,
                        b.special_requests
                      FROM bookings_$user_id b
                      LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                      WHERE DATE(b.check_out_date) = ?
                      AND b.status = 'checked_in'
                      ORDER BY b.check_out_date ASC
                      LIMIT 5";
                      
$stmt = $conn->prepare($today_departures_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_departures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Room type distribution
$room_type_distribution_sql = "SELECT 
                                rt.name as room_type,
                                COUNT(r.id) as room_count,
                                SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) as occupied_count,
                                SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available_count
                              FROM rooms_$user_id r
                              LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                              GROUP BY rt.id, rt.name
                              ORDER BY room_count DESC";
                              
$stmt = $conn->prepare($room_type_distribution_sql);
$stmt->execute();
$room_type_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Room Management Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .card-summary {
            transition: all 0.3s ease;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        .room-status-available { background: linear-gradient(135deg, #28a745, #20c997) !important; }
        .room-status-occupied { background: linear-gradient(135deg, #dc3545, #e83e8c) !important; }
        .room-status-maintenance { background: linear-gradient(135deg, #ffc107, #fd7e14) !important; color: #000 !important; }
        .room-status-cleaning { background: linear-gradient(135deg, #17a2b8, #6f42c1) !important; }
        .room-status-reserved { background: linear-gradient(135deg, #6f42c1, #e83e8c) !important; }
        
        .booking-status-checked_in { background-color: #28a745 !important; }
        .booking-status-reserved { background-color: #007bff !important; }
        .booking-status-checked_out { background-color: #6c757d !important; }
        .booking-status-cancelled { background-color: #dc3545 !important; }
        .booking-status-no_show { background-color: #fd7e14 !important; }
        
        .today-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .recent-bookings-table {
            font-size: 0.85rem;
        }
        
        .recent-bookings-table td {
            padding: 0.5rem;
            vertical-align: middle;
        }
        
        .arrival-departure-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #17a2b8;
        }
        
        .arrival-departure-card.departure {
            border-left-color: #fd7e14;
        }
        
        .room-type-distribution {
            font-size: 0.9rem;
        }
        
        .progress {
            height: 8px;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .dashboard .col-md-3 {
                width: 50%;
                margin-bottom: 15px;
            }
            .dashboard .col-md-3 .card-body {
                height: 140px !important;
                padding: 15px !important;
            }
            .recent-bookings-table {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .dashboard .col-md-3 {
                width: 100%;
            }
        }
        
        /* Session status indicator */
        .session-status-android {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 10000;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .session-status-android.web {
            background: #17a2b8;
        }
        
        .session-status-android.warning {
            background: #ffc107;
            color: #000;
        }
        
        .quick-actions {
            margin-bottom: 25px;
        }
        
        .quick-action-btn {
            margin: 5px;
            min-width: 140px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .setup-notice {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .card-title {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .occupancy-rate {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
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
            if ($has_active_subscription || ($is_trial && strtotime($trial_end) > time())) {
                include 'room_management_menu.php';
            } else {
                include 'unsubscriber_menu.php';
            }
        }
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Display trial notification if user is on trial -->
                        <?php if (!empty($trial_notification)) echo $trial_notification; ?>
                        
                        <!-- Error message if table creation failed -->
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <iconify-icon icon="mdi:alert-circle" class="me-2"></iconify-icon>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Setup Notice for New Users -->
                        <?php if ($summary_data['total_rooms'] == 0): ?>
                        <div class="setup-notice">
                            <h4>🏨 Welcome to Room Management System</h4>
                            <p>Your room management system is ready! Start by adding rooms and room types to begin managing your property.</p>
                            <div class="mt-3">
                                <a href="manage-rooms.php" class="btn btn-light me-2">
                                    <iconify-icon icon="mdi:plus-circle" class="me-1"></iconify-icon>
                                    Add Rooms
                                </a>
                                <a href="room-types.php" class="btn btn-outline-light">
                                    <iconify-icon icon="mdi:format-list-bulleted-type" class="me-1"></iconify-icon>
                                    Manage Room Types
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Today's Header -->
                        <div class="today-header">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h4 class="mb-1">Room Management Dashboard</h4>
                                    <p class="mb-0"><?php echo date('l, F j, Y'); ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="row">
                                        <div class="col-4">
                                            <small>Check-ins: <strong><?php echo $today_checkins['total_checkins'] ?? 0; ?></strong></small>
                                        </div>
                                        <div class="col-4">
                                            <small>Check-outs: <strong><?php echo $today_checkouts['total_checkouts'] ?? 0; ?></strong></small>
                                        </div>
                                        <div class="col-4">
                                            <small>Upcoming: <strong><?php echo $upcoming_checkins['upcoming_checkins'] ?? 0; ?></strong></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Action Buttons -->
                        <div class="quick-actions text-center">
                            <a href="quick-checkin.php" class="btn btn-success quick-action-btn">
                                <iconify-icon icon="mdi:login" class="me-1"></iconify-icon>
                                Quick Check-In
                            </a>
                            <a href="quick-checkout.php" class="btn btn-warning quick-action-btn">
                                <iconify-icon icon="mdi:logout" class="me-1"></iconify-icon>
                                Quick Check-Out
                            </a>
                            <a href="add-booking.php" class="btn btn-primary quick-action-btn">
                                <iconify-icon icon="mdi:calendar-plus" class="me-1"></iconify-icon>
                                New Booking
                            </a>
                            <a href="manage-rooms.php" class="btn btn-info quick-action-btn">
                                <iconify-icon icon="mdi:bed" class="me-1"></iconify-icon>
                                Manage Rooms
                            </a>
                            <a href="reports.php" class="btn btn-secondary quick-action-btn">
                                <iconify-icon icon="mdi:chart-bar" class="me-1"></iconify-icon>
                                Reports
                            </a>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-white border-bottom">
                                <h4 class="card-title mb-0">
                                    <iconify-icon icon="mdi:view-dashboard" class="me-2"></iconify-icon>
                                    Property Overview
                                </h4>
                            </div>
                            
                            <div class="card-body">
                                <!-- Summary Cards -->
                                <div class="row mb-4 dashboard">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary room-status-available text-white">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($summary_data['available_rooms']) ? $summary_data['available_rooms'] : '0'; ?></div>
                                                <div class="stat-label">Available Rooms</div>
                                                <small>Ready for booking</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary room-status-occupied text-white">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($summary_data['occupied_rooms']) ? $summary_data['occupied_rooms'] : '0'; ?></div>
                                                <div class="stat-label">Occupied Rooms</div>
                                                <small>Currently occupied</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary room-status-reserved text-white">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($summary_data['reserved_rooms']) ? $summary_data['reserved_rooms'] : '0'; ?></div>
                                                <div class="stat-label">Reserved Rooms</div>
                                                <small>Upcoming bookings</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary room-status-maintenance text-dark">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($summary_data['maintenance_rooms']) ? $summary_data['maintenance_rooms'] : '0'; ?></div>
                                                <div class="stat-label">Under Maintenance</div>
                                                <small>Unavailable</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Second Row of Stats -->
                                <div class="row mb-4">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary room-status-cleaning text-white">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($summary_data['cleaning_rooms']) ? $summary_data['cleaning_rooms'] : '0'; ?></div>
                                                <div class="stat-label">Cleaning</div>
                                                <small>Being cleaned</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary bg-primary text-white">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($today_checkins['total_checkins']) ? $today_checkins['total_checkins'] : '0'; ?></div>
                                                <div class="stat-label">Today's Check-ins</div>
                                                <small>Arrivals</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary bg-info text-white">
                                            <div class="card-body text-center">
                                                <div class="stat-number"><?php echo isset($today_checkouts['total_checkouts']) ? $today_checkouts['total_checkouts'] : '0'; ?></div>
                                                <div class="stat-label">Today's Check-outs</div>
                                                <small>Departures</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="card card-summary bg-dark text-white">
                                            <div class="card-body text-center">
                                                <?php 
                                                $occupancy_rate = 0;
                                                if (isset($summary_data['total_rooms']) && $summary_data['total_rooms'] > 0) {
                                                    $occupied_reserved = ($summary_data['occupied_rooms'] + $summary_data['reserved_rooms']);
                                                    $occupancy_rate = ($occupied_reserved / $summary_data['total_rooms']) * 100;
                                                }
                                                ?>
                                                <div class="occupancy-rate"><?php echo number_format($occupancy_rate, 1); ?>%</div>
                                                <div class="stat-label">Occupancy Rate</div>
                                                <small>Current</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Revenue and Today's Activity -->
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">
                                                    <iconify-icon icon="mdi:chart-line" class="me-2"></iconify-icon>
                                                    Revenue Trend (Last 6 Months)
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="revenueChart" height="120"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">
                                                    <iconify-icon icon="mdi:cash-multiple" class="me-2"></iconify-icon>
                                                    Today's Revenue
                                                </h5>
                                            </div>
                                            <div class="card-body text-center">
                                                <div class="display-4 text-success mb-2">
                                                    ₹<?php echo isset($today_checkins['today_revenue']) ? number_format($today_checkins['today_revenue']) : '0'; ?>
                                                </div>
                                                <p class="text-muted mb-1">From <?php echo isset($today_checkins['total_checkins']) ? $today_checkins['total_checkins'] : '0'; ?> check-ins</p>
                                                <?php if (isset($today_checkouts['checkout_revenue']) && $today_checkouts['checkout_revenue'] > 0): ?>
                                                    <p class="text-muted mb-0">+ ₹<?php echo number_format($today_checkouts['checkout_revenue']); ?> from check-outs</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Activity and Room Distribution -->
                                <div class="row">
                                    <!-- Recent Bookings -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">
                                                    <iconify-icon icon="mdi:bookmark-multiple" class="me-2"></iconify-icon>
                                                    Recent Bookings
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($recent_bookings)): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm recent-bookings-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Guest</th>
                                                                    <th>Room</th>
                                                                    <th>Amount</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($recent_bookings as $booking): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <small class="fw-bold"><?php echo htmlspecialchars($booking['guest_name']); ?></small><br>
                                                                            <small class="text-muted"><?php echo $booking['booking_reference']; ?></small>
                                                                        </td>
                                                                        <td>
                                                                            <small><?php echo htmlspecialchars($booking['room_number']); ?></small><br>
                                                                            <small class="text-muted"><?php echo htmlspecialchars($booking['room_type']); ?></small>
                                                                        </td>
                                                                        <td>
                                                                            <small class="fw-bold">₹<?php echo number_format($booking['total_amount']); ?></small>
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge booking-status-<?php echo $booking['status']; ?>">
                                                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <a href="bookings.php" class="btn btn-outline-primary btn-sm w-100 mt-2">View All Bookings</a>
                                                <?php else: ?>
                                                    <p class="text-muted text-center py-3">No recent bookings</p>
                                                    <a href="add-booking.php" class="btn btn-primary btn-sm w-100">Create First Booking</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Room Type Distribution -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">
                                                    <iconify-icon icon="mdi:chart-pie" class="me-2"></iconify-icon>
                                                    Room Type Distribution
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($room_type_data)): ?>
                                                    <div class="room-type-distribution">
                                                        <?php foreach ($room_type_data as $type): ?>
                                                            <div class="mb-3">
                                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                                    <span class="fw-bold"><?php echo htmlspecialchars($type['room_type']); ?></span>
                                                                    <span class="text-muted">
                                                                        <?php echo $type['occupied_count']; ?>/<?php echo $type['room_count']; ?> occupied
                                                                    </span>
                                                                </div>
                                                                <div class="progress">
                                                                    <div class="progress-bar bg-success" 
                                                                         style="width: <?php echo $type['room_count'] > 0 ? ($type['occupied_count'] / $type['room_count']) * 100 : 0; ?>%">
                                                                    </div>
                                                                </div>
                                                                <small class="text-muted">
                                                                    Available: <?php echo $type['available_count']; ?> | 
                                                                    Occupancy: <?php echo $type['room_count'] > 0 ? number_format(($type['occupied_count'] / $type['room_count']) * 100, 1) : 0; ?>%
                                                                </small>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted text-center py-3">No room types configured</p>
                                                    <a href="room-types.php" class="btn btn-primary btn-sm w-100">Add Room Types</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Today's Arrivals and Departures -->
                                <div class="row mt-4">
                                    <!-- Today's Arrivals -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-success text-white">
                                                <h5 class="card-title mb-0">
                                                    <iconify-icon icon="mdi:calendar-today" class="me-2"></iconify-icon>
                                                    Today's Arrivals (<?php echo count($today_arrivals); ?>)
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($today_arrivals)): ?>
                                                    <?php foreach ($today_arrivals as $arrival): ?>
                                                        <div class="arrival-departure-card">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1"><?php echo htmlspecialchars($arrival['guest_name']); ?></h6>
                                                                    <p class="mb-1 text-muted">Room: <?php echo htmlspecialchars($arrival['room_number']); ?></p>
                                                                    <small class="text-muted">Ref: <?php echo $arrival['booking_reference']; ?></small>
                                                                    <?php if (!empty($arrival['special_requests'])): ?>
                                                                        <p class="mb-0 mt-1"><small>Requests: <?php echo htmlspecialchars($arrival['special_requests']); ?></small></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <span class="badge bg-success">Arriving</span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted text-center py-3">No arrivals scheduled for today</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Today's Departures -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-warning text-dark">
                                                <h5 class="card-title mb-0">
                                                    <iconify-icon icon="mdi:calendar-arrow-right" class="me-2"></iconify-icon>
                                                    Today's Departures (<?php echo count($today_departures); ?>)
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($today_departures)): ?>
                                                    <?php foreach ($today_departures as $departure): ?>
                                                        <div class="arrival-departure-card departure">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1"><?php echo htmlspecialchars($departure['guest_name']); ?></h6>
                                                                    <p class="mb-1 text-muted">Room: <?php echo htmlspecialchars($departure['room_number']); ?></p>
                                                                    <small class="text-muted">Ref: <?php echo $departure['booking_reference']; ?></small>
                                                                    <?php if (!empty($departure['special_requests'])): ?>
                                                                        <p class="mb-0 mt-1"><small>Notes: <?php echo htmlspecialchars($departure['special_requests']); ?></small></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <span class="badge bg-warning text-dark">Departing</span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted text-center py-3">No departures scheduled for today</p>
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
    
    <!-- Enhanced Android Session Protection for Dashboard -->
    <script>
    // Enhanced Android Session Protection for Dashboard
    function setupDashboardSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('🏨 Room Dashboard: Setting up Android session protection');
        
        // Force immediate cookie update
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
                console.log('🔧 Room Dashboard: Initial cookie update completed');
            }
        }, 1000);
        
        // Additional protection for page transitions
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && WTN.forceUpdateCookies) {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                    console.log('🔧 Room Dashboard: Page restored from cache - cookies updated');
                }, 500);
            }
        });
        
        // Enhanced visibility change handling for dashboard
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
                // Dashboard became visible - force cookie update
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                    console.log('🏨 Room Dashboard: Visibility change - cookies updated');
                }, 300);
            }
        });
    }

    // Initialize dashboard protection
    document.addEventListener('DOMContentLoaded', function() {
        setupDashboardSessionProtection();
        
        // Enhanced session monitoring for room dashboard
        if (typeof WTN !== 'undefined') {
            // More frequent updates for room management system
            setInterval(() => {
                if (WTN.forceUpdateCookies) {
                    WTN.forceUpdateCookies();
                    console.log('🏨 Room Dashboard: Periodic cookie update');
                }
            }, 45000); // Every 45 seconds for room dashboard
            
            // Update cookies on room management activities
            const roomActivities = ['mousemove', 'keydown', 'click', 'touchstart'];
            roomActivities.forEach(activity => {
                document.addEventListener(activity, () => {
                    setTimeout(() => {
                        if (WTN.forceUpdateCookies) {
                            WTN.forceUpdateCookies();
                        }
                    }, 2000);
                }, { passive: true });
            });
        }
    });

    // Enhanced dashboard-specific session management
    class RoomDashboardSessionManager {
        constructor() {
            this.isAndroidApp = <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>;
            this.isWebToNative = typeof WTN !== 'undefined';
            this.init();
        }

        init() {
            if (this.isWebToNative) {
                console.log('🏨 Room Dashboard Session Manager: WebToNative detected');
                this.startDashboardSessionMaintenance();
            }
        }

        startDashboardSessionMaintenance() {
            // More frequent updates for room dashboard (every 30 seconds)
            setInterval(() => {
                this.maintainDashboardSession();
            }, 30000);
        }

        maintainDashboardSession() {
            if (!this.isWebToNative) return;

            // Update cookies
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }

            // Send session ping
            this.sendDashboardPing();
        }

        async sendDashboardPing() {
            try {
                await fetch('session-keepalive.php', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'X-Dashboard-Ping': 'true',
                        'X-WebToNative': 'true',
                        'X-Room-Dashboard': 'true'
                    }
                });
                console.log('🏨 Room Dashboard: Session ping sent');
            } catch (error) {
                console.log('🏨 Room Dashboard: Ping failed (app may be in background)');
            }
        }

        // Force session refresh for dashboard
        forceDashboardSessionRefresh() {
            if (this.isWebToNative && WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
                this.sendDashboardPing();
                console.log('🔧 Room Dashboard: Forced session refresh');
            }
        }
    }

    // Initialize dashboard session manager
    document.addEventListener('DOMContentLoaded', function() {
        window.roomDashboardSessionManager = new RoomDashboardSessionManager();
    });
    </script>
    
    <script>
        $(document).ready(function() {
            // Initialize Revenue Chart
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            <?php if (!empty($revenue_data)): ?>
                const monthLabels = <?php echo json_encode(array_column($revenue_data, 'month')); ?>;
                const revenueData = <?php echo json_encode(array_column($revenue_data, 'monthly_revenue')); ?>;
                const bookingData = <?php echo json_encode(array_column($revenue_data, 'monthly_bookings')); ?>;
                
                // Format month labels for better display
                const formattedLabels = monthLabels.map(month => {
                    const date = new Date(month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
                });
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: formattedLabels,
                        datasets: [
                            {
                                label: 'Revenue (₹)',
                                data: revenueData,
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                borderColor: 'rgba(40, 167, 69, 1)',
                                borderWidth: 3,
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Bookings',
                                data: bookingData,
                                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                                borderColor: 'rgba(23, 162, 184, 1)',
                                borderWidth: 2,
                                tension: 0.4,
                                fill: true,
                                type: 'bar',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Revenue & Booking Trend',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label.includes('Revenue')) {
                                            label += ': ₹' + context.raw.toLocaleString();
                                        } else {
                                            label += ': ' + context.raw;
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Revenue (₹)'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Bookings'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        }
                    }
                });
            <?php else: ?>
                // Empty chart when no data
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['No Data Available'],
                        datasets: [{
                            label: 'Revenue',
                            data: [0],
                            borderColor: 'rgba(200, 200, 200, 0.5)',
                            borderWidth: 1,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'No revenue data available yet'
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            <?php endif; ?>

            // Auto-refresh dashboard every 2 minutes
            setInterval(() => {
                // Simple page refresh for demo purposes
                // In production, you might want to use AJAX to update specific components
                console.log('🔄 Auto-refreshing dashboard data...');
                // Uncomment the line below to enable auto-refresh
                // window.location.reload();
            }, 120000); // 2 minutes

            // Add smooth animations to cards
            $('.card-summary').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );

            // Android session maintenance
            function androidSessionMaintenance() {
                if (navigator.userAgent.includes('WebToNative') || <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>) {
                    setInterval(() => {
                        $.ajax({
                            url: 'session-keepalive.php',
                            method: 'GET',
                            xhrFields: {
                                withCredentials: true
                            },
                            success: function(data) {
                                console.log('🏨 Room Dashboard Android session maintained');
                            },
                            error: function(xhr, status, error) {
                                console.warn('🏨 Room Dashboard Android session maintenance failed:', error);
                            }
                        });
                    }, 60000); // Every minute for Android apps
                }
            }

            // Start Android session maintenance
            androidSessionMaintenance();
        });
    </script>

    <!-- Floating Action Buttons -->
    <div class="floating-buttons">
        <a href="tel:9004998995" class="floating-btn call-btn" data-tooltip="Call Support: 9004998995">
            <span class="nav-icon">
                <iconify-icon icon="material-symbols:add-call-sharp"></iconify-icon>
            </span>
        </a>
        <a href="https://wa.me/919004998995?text=Hello!%20I%20need%20help%20with%20room%20management" 
           target="_blank" 
           class="floating-btn whatsapp-btn" 
           data-tooltip="WhatsApp Support: 9004998995">
            <span class="nav-icon">
                <iconify-icon icon="mingcute:whatsapp-line"></iconify-icon>
            </span>
        </a>
    </div>

    <script>
    // Enhanced session monitoring for room management
    function startEnhancedSessionMonitoring() {
        const isAndroid = <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>;
        
        // Health check every 2 minutes
        setInterval(() => {
            $.get('session_health_check.php')
                .done(data => {
                    if (data.session_active) {
                        console.log('✅ Room Dashboard Session health check passed');
                    } else {
                        console.error('❌ Room Dashboard Session health check failed');
                        if (isAndroid) {
                            window.location.href = 'login.php?session_expired=true';
                        }
                    }
                })
                .fail(() => {
                    console.error('❌ Room Dashboard Health check request failed');
                });
        }, 120000); // 2 minutes
        
        // More frequent heartbeat for Android
        if (isAndroid) {
            setInterval(() => {
                $.get('heartbeat.php')
                    .done(data => {
                        if (data.success) {
                            console.log('❤️ Room Dashboard Android heartbeat maintained');
                        }
                    });
            }, 300000); // 5 minutes
        }
    }

    // Initialize enhanced monitoring
    document.addEventListener('DOMContentLoaded', function() {
        startEnhancedSessionMonitoring();
    });
    </script>
</body>
</html>