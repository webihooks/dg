<?php
// room_db_helper.php

class RoomDBHelper {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    public function getAllTableDefinitions() {
        return [
            "rooms_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `rooms_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `room_number` VARCHAR(20) NOT NULL,
                    `room_type_id` INT(11) NOT NULL,
                    `floor` VARCHAR(10) DEFAULT NULL,
                    `status` ENUM('available', 'occupied', 'maintenance', 'cleaning', 'reserved') DEFAULT 'available',
                    `rate_per_night` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `amenities` TEXT,
                    `description` TEXT,
                    `images` TEXT,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `room_number` (`room_number`),
                    KEY `room_type_id` (`room_type_id`),
                    KEY `status` (`status`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "room_types_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `room_types_{$this->user_id}` (
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
                    UNIQUE KEY `name` (`name`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "bookings_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `bookings_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `booking_reference` VARCHAR(50) NOT NULL,
                    `guest_id` INT(11) DEFAULT NULL,
                    `guest_name` VARCHAR(255) NOT NULL,
                    `guest_phone` VARCHAR(20) NOT NULL,
                    `guest_email` VARCHAR(255) DEFAULT NULL,
                    `guest_address` TEXT,
                    `room_id` INT(11) NOT NULL,
                    `room_number` VARCHAR(20) NOT NULL,
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
                    `payment_method` VARCHAR(50) DEFAULT NULL,
                    `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
                    `status` ENUM('reserved', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'reserved',
                    `special_requests` TEXT,
                    `cancellation_reason` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `booking_reference` (`booking_reference`),
                    KEY `room_id` (`room_id`),
                    KEY `guest_id` (`guest_id`),
                    KEY `check_in_date` (`check_in_date`),
                    KEY `check_out_date` (`check_out_date`),
                    KEY `status` (`status`),
                    KEY `guest_phone` (`guest_phone`),
                    KEY `payment_status` (`payment_status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "guests_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `guests_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NOT NULL,
                    `phone` VARCHAR(20) NOT NULL,
                    `email` VARCHAR(255) DEFAULT NULL,
                    `address` TEXT,
                    `id_proof_type` VARCHAR(50) DEFAULT NULL,
                    `id_proof_number` VARCHAR(100) DEFAULT NULL,
                    `id_proof_image` VARCHAR(255) DEFAULT NULL,
                    `loyalty_points` INT(11) DEFAULT 0,
                    `total_stays` INT(11) DEFAULT 0,
                    `total_spent` DECIMAL(10,2) DEFAULT 0.00,
                    `preferences` TEXT,
                    `is_blacklisted` TINYINT(1) DEFAULT 0,
                    `blacklist_reason` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `phone` (`phone`),
                    KEY `email` (`email`),
                    KEY `is_blacklisted` (`is_blacklisted`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "housekeeping_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `housekeeping_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `room_id` INT(11) NOT NULL,
                    `room_number` VARCHAR(20) NOT NULL,
                    `task_type` ENUM('cleaning', 'maintenance', 'inspection', 'deep_cleaning') DEFAULT 'cleaning',
                    `assigned_to` VARCHAR(255) DEFAULT NULL,
                    `scheduled_date` DATE NOT NULL,
                    `scheduled_time` TIME DEFAULT NULL,
                    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
                    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                    `notes` TEXT,
                    `completed_at` DATETIME DEFAULT NULL,
                    `completed_by` VARCHAR(255) DEFAULT NULL,
                    `completion_notes` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `room_id` (`room_id`),
                    KEY `scheduled_date` (`scheduled_date`),
                    KEY `status` (`status`),
                    KEY `priority` (`priority`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "room_amenities_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `room_amenities_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `description` TEXT,
                    `icon` VARCHAR(50) DEFAULT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `name` (`name`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "room_rates_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `room_rates_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `room_type_id` INT(11) NOT NULL,
                    `season_name` VARCHAR(100) NOT NULL,
                    `start_date` DATE NOT NULL,
                    `end_date` DATE NOT NULL,
                    `rate_per_night` DECIMAL(10,2) NOT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `room_type_id` (`room_type_id`),
                    KEY `season_name` (`season_name`),
                    KEY `start_date` (`start_date`),
                    KEY `end_date` (`end_date`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "loyalty_programs_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `loyalty_programs_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `program_name` VARCHAR(100) NOT NULL,
                    `points_per_stay` INT(11) DEFAULT 0,
                    `points_per_amount` DECIMAL(10,2) DEFAULT 0.00,
                    `minimum_stays` INT(11) DEFAULT 0,
                    `minimum_amount` DECIMAL(10,2) DEFAULT 0.00,
                    `benefits` TEXT,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `program_name` (`program_name`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "staff_members_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `staff_members_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NOT NULL,
                    `email` VARCHAR(255) DEFAULT NULL,
                    `phone` VARCHAR(20) NOT NULL,
                    `role` ENUM('housekeeping', 'reception', 'manager', 'maintenance') DEFAULT 'housekeeping',
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `phone` (`phone`),
                    KEY `role` (`role`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "tax_settings_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `tax_settings_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `tax_name` VARCHAR(100) NOT NULL,
                    `tax_rate` DECIMAL(5,2) NOT NULL,
                    `tax_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tax_name` (`tax_name`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "payment_methods_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `payment_methods_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `method_name` VARCHAR(100) NOT NULL,
                    `description` TEXT,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `method_name` (`method_name`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            
            "room_maintenance_{$this->user_id}" => "
                CREATE TABLE IF NOT EXISTS `room_maintenance_{$this->user_id}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `room_id` INT(11) NOT NULL,
                    `room_number` VARCHAR(20) NOT NULL,
                    `issue_type` VARCHAR(100) NOT NULL,
                    `description` TEXT,
                    `reported_by` VARCHAR(255) DEFAULT NULL,
                    `reported_date` DATE NOT NULL,
                    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                    `status` ENUM('reported', 'in_progress', 'completed', 'cancelled') DEFAULT 'reported',
                    `assigned_to` VARCHAR(255) DEFAULT NULL,
                    `estimated_completion` DATE DEFAULT NULL,
                    `actual_completion` DATETIME DEFAULT NULL,
                    `cost` DECIMAL(10,2) DEFAULT 0.00,
                    `notes` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `room_id` (`room_id`),
                    KEY `status` (`status`),
                    KEY `priority` (`priority`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            "
        ];
    }
    
    public function createAllTables() {
        $tables = $this->getAllTableDefinitions();
        $results = [
            'success' => [],
            'errors' => []
        ];

        foreach ($tables as $table_name => $query) {
            try {
                if ($this->conn->query($query) === TRUE) {
                    $results['success'][] = $table_name;
                } else {
                    $results['errors'][] = "Error creating table '$table_name': " . $this->conn->error;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Exception creating table '$table_name': " . $e->getMessage();
            }
        }

        return $results;
    }
    
    public function checkAllTablesExist() {
        $tables = array_keys($this->getAllTableDefinitions());
        $results = [
            'existing' => [],
            'missing' => []
        ];

        foreach ($tables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                $results['existing'][] = $table;
            } else {
                $results['missing'][] = $table;
            }
        }

        return $results;
    }
    
    public function getTableName($base_name) {
        return $base_name . '_' . $this->user_id;
    }
    
    public function initializeSampleData() {
        $sample_data = [];
        
        // Sample room types
        $sample_data["room_types_{$this->user_id}"] = [
            "INSERT IGNORE INTO `room_types_{$this->user_id}` 
            (name, description, base_rate, max_occupancy, amenities) VALUES
            ('Standard Room', 'Comfortable room with basic amenities', 2500.00, 2, 'WiFi, TV, AC'),
            ('Deluxe Room', 'Spacious room with enhanced amenities', 4000.00, 3, 'WiFi, TV, AC, Mini Bar'),
            ('Suite', 'Luxurious suite with separate living area', 6000.00, 4, 'WiFi, TV, AC, Mini Bar, Jacuzzi')"
        ];
        
        // Sample amenities
        $sample_data["room_amenities_{$this->user_id}"] = [
            "INSERT IGNORE INTO `room_amenities_{$this->user_id}` (name, description, icon) VALUES
            ('WiFi', 'Free high-speed internet', 'wifi'),
            ('AC', 'Air conditioning', 'snowflake'),
            ('TV', 'Flat screen television', 'tv'),
            ('Mini Bar', 'Refrigerator with beverages', 'glass'),
            ('Jacuzzi', 'Hot tub bath', 'hot-tub')"
        ];
        
        // Sample tax settings
        $sample_data["tax_settings_{$this->user_id}"] = [
            "INSERT IGNORE INTO `tax_settings_{$this->user_id}` (tax_name, tax_rate, tax_type) VALUES
            ('GST', 18.00, 'percentage'),
            ('Service Charge', 10.00, 'percentage')"
        ];
        
        // Sample payment methods
        $sample_data["payment_methods_{$this->user_id}"] = [
            "INSERT IGNORE INTO `payment_methods_{$this->user_id}` (method_name, description) VALUES
            ('Cash', 'Cash payment'),
            ('Credit Card', 'Credit card payment'),
            ('Debit Card', 'Debit card payment'),
            ('UPI', 'UPI payment'),
            ('Net Banking', 'Internet banking')"
        ];
        
        $results = [];
        foreach ($sample_data as $table => $queries) {
            foreach ($queries as $query) {
                if ($this->conn->query($query) === TRUE) {
                    $results[$table] = 'Sample data inserted';
                } else {
                    $results[$table] = 'Error: ' . $this->conn->error;
                }
            }
        }
        
        return $results;
    }
    
    public function backupUserData() {
        $backup_file = "backup_room_data_{$this->user_id}_" . date('Y-m-d_H-i-s') . ".sql";
        $backup_content = "";
        
        $tables = array_keys($this->getAllTableDefinitions());
        
        foreach ($tables as $table) {
            // Get table structure
            $result = $this->conn->query("SHOW CREATE TABLE `$table`");
            if ($result) {
                $row = $result->fetch_assoc();
                $backup_content .= "-- Table structure for table `$table`\n";
                $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup_content .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $data_result = $this->conn->query("SELECT * FROM `$table`");
                if ($data_result->num_rows > 0) {
                    $backup_content .= "-- Data for table `$table`\n";
                    while ($data_row = $data_result->fetch_assoc()) {
                        $columns = implode('`, `', array_keys($data_row));
                        $values = implode("', '", array_map([$this->conn, 'real_escape_string'], array_values($data_row)));
                        $backup_content .= "INSERT INTO `$table` (`$columns`) VALUES ('$values');\n";
                    }
                    $backup_content .= "\n";
                }
            }
        }
        
        // Save to file
        file_put_contents($backup_file, $backup_content);
        return $backup_file;
    }
}

// Standalone functions for backward compatibility
function createUserRoomTables($conn, $user_id) {
    $helper = new RoomDBHelper($conn, $user_id);
    return $helper->createAllTables();
}

function checkUserTablesExist($conn, $user_id) {
    $helper = new RoomDBHelper($conn, $user_id);
    return $helper->checkAllTablesExist();
}

function getUserTableName($base_name, $user_id) {
    return $base_name . '_' . $user_id;
}
?>