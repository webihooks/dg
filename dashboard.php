<?php
// In your server configuration or PHP file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Start the session
session_start();

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

// Get user details including trial information AND country
$user_sql = "SELECT name, role, is_trial, trial_end, country FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role, $is_trial, $trial_end, $user_country);
$user_stmt->fetch();
$user_stmt->close();

// Set tax label based on user's country
$tax_label = ($user_country === 'UAE') ? 'VAT' : 'GST';

// Set timezone based on user's country
switch ($user_country) {
    case 'India':
        date_default_timezone_set('Asia/Kolkata');
        break;
    case 'UAE':
        date_default_timezone_set('Asia/Dubai');
        break;
    case 'UK':
        date_default_timezone_set('Europe/London');
        break;
    case 'USA':
        // For USA, you might want to get more specific based on user's timezone preference
        // Defaulting to Eastern Time
        date_default_timezone_set('America/New_York');
        break;
    default:
        date_default_timezone_set('Asia/Kolkata'); // Default fallback
}

// Function to get currency symbol based on country
function getCurrencySymbol($country) {
    $currencySymbols = [
        'India' => '₹',
        'UAE' => 'AED',
        'UK' => '£',
        'USA' => '$'
    ];
    
    return isset($currencySymbols[$country]) ? $currencySymbols[$country] : '₹';
}

// Get currency symbol for current user
$currencySymbol = getCurrencySymbol($user_country);

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

// Function to adjust time for UAE users (subtract 1 hour 30 minutes)
function adjustTimeForUAE($dateTime, $user_country) {
    if ($user_country == 'UAE') {
        $date = new DateTime($dateTime);
        $date->modify('-1 hour -30 minutes');
        return $date->format('Y-m-d H:i:s');
    }
    return $dateTime;
}

// Get today's date for display and query - FIX FOR UAE TIMEZONE
if ($user_country === 'UAE') {
    // For UAE users, adjust the date by subtracting 1 hour 30 minutes for display
    $display_time = new DateTime('now', new DateTimeZone('Asia/Dubai'));
    $display_time->modify('-90 minutes');
    $today_display = $display_time->format('F j, Y');
    $today_timezone_display = 'UAE (Adjusted)';
    
    // For UAE users, we need to adjust the date range to account for timezone difference
    // When showing "today" for UAE users, we need to include orders from Indian time perspective
    // that correspond to the UAE user's today
    
    // UAE user's today (00:00 to 23:59 UAE time)
    $uae_start = new DateTime('today', new DateTimeZone('Asia/Dubai'));
    $uae_end = new DateTime('today', new DateTimeZone('Asia/Dubai'));
    $uae_end->modify('+1 day')->modify('-1 second');
    
    // Convert to Indian time for database query
    $uae_start->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $uae_end->setTimezone(new DateTimeZone('Asia/Kolkata'));
    
    // Get date parts for query
    $today_for_query_start = $uae_start->format('Y-m-d');
    $today_for_query_end = $uae_end->format('Y-m-d');
    
    // Note: We'll use CONVERT_TZ in the query for UAE users
} else {
    $today_display = date('F j, Y');
    $today_timezone_display = date_default_timezone_get();
    $today_for_query = date('Y-m-d');
}

// Today's sales summary - use appropriate date filtering based on country
if ($user_country === 'UAE') {
    // For UAE users, use CONVERT_TZ to properly handle timezone conversion
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
                    AND DATE(CONVERT_TZ(created_at, '+00:00', '+05:30')) BETWEEN ? AND ?";
                    
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("iss", $user_id, $today_for_query_start, $today_for_query_end);
} else {
    // For other countries, use standard date filtering
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
    $stmt->bind_param("is", $user_id, $today_for_query);
}

