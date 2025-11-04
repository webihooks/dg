<?php
// booking-alerts.php
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_alert_settings'])) {
        $email_alerts = isset($_POST['email_alerts']) ? 1 : 0;
        $sms_alerts = isset($_POST['sms_alerts']) ? 1 : 0;
        $whatsapp_alerts = isset($_POST['whatsapp_alerts']) ? 1 : 0;
        $push_alerts = isset($_POST['push_alerts']) ? 1 : 0;
        
        $new_booking_alert = isset($_POST['new_booking_alert']) ? 1 : 0;
        $checkin_alert = isset($_POST['checkin_alert']) ? 1 : 0;
        $checkout_alert = isset($_POST['checkout_alert']) ? 1 : 0;
        $cancellation_alert = isset($_POST['cancellation_alert']) ? 1 : 0;
        $maintenance_alert = isset($_POST['maintenance_alert']) ? 1 : 0;
        
        $alert_before_checkin = intval($_POST['alert_before_checkin']);
        $alert_before_checkout = intval($_POST['alert_before_checkout']);
        
        // Check if alert settings already exist
        $check_sql = "SELECT id FROM booking_alerts_$user_id WHERE user_id = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            // Update existing settings
            $update_sql = "UPDATE booking_alerts_$user_id SET 
                          email_alerts = ?, sms_alerts = ?, whatsapp_alerts = ?, push_alerts = ?,
                          new_booking_alert = ?, checkin_alert = ?, checkout_alert = ?, 
                          cancellation_alert = ?, maintenance_alert = ?,
                          alert_before_checkin = ?, alert_before_checkout = ?,
                          updated_at = NOW() 
                          WHERE user_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("iiiiiiiiiiii", 
                $email_alerts, $sms_alerts, $whatsapp_alerts, $push_alerts,
                $new_booking_alert, $checkin_alert, $checkout_alert, 
                $cancellation_alert, $maintenance_alert,
                $alert_before_checkin, $alert_before_checkout,
                $user_id
            );
        } else {
            // Insert new settings
            $insert_sql = "INSERT INTO booking_alerts_$user_id 
                          (user_id, email_alerts, sms_alerts, whatsapp_alerts, push_alerts,
                          new_booking_alert, checkin_alert, checkout_alert, 
                          cancellation_alert, maintenance_alert,
                          alert_before_checkin, alert_before_checkout) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iiiiiiiiiiii", 
                $user_id,
                $email_alerts, $sms_alerts, $whatsapp_alerts, $push_alerts,
                $new_booking_alert, $checkin_alert, $checkout_alert, 
                $cancellation_alert, $maintenance_alert,
                $alert_before_checkin, $alert_before_checkout
            );
        }
        
        if ($stmt->execute()) {
            $success_message = "Alert settings updated successfully!";
        } else {
            $error_message = "Error updating alert settings: " . $conn->error;
        }
        $stmt->close();
        $check_stmt->close();
    }
    
    if (isset($_POST['test_alert'])) {
        // Send test alert
        $test_result = sendTestAlert($user_id, $conn);
        if ($test_result) {
            $success_message = "Test alert sent successfully!";
        } else {
            $error_message = "Failed to send test alert.";
        }
    }
}

// Get current alert settings
$alert_settings = [
    'email_alerts' => 0,
    'sms_alerts' => 0,
    'whatsapp_alerts' => 0,
    'push_alerts' => 1,
    'new_booking_alert' => 1,
    'checkin_alert' => 1,
    'checkout_alert' => 1,
    'cancellation_alert' => 1,
    'maintenance_alert' => 0,
    'alert_before_checkin' => 2,
    'alert_before_checkout' => 1
];

$settings_sql = "SELECT * FROM booking_alerts_$user_id WHERE user_id = ? LIMIT 1";
$settings_stmt = $conn->prepare($settings_sql);
$settings_stmt->bind_param("i", $user_id);
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();

if ($settings_result->num_rows > 0) {
    $alert_settings = $settings_result->fetch_assoc();
}
$settings_stmt->close();

// Get recent alerts for display
$recent_alerts_sql = "SELECT * FROM booking_alerts_log_$user_id 
                     ORDER BY created_at DESC LIMIT 10";
