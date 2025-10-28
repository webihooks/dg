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

// Include the database connection file
require 'db_connection.php';

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

// Today's sales summary
$summary_sql = "SELECT 
                  COUNT(*) as total_orders,
                  SUM(total_amount) as total_sales,
                  SUM(subtotal) as subtotal,
                  SUM(discount_amount) as total_discounts,
                  SUM(gst_amount) as total_tax,
                  SUM(delivery_charge) as total_delivery,
                  AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE user_id = ? 
                AND status != 'cancelled'
                AND DATE(created_at) = ?";
                
$stmt = $conn->prepare($summary_sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$summary_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Hourly sales data for today's chart
$hourly_sales_sql = "SELECT 
                    HOUR(created_at) as sale_hour,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_sales
                  FROM orders 
                  WHERE user_id = ? 
                  AND status != 'cancelled'
                  AND DATE(created_at) = ?
                  GROUP BY HOUR(created_at)
                  ORDER BY sale_hour ASC";
                  
$stmt = $conn->prepare($hourly_sales_sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$sales_data = [];
while ($row = $result->fetch_assoc()) {
    $sales_data[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-summary {
            transition: all 0.3s ease;
        }
        .card-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .today-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .dashboard .col-md-3 {
                width: 50%;
            }
            .dashboard .col-md-3 .card-body {
                height: 160px !important;
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
    </style>
</head>

<body>

    <!-- Session Status Indicator -->
    <div class="session-status-android <?php echo $sessionManager->isAndroidApp() ? 'android' : 'web'; ?>" id="sessionStatusIndicator">
        <?php echo $sessionManager->isAndroidApp() ? '📱 Android App - Session Active (365 Days)' : '🌐 Web - Session Active'; ?>
    </div>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            if ($has_active_subscription || ($is_trial && strtotime($trial_end) > time())) {
                include 'menu.php';
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
                        
                        <!-- Session Info Alert for Android -->
                        <?php if ($sessionManager->isAndroidApp()): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-3">
                            <i class="fas fa-mobile-alt me-2"></i>
                            <strong>Android App Session:</strong> Your session is configured to remain active for 365 days. 
                            You will stay logged in unless you manually log out.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Today's Sales Report - <?php echo date('F j, Y'); ?></h4>
                                <?php if ($sessionManager->isAndroidApp()): ?>
                                <span class="badge bg-success float-end">Android App</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <!-- Summary Cards -->
                                <div class="row mb-4 dashboard">
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-primary text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Today's Sales</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_sales']) ? number_format($summary_data['total_sales']) : '0'; ?></h3>
                                                <p class="card-text mb-0"><?php echo isset($summary_data['total_orders']) ? $summary_data['total_orders'] : '0'; ?> orders</p>
                                                <?php if (isset($summary_data['avg_order_value']) && $summary_data['total_orders'] > 0): ?>
                                                    <p class="card-text">Avg: ₹<?php echo number_format($summary_data['avg_order_value']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-success text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Subtotal</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['subtotal']) ? number_format($summary_data['subtotal']) : '0'; ?></h3>
                                                <p class="card-text mb-0">Before discounts & taxes</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-info text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Taxes & Charges</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_tax']) ? number_format($summary_data['total_tax'] + ($summary_data['total_delivery'] ?? 0)) : '0'; ?></h3>
                                                <p class="card-text mb-0">GST: ₹<?php echo isset($summary_data['total_tax']) ? number_format($summary_data['total_tax']) : '0'; ?></p>
                                                <p class="card-text">Delivery: ₹<?php echo isset($summary_data['total_delivery']) ? number_format($summary_data['total_delivery']) : '0'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-warning text-dark">
                                            <div class="card-body">
                                                <h5 class="card-title">Discounts</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_discounts']) ? number_format($summary_data['total_discounts']) : '0'; ?></h3>
                                                <p class="card-text">Applied to orders</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chart Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Hourly Sales Trend</h5>
                                                <canvas id="salesChart" height="100"></canvas>
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
            // Initialize Chart
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            <?php if (!empty($sales_data)): ?>
                // Prepare all hours (0-23) with default 0 values
                const allHours = Array.from({length: 24}, (_, i) => i);
                const salesByHour = Array(24).fill(0);
                const ordersByHour = Array(24).fill(0);
                
                // Fill in the actual data
                <?php foreach ($sales_data as $row): ?>
                    salesByHour[<?php echo $row['sale_hour']; ?>] = <?php echo $row['total_sales']; ?>;
                    ordersByHour[<?php echo $row['sale_hour']; ?>] = <?php echo $row['total_orders']; ?>;
                <?php endforeach; ?>
                
                // Format hours for display (e.g., "12 PM")
                const hourLabels = allHours.map(hour => {
                    return hour === 0 ? '12 AM' : 
                           hour < 12 ? hour + ' AM' : 
                           hour === 12 ? '12 PM' : 
                           (hour - 12) + ' PM';
                });
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: hourLabels,
                        datasets: [
                            {
                                label: 'Sales (₹)',
                                data: salesByHour,
                                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Orders',
                                data: ordersByHour,
                                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 1,
                                type: 'line',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Today\'s Sales by Hour'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label.includes('Sales')) {
                                            label += ': ₹' + context.raw.toFixed(2);
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
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Sales (₹)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Orders'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            <?php else: ?>
                // Empty chart when no data
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: Array.from({length: 24}, (_, i) => {
                            return i === 0 ? '12 AM' : 
                                   i < 12 ? i + ' AM' : 
                                   i === 12 ? '12 PM' : 
                                   (i - 12) + ' PM';
                        }),
                        datasets: []
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'No sales data available for today yet'
                            }
                        }
                    }
                });
            <?php endif; ?>

            // Session status indicator management
            function updateSessionStatus() {
                const indicator = $('#sessionStatusIndicator');
                if (indicator.length) {
                    // Check session status every 30 seconds
                    setInterval(() => {
                        $.get('session-keepalive.php', function(data) {
                            if (data.status === 'success') {
                                indicator.removeClass('warning').addClass('<?php echo $sessionManager->isAndroidApp() ? "android" : "web"; ?>');
                                console.log('Session active - last activity:', data.last_activity);
                            } else {
                                indicator.removeClass('android web').addClass('warning');
                                indicator.text('⚠️ Session Warning');
                            }
                        }).fail(() => {
                            indicator.removeClass('android web').addClass('warning');
                            indicator.text('⚠️ Connection Issue');
                        });
                    }, 30000);
                }
            }

            // Initialize session status monitoring
            updateSessionStatus();

            // Android-specific session maintenance
            function androidSessionMaintenance() {
                // For Android apps, send periodic keep-alive requests
                if (navigator.userAgent.includes('WebToNative') || <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>) {
                    setInterval(() => {
                        $.ajax({
                            url: 'session-keepalive.php',
                            method: 'GET',
                            xhrFields: {
                                withCredentials: true
                            },
                            success: function(data) {
                                console.log('Android session maintained:', data);
                            },
                            error: function(xhr, status, error) {
                                console.warn('Android session maintenance failed:', error);
                            }
                        });
                    }, 60000); // Every minute for Android apps
                }
            }

            // Start Android session maintenance
            androidSessionMaintenance();

            // Check availability button click handler
            $('#checkAvailability').click(function() {
                const profileUrl = $('#profile_url').val().trim();
                const availabilityMessage = $('#availabilityMessage');
                
                if (!profileUrl) {
                    availabilityMessage.html('<span class="text-danger">Please enter a profile URL</span>');
                    return;
                }
                
                // Check if the input matches the allowed pattern
                if (!/^[a-zA-Z0-9-]+$/.test(profileUrl)) {
                    availabilityMessage.html('<span class="text-danger">Only letters, numbers, and hyphens are allowed</span>');
                    return;
                }
                
                // Show loading
                availabilityMessage.html('<span class="text-info">Checking availability...</span>');
                
                // Make AJAX request
                $.get('?check_availability=1&profile_url=' + encodeURIComponent(profileUrl), function(response) {
                    if (response.available) {
                        availabilityMessage.html('<span class="text-success">This URL is available!</span>');
                    } else {
                        availabilityMessage.html('<span class="text-danger">This URL is already taken</span>');
                    }
                }).fail(function() {
                    availabilityMessage.html('<span class="text-danger">Error checking availability</span>');
                });
            });
            
            // Form validation
            $('#profileUrlForm').validate({
                rules: {
                    profile_url: {
                        required: true,
                        pattern: /^[a-zA-Z0-9-]+$/
                    }
                },
                messages: {
                    profile_url: {
                        required: "Please enter your profile URL",
                        pattern: "Only letters, numbers, and hyphens are allowed"
                    }
                },
                errorElement: "div",
                errorPlacement: function(error, element) {
                    error.addClass("invalid-feedback");
                    error.insertAfter(element.parent());
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                }
            });
            
            // Trigger check availability when user stops typing (after 1 second)
            let typingTimer;
            $('#profile_url').on('keyup', function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    if ($('#profile_url').valid()) {
                        $('#checkAvailability').trigger('click');
                    }
                }, 1000);
            });
            
            // Clear timer on keydown
            $('#profile_url').on('keydown', function() {
                clearTimeout(typingTimer);
            });
        });
    </script>

    <!-- Floating Action Buttons -->
    <div class="floating-buttons">
        <a href="tel:9004998995" class="floating-btn call-btn" data-tooltip="Call Us: 9004998995">
            <span class="nav-icon">
                <iconify-icon icon="material-symbols:add-call-sharp"></iconify-icon>
            </span>
        </a>
        <a href="https://wa.me/919004998995?text=Hello!%20I%20have%20a%20question%20about%20your%20services" 
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
            this.isAndroidApp = <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>;
            this.init();
        }

        init() {
            console.log('🚀 Initializing OneSignal on Dashboard for user:', this.userId);
            console.log('📱 Android App:', this.isAndroidApp);
            
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
            } else {
                console.log('ℹ️ No OneSignal environment detected');
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

    <!-- SIMPLIFIED OneSignal Registration -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    <script>
    // Enhanced Android-Only OneSignal Registration
    class AndroidOneSignalRegister {
        constructor() {
            this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
            console.log('🚀 Android Register - User ID:', this.userId);
            
            if (this.userId) {
                this.startAndroidRegistration();
            }
        }
        
        startAndroidRegistration() {
            console.log('🔄 Starting Android-only registration...');
            
            // ONLY attempt registration for Android WebToNative
            if (typeof WTN !== 'undefined' && WTN.OneSignal) {
                console.log('📱 Android WebToNative detected - registering...');
                this.registerViaWebToNative();
            } else {
                console.log('🌐 Web browser detected - skipping device registration');
            }
        }
        
        registerViaWebToNative() {
            WTN.OneSignal.getPlayerId().then(playerId => {
                if (playerId) {
                    console.log('✅ Got Android Player ID:', playerId);
                    this.sendRegistration(playerId, 'android_webtonative', 'android');
                } else {
                    console.log('❌ No Player ID from WebToNative');
                }
            }).catch(error => {
                console.error('❌ WebToNative error:', error);
            });
        }
        
        sendRegistration(playerId, deviceType, platform) {
            const payload = {
                player_id: playerId,
                device_type: deviceType,
                platform: platform,
                user_id: this.userId,
                source: 'android_only_script'
            };
            
            console.log('📨 Sending Android registration:', payload);
            
            fetch('register_device_unified.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                console.log('✅ Registration response:', data);
                if (data.success) {
                    if (data.skipped) {
                        console.log('ℹ️ Registration skipped:', data.reason);
                    } else {
                        console.log('🎉 ANDROID DEVICE REGISTERED SUCCESSFULLY!');
                    }
                } else {
                    console.error('❌ Registration failed:', data.message);
                }
            })
            .catch(error => {
                console.error('❌ Request failed:', error);
            });
        }
    }

    // Start Android-only registration when page loads
    document.addEventListener('DOMContentLoaded', function() {
        new AndroidOneSignalRegister();
    });
    </script>