$stmt->execute();
$summary_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Hourly sales data for today's chart - use appropriate date filtering based on country
if ($user_country === 'UAE') {
    // For UAE users, use CONVERT_TZ to get hour in UAE time
    $hourly_sales_sql = "SELECT 
                        HOUR(CONVERT_TZ(created_at, '+00:00', '+04:00')) as sale_hour,
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_sales
                      FROM orders 
                      WHERE user_id = ? 
                      AND status != 'cancelled'
                      AND DATE(CONVERT_TZ(created_at, '+00:00', '+05:30')) BETWEEN ? AND ?
                      GROUP BY HOUR(CONVERT_TZ(created_at, '+00:00', '+04:00'))
                      ORDER BY sale_hour ASC";
                      
    $stmt = $conn->prepare($hourly_sales_sql);
    $stmt->bind_param("iss", $user_id, $today_for_query_start, $today_for_query_end);
} else {
    // For other countries
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
    $stmt->bind_param("is", $user_id, $today_for_query);
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA Meta Tags -->

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
        
        .uae-time-note {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
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
                        
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Today's Sales Report - <?php echo $today_display; ?></h4>
                            </div>
                            
                            <div class="card-body">
                                <!-- Summary Cards -->
                                <div class="row mb-4 dashboard">
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-primary text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Today's Sales</h5>
                                                <h3 class="card-text"><?php echo $currencySymbol; ?> <?php echo isset($summary_data['total_sales']) ? number_format($summary_data['total_sales']) : '0'; ?></h3>
                                                <p class="card-text mb-0"><?php echo isset($summary_data['total_orders']) ? $summary_data['total_orders'] : '0'; ?> orders</p>
                                                <?php if (isset($summary_data['avg_order_value']) && $summary_data['total_orders'] > 0): ?>
                                                    <p class="card-text">Avg: <?php echo $currencySymbol; ?> <?php echo number_format($summary_data['avg_order_value']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-success text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Subtotal</h5>
                                                <h3 class="card-text"><?php echo $currencySymbol; ?> <?php echo isset($summary_data['subtotal']) ? number_format($summary_data['subtotal']) : '0'; ?></h3>
                                                <p class="card-text mb-0">Before discounts & taxes</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-info text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Taxes & Charges</h5>
                                                <h3 class="card-text"><?php echo $currencySymbol; ?> <?php echo isset($summary_data['total_tax']) ? number_format($summary_data['total_tax'] + ($summary_data['total_delivery'] ?? 0)) : '0'; ?></h3>
                                                <p class="card-text mb-0"><?php echo $tax_label; ?>: <?php echo $currencySymbol; ?> <?php echo isset($summary_data['total_tax']) ? number_format($summary_data['total_tax']) : '0'; ?></p>
                                                <p class="card-text">Delivery: <?php echo $currencySymbol; ?> <?php echo isset($summary_data['total_delivery']) ? number_format($summary_data['total_delivery']) : '0'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-warning text-dark">
                                            <div class="card-body">
                                                <h5 class="card-title">Discounts</h5>
                                                <h3 class="card-text"><?php echo $currencySymbol; ?> <?php echo isset($summary_data['total_discounts']) ? number_format($summary_data['total_discounts']) : '0'; ?></h3>
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
                                                <h5 class="card-title">Hourly Sales Trend
                                                    <?php if ($user_country === 'UAE'): ?>
                                                        <span class="uae-time-note">(UAE Local Time)</span>
                                                    <?php endif; ?>
                                                </h5>
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
    
    <!-- Enhanced Android Session Protection for Dashboard -->
    <script>
    // Enhanced Android Session Protection for Dashboard
    function setupDashboardSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('📱 Dashboard: Setting up Android session protection');
        
        // Force immediate cookie update
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
                console.log('🔧 Dashboard: Initial cookie update completed');
            }
        }, 1000);
        
        // Additional protection for page transitions
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && WTN.forceUpdateCookies) {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                    console.log('🔧 Dashboard: Page restored from cache - cookies updated');
                }, 500);
            }
        });
        
        // Enhanced visibility change handling for dashboard
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
                // Dashboard became visible - force cookie update
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                    console.log('📱 Dashboard: Visibility change - cookies updated');
                }, 300);
            }
        });
        
        // Additional protection for order system interactions
        const originalAddEventListener = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function(type, listener, options) {
            if (type === 'click' && this.classList && (
                this.classList.contains('order-popup') || 
                this.id && this.id.includes('acceptOrder') || 
                this.id && this.id.includes('rejectOrder')
            )) {
                // Order system interaction - update cookies
                setTimeout(() => {
                    if (WTN.forceUpdateCookies) {
                        WTN.forceUpdateCookies();
                        console.log('🔧 Dashboard: Order interaction - cookies updated');
                    }
                }, 100);
            }
            return originalAddEventListener.call(this, type, listener, options);
        };
    }

    // Initialize dashboard protection
    document.addEventListener('DOMContentLoaded', function() {
        setupDashboardSessionProtection();
        
        // Additional protection for order system
        if (typeof WTN !== 'undefined') {
            // Override order system functions to include cookie updates
                const originalInitOrderSystem = window.initOrderSystem;
            if (originalInitOrderSystem) {
                window.initOrderSystem = function() {
                    if (WTN.forceUpdateCookies) {
                        WTN.forceUpdateCookies();
                        console.log('🔧 Order System: Cookies updated before initialization');
                    }
                    return originalInitOrderSystem.apply(this, arguments);
                };
            }
            
            // Enhanced session monitoring for dashboard
            setInterval(() => {
                if (WTN.forceUpdateCookies) {
                    WTN.forceUpdateCookies();
                    console.log('📱 Dashboard: Periodic cookie update');
                }
            }, 60000); // Every minute for dashboard
            
            // Update cookies on any significant dashboard activity
            const dashboardActivities = ['mousemove', 'keydown', 'scroll', 'touchstart'];
            dashboardActivities.forEach(activity => {
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
    class DashboardSessionManager {
        constructor() {
            this.isAndroidApp = <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>;
            this.isWebToNative = typeof WTN !== 'undefined';
            this.init();
        }

        init() {
            if (this.isWebToNative) {
                console.log('📱 Dashboard Session Manager: WebToNative detected');
                this.startDashboardSessionMaintenance();
            }
        }

        startDashboardSessionMaintenance() {
            // More frequent updates for dashboard (every 30 seconds)
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
                        'X-WebToNative': 'true'
                    }
                });
                console.log('📱 Dashboard: Session ping sent');
            } catch (error) {
                console.log('📱 Dashboard: Ping failed (app may be in background)');
            }
        }

        // Force session refresh for dashboard
        forceDashboardSessionRefresh() {
            if (this.isWebToNative && WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
                this.sendDashboardPing();
                console.log('🔧 Dashboard: Forced session refresh');
            }
        }
    }

    // Initialize dashboard session manager
    document.addEventListener('DOMContentLoaded', function() {
        window.dashboardSessionManager = new DashboardSessionManager();
    });
    </script>
    
    <script>
        $(document).ready(function() {
            // Get currency symbol from PHP
            const currencySymbol = '<?php echo $currencySymbol; ?>';
            const userCountry = '<?php echo $user_country; ?>';
            const taxLabel = '<?php echo $tax_label; ?>';
            
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
                    // Format the hour for display
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
                                label: 'Sales (' + currencySymbol + ')',
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
                                text: userCountry === 'UAE' ? 'Today\'s Sales by Hour (UAE Local Time)' : 'Today\'s Sales by Hour'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label.includes('Sales')) {
                                            label += ': ' + currencySymbol + ' ' + context.raw.toFixed(2);
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
                                    text: 'Sales (' + currencySymbol + ')'
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
                const hourLabels = Array.from({length: 24}, (_, i) => {
                    return i === 0 ? '12 AM' : 
                           i < 12 ? i + ' AM' : 
                           i === 12 ? '12 PM' : 
                           (i - 12) + ' PM';
                });
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: hourLabels,
                        datasets: []
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: userCountry === 'UAE' ? 'No sales data available for today yet (UAE Local Time)' : 'No sales data available for today yet'
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

// Initialize enhanced monitoring
document.addEventListener('DOMContentLoaded', function() {
    startEnhancedSessionMonitoring();
});
</script>
</body>
</html>