<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// In your server configuration or PHP file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
// Start the session
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once 'session_check.php';

// Check if the user is logged in and has rider role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rider') {
    header("Location: login.php");
    exit();
}

// Include the database connection file
require 'db_connection.php';

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

// Today's delivery summary
$summary_sql = "SELECT 
                  COUNT(*) as total_deliveries,
                  SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
                  SUM(CASE WHEN status = 'picked_up' THEN 1 ELSE 0 END) as in_transit_deliveries,
                  SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as pending_deliveries,
                  SUM(delivery_charge) as total_earnings,
                  AVG(TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at)) as avg_delivery_time
                FROM deliveries 
                WHERE rider_id = ? 
                AND DATE(assigned_at) = ?";
                
$stmt = $conn->prepare($summary_sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$summary_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Recent deliveries
$recent_deliveries_sql = "SELECT 
                    d.id, d.tracking_number, d.pickup_address, d.delivery_address, 
                    d.status, d.delivery_charge, d.assigned_at,
                    c.name as customer_name
                  FROM deliveries d
                  LEFT JOIN customers c ON d.customer_id = c.id
                  WHERE d.rider_id = ? 
                  ORDER BY d.assigned_at DESC 
                  LIMIT 5";
                  
$stmt = $conn->prepare($recent_deliveries_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get pending deliveries for quick action
$pending_deliveries_sql = "SELECT COUNT(*) as pending_count 
                          FROM deliveries 
                          WHERE rider_id = ? 
                          AND status IN ('assigned', 'picked_up')";
$stmt = $conn->prepare($pending_deliveries_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Rider Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-summary {
            transition: all 0.3s ease;
        }
        .card-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .delivery-status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-assigned { background-color: #fff3cd; color: #856404; }
        .status-picked_up { background-color: #cce7ff; color: #004085; }
        .status-delivered { background-color: #d4edda; color: #155724; }
        .quick-action-card {
            border-left: 4px solid #007bff;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($has_active_subscription || ($is_trial && strtotime($trial_end) > time())) {
            include 'rider_menu.php';
        } else {
            include 'unsubscriber_menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Display trial notification if user is on trial -->
                        <?php if (!empty($trial_notification)) echo $trial_notification; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Rider Dashboard - <?php echo date('F j, Y'); ?></h4>
                                <p class="card-subtitle">Welcome, <?php echo htmlspecialchars($user_name); ?>! Here's your delivery overview for today.</p>
                            </div>
                            
                            <div class="card-body">
                                <!-- Summary Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-primary text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Total Deliveries</h5>
                                                <h3 class="card-text"><?php echo isset($summary_data['total_deliveries']) ? $summary_data['total_deliveries'] : '0'; ?></h3>
                                                <p class="card-text">Assigned today</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-success text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Completed</h5>
                                                <h3 class="card-text"><?php echo isset($summary_data['completed_deliveries']) ? $summary_data['completed_deliveries'] : '0'; ?></h3>
                                                <p class="card-text">Successfully delivered</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-warning text-dark">
                                            <div class="card-body">
                                                <h5 class="card-title">In Transit</h5>
                                                <h3 class="card-text"><?php echo isset($summary_data['in_transit_deliveries']) ? $summary_data['in_transit_deliveries'] : '0'; ?></h3>
                                                <p class="card-text">Currently delivering</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-info text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Today's Earnings</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_earnings']) ? number_format($summary_data['total_earnings']) : '0'; ?></h3>
                                                <p class="card-text">Delivery charges</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Deliveries Alert -->
                                <?php if ($pending_count > 0): ?>
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card quick-action-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="card-title text-primary mb-1">
                                                            <i class="fas fa-exclamation-circle me-2"></i>
                                                            You have <?php echo $pending_count; ?> pending delivery<?php echo $pending_count > 1 ? 'ies' : ''; ?>
                                                        </h5>
                                                        <p class="card-text text-muted mb-0">These deliveries need your attention.</p>
                                                    </div>
                                                    <a href="my-deliveries.php" class="btn btn-primary">
                                                        <i class="fas fa-shipping-fast me-2"></i>View Deliveries
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Recent Deliveries Section -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Recent Deliveries</h5>
                                                <a href="my-deliveries.php" class="btn btn-sm btn-primary">View All Deliveries</a>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($recent_deliveries)): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Tracking No</th>
                                                                    <th>Customer</th>
                                                                    <th>Pickup Address</th>
                                                                    <th>Delivery Address</th>
                                                                    <th>Charge</th>
                                                                    <th>Status</th>
                                                                    <th>Assigned</th>
                                                                    <th>Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($recent_deliveries as $delivery): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($delivery['tracking_number']); ?></td>
                                                                        <td><?php echo htmlspecialchars($delivery['customer_name']); ?></td>
                                                                        <td><?php echo htmlspecialchars(substr($delivery['pickup_address'], 0, 30)) . '...'; ?></td>
                                                                        <td><?php echo htmlspecialchars(substr($delivery['delivery_address'], 0, 30)) . '...'; ?></td>
                                                                        <td>₹<?php echo number_format($delivery['delivery_charge']); ?></td>
                                                                        <td>
                                                                            <span class="delivery-status-badge status-<?php echo $delivery['status']; ?>">
                                                                                <?php echo ucwords(str_replace('_', ' ', $delivery['status'])); ?>
                                                                            </span>
                                                                        </td>
                                                                        <td><?php echo date('M j, g:i A', strtotime($delivery['assigned_at'])); ?></td>
                                                                        <td>
                                                                            <a href="view-delivery.php?id=<?php echo $delivery['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-4">
                                                        <p class="text-muted">No deliveries assigned today.</p>
                                                        <p class="text-muted">Check back later for new delivery assignments.</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Quick Actions</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-3 mb-3">
                                                        <a href="my-deliveries.php" class="btn btn-primary w-100">
                                                            <i class="fas fa-shipping-fast me-2"></i>My Deliveries
                                                            <?php if ($pending_count > 0): ?>
                                                                <span class="badge bg-danger ms-1"><?php echo $pending_count; ?></span>
                                                            <?php endif; ?>
                                                        </a>
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <a href="available-deliveries.php" class="btn btn-success w-100">
                                                            <i class="fas fa-list me-2"></i>Available Deliveries
                                                        </a>
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <a href="rider-profile.php" class="btn btn-info w-100">
                                                            <i class="fas fa-user me-2"></i>My Profile
                                                        </a>
                                                    </div>
                                                    <div class="col-md-3 mb-3">
                                                        <a href="rider-reports.php" class="btn btn-warning w-100">
                                                            <i class="fas fa-chart-bar me-2"></i>Earnings Report
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
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize any dashboard-specific JavaScript here
            console.log('Rider Dashboard loaded successfully');
            
            // Auto-refresh deliveries every 30 seconds
            setInterval(function() {
                $.get('check-new-deliveries.php', function(data) {
                    if (data.new_deliveries > 0) {
                        // Show notification for new deliveries
                        showNotification('New delivery assigned!', 'success');
                    }
                });
            }, 30000);
        });

        function showNotification(message, type) {
            // Simple notification function
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container-fluid').prepend(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    </script>

    <!-- Floating Action Buttons -->
    <div class="floating-buttons">
        <a href="tel:9004998995" class="floating-btn call-btn" data-tooltip="Call Us: 9004998995">
            <span class="nav-icon">
                <iconify-icon icon="material-symbols:add-call-sharp"></iconify-icon>
            </span>
        </a>
        <a href="https://wa.me/919004998995?text=Hello!%20I%20have%20a%20question%20about%20delivery%20services" 
           target="_blank" 
           class="floating-btn whatsapp-btn" 
           data-tooltip="WhatsApp: 9004998995">
            <span class="nav-icon">
                <iconify-icon icon="mingcute:whatsapp-line"></iconify-icon>
            </span>
        </a>
    </div>














<!-- OneSignal Integration for Dashboard -->
<script>
// OneSignal Dashboard Manager
class OneSignalDashboardManager {
    constructor() {
        this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        this.init();
    }

    init() {
        console.log('🚀 Initializing OneSignal on Dashboard for user:', this.userId);
        
        if (this.userId) {
            // Initialize OneSignal registration
            this.initializeOneSignal();
        }
    }

    initializeOneSignal() {
        // Check if we have a pending player ID from login
        const pendingPlayerId = localStorage.getItem('pending_player_id');
        
        if (pendingPlayerId) {
            console.log('🔄 Completing device registration from login...');
            this.registerDevice(
                pendingPlayerId,
                localStorage.getItem('pending_device_type') || 'web_browser',
                localStorage.getItem('pending_platform') || 'web'
            );
        } else {
            // Initialize fresh OneSignal registration
            this.detectAndRegister();
        }
    }

    detectAndRegister() {
        // WebToNative environment
        if (typeof WTN !== 'undefined' && WTN.OneSignal) {
            console.log('📱 WebToNative detected on dashboard');
            this.initializeWebToNative();
        } 
        // Regular OneSignal environment
        else if (typeof OneSignal !== 'undefined') {
            console.log('🌐 Web OneSignal detected on dashboard');
            this.initializeWebOneSignal();
        }
    }

    initializeWebToNative() {
        const { getPlayerId, setExternalUserId } = WTN.OneSignal;
        
        getPlayerId().then((playerId) => {
            if (playerId) {
                console.log('✅ WebToNative Player ID on dashboard:', playerId);
                this.registerDevice(playerId, 'android_webtonative', 'android');
                setExternalUserId(this.userId.toString());
            }
        }).catch(error => {
            console.error('❌ WebToNative error on dashboard:', error);
        });
    }

    initializeWebOneSignal() {
        window.OneSignal = window.OneSignal || [];
        
        OneSignal.push(() => {
            OneSignal.init({
                appId: "9d512a16-1b7c-4d2c-ae9f-07c36c963086",
                safari_web_id: "",
                notifyButton: { enable: false },
                allowLocalhostAsSecureOrigin: true,
            });

            OneSignal.getUserId((playerId) => {
                if (playerId) {
                    console.log('✅ Web OneSignal Player ID on dashboard:', playerId);
                    this.registerDevice(playerId, 'web_browser', 'web');
                    OneSignal.setExternalUserId(this.userId.toString());
                }
            });
        });
    }

    registerDevice(playerId, deviceType, platform) {
        const payload = {
            player_id: playerId,
            device_type: deviceType,
            platform: platform,
            user_id: this.userId,
            source: (deviceType === 'android_webtonative') ? 'webtonative_app' : 'web_browser'
        };

        fetch('register_device_unified.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Device registered on dashboard:', data.message);
                
                // Clear any pending registration data
                localStorage.removeItem('pending_player_id');
                localStorage.removeItem('pending_device_type');
                localStorage.removeItem('pending_platform');
                
                // Store successful registration
                localStorage.setItem('onesignal_registered', 'true');
                localStorage.setItem('player_id', playerId);
                localStorage.setItem('user_id', this.userId.toString());
                
            } else {
                console.error('❌ Device registration failed on dashboard:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ Device registration request failed on dashboard:', error);
        });
    }
}

// Initialize on dashboard
document.addEventListener('DOMContentLoaded', function() {
    if (<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
        window.oneSignalDashboardManager = new OneSignalDashboardManager();
    }
});
</script>
<!-- OneSignal Integration for Dashboard -->
</body>
</html>