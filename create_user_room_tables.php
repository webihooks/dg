<?php
// create_user_room_tables.php

/**
 * Creates user-specific room management tables
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True if tables were created successfully, false otherwise
 */
function createUserRoomTables($conn, $user_id) {
    // Validate inputs
    if (!$conn || !$user_id) {
        error_log("Invalid parameters for createUserRoomTables: conn=" . ($conn ? "valid" : "invalid") . ", user_id=$user_id");
        return false;
    }

    // Check if tables already exist
    $check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
    $table_result = $conn->query($check_table_sql);
    if ($table_result && $table_result->num_rows > 0) {
        error_log("Tables already exist for user $user_id");
        return true; // Tables already exist
    }

    // Table creation queries with enhanced structure
    $tables = [
        "room_types_$user_id" => "
            CREATE TABLE IF NOT EXISTS `room_types_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `base_rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `max_occupancy` INT(3) DEFAULT 2,
                `amenities` TEXT,
                `images` TEXT,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_name` (`name`),
                KEY `is_active` (`is_active`),
                KEY `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "rooms_$user_id" => "
            CREATE TABLE IF NOT EXISTS `rooms_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `room_number` VARCHAR(20) NOT NULL,
                `room_type_id` INT(11) NOT NULL,
                `floor` VARCHAR(10) DEFAULT '1',
                `wing` VARCHAR(50) DEFAULT NULL,
                `status` ENUM('available', 'occupied', 'maintenance', 'cleaning', 'reserved') DEFAULT 'available',
                `rate_per_night` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `amenities` TEXT,
                `description` TEXT,
                `images` TEXT,
                `features` TEXT COMMENT 'JSON encoded features',
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_room_number` (`room_number`),
                KEY `room_type_id` (`room_type_id`),
                KEY `status` (`status`),
                KEY `floor` (`floor`),
                KEY `is_active` (`is_active`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `fk_rooms_room_type_$user_id` FOREIGN KEY (`room_type_id`) REFERENCES `room_types_$user_id` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "guests_$user_id" => "
            CREATE TABLE IF NOT EXISTS `guests_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `address` TEXT,
                `city` VARCHAR(100) DEFAULT NULL,
                `state` VARCHAR(100) DEFAULT NULL,
                `country` VARCHAR(100) DEFAULT 'India',
                `pincode` VARCHAR(10) DEFAULT NULL,
                `id_proof_type` ENUM('aadhaar', 'passport', 'driving_license', 'voter_id', 'other') DEFAULT NULL,
                `id_proof_number` VARCHAR(100) DEFAULT NULL,
                `id_proof_image` VARCHAR(500) DEFAULT NULL,
                `loyalty_points` INT(11) DEFAULT 0,
                `preferences` TEXT COMMENT 'JSON encoded preferences',
                `special_requests` TEXT,
                `is_blacklisted` TINYINT(1) DEFAULT 0,
                `blacklist_reason` TEXT,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_phone` (`phone`),
                UNIQUE KEY `unique_email` (`email`),
                KEY `name` (`name`),
                KEY `city` (`city`),
                KEY `is_blacklisted` (`is_blacklisted`),
                KEY `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "bookings_$user_id" => "
            CREATE TABLE IF NOT EXISTS `bookings_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `booking_reference` VARCHAR(50) NOT NULL,
                `guest_id` INT(11) DEFAULT NULL,
                `guest_name` VARCHAR(255) NOT NULL,
                `guest_phone` VARCHAR(20) NOT NULL,
                `guest_email` VARCHAR(255) DEFAULT NULL,
                `guest_address` TEXT,
                `room_id` INT(11) NOT NULL,
                `check_in_date` DATE NOT NULL,
                `check_out_date` DATE NOT NULL,
                `check_in_time` TIME DEFAULT NULL,
                `check_out_time` TIME DEFAULT NULL,
                `adults` INT(2) DEFAULT 1,
                `children` INT(2) DEFAULT 0,
                `infants` INT(2) DEFAULT 0,
                `total_nights` INT(3) DEFAULT 1,
                `room_rate` DECIMAL(12,2) NOT NULL,
                `subtotal` DECIMAL(12,2) NOT NULL,
                `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
                `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
                `discount_type` ENUM('percentage', 'fixed') DEFAULT NULL,
                `discount_value` DECIMAL(10,2) DEFAULT 0.00,
                `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
                `extra_charges` DECIMAL(12,2) DEFAULT 0.00,
                `total_amount` DECIMAL(12,2) NOT NULL,
                `advance_paid` DECIMAL(12,2) DEFAULT 0.00,
                `balance_amount` DECIMAL(12,2) DEFAULT 0.00,
                `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
                `payment_method` VARCHAR(50) DEFAULT NULL,
                `transaction_id` VARCHAR(100) DEFAULT NULL,
                `status` ENUM('reserved', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'reserved',
                `special_requests` TEXT,
                `cancellation_reason` TEXT,
                `cancelled_by` INT(11) DEFAULT NULL,
                `cancelled_at` DATETIME DEFAULT NULL,
                `checked_in_by` INT(11) DEFAULT NULL,
                `checked_out_by` INT(11) DEFAULT NULL,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_booking_reference` (`booking_reference`),
                KEY `guest_id` (`guest_id`),
                KEY `room_id` (`room_id`),
                KEY `check_in_date` (`check_in_date`),
                KEY `check_out_date` (`check_out_date`),
                KEY `status` (`status`),
                KEY `payment_status` (`payment_status`),
                KEY `guest_phone` (`guest_phone`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `fk_bookings_guest_$user_id` FOREIGN KEY (`guest_id`) REFERENCES `guests_$user_id` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_bookings_room_$user_id` FOREIGN KEY (`room_id`) REFERENCES `rooms_$user_id` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "payments_$user_id" => "
            CREATE TABLE IF NOT EXISTS `payments_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `booking_id` INT(11) NOT NULL,
                `payment_reference` VARCHAR(50) NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `payment_method` VARCHAR(50) NOT NULL,
                `transaction_id` VARCHAR(100) DEFAULT NULL,
                `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                `payment_date` DATETIME DEFAULT NULL,
                `notes` TEXT,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_payment_reference` (`payment_reference`),
                UNIQUE KEY `unique_transaction_id` (`transaction_id`),
                KEY `booking_id` (`booking_id`),
                KEY `payment_status` (`payment_status`),
                KEY `payment_date` (`payment_date`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `fk_payments_booking_$user_id` FOREIGN KEY (`booking_id`) REFERENCES `bookings_$user_id` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "maintenance_requests_$user_id" => "
            CREATE TABLE IF NOT EXISTS `maintenance_requests_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `room_id` INT(11) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
                `reported_by` INT(11) DEFAULT NULL,
                `assigned_to` INT(11) DEFAULT NULL,
                `estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
                `actual_cost` DECIMAL(10,2) DEFAULT 0.00,
                `start_date` DATETIME DEFAULT NULL,
                `completion_date` DATETIME DEFAULT NULL,
                `notes` TEXT,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `room_id` (`room_id`),
                KEY `priority` (`priority`),
                KEY `status` (`status`),
                KEY `reported_by` (`reported_by`),
                KEY `assigned_to` (`assigned_to`),
                CONSTRAINT `fk_maintenance_room_$user_id` FOREIGN KEY (`room_id`) REFERENCES `rooms_$user_id` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "housekeeping_$user_id" => "
            CREATE TABLE IF NOT EXISTS `housekeeping_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `room_id` INT(11) NOT NULL,
                `task_type` ENUM('cleaning', 'inspection', 'maintenance') DEFAULT 'cleaning',
                `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
                `assigned_to` INT(11) DEFAULT NULL,
                `scheduled_date` DATE NOT NULL,
                `scheduled_time` TIME DEFAULT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                `notes` TEXT,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `room_id` (`room_id`),
                KEY `task_type` (`task_type`),
                KEY `status` (`status`),
                KEY `scheduled_date` (`scheduled_date`),
                KEY `assigned_to` (`assigned_to`),
                CONSTRAINT `fk_housekeeping_room_$user_id` FOREIGN KEY (`room_id`) REFERENCES `rooms_$user_id` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "room_rates_$user_id" => "
            CREATE TABLE IF NOT EXISTS `room_rates_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `room_type_id` INT(11) NOT NULL,
                `season_name` VARCHAR(100) NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `rate` DECIMAL(12,2) NOT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `room_type_id` (`room_type_id`),
                KEY `season_dates` (`start_date`, `end_date`),
                KEY `is_active` (`is_active`),
                CONSTRAINT `fk_room_rates_type_$user_id` FOREIGN KEY (`room_type_id`) REFERENCES `room_types_$user_id` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        
        "settings_$user_id" => "
            CREATE TABLE IF NOT EXISTS `settings_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `setting_key` VARCHAR(100) NOT NULL,
                `setting_value` TEXT,
                `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
                `description` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    ];

    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Create tables
    $errors = [];
    foreach ($tables as $table_name => $query) {
        try {
            // Check if table exists first
            $check_sql = "SHOW TABLES LIKE '$table_name'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result && $check_result->num_rows > 0) {
                error_log("Table $table_name already exists, skipping creation");
                continue;
            }
            
            // Create table
            if ($conn->query($query) !== TRUE) {
                $error_msg = "Error creating table '$table_name': " . $conn->error;
                $errors[] = $error_msg;
                error_log($error_msg);
            } else {
                error_log("Successfully created table: $table_name");
            }
        } catch (Exception $e) {
            $error_msg = "Exception creating table '$table_name': " . $e->getMessage();
            $errors[] = $error_msg;
            error_log($error_msg);
        }
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // Insert default data if no errors
    if (empty($errors)) {
        insertDefaultSettings($conn, $user_id);
        insertDefaultRoomTypes($conn, $user_id);
        insertSampleRooms($conn, $user_id);
        insertDefaultRates($conn, $user_id);
        
        error_log("Successfully created all tables and default data for user $user_id");
        return true;
    } else {
        error_log("Errors occurred while creating tables for user $user_id: " . implode(', ', $errors));
        return false;
    }
}

/**
 * Insert default settings for the user
 */
function insertDefaultSettings($conn, $user_id) {
    $default_settings = [
        ['hotel_name', 'My Hotel', 'string', 'Name of your hotel/property'],
        ['hotel_address', '', 'string', 'Complete address of your property'],
        ['hotel_phone', '', 'string', 'Primary contact number'],
        ['hotel_email', '', 'string', 'Contact email address'],
        ['check_in_time', '14:00:00', 'string', 'Standard check-in time'],
        ['check_out_time', '12:00:00', 'string', 'Standard check-out time'],
        ['tax_rate', '12.00', 'number', 'GST tax rate in percentage'],
        ['currency', 'INR', 'string', 'Default currency'],
        ['currency_symbol', '₹', 'string', 'Currency symbol'],
        ['cancellation_policy', 'Free cancellation until 24 hours before check-in', 'string', 'Cancellation policy'],
        ['auto_checkout_enabled', '1', 'boolean', 'Enable automatic checkout at check-out time'],
        ['sms_notifications', '1', 'boolean', 'Enable SMS notifications'],
        ['email_notifications', '1', 'boolean', 'Enable email notifications']
    ];
    
    $sql = "INSERT IGNORE INTO settings_$user_id (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($default_settings as $setting) {
        $stmt->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[3]);
        if (!$stmt->execute()) {
            error_log("Error inserting setting {$setting[0]}: " . $stmt->error);
        }
    }
    $stmt->close();
}