<script>
// Add to dashboard.php JavaScript section
// Enhanced session monitoring
function startEnhancedSessionMonitoring() {
    const isAndroid = <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>;
    
    // Health check every 2 minutes
    setInterval(() => {
        $.get('session_health_check.php')
            .done(data => {
                if (data.session_active) {
                    console.log('✅ Session health check passed');
                    if (data.issues && data.issues.length > 0) {
                        console.warn('Session issues:', data.issues);
                    }
                } else {
                    console.error('❌ Session health check failed');
                    // Optionally redirect to login if session is completely dead
                    if (isAndroid) {
                        window.location.href = 'login.php?session_expired=true';
                    }
                }
            })
            .fail(() => {
                console.error('❌ Health check request failed');
            });
    }, 120000); // 2 minutes
    
    // More frequent heartbeat for Android
    if (isAndroid) {
        setInterval(() => {
            $.get('heartbeat.php')
                .done(data => {
                    if (data.success) {
                        console.log('❤️ Android heartbeat maintained');
                    }
                });
        }, 300000); // 5 minutes
    }
}

// Start enhanced monitoring when dashboard loads
$(document).ready(function() {
    startEnhancedSessionMonitoring();
    
    // Add device management link to toolbar or menu
    $('#navbar-nav').append(`
        <li class="nav-item">
            <a class="nav-link" href="device_management.php">
                <span class="nav-icon">
                    <iconify-icon icon="fas fa-mobile-alt"></iconify-icon>
                </span>
                <span class="nav-text">Device Management</span>
            </a>
        </li>
    `);
});
</script>
</body>
</html>