$recent_alerts_result = $conn->query($recent_alerts_sql);
$recent_alerts = [];
if ($recent_alerts_result) {
    $recent_alerts = $recent_alerts_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

// Function to send test alert
function sendTestAlert($user_id, $conn) {
    // Create test alert log
    $test_sql = "INSERT INTO booking_alerts_log_$user_id 
                (alert_type, alert_message, recipient_type, status, sent_to) 
                VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($test_sql);
    
    $alert_type = 'test';
    $alert_message = 'This is a test alert to verify your notification settings.';
    $recipient_type = 'system';
    $status = 'sent';
    $sent_to = 'System Test';
    
    $stmt->bind_param("sssss", $alert_type, $alert_message, $recipient_type, $status, $sent_to);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Booking Alerts & Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .alert-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .alert-type-booking { border-left-color: #28a745; }
        .alert-type-checkin { border-left-color: #17a2b8; }
        .alert-type-checkout { border-left-color: #ffc107; }
        .alert-type-cancellation { border-left-color: #dc3545; }
        .alert-type-maintenance { border-left-color: #6f42c1; }
        .alert-type-test { border-left-color: #6c757d; }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .toggle-switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #28a745;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
                            <h4 class="page-title">Booking Alerts & Notifications</h4>
                            <p class="text-muted mb-4">Manage your booking notifications and alert preferences</p>
                        </div>

                        <!-- Success/Error Messages -->
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

                        <div class="row">
                            <!-- Alert Statistics -->
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <span class="stat-number"><?php echo count($recent_alerts); ?></span>
                                    <span class="stat-label">Recent Alerts</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <span class="stat-number">
                                        <?php 
                                        $active_alerts = array_sum([
                                            $alert_settings['new_booking_alert'],
                                            $alert_settings['checkin_alert'],
                                            $alert_settings['checkout_alert'],
                                            $alert_settings['cancellation_alert'],
                                            $alert_settings['maintenance_alert']
                                        ]);
                                        echo $active_alerts;
                                        ?>
                                    </span>
                                    <span class="stat-label">Active Alert Types</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <span class="stat-number">
                                        <?php 
                                        $active_channels = array_sum([
                                            $alert_settings['email_alerts'],
                                            $alert_settings['sms_alerts'],
                                            $alert_settings['whatsapp_alerts'],
                                            $alert_settings['push_alerts']
                                        ]);
                                        echo $active_channels;
                                        ?>
                                    </span>
                                    <span class="stat-label">Notification Channels</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                    <span class="stat-number">24/7</span>
                                    <span class="stat-label">Monitoring</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Alert Settings Form -->
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Alert Settings & Preferences</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="alertSettingsForm">
                                            <!-- Notification Channels -->
                                            <div class="mb-4">
                                                <h6 class="mb-3">Notification Channels</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="email_alerts" id="email_alerts" <?php echo $alert_settings['email_alerts'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="email_alerts">
                                                                📧 Email Alerts
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Receive alerts via email</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="sms_alerts" id="sms_alerts" <?php echo $alert_settings['sms_alerts'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="sms_alerts">
                                                                📱 SMS Alerts
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Receive alerts via SMS</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="whatsapp_alerts" id="whatsapp_alerts" <?php echo $alert_settings['whatsapp_alerts'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="whatsapp_alerts">
                                                                💬 WhatsApp Alerts
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Receive alerts via WhatsApp</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="push_alerts" id="push_alerts" <?php echo $alert_settings['push_alerts'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="push_alerts">
                                                                🔔 Push Notifications
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Receive browser/app push notifications</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Alert Types -->
                                            <div class="mb-4">
                                                <h6 class="mb-3">Alert Types</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="new_booking_alert" id="new_booking_alert" <?php echo $alert_settings['new_booking_alert'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="new_booking_alert">
                                                                🆕 New Bookings
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Get notified for new bookings</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="checkin_alert" id="checkin_alert" <?php echo $alert_settings['checkin_alert'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="checkin_alert">
                                                                🏨 Check-ins
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Get notified for guest check-ins</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="checkout_alert" id="checkout_alert" <?php echo $alert_settings['checkout_alert'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="checkout_alert">
                                                                🚪 Check-outs
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Get notified for guest check-outs</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="cancellation_alert" id="cancellation_alert" <?php echo $alert_settings['cancellation_alert'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="cancellation_alert">
                                                                ❌ Cancellations
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Get notified for booking cancellations</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="maintenance_alert" id="maintenance_alert" <?php echo $alert_settings['maintenance_alert'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="maintenance_alert">
                                                                🔧 Maintenance
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Get notified for room maintenance</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Alert Timing -->
                                            <div class="mb-4">
                                                <h6 class="mb-3">Alert Timing</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="alert_before_checkin" class="form-label">
                                                            Alert before check-in (hours)
                                                        </label>
                                                        <input type="number" class="form-control" name="alert_before_checkin" id="alert_before_checkin" 
                                                               value="<?php echo $alert_settings['alert_before_checkin']; ?>" min="0" max="24">
                                                        <small class="text-muted">Send reminder before scheduled check-in</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="alert_before_checkout" class="form-label">
                                                            Alert before check-out (hours)
                                                        </label>
                                                        <input type="number" class="form-control" name="alert_before_checkout" id="alert_before_checkout" 
                                                               value="<?php echo $alert_settings['alert_before_checkout']; ?>" min="0" max="24">
                                                        <small class="text-muted">Send reminder before scheduled check-out</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2">
                                                <button type="submit" name="update_alert_settings" class="btn btn-primary">
                                                    💾 Save Settings
                                                </button>
                                                <button type="submit" name="test_alert" class="btn btn-outline-secondary">
                                                    🧪 Send Test Alert
                                                </button>
                                                <a href="room-dashboard.php" class="btn btn-outline-dark">
                                                    ← Back to Dashboard
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Alerts -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Recent Alerts</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($recent_alerts)): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($recent_alerts as $alert): ?>
                                                    <div class="list-group-item px-0">
                                                        <div class="d-flex align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-1"><?php echo ucfirst($alert['alert_type']); ?> Alert</h6>
                                                                <p class="mb-1 text-muted small"><?php echo $alert['alert_message']; ?></p>
                                                                <small class="text-muted">
                                                                    <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?>
                                                                    • <?php echo ucfirst($alert['status']); ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-<?php echo $alert['status'] === 'sent' ? 'success' : 'secondary'; ?> ms-2">
                                                                <?php echo $alert['status']; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <div class="text-muted mb-2">
                                                    <iconify-icon icon="mdi:bell-outline" style="font-size: 3rem;"></iconify-icon>
                                                </div>
                                                <p class="text-muted">No recent alerts</p>
                                                <small class="text-muted">Alerts will appear here when triggered</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5 class="card-title">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <a href="manage-rooms.php" class="btn btn-outline-primary">
                                                🏨 Manage Rooms
                                            </a>
                                            <a href="bookings.php" class="btn btn-outline-success">
                                                📋 View Bookings
                                            </a>
                                            <a href="room-configuration.php" class="btn btn-outline-info">
                                                ⚙️ Room Settings
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Form validation
        $('#alertSettingsForm').on('submit', function(e) {
            const emailAlerts = $('#email_alerts').is(':checked');
            const smsAlerts = $('#sms_alerts').is(':checked');
            const whatsappAlerts = $('#whatsapp_alerts').is(':checked');
            const pushAlerts = $('#push_alerts').is(':checked');
            
            // At least one notification channel should be selected
            if (!emailAlerts && !smsAlerts && !whatsappAlerts && !pushAlerts) {
                e.preventDefault();
                alert('Please select at least one notification channel.');
                return false;
            }
            
            return true;
        });
        
        // Real-time alert simulation
        function simulateNewAlert() {
            const alertTypes = ['booking', 'checkin', 'checkout', 'cancellation'];
            const randomType = alertTypes[Math.floor(Math.random() * alertTypes.length)];
            
            // This would typically come from a WebSocket or AJAX call
            console.log('Simulating new alert:', randomType);
        }
        
        // Check for new alerts every 30 seconds (simulated)
        setInterval(simulateNewAlert, 30000);
    });
    </script>
</body>
</html>