/**
 * Insert default room types
 */
function insertDefaultRoomTypes($conn, $user_id) {
    $default_types = [
        ['Single Room', 'Cozy room with a single bed, perfect for solo travelers', 1500.00, 1, '["WiFi", "TV", "AC", "Attached Bathroom"]'],
        ['Double Room', 'Comfortable room with a double bed, ideal for couples', 2000.00, 2, '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service"]'],
        ['Deluxe Room', 'Spacious room with premium amenities and beautiful view', 3000.00, 2, '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Balcony"]'],
        ['Family Suite', 'Large suite with separate living area, perfect for families', 4500.00, 4, '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Sofa", "Dining Area"]'],
        ['Executive Suite', 'Luxury suite with premium amenities and exclusive services', 6000.00, 2, '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Jacuzzi", "Executive Lounge Access"]']
    ];
    
    $sql = "INSERT IGNORE INTO room_types_$user_id (name, description, base_rate, max_occupancy, amenities) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($default_types as $type) {
        $stmt->bind_param("ssdis", $type[0], $type[1], $type[2], $type[3], $type[4]);
        if (!$stmt->execute()) {
            error_log("Error inserting room type {$type[0]}: " . $stmt->error);
        }
    }
    $stmt->close();
}

/**
 * Insert sample rooms
 */
