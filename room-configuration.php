<?php
// room-configuration.php
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

// First, ensure user_config table exists
createUserConfigTable($conn);

// Check if room tables exist
$tables_exist = true;
$required_tables = ["rooms_$user_id", "room_types_$user_id", "bookings_$user_id", "guests_$user_id"];
$existing_tables = [];

foreach ($required_tables as $table) {
    $check_sql = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($check_sql);
    if ($result->num_rows > 0) {
        $existing_tables[] = $table;
    } else {
        $tables_exist = false;
    }
}

// Handle configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_tables'])) {
        // Create missing tables
        $created_tables = createUserTables($user_id, $conn);
        if (!empty($created_tables)) {
            $success_message = "Successfully created tables: " . implode(', ', $created_tables);
            $tables_exist = true;
            // Refresh existing tables list
            $existing_tables = $required_tables;
        } else {
            $error_message = "Failed to create tables. They may already exist.";
        }
    }
    elseif (isset($_POST['update_config'])) {
        // Update room configuration
        $hotel_name = $conn->real_escape_string($_POST['hotel_name']);
        $check_in_time = $conn->real_escape_string($_POST['check_in_time']);
        $check_out_time = $conn->real_escape_string($_POST['check_out_time']);
        $currency = $conn->real_escape_string($_POST['currency']);
        $timezone = $conn->real_escape_string($_POST['timezone']);
        $tax_rate = floatval($_POST['tax_rate']);
        $service_charge = floatval($_POST['service_charge']);
        
        // Store configuration in user_config table
        $config_sql = "INSERT INTO user_config (user_id, config_key, config_value, created_at, updated_at) 
                      VALUES (?, ?, ?, NOW(), NOW())
                      ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()";
        
        $config_data = [
            ['hotel_name', $hotel_name],
            ['check_in_time', $check_in_time],
            ['check_out_time', $check_out_time],
            ['currency', $currency],
            ['timezone', $timezone],
            ['tax_rate', $tax_rate],
            ['service_charge', $service_charge]
        ];
        
        $stmt = $conn->prepare($config_sql);
        $success_count = 0;
        
        foreach ($config_data as $data) {
            $stmt->bind_param("iss", $user_id, $data[0], $data[1]);
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        $stmt->close();
        
        if ($success_count > 0) {
            $success_message = "Configuration updated successfully!";
        } else {
            $error_message = "Failed to update configuration.";
        }
    }
    elseif (isset($_POST['add_sample_data'])) {
        // Add sample room types and rooms
        $sample_added = addSampleData($user_id, $conn);
        if ($sample_added) {
            $success_message = "Sample data added successfully!";
        } else {
            $error_message = "Failed to add sample data. Tables may not exist.";
        }
    }
}

// Get current configuration
$current_config = [];
try {
    $config_sql = "SELECT config_key, config_value FROM user_config WHERE user_id = ?";
    $stmt = $conn->prepare($config_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $current_config[$row['config_key']] = $row['config_value'];
    }
    $stmt->close();
} catch (Exception $e) {
    // If user_config table doesn't exist, create it
    createUserConfigTable($conn);
    error_log("Configuration table error: " . $e->getMessage());
}

$conn->close();

// Function to create user_config table
function createUserConfigTable($conn) {
    $user_config_table = "
        CREATE TABLE IF NOT EXISTS `user_config` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `config_key` VARCHAR(100) NOT NULL,
            `config_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_config_key` (`user_id`, `config_key`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        $conn->query($user_config_table);
        return true;
    } catch (Exception $e) {
        error_log("Error creating user_config table: " . $e->getMessage());
        return false;
    }
}

