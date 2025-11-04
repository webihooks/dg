<?php
// staff-alerts.php
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

// Check and create staff tables if they don't exist
createStaffTablesIfNotExist($user_id, $conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_alert'])) {
        $alert_title = trim($_POST['alert_title']);
        $alert_message = trim($_POST['alert_message']);
        $alert_type = $_POST['alert_type'];
        $priority = $_POST['priority'];
        $target_staff = isset($_POST['target_staff']) ? $_POST['target_staff'] : [];
        $scheduled_time = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;

        if (!empty($alert_title) && !empty($alert_message)) {
            // Create alert
            $alert_sql = "INSERT INTO staff_alerts_$user_id 
                         (alert_title, alert_message, alert_type, priority, target_staff, scheduled_time, created_by, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($alert_sql);
            $target_staff_json = json_encode($target_staff);
            $stmt->bind_param("ssssssi", $alert_title, $alert_message, $alert_type, $priority, $target_staff_json, $scheduled_time, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Alert created successfully!";
                
                // Send notifications to targeted staff
                sendStaffNotifications($user_id, $alert_title, $alert_message, $target_staff, $conn);
            } else {
                $error_message = "Error creating alert: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Please fill in all required fields";
        }
    }
    
    if (isset($_POST['add_staff'])) {
        $staff_name = trim($_POST['staff_name']);
        $staff_phone = trim($_POST['staff_phone']);
        $staff_email = trim($_POST['staff_email']);
        $staff_role = trim($_POST['staff_role']);
        $department = trim($_POST['department']);
        $shift_timing = trim($_POST['shift_timing']);

        if (!empty($staff_name) && !empty($staff_phone)) {
            // Check if staff already exists
            $check_sql = "SELECT id FROM staff_members_$user_id WHERE phone = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $staff_phone);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Staff member with this phone number already exists!";
            } else {
                $staff_sql = "INSERT INTO staff_members_$user_id 
                             (name, phone, email, role, department, shift_timing, is_active) 
                             VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($staff_sql);
                $stmt->bind_param("ssssss", $staff_name, $staff_phone, $staff_email, $staff_role, $department, $shift_timing);
                
                if ($stmt->execute()) {
                    $success_message = "Staff member added successfully!";
                } else {
                    $error_message = "Error adding staff member: " . $conn->error;
                }
                $stmt->close();
            }
            $check_stmt->close();
        } else {
            $error_message = "Please fill in required staff details";
        }
    }
    
    if (isset($_POST['update_alert_status'])) {
        $alert_id = $_POST['alert_id'];
        $status = $_POST['status'];
        
        $update_sql = "UPDATE staff_alerts_$user_id SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $status, $alert_id);
        
        if ($stmt->execute()) {
            $success_message = "Alert status updated successfully!";
        } else {
            $error_message = "Error updating alert status";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_staff_status'])) {
        $staff_id = $_POST['staff_id'];
        $is_active = $_POST['is_active'];
        
        $update_sql = "UPDATE staff_members_$user_id SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $is_active, $staff_id);
        
        if ($stmt->execute()) {
            $success_message = "Staff status updated successfully!";
        } else {
            $error_message = "Error updating staff status";
        }
        $stmt->close();
    }
}

// Get staff members - FIXED: using is_active instead of status
$staff_sql = "SELECT * FROM staff_members_$user_id WHERE is_active = 1 ORDER BY name";
$staff_result = $conn->query($staff_sql);
$staff_members = [];
if ($staff_result) {
    while ($row = $staff_result->fetch_assoc()) {
        $staff_members[] = $row;
    }
}

// Get all staff members (including inactive) for management
$all_staff_sql = "SELECT * FROM staff_members_$user_id ORDER BY name";
$all_staff_result = $conn->query($all_staff_sql);
$all_staff_members = [];
if ($all_staff_result) {
    while ($row = $all_staff_result->fetch_assoc()) {
        $all_staff_members[] = $row;
    }
}

// Get active alerts
$alerts_sql = "SELECT sa.*, u.name as created_by_name 
               FROM staff_alerts_$user_id sa 
               LEFT JOIN users u ON sa.created_by = u.id 
               ORDER BY sa.created_at DESC";
$alerts_result = $conn->query($alerts_sql);
$alerts = [];
if ($alerts_result) {
    while ($row = $alerts_result->fetch_assoc()) {
        $alerts[] = $row;
    }
}

// Get alert statistics
$stats_sql = "SELECT 
                COUNT(*) as total_alerts,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_alerts,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_alerts,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_alerts
              FROM staff_alerts_$user_id";
$stats_result = $conn->query($stats_sql);
$alert_stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_alerts' => 0,
    'active_alerts' => 0,
    'completed_alerts' => 0,
    'cancelled_alerts' => 0
];