function insertSampleRooms($conn, $user_id) {
    // Get room type IDs
    $type_result = $conn->query("SELECT id, name FROM room_types_$user_id");
    if (!$type_result) {
        error_log("Error fetching room types: " . $conn->error);
        return;
    }
    
    $room_types = [];
    while ($row = $type_result->fetch_assoc()) {
        $room_types[$row['name']] = $row['id'];
    }
    
    $sample_rooms = [
        // Single Rooms
        ['101', $room_types['Single Room'] ?? 1, '1', 'A', 1500.00, 'Cozy single room with garden view', '["WiFi", "TV", "AC", "Attached Bathroom"]'],
        ['102', $room_types['Single Room'] ?? 1, '1', 'A', 1500.00, 'Quiet single room facing courtyard', '["WiFi", "TV", "AC", "Attached Bathroom"]'],
        ['103', $room_types['Single Room'] ?? 1, '1', 'A', 1500.00, 'Standard single room', '["WiFi", "TV", "AC", "Attached Bathroom"]'],
        
        // Double Rooms
        ['201', $room_types['Double Room'] ?? 2, '2', 'B', 2000.00, 'Comfortable double room with city view', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service"]'],
        ['202', $room_types['Double Room'] ?? 2, '2', 'B', 2000.00, 'Spacious double room', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service"]'],
        ['203', $room_types['Double Room'] ?? 2, '2', 'B', 2000.00, 'Double room with balcony', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Balcony"]'],
        
        // Deluxe Rooms
        ['301', $room_types['Deluxe Room'] ?? 3, '3', 'C', 3000.00, 'Deluxe room with mountain view', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Balcony"]'],
        ['302', $room_types['Deluxe Room'] ?? 3, '3', 'C', 3000.00, 'Premium deluxe room', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Balcony"]'],
        
        // Family Suites
        ['401', $room_types['Family Suite'] ?? 4, '4', 'D', 4500.00, 'Family suite with separate bedrooms', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Sofa", "Dining Area"]'],
        
        // Executive Suite
        ['501', $room_types['Executive Suite'] ?? 5, '5', 'E', 6000.00, 'Luxury executive suite', '["WiFi", "TV", "AC", "Attached Bathroom", "Room Service", "Minibar", "Jacuzzi", "Executive Lounge Access"]']
    ];
    
    $sql = "INSERT IGNORE INTO rooms_$user_id (room_number, room_type_id, floor, wing, rate_per_night, description, amenities) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($sample_rooms as $room) {
        $stmt->bind_param("sissdss", $room[0], $room[1], $room[2], $room[3], $room[4], $room[5], $room[6]);
        if (!$stmt->execute()) {
            error_log("Error inserting room {$room[0]}: " . $stmt->error);
        }
    }
    $stmt->close();
}

/**
 * Insert default seasonal rates
 */
function insertDefaultRates($conn, $user_id) {
    // Get room type IDs
    $type_result = $conn->query("SELECT id, name FROM room_types_$user_id");
    if (!$type_result) return;
    
    $room_types = [];
    while ($row = $type_result->fetch_assoc()) {
        $room_types[$row['name']] = $row['id'];
    }
    
    $current_year = date('Y');
    $seasonal_rates = [
        // Peak Season (December - January)
        [$room_types['Single Room'] ?? 1, 'Peak Season', $current_year . '-12-01', $current_year . '-01-31', 1800.00],
        [$room_types['Double Room'] ?? 2, 'Peak Season', $current_year . '-12-01', $current_year . '-01-31', 2400.00],
        [$room_types['Deluxe Room'] ?? 3, 'Peak Season', $current_year . '-12-01', $current_year . '-01-31', 3600.00],
        [$room_types['Family Suite'] ?? 4, 'Peak Season', $current_year . '-12-01', $current_year . '-01-31', 5400.00],
        [$room_types['Executive Suite'] ?? 5, 'Peak Season', $current_year . '-12-01', $current_year . '-01-31', 7200.00],
        
        // Holiday Season (April - May)
        [$room_types['Single Room'] ?? 1, 'Holiday Season', $current_year . '-04-01', $current_year . '-05-31', 1600.00],
        [$room_types['Double Room'] ?? 2, 'Holiday Season', $current_year . '-04-01', $current_year . '-05-31', 2100.00],
        [$room_types['Deluxe Room'] ?? 3, 'Holiday Season', $current_year . '-04-01', $current_year . '-05-31', 3200.00],
        [$room_types['Family Suite'] ?? 4, 'Holiday Season', $current_year . '-04-01', $current_year . '-05-31', 4800.00],
        [$room_types['Executive Suite'] ?? 5, 'Holiday Season', $current_year . '-04-01', $current_year . '-05-31', 6500.00]
    ];
    
    $sql = "INSERT IGNORE INTO room_rates_$user_id (room_type_id, season_name, start_date, end_date, rate) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($seasonal_rates as $rate) {
        $stmt->bind_param("isssd", $rate[0], $rate[1], $rate[2], $rate[3], $rate[4]);
        if (!$stmt->execute()) {
            error_log("Error inserting seasonal rate: " . $stmt->error);
        }
    }
    $stmt->close();
}

/**
 * Check if user tables exist
 */
function checkUserTablesExist($conn, $user_id) {
    $required_tables = [
        "room_types_$user_id",
        "rooms_$user_id", 
        "guests_$user_id",
        "bookings_$user_id",
        "settings_$user_id"
    ];
    
    foreach ($required_tables as $table) {
        $check_sql = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($check_sql);
        if (!$result || $result->num_rows == 0) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get table creation status
 */
function getTableCreationStatus($conn, $user_id) {
    $tables = [
        "room_types_$user_id" => "Room Types",
        "rooms_$user_id" => "Rooms", 
        "guests_$user_id" => "Guests",
        "bookings_$user_id" => "Bookings",
        "payments_$user_id" => "Payments",
        "maintenance_requests_$user_id" => "Maintenance",
        "housekeeping_$user_id" => "Housekeeping",
        "room_rates_$user_id" => "Room Rates",
        "settings_$user_id" => "Settings"
    ];
    
    $status = [];
    foreach ($tables as $table_name => $display_name) {
        $check_sql = "SHOW TABLES LIKE '$table_name'";
        $result = $conn->query($check_sql);
        $status[$display_name] = ($result && $result->num_rows > 0) ? 'exists' : 'missing';
    }
    
    return $status;
}

/**
 * Drop all user tables (for cleanup/reset)
 * WARNING: This will delete all user data
 */
function dropUserTables($conn, $user_id) {
    $tables = [
        "settings_$user_id",
        "room_rates_$user_id", 
        "housekeeping_$user_id",
        "maintenance_requests_$user_id",
        "payments_$user_id",
        "bookings_$user_id",
        "guests_$user_id",
        "rooms_$user_id",
        "room_types_$user_id"
    ];
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    $errors = [];
    foreach ($tables as $table_name) {
        $drop_sql = "DROP TABLE IF EXISTS `$table_name`";
        if (!$conn->query($drop_sql)) {
            $errors[] = "Error dropping table $table_name: " . $conn->error;
        }
    }
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    return empty($errors);
}
?>