// Function to create user-specific tables
function createUserTables($user_id, $conn) {
    $tables = [
        "rooms_$user_id" => "
            CREATE TABLE IF NOT EXISTS `rooms_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `room_number` VARCHAR(20) NOT NULL,
                `room_type_id` INT(11) NOT NULL,
                `floor` VARCHAR(10) DEFAULT NULL,
                `status` ENUM('available', 'occupied', 'maintenance', 'cleaning', 'reserved') DEFAULT 'available',
                `rate_per_night` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `amenities` TEXT,
                `description` TEXT,
                `images` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `room_number` (`room_number`),
                KEY `room_type_id` (`room_type_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "room_types_$user_id" => "
            CREATE TABLE IF NOT EXISTS `room_types_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `base_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `max_occupancy` INT(3) DEFAULT 1,
                `amenities` TEXT,
                `images` TEXT,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "bookings_$user_id" => "
            CREATE TABLE IF NOT EXISTS `bookings_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `booking_reference` VARCHAR(50) NOT NULL,
                `guest_name` VARCHAR(255) NOT NULL,
                `guest_phone` VARCHAR(20) NOT NULL,
                `guest_email` VARCHAR(255) DEFAULT NULL,
                `guest_address` TEXT,
                `room_id` INT(11) NOT NULL,
                `check_in_date` DATE NOT NULL,
                `check_out_date` DATE NOT NULL,
                `adults` INT(2) DEFAULT 1,
                `children` INT(2) DEFAULT 0,
                `total_nights` INT(3) DEFAULT 1,
                `room_rate` DECIMAL(10,2) NOT NULL,
                `subtotal` DECIMAL(10,2) NOT NULL,
                `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
                `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
                `total_amount` DECIMAL(10,2) NOT NULL,
                `advance_paid` DECIMAL(10,2) DEFAULT 0.00,
                `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
                `status` ENUM('reserved', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'reserved',
                `special_requests` TEXT,
                `cancellation_reason` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `booking_reference` (`booking_reference`),
                KEY `room_id` (`room_id`),
                KEY `check_in_date` (`check_in_date`),
                KEY `check_out_date` (`check_out_date`),
                KEY `status` (`status`),
                KEY `guest_phone` (`guest_phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "guests_$user_id" => "
            CREATE TABLE IF NOT EXISTS `guests_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `address` TEXT,
                `id_proof_type` VARCHAR(50) DEFAULT NULL,
                `id_proof_number` VARCHAR(100) DEFAULT NULL,
                `id_proof_image` VARCHAR(255) DEFAULT NULL,
                `loyalty_points` INT(11) DEFAULT 0,
                `preferences` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `phone` (`phone`),
                KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        "
    ];

    $created_tables = [];
    foreach ($tables as $table_name => $query) {
        try {
            if ($conn->query($query) === TRUE) {
                $created_tables[] = $table_name;
            }
        } catch (Exception $e) {
            // Log error but continue
            error_log("Error creating table $table_name: " . $e->getMessage());
        }
    }
    
    return $created_tables;
}

// Function to add sample data
function addSampleData($user_id, $conn) {
    // Check if tables exist
    $check_sql = "SHOW TABLES LIKE 'room_types_$user_id'";
    $result = $conn->query($check_sql);
    if ($result->num_rows == 0) {
        return false;
    }

    // Add sample room types
    $room_types = [
        ['Standard Room', 'Comfortable room with basic amenities', 2500.00, 2, 'WiFi, TV, AC'],
        ['Deluxe Room', 'Spacious room with premium amenities', 4000.00, 3, 'WiFi, TV, AC, Mini Bar'],
        ['Suite', 'Luxurious suite with separate living area', 6000.00, 4, 'WiFi, TV, AC, Mini Bar, Jacuzzi']
    ];

    $type_stmt = $conn->prepare("INSERT IGNORE INTO room_types_$user_id (name, description, base_rate, max_occupancy, amenities) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($room_types as $type) {
        $type_stmt->bind_param("ssdis", $type[0], $type[1], $type[2], $type[3], $type[4]);
        $type_stmt->execute();
    }
    $type_stmt->close();

    // Add sample rooms
    $rooms = [
        ['101', 1, '1', 'available', 2500.00, 'Standard room with city view'],
        ['102', 1, '1', 'available', 2500.00, 'Standard room with garden view'],
        ['201', 2, '2', 'available', 4000.00, 'Deluxe room with balcony'],
        ['202', 2, '2', 'available', 4000.00, 'Deluxe room with sea view'],
        ['301', 3, '3', 'available', 6000.00, 'Executive suite'],
    ];

    $room_stmt = $conn->prepare("INSERT IGNORE INTO rooms_$user_id (room_number, room_type_id, floor, status, rate_per_night, description) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($rooms as $room) {
        $room_stmt->bind_param("sissds", $room[0], $room[1], $room[2], $room[3], $room[4], $room[5]);
        $room_stmt->execute();
    }
    $room_stmt->close();

    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Configuration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .config-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .config-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .table-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .table-exists { background: #d4edda; color: #155724; }
        .table-missing { background: #f8d7da; color: #721c24; }
        .setup-wizard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background: white;
            color: #667eea;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: rgba(255,255,255,0.3);
            z-index: -1;
        }
        .step:last-child::after {
            display: none;
        }
        .system-status {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-healthy { background: #d4edda; border: 1px solid #c3e6cb; }
        .status-warning { background: #fff3cd; border: 1px solid #ffeaa7; }
        .status-error { background: #f8d7da; border: 1px solid #f5c6cb; }
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
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- System Status -->
                        <div class="system-status <?php 
                            echo $tables_exist && !empty($current_config) ? 'status-healthy' : 
                                 ($tables_exist ? 'status-warning' : 'status-error'); 
                        ?>">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="mb-1">
                                        <?php if ($tables_exist && !empty($current_config)): ?>
                                            ✅ System Ready
                                        <?php elseif ($tables_exist): ?>
                                            ⚠️ System Partially Configured
                                        <?php else: ?>
                                            ❌ System Setup Required
                                        <?php endif; ?>
                                    </h5>
                                    <p class="mb-0">
                                        <?php if ($tables_exist && !empty($current_config)): ?>
                                            Your room management system is fully configured and ready to use.
                                        <?php elseif ($tables_exist): ?>
                                            Database tables are ready. Please complete the configuration.
                                        <?php else: ?>
                                            Please create the database tables to start using the room management system.
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar 
                                            <?php 
                                                $progress = 0;
                                                if ($tables_exist) $progress += 50;
                                                if (!empty($current_config)) $progress += 50;
                                                echo $progress == 100 ? 'bg-success' : ($progress >= 50 ? 'bg-warning' : 'bg-danger');
                                            ?>" 
                                            style="width: <?php echo $progress; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo $progress; ?>% Complete</small>
                                </div>
                            </div>
                        </div>

                        <!-- Setup Wizard -->
                        <div class="setup-wizard">
                            <h4>🏨 Room Management Setup Wizard</h4>
                            <p class="mb-0">Complete these steps to set up your room management system</p>
                            
                            <div class="step-indicator">
                                <div class="step <?php echo $tables_exist ? 'active' : ''; ?>">
                                    <div class="step-number">1</div>
                                    <div>Create Tables</div>
                                </div>
                                <div class="step <?php echo !empty($current_config) ? 'active' : ''; ?>">
                                    <div class="step-number">2</div>
                                    <div>Configuration</div>
                                </div>
                                <div class="step <?php echo $tables_exist && !empty($current_config) ? 'active' : ''; ?>">
                                    <div class="step-number">3</div>
                                    <div>Add Rooms</div>
                                </div>
                                <div class="step <?php echo $tables_exist && !empty($current_config) ? 'active' : ''; ?>">
                                    <div class="step-number">4</div>
                                    <div>Ready</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Database Setup Card -->
                            <div class="col-md-6">
                                <div class="card config-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">📊 Database Setup</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Your user-specific database tables will be created:</p>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Table Name</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($required_tables as $table): ?>
                                                        <tr>
                                                            <td><code><?php echo $table; ?></code></td>
                                                            <td>
                                                                <span class="table-status <?php echo in_array($table, $existing_tables) ? 'table-exists' : 'table-missing'; ?>">
                                                                    <?php echo in_array($table, $existing_tables) ? '✓ Exists' : '✗ Missing'; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <?php if (!$tables_exist): ?>
                                            <form method="POST" class="mt-3">
                                                <button type="submit" name="create_tables" class="btn btn-primary">
                                                    🚀 Create My Database Tables
                                                </button>
                                                <small class="form-text text-muted d-block mt-2">
                                                    This will create all necessary tables for your room management system.
                                                </small>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert alert-success mt-3">
                                                <strong>✓ All tables are ready!</strong> Your database is properly set up.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuration Card -->
                            <div class="col-md-6">
                                <div class="card config-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">⚙️ Hotel Configuration</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label for="hotel_name" class="form-label">Hotel Name *</label>
                                                    <input type="text" class="form-control" id="hotel_name" name="hotel_name" 
                                                           value="<?php echo $current_config['hotel_name'] ?? ''; ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="check_in_time" class="form-label">Check-in Time *</label>
                                                    <input type="time" class="form-control" id="check_in_time" name="check_in_time" 
                                                           value="<?php echo $current_config['check_in_time'] ?? '14:00'; ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="check_out_time" class="form-label">Check-out Time *</label>
                                                    <input type="time" class="form-control" id="check_out_time" name="check_out_time" 
                                                           value="<?php echo $current_config['check_out_time'] ?? '12:00'; ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="currency" class="form-label">Currency *</label>
                                                    <select class="form-control" id="currency" name="currency" required>
                                                        <option value="INR" <?php echo ($current_config['currency'] ?? 'INR') === 'INR' ? 'selected' : ''; ?>>₹ Indian Rupee (INR)</option>
                                                        <option value="USD" <?php echo ($current_config['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>$ US Dollar (USD)</option>
                                                        <option value="EUR" <?php echo ($current_config['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>€ Euro (EUR)</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="timezone" class="form-label">Timezone *</label>
                                                    <select class="form-control" id="timezone" name="timezone" required>
                                                        <option value="Asia/Kolkata" <?php echo ($current_config['timezone'] ?? 'Asia/Kolkata') === 'Asia/Kolkata' ? 'selected' : ''; ?>>India (Asia/Kolkata)</option>
                                                        <option value="America/New_York" <?php echo ($current_config['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>New York (America/New_York)</option>
                                                        <option value="Europe/London" <?php echo ($current_config['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London (Europe/London)</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                                           value="<?php echo $current_config['tax_rate'] ?? '18'; ?>" step="0.01" min="0" max="50">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="service_charge" class="form-label">Service Charge (%)</label>
                                                    <input type="number" class="form-control" id="service_charge" name="service_charge" 
                                                           value="<?php echo $current_config['service_charge'] ?? '5'; ?>" step="0.01" min="0" max="20">
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="update_config" class="btn btn-success" <?php echo !$tables_exist ? 'disabled' : ''; ?>>
                                                💾 Save Configuration
                                            </button>
                                            
                                            <?php if (!$tables_exist): ?>
                                                <small class="form-text text-muted d-block mt-2">
                                                    Please create database tables first before configuring settings.
                                                </small>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <?php if ($tables_exist): ?>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">🚀 Quick Setup Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h5>🛏️ Add Sample Data</h5>
                                                        <p class="text-muted">Add sample room types and rooms to get started quickly</p>
                                                        <form method="POST">
                                                            <button type="submit" name="add_sample_data" class="btn btn-outline-primary">
                                                                Add Sample Data
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h5>📝 Manage Rooms</h5>
                                                        <p class="text-muted">Add and manage your rooms and room types</p>
                                                        <a href="manage-rooms.php" class="btn btn-outline-primary">
                                                            Go to Rooms
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h5>📊 View Dashboard</h5>
                                                        <p class="text-muted">Check your room management dashboard</p>
                                                        <a href="room-dashboard.php" class="btn btn-outline-primary">
                                                            Go to Dashboard
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- System Information -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">ℹ️ System Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr>
                                                        <th>User ID:</th>
                                                        <td><code><?php echo $user_id; ?></code></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Database Prefix:</th>
                                                        <td><code>rooms_<?php echo $user_id; ?></code></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Tables Created:</th>
                                                        <td>
                                                            <span class="badge bg-<?php echo $tables_exist ? 'success' : 'danger'; ?>">
                                                                <?php echo count($existing_tables) . '/' . count($required_tables); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr>
                                                        <th>Configuration Status:</th>
                                                        <td>
                                                            <span class="badge bg-<?php echo !empty($current_config) ? 'success' : 'warning'; ?>">
                                                                <?php echo !empty($current_config) ? 'Configured' : 'Not Configured'; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Setup Progress:</th>
                                                        <td>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar 
                                                                    <?php 
                                                                        $progress = 0;
                                                                        if ($tables_exist) $progress += 50;
                                                                        if (!empty($current_config)) $progress += 50;
                                                                        echo $progress == 100 ? 'bg-success' : ($progress >= 50 ? 'bg-warning' : 'bg-danger');
                                                                    ?>" 
                                                                    style="width: <?php echo $progress; ?>%">
                                                                </div>
                                                            </div>
                                                            <small class="text-muted"><?php echo $progress; ?>% Complete</small>
                                                        </td>
                                                    </tr>
                                                </table>
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
        // Form validation
        $('form').on('submit', function() {
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Processing...');
        });

        // Auto-suggest hotel name based on user name
        const userName = "<?php echo $user_name; ?>";
        if (userName && !$('#hotel_name').val()) {
            $('#hotel_name').val(userName + "'s Hotel");
        }

        // Update progress bar color based on progress
        function updateProgress() {
            const progress = <?php 
                $progress = 0;
                if ($tables_exist) $progress += 50;
                if (!empty($current_config)) $progress += 50;
                echo $progress;
            ?>;
            
            $('.progress-bar').css('width', progress + '%');
        }

        updateProgress();
    });
    </script>
</body>
</html>