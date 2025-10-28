<?php
// device_management.php - Enhanced User Device Management Page
session_start();

// Include the enhanced session manager
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_player_id = $_SESSION['current_player_id'] ?? null;

// Include database connection
require 'db_connection.php';

// Handle device deactivation (single device)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_device'])) {
    $device_id = $_POST['device_id'];
    $device_player_id = $_POST['device_player_id'];
    
    try {
        // Only deactivate if it's not the current device (prevent self-lockout)
        if ($device_player_id === $current_player_id) {
            $error_message = "Cannot deactivate your current device. Please logout instead.";
        } else {
            $stmt = $conn->prepare("UPDATE user_devices SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $device_id, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Device deactivated successfully";
                
                // Log the event
                if (file_exists('enhanced_logger.php')) {
                    require_once 'enhanced_logger.php';
                    $logger = new EnhancedSessionLogger($user_id);
                    $logger->logSessionEvent('DEVICE_DEACTIVATED_MANUAL', [
                        'device_id' => $device_id,
                        'player_id' => $device_player_id,
                        'user_id' => $user_id,
                        'affected_rows' => $stmt->affected_rows
                    ]);
                }
            } else {
                $error_message = "Error deactivating device";
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
        error_log("Device deactivation error: " . $e->getMessage());
    }
}

// Handle device reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_device'])) {
    $device_id = $_POST['device_id'];
    $device_player_id = $_POST['device_player_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE user_devices SET is_active = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $device_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Device reactivated successfully";
            
            // Log the event
            if (file_exists('enhanced_logger.php')) {
                require_once 'enhanced_logger.php';
                $logger = new EnhancedSessionLogger($user_id);
                $logger->logSessionEvent('DEVICE_REACTIVATED', [
                    'device_id' => $device_id,
                    'player_id' => $device_player_id,
                    'user_id' => $user_id
                ]);
            }
        } else {
            $error_message = "Error reactivating device";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle deactivate all other devices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_all_other'])) {
    try {
        $stmt = $conn->prepare("UPDATE user_devices SET is_active = 0 WHERE user_id = ? AND player_id != ? AND is_active = 1");
        $stmt->bind_param("is", $user_id, $current_player_id);
        
        if ($stmt->execute()) {
            $deactivated_count = $stmt->affected_rows;
            $success_message = "Deactivated {$deactivated_count} other device(s). Only this device remains active.";
            
            // Log the event
            if (file_exists('enhanced_logger.php')) {
                require_once 'enhanced_logger.php';
                $logger = new EnhancedSessionLogger($user_id);
                $logger->logSessionEvent('DEVICES_DEACTIVATE_ALL_OTHER', [
                    'user_id' => $user_id,
                    'current_player_id' => $current_player_id,
                    'deactivated_count' => $deactivated_count
                ]);
            }
        } else {
            $error_message = "Error deactivating other devices";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch user's devices with detailed information
$devices_sql = "SELECT 
    id, 
    player_id, 
    device_type, 
    platform, 
    source,
    is_active,
    created_at,
    updated_at,
    TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_since_update,
    TIMESTAMPDIFF(DAY, created_at, NOW()) as days_since_creation
FROM user_devices 
WHERE user_id = ? 
ORDER BY 
    is_active DESC,
    updated_at DESC";

$devices_stmt = $conn->prepare($devices_sql);
$devices_stmt->bind_param("i", $user_id);
$devices_stmt->execute();
$devices_result = $devices_stmt->get_result();

$user_devices = [];
$active_devices_count = 0;
$inactive_devices_count = 0;
$total_devices_count = 0;

if ($devices_result->num_rows > 0) {
    while ($row = $devices_result->fetch_assoc()) {
        $user_devices[] = $row;
        if ($row['is_active']) {
            $active_devices_count++;
        } else {
            $inactive_devices_count++;
        }
        $total_devices_count++;
    }
}
$devices_stmt->close();

// Get device statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(is_active = 1) as active,
    SUM(is_active = 0) as inactive,
    COUNT(DISTINCT platform) as platforms,
    MAX(created_at) as latest_device
FROM user_devices 
WHERE user_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$device_stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$conn->close();

// Log page access
if (file_exists('enhanced_logger.php')) {
    require_once 'enhanced_logger.php';
    $logger = new EnhancedSessionLogger($user_id);
    $logger->logSessionEvent('DEVICE_MANAGEMENT_ACCESS', [
        'device_count' => $total_devices_count,
        'active_devices' => $active_devices_count,
        'inactive_devices' => $inactive_devices_count,
        'current_player_id' => $current_player_id
    ], $user_id);
}
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
            transition: all 0.3s ease;
        }
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
        .device-card.current-device {
            border: 2px solid #007bff;
            background-color: #f8f9fa;
        }
        .player-id {
            font-family: monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 3px;
            word-break: break-all;
        }
        .device-badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-3px);
        }
        .device-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .device-card:hover .device-actions {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php 
        // Include appropriate menu based on user role
        if (isset($_SESSION['role'])) {
            switch ($_SESSION['role']) {
                case 'admin':
                    include 'admin_menu.php';
                    break;
                case 'sales_person':
                    include 'sales_menu.php';
                    break;
                case 'printer':
                    include 'printer_menu.php';
                    break;
                default:
                    include 'menu.php';
            }
        } else {
            include 'menu.php';
        }
        ?>

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

                                <!-- Device Statistics Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card stats-card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <h5>Total Devices</h5>
                                                <h2><?php echo $device_stats['total'] ?? 0; ?></h2>
                                                <small>All registered devices</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stats-card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h5>Active</h5>
                                                <h2><?php echo $device_stats['active'] ?? 0; ?></h2>
                                                <small>Receiving notifications</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stats-card bg-warning text-dark">
                                            <div class="card-body text-center">
                                                <h5>Inactive</h5>
                                                <h2><?php echo $device_stats['inactive'] ?? 0; ?></h2>
                                                <small>Not receiving notifications</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stats-card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h5>Platforms</h5>
                                                <h2><?php echo $device_stats['platforms'] ?? 0; ?></h2>
                                                <small>Different platforms</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Session Info Card -->
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-info-circle me-2"></i>Current Session Information
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Platform:</strong> 
                                                <?php echo $sessionManager->isAndroidApp() ? '📱 Android App' : '🌐 Web Browser'; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Current Device ID:</strong> 
                                                <code class="player-id"><?php echo $current_player_id ? substr($current_player_id, 0, 20) . '...' : 'Not registered'; ?></code>
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
                                        </div>
                                    </div>
                                </div>

                                <!-- Bulk Actions -->
                                <?php if ($active_devices_count > 1): ?>
                                <div class="card mb-4 border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Bulk Device Management
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">
                                            <strong>Warning:</strong> You have <?php echo $active_devices_count; ?> active devices receiving notifications. 
                                            You can deactivate all other devices to ensure notifications only come to this device.
                                        </p>
                                        <form method="POST" onsubmit="return confirm('This will deactivate all your other devices. Only this device will receive notifications. Continue?')">
                                            <button type="submit" name="deactivate_all_other" class="btn btn-warning">
                                                <i class="fas fa-ban me-2"></i>Deactivate All Other Devices (Keep Only This One)
                                            </button>
                                            <small class="text-muted ms-2">This will affect <?php echo $active_devices_count - 1; ?> device(s)</small>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Registered Devices -->
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-mobile-alt me-2"></i>Registered Devices 
                                            <span class="badge bg-primary"><?php echo $total_devices_count; ?></span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($user_devices)): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No devices registered yet. Devices will appear here when you receive push notifications.
                                            </div>
                                        <?php else: ?>
                                            <!-- Desktop Table View -->
                                            <div class="table-responsive d-none d-md-block">
                                                <table class="table table-striped table-hover">
                                                    <thead class="table-light">
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
                                                        <?php foreach ($user_devices as $device): 
                                                            $isCurrentDevice = ($device['player_id'] === $current_player_id);
                                                            $deviceClass = '';
                                                            if (strpos($device['device_type'], 'android') !== false) $deviceClass = 'android';
                                                            elseif (strpos($device['device_type'], 'ios') !== false) $deviceClass = 'ios';
                                                            else $deviceClass = 'web';
                                                        ?>
                                                            <tr class="<?php echo !$device['is_active'] ? 'table-secondary' : ''; echo $isCurrentDevice ? ' table-info' : ''; ?>">
                                                                <td>
                                                                    <i class="fas 
                                                                        <?php echo $deviceClass === 'android' ? 'fa-android' : 
                                                                              ($deviceClass === 'ios' ? 'fa-apple' : 'fa-desktop'); ?> 
                                                                        me-2 text-<?php echo $deviceClass === 'android' ? 'success' : ($deviceClass === 'ios' ? 'dark' : 'info'); ?>">
                                                                    </i>
                                                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                                                    <?php if ($isCurrentDevice): ?>
                                                                        <span class="badge device-badge bg-primary ms-1">Current Device</span>
                                                                    <?php endif; ?>
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
                                                                        <span class="badge device-badge bg-success">
                                                                            <i class="fas fa-bell me-1"></i>Active
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge device-badge bg-secondary">
                                                                            <i class="fas fa-bell-slash me-1"></i>Inactive
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('M j, Y g:i A', strtotime($device['created_at'])); ?>
                                                                    <br>
                                                                    <small class="text-muted">(<?php echo $device['days_since_creation']; ?> days ago)</small>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('M j, Y g:i A', strtotime($device['updated_at'])); ?>
                                                                    <br>
                                                                    <small class="text-muted">(<?php echo $device['minutes_since_update']; ?> min ago)</small>
                                                                </td>
                                                                <td class="device-actions">
                                                                    <?php if ($device['is_active'] && !$isCurrentDevice): ?>
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                                            <input type="hidden" name="device_player_id" value="<?php echo $device['player_id']; ?>">
                                                                            <button type="submit" name="deactivate_device" class="btn btn-sm btn-warning" 
                                                                                    onclick="return confirm('Deactivate this device? It will stop receiving push notifications.')">
                                                                                <i class="fas fa-ban me-1"></i>Deactivate
                                                                            </button>
                                                                        </form>
                                                                    <?php elseif (!$device['is_active']): ?>
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                                            <input type="hidden" name="device_player_id" value="<?php echo $device['player_id']; ?>">
                                                                            <button type="submit" name="reactivate_device" class="btn btn-sm btn-success">
                                                                                <i class="fas fa-check me-1"></i>Reactivate
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Current Device</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <!-- Mobile Card View -->
                                            <div class="d-md-none">
                                                <?php foreach ($user_devices as $device): 
                                                    $isCurrentDevice = ($device['player_id'] === $current_player_id);
                                                    $deviceClass = '';
                                                    if (strpos($device['device_type'], 'android') !== false) $deviceClass = 'android';
                                                    elseif (strpos($device['device_type'], 'ios') !== false) $deviceClass = 'ios';
                                                    else $deviceClass = 'web';
                                                ?>
                                                    <div class="card device-card <?php echo $deviceClass; echo !$device['is_active'] ? ' inactive' : ''; echo $isCurrentDevice ? ' current-device' : ''; ?> mb-3">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title mb-0">
                                                                    <i class="fas 
                                                                        <?php echo $deviceClass === 'android' ? 'fa-android' : 
                                                                              ($deviceClass === 'ios' ? 'fa-apple' : 'fa-desktop'); ?> 
                                                                        me-2 text-<?php echo $deviceClass === 'android' ? 'success' : ($deviceClass === 'ios' ? 'dark' : 'info'); ?>">
                                                                    </i>
                                                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                                                </h6>
                                                                <div>
                                                                    <?php if ($device['is_active']): ?>
                                                                        <span class="badge device-badge bg-success">Active</span>
                                                                    <?php else: ?>
                                                                        <span class="badge device-badge bg-secondary">Inactive</span>
                                                                    <?php endif; ?>
                                                                    <?php if ($isCurrentDevice): ?>
                                                                        <span class="badge device-badge bg-primary">Current</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <strong>Platform:</strong>
                                                                <span class="badge bg-<?php echo $device['platform'] === 'android' ? 'success' : ($device['platform'] === 'ios' ? 'dark' : 'info'); ?>">
                                                                    <?php echo ucfirst($device['platform']); ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <strong>Device ID:</strong>
                                                                <code class="player-id d-block mt-1"><?php echo substr($device['player_id'], 0, 25) . '...'; ?></code>
                                                            </div>
                                                            
                                                            <div class="row mb-2">
                                                                <div class="col-6">
                                                                    <small class="text-muted">
                                                                        <strong>Registered:</strong><br>
                                                                        <?php echo date('M j, Y', strtotime($device['created_at'])); ?>
                                                                    </small>
                                                                </div>
                                                                <div class="col-6">
                                                                    <small class="text-muted">
                                                                        <strong>Updated:</strong><br>
                                                                        <?php echo date('M j, Y', strtotime($device['updated_at'])); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="device-actions mt-3">
                                                                <?php if ($device['is_active'] && !$isCurrentDevice): ?>
                                                                    <form method="POST">
                                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                                        <input type="hidden" name="device_player_id" value="<?php echo $device['player_id']; ?>">
                                                                        <button type="submit" name="deactivate_device" class="btn btn-sm btn-warning w-100" 
                                                                                onclick="return confirm('Deactivate this device? It will stop receiving push notifications.')">
                                                                            <i class="fas fa-ban me-1"></i>Deactivate Device
                                                                        </button>
                                                                    </form>
                                                                <?php elseif (!$device['is_active']): ?>
                                                                    <form method="POST">
                                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                                        <input type="hidden" name="device_player_id" value="<?php echo $device['player_id']; ?>">
                                                                        <button type="submit" name="reactivate_device" class="btn btn-sm btn-success w-100">
                                                                            <i class="fas fa-check me-1"></i>Reactivate Device
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <button class="btn btn-sm btn-outline-primary w-100" disabled>
                                                                        <i class="fas fa-mobile-alt me-1"></i>Current Device
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="alert alert-info mt-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Note:</strong> 
                                                <ul class="mb-0 mt-2">
                                                    <li>Deactivating a device will stop push notifications on that device only</li>
                                                    <li>Other active devices will continue to receive notifications</li>
                                                    <li>You cannot deactivate your current device - use logout instead</li>
                                                    <li>Reactivated devices will start receiving notifications again</li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>












<!-- Add to device_management.php -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">
            <i class="fas fa-sync-alt me-2"></i>Device Reactivation
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted">If a device is not receiving notifications after login, you can manually reactivate it.</p>
        
        <button id="reactivateDeviceBtn" class="btn btn-primary">
            <i class="fas fa-sync-alt me-2"></i>Reactivate Current Device
        </button>
        
        <div id="reactivationResult" class="mt-3" style="display: none;"></div>
    </div>
</div>

<script>
// Manual device reactivation
$('#reactivateDeviceBtn').click(function() {
    const btn = $(this);
    const resultDiv = $('#reactivationResult');
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Reactivating...');
    
    // Trigger device registration
    if (typeof WTN !== 'undefined' && WTN.OneSignal) {
        WTN.OneSignal.getPlayerId().then(playerId => {
            if (playerId) {
                fetch('register_device_unified.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        player_id: playerId,
                        device_type: 'android_webtonative',
                        platform: 'android',
                        user_id: <?php echo $user_id; ?>,
                        source: 'manual_reactivation',
                        force_reactivate: true
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.html('<div class="alert alert-success">✅ Device reactivated successfully! You should now receive push notifications.</div>').show();
                    } else {
                        resultDiv.html('<div class="alert alert-danger">❌ Reactivation failed: ' + data.message + '</div>').show();
                    }
                })
                .catch(error => {
                    resultDiv.html('<div class="alert alert-danger">❌ Request failed: ' + error + '</div>').show();
                })
                .finally(() => {
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-2"></i>Reactivate Current Device');
                });
            }
        });
    } else {
        resultDiv.html('<div class="alert alert-warning">⚠️ Android app not detected</div>').show();
        btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-2"></i>Reactivate Current Device');
    }
});
</script>








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
                    html += '<div><strong>Active Devices:</strong> <?php echo $active_devices_count; ?></div>';
                    
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

        // Test Notification functionality
        $('#deviceTestBtn').click(function() {
            const btn = $(this);
            const resultDiv = $('#deviceTestResult');
            
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
            
            $.post('test_notification.php', { test: true })
                .done(function(data) {
                    let html = '<div class="alert alert-' + (data.success ? 'success' : 'warning') + '">';
                    html += '<h6><i class="fas fa-' + (data.success ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>';
                    html += data.success ? 'Test Notification Sent' : 'Test Failed';
                    html += '</h6>';
                    
                    if (data.success) {
                        html += '<div class="mt-2"><strong>Sent to:</strong> ' + (data.devices_count || 0) + ' active device(s)</div>';
                        html += '<div><strong>Message:</strong> Test notification from device management</div>';
                    } else {
                        html += '<div class="mt-2"><strong>Error:</strong> ' + (data.message || 'Unknown error') + '</div>';
                    }
                    
                    html += '</div>';
                    
                    resultDiv.html(html).show();
                })
                .fail(function() {
                    resultDiv.html('<div class="alert alert-danger">Test request failed. Please try again.</div>').show();
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-bell me-2"></i>Test Notifications');
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
                                console.log('❤️ Device management heartbeat maintained');
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

        // Auto-refresh device list every 2 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 120000); // 2 minutes
    });
    </script>
</body>
</html>