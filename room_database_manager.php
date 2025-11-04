<?php
// room_database_manager.php
class RoomDatabaseManager {
    private $conn;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->conn = $connection;
        $this->user_id = $user_id;
    }
    
    // Check if all required tables exist
    public function checkRequiredTables() {
        $required_tables = [
            'rooms_' . $this->user_id,
            'room_types_' . $this->user_id,
            'bookings_' . $this->user_id,
            'guests_' . $this->user_id,
            'seasonal_rates_' . $this->user_id
        ];
        
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $check_sql = "SHOW TABLES LIKE '$table'";
            $result = $this->conn->query($check_sql);
            if ($result->num_rows == 0) {
                $missing_tables[] = $table;
            }
        }
        
        return $missing_tables;
    }
    
    // Create all required tables
    public function createAllTables() {
        $tables = $this->getTableDefinitions();
        $errors = [];
        $success = [];
        
        foreach ($tables as $table_name => $query) {
            try {
                if ($this->conn->query($query) === TRUE) {
                    $success[] = "Table '$table_name' created successfully";
                } else {
                    $errors[] = "Error creating table '$table_name': " . $this->conn->error;
                }
            } catch (Exception $e) {
                $errors[] = "Exception creating table '$table_name': " . $e->getMessage();
            }
        }
        
        return ['success' => $success, 'errors' => $errors];
    }
    
    // Get table definitions
    private function getTableDefinitions() {
        $user_id = $this->user_id;
        
        return [
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
            ",
            
            "seasonal_rates_$user_id" => "
                CREATE TABLE IF NOT EXISTS `seasonal_rates_$user_id` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `season_name` VARCHAR(100) NOT NULL,
                    `room_type_id` INT(11) NOT NULL,
                    `start_date` DATE NOT NULL,
                    `end_date` DATE NOT NULL,
                    `rate_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                    `fixed_rate` DECIMAL(10,2) DEFAULT NULL,
                    `min_nights` INT(3) DEFAULT 1,
                    `description` TEXT,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `room_type_id` (`room_type_id`),
                    KEY `start_date` (`start_date`),
                    KEY `end_date` (`end_date`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            "
        ];
    }
    
    // Insert sample data
    public function insertSampleData() {
        $user_id = $this->user_id;
        
        // Insert sample room types
        $room_types = [
            ['Deluxe Room', 'Spacious room with city view', 2500.00, 2],
            ['Superior Room', 'Luxury room with balcony', 3500.00, 2],
            ['Family Suite', 'Large suite for families', 5000.00, 4]
        ];
        
        foreach ($room_types as $type) {
            $sql = "INSERT IGNORE INTO room_types_$user_id (name, description, base_rate, max_occupancy) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssdi", $type[0], $type[1], $type[2], $type[3]);
            $stmt->execute();
        }
        
        // Insert sample rooms
        $rooms = [
            ['101', 1, '1', 2500.00],
            ['102', 1, '1', 2500.00],
            ['201', 2, '2', 3500.00],
            ['301', 3, '3', 5000.00]
        ];
        
        foreach ($rooms as $room) {
            $sql = "INSERT IGNORE INTO rooms_$user_id (room_number, room_type_id, floor, rate_per_night) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sisd", $room[0], $room[1], $room[2], $room[3]);
            $stmt->execute();
        }
        
        return true;
    }
}
?>