$conn->close();

// Function to create staff tables if they don't exist
function createStaffTablesIfNotExist($user_id, $conn) {
    $tables = [
        "staff_members_$user_id" => "
            CREATE TABLE IF NOT EXISTS `staff_members_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `staff_code` VARCHAR(20) DEFAULT NULL,
                `name` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `role` VARCHAR(100) DEFAULT NULL,
                `department` ENUM('housekeeping', 'reception', 'management', 'maintenance', 'kitchen', 'security') DEFAULT 'housekeeping',
                `position` VARCHAR(100) DEFAULT NULL,
                `shift_timing` VARCHAR(100) DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `phone` (`phone`),
                UNIQUE KEY `email` (`email`),
                KEY `department` (`department`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "staff_alerts_$user_id" => "
            CREATE TABLE IF NOT EXISTS `staff_alerts_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `alert_title` VARCHAR(255) NOT NULL,
                `alert_message` TEXT NOT NULL,
                `alert_type` ENUM('general', 'emergency', 'maintenance', 'cleaning', 'guest_request', 'shift_change') DEFAULT 'general',
                `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                `target_staff` JSON DEFAULT NULL,
                `scheduled_time` DATETIME DEFAULT NULL,
                `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                `created_by` INT(11) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `alert_type` (`alert_type`),
                KEY `priority` (`priority`),
                KEY `status` (`status`),
                KEY `created_by` (`created_by`),
                KEY `scheduled_time` (`scheduled_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "alert_templates_$user_id" => "
            CREATE TABLE IF NOT EXISTS `alert_templates_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `template_name` VARCHAR(255) NOT NULL,
                `template_content` TEXT NOT NULL,
                `alert_type` VARCHAR(100) DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `template_name` (`template_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        "
    ];

    foreach ($tables as $table_name => $query) {
        $result = $conn->query("SHOW TABLES LIKE '$table_name'");
        if ($result->num_rows == 0) {
            if (!$conn->query($query)) {
                error_log("Error creating table $table_name: " . $conn->error);
            } else {
                error_log("Table $table_name created successfully");
            }
        } else {
            error_log("Table $table_name already exists");
        }
    }
    
    // Check if alert_templates table exists and has the right structure
    $template_check = $conn->query("SHOW TABLES LIKE 'alert_templates_$user_id'");
    if ($template_check->num_rows > 0) {
        // Check if table has any data
        $count_result = $conn->query("SELECT COUNT(*) as count FROM alert_templates_$user_id");
        $template_count = 0;
        if ($count_result) {
            $template_count = $count_result->fetch_assoc()['count'];
        }
        
        if ($template_count == 0) {
            // Try to insert default templates with different column name possibilities
            $default_templates = [
                ['Room Cleaning Required', 'Room {{room_number}} requires immediate cleaning. Please attend to it as soon as possible.', 'cleaning'],
                ['Maintenance Issue', 'Maintenance required in {{location}}. Issue: {{issue_description}}', 'maintenance'],
                ['Guest Request', 'Guest in room {{room_number}} has requested: {{request_details}}', 'guest_request'],
                ['Shift Change Alert', 'Shift change reminder for {{shift_time}}. Please ensure smooth handover.', 'shift_change'],
                ['Emergency Alert', 'EMERGENCY: {{emergency_details}}. Please respond immediately.', 'emergency']
            ];
            
            // Try different column name combinations
            $column_attempts = [
                ['template_name', 'template_content', 'alert_type'],
                ['template_name', 'template_message', 'alert_type'],
                ['name', 'content', 'type']
            ];
            
            $inserted = false;
            foreach ($column_attempts as $columns) {
                try {
                    foreach ($default_templates as $template) {
                        $template_sql = "INSERT INTO alert_templates_$user_id ({$columns[0]}, {$columns[1]}, {$columns[2]}) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($template_sql);
                        if ($stmt) {
                            $stmt->bind_param("sss", $template[0], $template[1], $template[2]);
                            if ($stmt->execute()) {
                                $inserted = true;
                            }
                            $stmt->close();
                        }
                    }
                    if ($inserted) break;
                } catch (Exception $e) {
                    // Continue to next column combination
                    continue;
                }
            }
            
            if (!$inserted) {
                error_log("Could not insert default templates - column structure mismatch");
            }
        }
    }
    
    // Insert sample staff members if table is empty
    $staff_check = $conn->query("SELECT COUNT(*) as count FROM staff_members_$user_id");
    $staff_count = 0;
    if ($staff_check) {
        $staff_count = $staff_check->fetch_assoc()['count'];
    }
    
    if ($staff_count == 0) {
        $sample_staff = [
            ['HK001', 'Housekeeping Staff 1', '9876543210', 'hk1@hotel.com', 'Room Attendant', 'housekeeping', '9 AM - 6 PM'],
            ['RC001', 'Reception Staff 1', '9876543211', 'reception@hotel.com', 'Front Desk Executive', 'reception', '8 AM - 4 PM'],
            ['MN001', 'Maintenance Staff 1', '9876543212', 'maintenance@hotel.com', 'Technician', 'maintenance', '10 AM - 7 PM']
        ];
        
        foreach ($sample_staff as $staff) {
            $staff_sql = "INSERT INTO staff_members_$user_id (staff_code, name, phone, email, position, department, shift_timing) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($staff_sql);
            if ($stmt) {
                $stmt->bind_param("sssssss", $staff[0], $staff[1], $staff[2], $staff[3], $staff[4], $staff[5], $staff[6]);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Function to send staff notifications
function sendStaffNotifications($user_id, $title, $message, $target_staff, $conn) {
    $notification_log = [];
    
    if (empty($target_staff)) {
        // Send to all active staff
        $staff_sql = "SELECT id, name, phone FROM staff_members_$user_id WHERE is_active = 1";
        $result = $conn->query($staff_sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $notification_log[] = "Alert sent to: " . $row['name'] . " (" . $row['phone'] . ")";
                // Here you would integrate with your preferred notification system
                // SMS, WhatsApp, Push Notification, etc.
            }
        }
    } else {
        // Send to specific staff
        foreach ($target_staff as $staff_id) {
            $staff_sql = "SELECT name, phone FROM staff_members_$user_id WHERE id = ? AND is_active = 1";
            $stmt = $conn->prepare($staff_sql);
            if ($stmt) {
                $stmt->bind_param("i", $staff_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $notification_log[] = "Alert sent to staff ID $staff_id: " . $row['name'] . " (" . $row['phone'] . ")";
                    
                    // Integration points for different notification methods:
                    // 1. SMS Integration
                    // sendSMS($row['phone'], $title . ": " . $message);
                    
                    // 2. WhatsApp Integration
                    // sendWhatsApp($row['phone'], $title . ": " . $message);
                    
                    // 3. Push Notification
                    // sendPushNotification($staff_id, $title, $message);
                }
                $stmt->close();
            }
        }
    }
    
    // Log notifications (you can save this to a log file or database)
    error_log("Staff Alerts Sent: " . implode(", ", $notification_log));
    
    return $notification_log;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Staff Alerts Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .alert-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            background: white;
        }
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .alert-priority-low { border-left-color: #28a745; }
        .alert-priority-medium { border-left-color: #ffc107; }
        .alert-priority-high { border-left-color: #fd7e14; }
        .alert-priority-urgent { border-left-color: #dc3545; }
        
        .staff-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .staff-card.inactive {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            opacity: 0.7;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        .stat-total { background: #17a2b8; }
        .stat-active { background: #28a745; }
        .stat-completed { background: #6c757d; }
        .stat-cancelled { background: #dc3545; }
        
        .quick-template {
            cursor: pointer;
            padding: 8px 12px;
            margin: 2px;
            border-radius: 5px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            font-size: 0.9em;
        }
        .quick-template:hover {
            background: #007bff;
            color: white;
        }
        
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 10px;
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
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Notifications -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">👥 Staff Alerts & Notifications</h4>
                                <p class="card-subtitle">Manage staff communications and emergency alerts</p>
                            </div>
                            <div class="card-body">
                                <!-- Quick Stats -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="stat-card stat-total">
                                            <h3><?php echo $alert_stats['total_alerts']; ?></h3>
                                            <p>Total Alerts</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card stat-active">
                                            <h3><?php echo $alert_stats['active_alerts']; ?></h3>
                                            <p>Active Alerts</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card stat-completed">
                                            <h3><?php echo $alert_stats['completed_alerts']; ?></h3>
                                            <p>Completed</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card stat-cancelled">
                                            <h3><?php echo $alert_stats['cancelled_alerts']; ?></h3>
                                            <p>Cancelled</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Create Alert Form -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">📢 Create New Alert</h5>
                                            </div>
                                            <div class="card-body">
                                                <!-- Quick Templates -->
                                                <div class="mb-3">
                                                    <label class="form-label">Quick Templates:</label>
                                                    <div class="d-flex flex-wrap">
                                                        <span class="quick-template" onclick="useTemplate('cleaning')">🧹 Cleaning</span>
                                                        <span class="quick-template" onclick="useTemplate('maintenance')">🔧 Maintenance</span>
                                                        <span class="quick-template" onclick="useTemplate('guest_request')">👤 Guest Request</span>
                                                        <span class="quick-template" onclick="useTemplate('shift_change')">🔄 Shift Change</span>
                                                        <span class="quick-template" onclick="useTemplate('emergency')">🚨 Emergency</span>
                                                    </div>
                                                </div>

                                                <form method="POST">
                                                    <div class="mb-3">
                                                        <label class="form-label">Alert Title *</label>
                                                        <input type="text" class="form-control" name="alert_title" required 
                                                               placeholder="Enter alert title">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Alert Message *</label>
                                                        <textarea class="form-control" name="alert_message" rows="4" required 
                                                                  placeholder="Enter detailed alert message"></textarea>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Alert Type</label>
                                                                <select class="form-control" name="alert_type">
                                                                    <option value="general">📋 General</option>
                                                                    <option value="emergency">🚨 Emergency</option>
                                                                    <option value="maintenance">🔧 Maintenance</option>
                                                                    <option value="cleaning">🧹 Cleaning</option>
                                                                    <option value="guest_request">👤 Guest Request</option>
                                                                    <option value="shift_change">🔄 Shift Change</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Priority</label>
                                                                <select class="form-control" name="priority">
                                                                    <option value="low">🟢 Low</option>
                                                                    <option value="medium" selected>🟡 Medium</option>
                                                                    <option value="high">🟠 High</option>
                                                                    <option value="urgent">🔴 Urgent</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Target Staff</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="all_staff" 
                                                                   onchange="toggleAllStaff(this)">
                                                            <label class="form-check-label" for="all_staff">
                                                                Send to all active staff members
                                                            </label>
                                                        </div>
                                                        <div id="staffSelection" class="mt-2" style="max-height: 200px; overflow-y: auto;">
                                                            <?php if (!empty($staff_members)): ?>
                                                                <?php foreach ($staff_members as $staff): ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input staff-checkbox" type="checkbox" 
                                                                               name="target_staff[]" value="<?php echo $staff['id']; ?>" 
                                                                               id="staff_<?php echo $staff['id']; ?>">
                                                                        <label class="form-check-label" for="staff_<?php echo $staff['id']; ?>">
                                                                            <?php echo htmlspecialchars($staff['name']); ?> 
                                                                            (<?php echo htmlspecialchars($staff['department']); ?>)
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <p class="text-muted">No active staff members found.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Schedule Alert (Optional)</label>
                                                        <input type="datetime-local" class="form-control" name="scheduled_time">
                                                    </div>
                                                    
                                                    <button type="submit" name="create_alert" class="btn btn-primary w-100">
                                                        <i class="fas fa-bell me-2"></i> Send Alert
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Active Alerts & Staff Management -->
                                    <div class="col-md-6">
                                        <!-- Active Alerts -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="card-title">🚨 Active Alerts</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($alerts)): ?>
                                                    <?php foreach ($alerts as $alert): ?>
                                                        <div class="alert-card alert-priority-<?php echo $alert['priority']; ?> mb-3 p-3">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="flex-grow-1">
                                                                    <h6 class="mb-1"><?php echo htmlspecialchars($alert['alert_title']); ?></h6>
                                                                    <p class="mb-2 text-muted"><?php echo htmlspecialchars($alert['alert_message']); ?></p>
                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        <small class="badge bg-light text-dark">
                                                                            Type: <?php echo ucfirst($alert['alert_type']); ?>
                                                                        </small>
                                                                        <small class="badge bg-<?php echo getPriorityBadge($alert['priority']); ?>">
                                                                            Priority: <?php echo ucfirst($alert['priority']); ?>
                                                                        </small>
                                                                        <small class="badge bg-<?php echo getStatusBadge($alert['status']); ?>">
                                                                            <?php echo ucfirst($alert['status']); ?>
                                                                        </small>
                                                                        <small class="text-muted">
                                                                            <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                                <?php if ($alert['status'] == 'active'): ?>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                            type="button" data-bs-toggle="dropdown">
                                                                        Actions
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <li>
                                                                            <form method="POST" class="d-inline">
                                                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                                                <input type="hidden" name="status" value="completed">
                                                                                <button type="submit" name="update_alert_status" 
                                                                                        class="dropdown-item text-success">
                                                                                    ✅ Mark Completed
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="POST" class="d-inline">
                                                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                                                <input type="hidden" name="status" value="cancelled">
                                                                                <button type="submit" name="update_alert_status" 
                                                                                        class="dropdown-item text-danger">
                                                                                    ❌ Cancel Alert
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted text-center py-3">No active alerts</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Staff Management -->
                                        <div class="card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="card-title">👥 Staff Members (<?php echo count($all_staff_members); ?>)</h5>
                                                <a href="#addStaffForm" class="btn btn-sm btn-success" data-bs-toggle="collapse">
                                                    <i class="fas fa-user-plus"></i> Add Staff
                                                </a>
                                            </div>
                                            <div class="card-body">
                                                <!-- Add Staff Form (Collapsible) -->
                                                <div class="collapse" id="addStaffForm">
                                                    <form method="POST" class="mb-4 p-3 border rounded">
                                                        <h6>Add New Staff Member</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-2">
                                                                    <label class="form-label small">Name *</label>
                                                                    <input type="text" class="form-control form-control-sm" name="staff_name" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-2">
                                                                    <label class="form-label small">Phone *</label>
                                                                    <input type="tel" class="form-control form-control-sm" name="staff_phone" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-2">
                                                                    <label class="form-label small">Email</label>
                                                                    <input type="email" class="form-control form-control-sm" name="staff_email">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-2">
                                                                    <label class="form-label small">Role</label>
                                                                    <input type="text" class="form-control form-control-sm" name="staff_role">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-2">
                                                                    <label class="form-label small">Department</label>
                                                                    <select class="form-control form-control-sm" name="department">
                                                                        <option value="housekeeping">Housekeeping</option>
                                                                        <option value="reception">Reception</option>
                                                                        <option value="management">Management</option>
                                                                        <option value="maintenance">Maintenance</option>
                                                                        <option value="kitchen">Kitchen</option>
                                                                        <option value="security">Security</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-2">
                                                                    <label class="form-label small">Shift Timing</label>
                                                                    <input type="text" class="form-control form-control-sm" name="shift_timing" 
                                                                           placeholder="e.g., 9 AM - 6 PM">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button type="submit" name="add_staff" class="btn btn-success btn-sm w-100">
                                                            <i class="fas fa-user-plus"></i> Add Staff Member
                                                        </button>
                                                    </form>
                                                </div>

                                                <!-- Staff List -->
                                                <?php if (!empty($all_staff_members)): ?>
                                                    <div class="row">
                                                        <?php foreach ($all_staff_members as $staff): ?>
                                                            <div class="col-md-6 mb-3">
                                                                <div class="staff-card <?php echo $staff['is_active'] ? '' : 'inactive'; ?>">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
                                                                            <h6 class="mb-1"><?php echo htmlspecialchars($staff['name']); ?></h6>
                                                                            <small class="opacity-75">
                                                                                <?php echo htmlspecialchars($staff['position'] ?: $staff['role']); ?><br>
                                                                                📞 <?php echo htmlspecialchars($staff['phone']); ?><br>
                                                                                ⏰ <?php echo htmlspecialchars($staff['shift_timing']); ?>
                                                                            </small>
                                                                        </div>
                                                                        <form method="POST" class="ms-2">
                                                                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                                            <input type="hidden" name="is_active" value="<?php echo $staff['is_active'] ? 0 : 1; ?>">
                                                                            <button type="submit" name="update_staff_status" 
                                                                                    class="btn btn-sm <?php echo $staff['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                                                <?php echo $staff['is_active'] ? '❌' : '✅'; ?>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted text-center">No staff members added yet</p>
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

    <script>
    function toggleAllStaff(checkbox) {
        const staffCheckboxes = document.querySelectorAll('.staff-checkbox');
        staffCheckboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    }

    // Quick templates functionality
    const quickTemplates = {
        'cleaning': 'Room cleaning required. Please attend to room {{room_number}} immediately.',
        'maintenance': 'Maintenance required. Issue: {{issue_description}}. Location: {{location}}',
        'guest_request': 'Guest request: {{request_details}}. Room: {{room_number}}',
        'shift_change': 'Shift change reminder. Next shift: {{shift_time}}. Please ensure proper handover.',
        'emergency': 'EMERGENCY: {{emergency_details}}. Please respond immediately and follow emergency protocols.'
    };

    function useTemplate(type) {
        const messageField = document.querySelector('[name="alert_message"]');
        const titleField = document.querySelector('[name="alert_title"]');
        
        // Set message
        messageField.value = quickTemplates[type] || '';
        
        // Set appropriate title based on template type
        const titleMap = {
            'cleaning': 'Room Cleaning Required',
            'maintenance': 'Maintenance Alert',
            'guest_request': 'Guest Request',
            'shift_change': 'Shift Change Reminder',
            'emergency': 'EMERGENCY ALERT'
        };
        titleField.value = titleMap[type] || 'New Alert';
        
        // Set appropriate alert type
        document.querySelector('[name="alert_type"]').value = type;
        
        // Set appropriate priority for emergency
        if (type === 'emergency') {
            document.querySelector('[name="priority"]').value = 'urgent';
        }
        
        // Focus on message field for editing
        messageField.focus();
    }

    // Auto-refresh alerts every 30 seconds
    setInterval(() => {
        window.location.reload();
    }, 30000);
    </script>
</body>
</html>

<?php
// Helper functions
function getPriorityBadge($priority) {
    switch ($priority) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'orange';
        case 'urgent': return 'danger';
        default: return 'secondary';
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'active': return 'primary';
        case 'completed': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}
?>