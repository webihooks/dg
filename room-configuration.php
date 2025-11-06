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

// First, ensure user_config table exists with proper structure
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

// Load current configuration
$current_config = loadCurrentConfig($user_id, $conn);

// Check if configuration is complete (now only tables matter)
$config_complete = $tables_exist;

// Calculate progress - 100% when tables are created
$progress = $tables_exist ? 100 : 0;

$conn->close();

// Function to load current configuration
function loadCurrentConfig($user_id, $conn) {
    $config = [];
    try {
        $config_sql = "SELECT config_key, config_value FROM user_config WHERE user_id = ?";
        $stmt = $conn->prepare($config_sql);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $config[$row['config_key']] = $row['config_value'];
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Configuration retrieval error: " . $e->getMessage());
    }
    return $config;
}

// Function to create user_config table with proper structure
function createUserConfigTable($conn) {
    $user_config_table = "
        CREATE TABLE IF NOT EXISTS `user_config` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `config_key` VARCHAR(100) NOT NULL,
            `config_value` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_config` (`user_id`, `config_key`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_config_key` (`config_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    try {
        $result = $conn->query($user_config_table);
        if ($result === FALSE) {
            error_log("Error creating user_config table: " . $conn->error);
            return false;
        }
        
        // Check if table was created or already exists
        $check_table = "SHOW TABLES LIKE 'user_config'";
        $table_result = $conn->query($check_table);
        return $table_result->num_rows > 0;
        
    } catch (Exception $e) {
        error_log("Exception creating user_config table: " . $e->getMessage());
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
                `wing` VARCHAR(50) DEFAULT NULL,
                `status` ENUM('available', 'occupied', 'maintenance', 'cleaning', 'reserved') DEFAULT 'available',
                `rate_per_night` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `amenities` TEXT DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `images` TEXT DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `room_number` (`room_number`),
                KEY `room_type_id` (`room_type_id`),
                KEY `status` (`status`),
                KEY `is_active` (`is_active`),
                KEY `floor_wing` (`floor`,`wing`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ",
        
        "room_types_$user_id" => "
            CREATE TABLE IF NOT EXISTS `room_types_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `base_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `max_occupancy` INT(3) DEFAULT 1,
                `size_sqft` INT(5) DEFAULT NULL,
                `bed_type` VARCHAR(50) DEFAULT NULL,
                `amenities` TEXT DEFAULT NULL,
                `images` TEXT DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ",
        
        "bookings_$user_id" => "
            CREATE TABLE IF NOT EXISTS `bookings_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `booking_reference` VARCHAR(50) NOT NULL,
                `guest_id` INT(11) DEFAULT NULL,
                `guest_name` VARCHAR(255) NOT NULL,
                `guest_phone` VARCHAR(20) NOT NULL,
                `guest_email` VARCHAR(255) DEFAULT NULL,
                `guest_address` TEXT DEFAULT NULL,
                `id_proof_type` VARCHAR(50) DEFAULT NULL,
                `id_proof_number` VARCHAR(100) DEFAULT NULL,
                `room_id` INT(11) NOT NULL,
                `room_number` VARCHAR(20) NOT NULL,
                `check_in_date` DATE NOT NULL,
                `check_out_date` DATE NOT NULL,
                `actual_check_in` DATETIME DEFAULT NULL,
                `actual_check_out` DATETIME DEFAULT NULL,
                `adults` INT(2) DEFAULT 1,
                `children` INT(2) DEFAULT 0,
                `total_nights` INT(3) DEFAULT 1,
                `room_rate` DECIMAL(10,2) NOT NULL,
                `subtotal` DECIMAL(10,2) NOT NULL,
                `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
                `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
                `additional_charges` DECIMAL(10,2) DEFAULT 0.00,
                `total_amount` DECIMAL(10,2) NOT NULL,
                `advance_paid` DECIMAL(10,2) DEFAULT 0.00,
                `balance_due` DECIMAL(10,2) DEFAULT 0.00,
                `payment_method` VARCHAR(50) DEFAULT NULL,
                `payment_status` ENUM('pending','paid','partial','refunded') DEFAULT 'pending',
                `status` ENUM('reserved','checked_in','checked_out','cancelled','no_show') DEFAULT 'reserved',
                `source` ENUM('website','walk_in','phone','agent','online') DEFAULT 'walk_in',
                `special_requests` TEXT DEFAULT NULL,
                `additional_notes` TEXT DEFAULT NULL,
                `cancellation_reason` TEXT DEFAULT NULL,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `booking_reference` (`booking_reference`),
                KEY `room_id` (`room_id`),
                KEY `guest_id` (`guest_id`),
                KEY `check_in_date` (`check_in_date`),
                KEY `check_out_date` (`check_out_date`),
                KEY `status` (`status`),
                KEY `guest_phone` (`guest_phone`),
                KEY `payment_status` (`payment_status`),
                KEY `source` (`source`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ",
        
        "guests_$user_id" => "
            CREATE TABLE IF NOT EXISTS `guests_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `address` TEXT DEFAULT NULL,
                `city` VARCHAR(100) DEFAULT NULL,
                `state` VARCHAR(100) DEFAULT NULL,
                `country` VARCHAR(100) DEFAULT NULL,
                `id_proof_type` VARCHAR(50) DEFAULT NULL,
                `id_proof_number` VARCHAR(100) DEFAULT NULL,
                `id_proof_image` VARCHAR(255) DEFAULT NULL,
                `loyalty_points` INT(11) DEFAULT 0,
                `total_stays` INT(11) DEFAULT 0,
                `total_spent` DECIMAL(15,2) DEFAULT 0.00,
                `preferences` TEXT DEFAULT NULL,
                `special_notes` TEXT DEFAULT NULL,
                `is_blacklisted` TINYINT(1) DEFAULT 0,
                `blacklist_reason` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `phone` (`phone`),
                UNIQUE KEY `email` (`email`),
                KEY `is_blacklisted` (`is_blacklisted`),
                KEY `loyalty_points` (`loyalty_points`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        "
    ];

    $created_tables = [];
    foreach ($tables as $table_name => $query) {
        try {
            if ($conn->query($query) === TRUE) {
                $created_tables[] = $table_name;
            } else {
                error_log("Error creating table $table_name: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log("Exception creating table $table_name: " . $e->getMessage());
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
        if (!$type_stmt->execute()) {
            error_log("Error inserting room type: " . $type_stmt->error);
        }
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
        if (!$room_stmt->execute()) {
            error_log("Error inserting room: " . $room_stmt->error);
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .success-card {
            border-left: 4px solid #28a745;
        }
        .action-card {
            transition: all 0.3s ease;
            height: 100%;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- System Status -->
                        <div class="system-status <?php echo $progress == 100 ? 'status-healthy' : 'status-error'; ?>">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="mb-1">
                                        <?php if ($progress == 100): ?>
                                            <i class="fas fa-check-circle me-2"></i>System Ready
                                        <?php else: ?>
                                            <i class="fas fa-times-circle me-2"></i>System Setup Required
                                        <?php endif; ?>
                                    </h5>
                                    <p class="mb-0">
                                        <?php if ($progress == 100): ?>
                                            Your room management system is fully configured and ready to use.
                                        <?php else: ?>
                                            Please create the database tables to start using the room management system.
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar <?php echo $progress == 100 ? 'bg-success' : 'bg-danger'; ?>" 
                                            style="width: <?php echo $progress; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo $progress; ?>% Complete</small>
                                </div>
                            </div>
                        </div>

                        <!-- Setup Wizard -->
                        <div class="setup-wizard">
                            <h4><i class="fas fa-hotel me-2"></i>Room Management Setup Wizard</h4>
                            <p class="mb-0">Complete this step to set up your room management system</p>
                            
                            <div class="step-indicator">
                                <div class="step <?php echo $tables_exist ? 'active' : ''; ?>">
                                    <div class="step-number">1</div>
                                    <div>Create Tables</div>
                                </div>
                                <div class="step <?php echo $tables_exist ? 'active' : ''; ?>">
                                    <div class="step-number">2</div>
                                    <div>Ready</div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <?php if (!$tables_exist): ?>
                        <!-- Database Setup Required -->
                        <div class="row">
                            <div class="col-md-8 mx-auto">
                                <div class="card config-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="fas fa-database me-2"></i>Database Setup Required</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="mb-4">
                                            <i class="fas fa-database fa-4x text-primary mb-3"></i>
                                            <h4>Create Your Database Tables</h4>
                                            <p class="text-muted">Your user-specific database tables need to be created before you can use the room management system.</p>
                                        </div>
                                        
                                        <div class="table-responsive mb-4">
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
                                                                <span class="table-status table-missing">
                                                                    ✗ Missing
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <form method="POST">
                                            <button type="submit" name="create_tables" class="btn btn-primary btn-lg">
                                                <i class="fas fa-rocket me-2"></i>Create My Database Tables
                                            </button>
                                            <small class="form-text text-muted d-block mt-2">
                                                This will create all necessary tables for your room management system.
                                            </small>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- System Ready -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card success-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0 text-success">
                                            <i class="fas fa-check-circle me-2"></i>System Successfully Configured
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="alert alert-success">
                                                    <h5><i class="fas fa-thumbs-up me-2"></i>All Set!</h5>
                                                    <p class="mb-0">Your room management system is ready to use. You can now start adding rooms and managing bookings.</p>
                                                </div>
                                                
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead class="table-light">
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
                                                                        <span class="table-status table-exists">
                                                                            ✓ Ready
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h5><i class="fas fa-info-circle me-2"></i>Next Steps</h5>
                                                        <ul class="list-unstyled">
                                                            <li class="mb-2">
                                                                <i class="fas fa-arrow-right text-success me-2"></i>
                                                                <strong>Add Room Types:</strong> Define different types of rooms (Standard, Deluxe, Suite, etc.)
                                                            </li>
                                                            <li class="mb-2">
                                                                <i class="fas fa-arrow-right text-success me-2"></i>
                                                                <strong>Add Rooms:</strong> Create individual rooms with room numbers and rates
                                                            </li>
                                                            <li class="mb-2">
                                                                <i class="fas fa-arrow-right text-success me-2"></i>
                                                                <strong>Manage Bookings:</strong> Start accepting and managing guest bookings
                                                            </li>
                                                            <li class="mb-2">
                                                                <i class="fas fa-arrow-right text-success me-2"></i>
                                                                <strong>Track Guests:</strong> Maintain guest records and preferences
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>Quick Setup Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <div class="card action-card bg-light h-100">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-magic fa-2x text-primary mb-3"></i>
                                                        <h5>Add Sample Data</h5>
                                                        <p class="text-muted">Add sample room types and rooms to get started quickly</p>
                                                        <form method="POST" class="mt-3">
                                                            <button type="submit" name="add_sample_data" class="btn btn-outline-primary">
                                                                <i class="fas fa-plus me-1"></i>Add Sample Data
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <div class="card action-card bg-light h-100">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-edit fa-2x text-success mb-3"></i>
                                                        <h5>Manage Rooms</h5>
                                                        <p class="text-muted">Add and manage your rooms and room types</p>
                                                        <a href="manage-rooms.php" class="btn btn-outline-success mt-3">
                                                            <i class="fas fa-arrow-right me-1"></i>Go to Rooms
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <div class="card action-card bg-light h-100">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-tachometer-alt fa-2x text-info mb-3"></i>
                                                        <h5>View Dashboard</h5>
                                                        <p class="text-muted">Check your room management dashboard</p>
                                                        <a href="room-dashboard.php" class="btn btn-outline-info mt-3">
                                                            <i class="fas fa-arrow-right me-1"></i>Go to Dashboard
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
                                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
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
                                                        <th>Setup Progress:</th>
                                                        <td>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar <?php echo $progress == 100 ? 'bg-success' : 'bg-danger'; ?>" 
                                                                    style="width: <?php echo $progress; ?>%">
                                                                </div>
                                                            </div>
                                                            <small class="text-muted"><?php echo $progress; ?>% Complete</small>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Current Status:</th>
                                                        <td>
                                                            <?php if ($progress == 100): ?>
                                                                <span class="text-success"><i class="fas fa-check-circle me-1"></i>Ready to Use</span>
                                                            <?php else: ?>
                                                                <span class="text-danger"><i class="fas fa-times-circle me-1"></i>Setup Required</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Required Action:</th>
                                                        <td>
                                                            <?php if (!$tables_exist): ?>
                                                                <span class="text-danger">Create database tables</span>
                                                            <?php else: ?>
                                                                <span class="text-success">No action required</span>
                                                            <?php endif; ?>
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
    });
    </script>
</body>
</html>