<?php
// device_management.php - User device management page
session_start();
require_once 'android_session_manager.php';
require_once 'enhanced_logger.php';

$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
require 'db_connection.php';

// Handle device deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_device'])) {
    $device_id = $_POST['device_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE user_devices SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $device_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Device deactivated successfully";
            log_session_event('DEVICE_DEACTIVATED', [
                'device_id' => $device_id,
                'user_id' => $user_id,
                'affected_rows' => $stmt->affected_rows
            ], $user_id);
        } else {
            $error_message = "Error deactivating device";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
        log_session_event('DEVICE_DEACTIVATION_ERROR', [
            'error' => $e->getMessage(),
            'device_id' => $device_id
        ], $user_id);
    }
}

// Handle device reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_device'])) {
    $device_id = $_POST['device_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE user_devices SET is_active = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $device_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Device reactivated successfully";
            log_session_event('DEVICE_REACTIVATED', [
                'device_id' => $device_id,
                'user_id' => $user_id
            ], $user_id);
        } else {
            $error_message = "Error reactivating device";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch user's devices
$devices_sql = "SELECT 
    id, 
    player_id, 
    device_type, 
    platform, 
    source,
    is_active,
    created_at,
    updated_at
FROM user_devices 
WHERE user_id = ? 
ORDER BY created_at DESC";

$devices_stmt = $conn->prepare($devices_sql);
$devices_stmt->bind_param("i", $user_id);
$devices_stmt->execute();
$devices_result = $devices_stmt->get_result();

$user_devices = [];
if ($devices_result->num_rows > 0) {
    while ($row = $devices_result->fetch_assoc()) {
        $user_devices[] = $row;
    }
}
$devices_stmt->close();
$conn->close();

// Log page access
log_session_event('DEVICE_MANAGEMENT_ACCESS', [
    'device_count' => count($user_devices),
    'active_devices' => count(array_filter($user_devices, function($d) { return $d['is_active']; }))
], $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Device Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .device-card {
            border-left: 4px solid #28a745;
            margin-bottom: 15px;
        }
        .device-card.inactive {
            border-left-color: #6c757d;
            opacity: 0.7;
        }
        .device-card.android {
            border-left-color: #3ddc84;
        }
        .device-card.ios {
            border-left-color: #007aff;
        }
        .device-card.web {
            border-left-color: #17a2b8;
        }
        .player-id {
            font-family: monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 3px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Device Management</h4>
                                <p class="text-muted mb-0">Manage your registered devices and push notification settings</p>
                            </div>
                            <div class="card-body">
                                
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check me-2"></i>
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- Session Info Card -->
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-info-circle me-2"></i>Session Information
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Platform:</strong> 
                                                <?php echo $sessionManager->isAndroidApp() ? '📱 Android App' : '🌐 Web Browser'; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Session Duration:</strong> 
                                                <?php 
                                                    $duration = time() - ($_SESSION['login_time'] ?? time());
                                                    echo floor($duration / 3600) . 'h ' . floor(($duration % 3600) / 60) . 'm';
                                                ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Last Activity:</strong> 
                                                <?php 
                                                    $last_activity = $_SESSION['last_activity'] ?? time();
                                                    echo date('M j, g:i A', $last_activity);
                                                ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Session Health:</strong> 
                                                <span class="badge bg-success">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Registered Devices -->
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-mobile-alt me-2"></i>Registered Devices (<?php echo count($user_devices); ?>)
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($user_devices)): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No devices registered yet. Devices will appear here when you receive push notifications.
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Device Type</th>
                                                            <th>Platform</th>
                                                            <th>Player ID</th>
                                                            <th>Status</th>
                                                            <th>Registered</th>
                                                            <th>Last Updated</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($user_devices as $device): ?>
                                                            <tr class="<?php echo !$device['is_active'] ? 'table-secondary' : ''; ?>">
                                                                <td>
                                                                    <i class="fas 
                                                                        <?php echo $device['device_type'] === 'android_webtonative' ? 'fa-android' : 
                                                                              (strpos($device['device_type'], 'ios') !== false ? 'fa-apple' : 'fa-desktop'); ?> 
                                                                        me-2">
                                                                    </i>
                                                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge 
                                                                        <?php echo $device['platform'] === 'android' ? 'bg-success' : 
                                                                              ($device['platform'] === 'ios' ? 'bg-dark' : 'bg-info'); ?>">
                                                                        <?php echo ucfirst($device['platform']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <code class="player-id" title="<?php echo htmlspecialchars($device['player_id']); ?>">
                                                                        <?php echo substr($device['player_id'], 0, 20) . '...'; ?>
                                                                    </code>
                                                                </td>
                                                                <td>
                                                                    <?php if ($device['is_active']): ?>
                                                                        <span class="badge bg-success">Active</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Inactive</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('M j, Y g:i A', strtotime($device['created_at'])); ?>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('M j, Y g:i A', strtotime($device['updated_at'])); ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($device['is_active']): ?>
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                                            <button type="submit" name="deactivate_device" class="btn btn-sm btn-warning" 
                                                                                    onclick="return confirm('Are you sure you want to deactivate this device? You will stop receiving push notifications on this device.')">
                                                                                <i class="fas fa-ban me-1"></i>Deactivate
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                                            <button type="submit" name="reactivate_device" class="btn btn-sm btn-success">
                                                                                <i class="fas fa-check me-1"></i>Reactivate
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <div class="alert alert-warning mt-3">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>Note:</strong> Deactivating a device will stop push notifications on that device. 
                                                You can reactivate it later if needed.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Session Health Check -->
                                <div class="card mt-4">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-heartbeat me-2"></i>Session Health Check
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <button id="healthCheckBtn" class="btn btn-outline-primary">
                                            <i class="fas fa-stethoscope me-2"></i>Run Health Check
                                        </button>
                                        <div id="healthCheckResult" class="mt-3" style="display: none;"></div>
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
        // Health Check functionality
        $('#healthCheckBtn').click(function() {
            const btn = $(this);
            const resultDiv = $('#healthCheckResult');
            
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Checking...');
            
            $.get('session_health_check.php')
                .done(function(data) {
                    let html = '<div class="alert alert-' + (data.session_active ? 'success' : 'warning') + '">';
                    html += '<h6><i class="fas fa-' + (data.session_active ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>';
                    html += data.session_active ? 'Session is Healthy' : 'Session Issues Found';
                    html += '</h6>';
                    
                    html += '<div class="mt-2"><strong>User ID:</strong> ' + (data.user_id || 'Not logged in') + '</div>';
                    html += '<div><strong>Platform:</strong> ' + (data.is_android_app ? 'Android App' : 'Web Browser') + '</div>';
                    html += '<div><strong>Session Age:</strong> ' + (data.session_age ? Math.floor(data.session_age / 60) + ' minutes' : 'Unknown') + '</div>';
                    
                    if (data.issues && data.issues.length > 0) {
                        html += '<div class="mt-2"><strong>Issues:</strong><ul class="mb-0">';
                        data.issues.forEach(issue => {
                            html += '<li>' + issue + '</li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    html += '<div class="mt-2"><small class="text-muted">Checked at: ' + new Date(data.timestamp * 1000).toLocaleString() + '</small></div>';
                    html += '</div>';
                    
                    resultDiv.html(html).show();
                })
                .fail(function() {
                    resultDiv.html('<div class="alert alert-danger">Health check failed. Please try again.</div>').show();
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-stethoscope me-2"></i>Run Health Check');
                });
        });

        // Auto heartbeat for Android apps
        function startHeartbeat() {
            const isAndroid = <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>;
            
            if (isAndroid) {
                setInterval(() => {
                    $.get('heartbeat.php')
                        .done(data => {
                            if (data.success) {
                                console.log('❤️ Heartbeat maintained - Count:', data.heartbeat_count);
                            } else {
                                console.warn('💔 Heartbeat failed:', data.error);
                            }
                        })
                        .fail(() => {
                            console.error('💔 Heartbeat request failed');
                        });
                }, 300000); // Every 5 minutes for Android
            }
        }

        // Start heartbeat
        startHeartbeat();
    });
    </script>
</body>
</html>