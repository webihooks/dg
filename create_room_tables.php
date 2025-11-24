<?php
// create_room_tables.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_messages = [];
$error_messages = [];
$table_results = [];

// Check if we should force recreate tables
$force_recreate = isset($_GET['force']) && $_GET['force'] == 'true';

// Define tables in correct dependency order (parents first, children later)
$tables = [
    // Independent tables first (no foreign keys)
    "room_types_$user_id" => "
        CREATE TABLE IF NOT EXISTS `room_types_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `base_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `max_occupancy` INT(3) DEFAULT 1,
            `size_sqft` INT(5) DEFAULT NULL,
            `bed_type` VARCHAR(50) DEFAULT NULL,
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
    
    "room_amenities_$user_id" => "
        CREATE TABLE IF NOT EXISTS `room_amenities_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `icon` VARCHAR(50) DEFAULT NULL,
            `description` TEXT,
            `category` ENUM('basic', 'premium', 'luxury') DEFAULT 'basic',
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`),
            KEY `category` (`category`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
            `country` VARCHAR(100) DEFAULT NULL,
            `id_proof_type` VARCHAR(50) DEFAULT NULL,
            `id_proof_number` VARCHAR(100) DEFAULT NULL,
            `id_proof_image` VARCHAR(255) DEFAULT NULL,
            `loyalty_points` INT(11) DEFAULT 0,
            `total_stays` INT(11) DEFAULT 0,
            `total_spent` DECIMAL(15,2) DEFAULT 0.00,
            `preferences` TEXT,
            `special_notes` TEXT,
            `is_blacklisted` TINYINT(1) DEFAULT 0,
            `blacklist_reason` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `phone` (`phone`),
            UNIQUE KEY `email` (`email`),
            KEY `is_blacklisted` (`is_blacklisted`),
            KEY `loyalty_points` (`loyalty_points`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    "tax_settings_$user_id" => "
        CREATE TABLE IF NOT EXISTS `tax_settings_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tax_name` VARCHAR(100) NOT NULL,
            `tax_rate` DECIMAL(5,2) NOT NULL,
            `tax_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
            `is_active` TINYINT(1) DEFAULT 1,
            `apply_to` ENUM('room_rate', 'extra_charges', 'both') DEFAULT 'room_rate',
            `description` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tax_name` (`tax_name`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    "discounts_$user_id" => "
        CREATE TABLE IF NOT EXISTS `discounts_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `discount_name` VARCHAR(100) NOT NULL,
            `discount_code` VARCHAR(50) DEFAULT NULL,
            `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
            `discount_value` DECIMAL(10,2) NOT NULL,
            `min_stay` INT(3) DEFAULT 0,
            `min_amount` DECIMAL(10,2) DEFAULT 0.00,
            `max_discount` DECIMAL(10,2) DEFAULT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `usage_limit` INT(5) DEFAULT NULL,
            `used_count` INT(5) DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `applicable_room_types` TEXT,
            `description` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `discount_code` (`discount_code`),
            KEY `discount_dates` (`start_date`, `end_date`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    "staff_members_$user_id" => "
        CREATE TABLE IF NOT EXISTS `staff_members_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `staff_code` VARCHAR(20) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `department` ENUM('housekeeping', 'reception', 'management', 'maintenance', 'kitchen', 'security') DEFAULT 'housekeeping',
            `position` VARCHAR(100) DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `shift_timing` VARCHAR(100) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `staff_code` (`staff_code`),
            UNIQUE KEY `phone` (`phone`),
            UNIQUE KEY `email` (`email`),
            KEY `department` (`department`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Alert templates (independent)
    "alert_templates_$user_id" => "
        CREATE TABLE IF NOT EXISTS `alert_templates_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `template_name` VARCHAR(100) NOT NULL,
            `template_type` ENUM('email', 'sms', 'whatsapp', 'push') NOT NULL,
            `subject` VARCHAR(255) DEFAULT NULL,
            `message` TEXT NOT NULL,
            `variables` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `template_name_type` (`template_name`, `template_type`),
            KEY `template_type` (`template_type`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Alert recipients (independent)
    "alert_recipients_$user_id" => "
        CREATE TABLE IF NOT EXISTS `alert_recipients_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recipient_name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `whatsapp` VARCHAR(20) DEFAULT NULL,
            `recipient_type` ENUM('owner', 'manager', 'staff', 'reception') DEFAULT 'staff',
            `alert_types` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            UNIQUE KEY `phone` (`phone`),
            KEY `recipient_type` (`recipient_type`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Rooms table (depends on room_types)
    "rooms_$user_id" => "
        CREATE TABLE IF NOT EXISTS `rooms_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `room_number` VARCHAR(20) NOT NULL,
            `room_type_id` INT(11) NOT NULL,
            `floor` VARCHAR(10) DEFAULT NULL,
            `wing` VARCHAR(50) DEFAULT NULL,
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
            KEY `is_active` (`is_active`),
            KEY `floor_wing` (`floor`, `wing`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    // Room rates table (depends on room_types)
    "room_rates_$user_id" => "
        CREATE TABLE IF NOT EXISTS `room_rates_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `room_type_id` INT(11) NOT NULL,
            `season_name` VARCHAR(100) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `rate_per_night` DECIMAL(10,2) NOT NULL,
            `extra_adult_charge` DECIMAL(10,2) DEFAULT 0.00,
            `extra_child_charge` DECIMAL(10,2) DEFAULT 0.00,
            `breakfast_included` TINYINT(1) DEFAULT 0,
            `min_stay` INT(3) DEFAULT 1,
            `max_stay` INT(3) DEFAULT 30,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `room_type_id` (`room_type_id`),
            KEY `season_dates` (`start_date`, `end_date`),
            KEY `is_active` (`is_active`),
            UNIQUE KEY `unique_season_rate` (`room_type_id`, `season_name`, `start_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Bookings table (depends on rooms and guests)
    "bookings_$user_id" => "
        CREATE TABLE IF NOT EXISTS `bookings_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `booking_reference` VARCHAR(50) NOT NULL,
            `guest_id` INT(11) DEFAULT NULL,
            `guest_name` VARCHAR(255) NOT NULL,
            `guest_phone` VARCHAR(20) NOT NULL,
            `guest_email` VARCHAR(255) DEFAULT NULL,
            `guest_address` TEXT,
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
            `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
            `status` ENUM('reserved', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'reserved',
            `source` ENUM('website', 'walk_in', 'phone', 'agent', 'online') DEFAULT 'walk_in',
            `special_requests` TEXT,
            `additional_notes` TEXT,
            `cancellation_reason` TEXT,
            `created_by` INT(11) DEFAULT NULL,
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
            KEY `payment_status` (`payment_status`),
            KEY `source` (`source`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    // Room maintenance table (depends on rooms)
    "room_maintenance_$user_id" => "
        CREATE TABLE IF NOT EXISTS `room_maintenance_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `room_id` INT(11) NOT NULL,
            `room_number` VARCHAR(20) NOT NULL,
            `maintenance_type` ENUM('cleaning', 'repair', 'renovation', 'inspection', 'upgrade') DEFAULT 'repair',
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `reported_by` VARCHAR(255) DEFAULT NULL,
            `reported_date` DATE NOT NULL,
            `start_date` DATE NOT NULL,
            `expected_end_date` DATE NOT NULL,
            `actual_end_date` DATE DEFAULT NULL,
            `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            `status` ENUM('reported', 'approved', 'in_progress', 'completed', 'cancelled') DEFAULT 'reported',
            `assigned_to` VARCHAR(255) DEFAULT NULL,
            `estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
            `actual_cost` DECIMAL(10,2) DEFAULT 0.00,
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `room_id` (`room_id`),
            KEY `status` (`status`),
            KEY `priority` (`priority`),
            KEY `maintenance_dates` (`start_date`, `expected_end_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Housekeeping schedule table (depends on rooms)
    "housekeeping_$user_id" => "
        CREATE TABLE IF NOT EXISTS `housekeeping_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `room_id` INT(11) NOT NULL,
            `room_number` VARCHAR(20) NOT NULL,
            `task_date` DATE NOT NULL,
            `task_time` TIME DEFAULT NULL,
            `task_type` ENUM('daily', 'checkout', 'deep', 'special', 'turn_down') DEFAULT 'daily',
            `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'verified') DEFAULT 'scheduled',
            `assigned_to` VARCHAR(255) DEFAULT NULL,
            `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            `notes` TEXT,
            `completed_at` DATETIME DEFAULT NULL,
            `completed_by` VARCHAR(255) DEFAULT NULL,
            `verified_by` VARCHAR(255) DEFAULT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `room_id` (`room_id`),
            KEY `task_date` (`task_date`),
            KEY `status` (`status`),
            KEY `task_type` (`task_type`),
            KEY `priority` (`priority`),
            UNIQUE KEY `unique_daily_task` (`room_id`, `task_date`, `task_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    // Payments table (depends on bookings)
    "payments_$user_id" => "
        CREATE TABLE IF NOT EXISTS `payments_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `booking_id` INT(11) NOT NULL,
            `payment_reference` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `payment_method` ENUM('cash', 'credit_card', 'debit_card', 'upi', 'bank_transfer', 'cheque', 'online') DEFAULT 'cash',
            `payment_type` ENUM('advance', 'full', 'partial', 'refund') DEFAULT 'advance',
            `payment_status` ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
            `transaction_id` VARCHAR(255) DEFAULT NULL,
            `payment_date` DATE NOT NULL,
            `payment_time` TIME DEFAULT NULL,
            `collected_by` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `payment_reference` (`payment_reference`),
            KEY `booking_id` (`booking_id`),
            KEY `payment_status` (`payment_status`),
            KEY `payment_date` (`payment_date`),
            KEY `payment_method` (`payment_method`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    // Loyalty program table (depends on guests)
    "loyalty_program_$user_id" => "
        CREATE TABLE IF NOT EXISTS `loyalty_program_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `guest_id` INT(11) NOT NULL,
            `tier_name` ENUM('standard', 'silver', 'gold', 'platinum') DEFAULT 'standard',
            `points_earned` INT(11) DEFAULT 0,
            `points_redeemed` INT(11) DEFAULT 0,
            `current_points` INT(11) DEFAULT 0,
            `total_stays` INT(11) DEFAULT 0,
            `total_amount` DECIMAL(15,2) DEFAULT 0.00,
            `membership_since` DATE DEFAULT NULL,
            `last_activity` TIMESTAMP NULL DEFAULT NULL,
            `benefits` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `guest_id` (`guest_id`),
            KEY `tier_name` (`tier_name`),
            KEY `is_active` (`is_active`),
            KEY `current_points` (`current_points`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    // Extra charges table (depends on bookings)
    "extra_charges_$user_id" => "
        CREATE TABLE IF NOT EXISTS `extra_charges_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `booking_id` INT(11) NOT NULL,
            `charge_type` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `amount` DECIMAL(10,2) NOT NULL,
            `quantity` INT(3) DEFAULT 1,
            `total_amount` DECIMAL(10,2) NOT NULL,
            `added_by` VARCHAR(255) DEFAULT NULL,
            `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `is_paid` TINYINT(1) DEFAULT 0,
            `notes` TEXT,
            PRIMARY KEY (`id`),
            KEY `booking_id` (`booking_id`),
            KEY `charge_type` (`charge_type`),
            KEY `is_paid` (`is_paid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Booking alerts settings table
    "booking_alerts_$user_id" => "
        CREATE TABLE IF NOT EXISTS `booking_alerts_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `email_alerts` TINYINT(1) DEFAULT 0,
            `sms_alerts` TINYINT(1) DEFAULT 0,
            `whatsapp_alerts` TINYINT(1) DEFAULT 0,
            `push_alerts` TINYINT(1) DEFAULT 1,
            `new_booking_alert` TINYINT(1) DEFAULT 1,
            `checkin_alert` TINYINT(1) DEFAULT 1,
            `checkout_alert` TINYINT(1) DEFAULT 1,
            `cancellation_alert` TINYINT(1) DEFAULT 1,
            `maintenance_alert` TINYINT(1) DEFAULT 0,
            `no_show_alert` TINYINT(1) DEFAULT 1,
            `early_checkin_alert` TINYINT(1) DEFAULT 1,
            `late_checkout_alert` TINYINT(1) DEFAULT 1,
            `alert_before_checkin` INT(3) DEFAULT 2,
            `alert_before_checkout` INT(3) DEFAULT 1,
            `alert_for_maintenance` INT(3) DEFAULT 24,
            `email_recipients` TEXT,
            `sms_recipients` TEXT,
            `whatsapp_recipients` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_id` (`user_id`),
            KEY `email_alerts` (`email_alerts`),
            KEY `sms_alerts` (`sms_alerts`),
            KEY `push_alerts` (`push_alerts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Booking alerts log table (depends on bookings, rooms, guests)
    "booking_alerts_log_$user_id" => "
        CREATE TABLE IF NOT EXISTS `booking_alerts_log_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `alert_type` ENUM('new_booking', 'checkin', 'checkout', 'cancellation', 'maintenance', 'no_show', 'test', 'reminder') NOT NULL,
            `alert_message` TEXT NOT NULL,
            `booking_id` INT(11) DEFAULT NULL,
            `room_id` INT(11) DEFAULT NULL,
            `guest_id` INT(11) DEFAULT NULL,
            `recipient_type` ENUM('email', 'sms', 'whatsapp', 'push', 'system') DEFAULT 'system',
            `recipient_address` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
            `sent_at` DATETIME DEFAULT NULL,
            `delivered_at` DATETIME DEFAULT NULL,
            `error_message` TEXT,
            `retry_count` INT(2) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `alert_type` (`alert_type`),
            KEY `status` (`status`),
            KEY `sent_at` (`sent_at`),
            KEY `booking_id` (`booking_id`),
            KEY `room_id` (`room_id`),
            KEY `guest_id` (`guest_id`),
            KEY `recipient_type` (`recipient_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // Guest notifications table (depends on guests)
    "guest_notifications_$user_id" => "
        CREATE TABLE IF NOT EXISTS `guest_notifications_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `guest_id` INT(11) NOT NULL,
            `notification_type` ENUM('welcome', 'checkin_reminder', 'checkout_reminder', 'special_offer', 'feedback_request', 'loyalty_reward', 'bulk', 'custom') DEFAULT 'custom',
            `message` TEXT NOT NULL,
            `status` ENUM('scheduled', 'sent', 'failed', 'cancelled') DEFAULT 'scheduled',
            `schedule_time` DATETIME DEFAULT NULL,
            `sent_at` DATETIME DEFAULT NULL,
            `whatsapp_url` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `guest_id` (`guest_id`),
            KEY `notification_type` (`notification_type`),
            KEY `status` (`status`),
            KEY `schedule_time` (`schedule_time`),
            KEY `sent_at` (`sent_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",    
    
    // Audit log table (depends on users)
    "audit_logs_$user_id" => "
        CREATE TABLE IF NOT EXISTS `audit_logs_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `table_name` VARCHAR(100) NOT NULL,
            `record_id` INT(11) DEFAULT NULL,
            `old_values` TEXT,
            `new_values` TEXT,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `table_name` (`table_name`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

// Check if tables already exist
$existing_tables = [];
foreach ($tables as $table_name => $query) {
    $result = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($result->num_rows > 0) {
        $existing_tables[] = $table_name;
    }
}

// Handle table creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $force_recreate || empty($existing_tables)) {
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // If force recreate, drop all tables in reverse dependency order
    if ($force_recreate) {
        $tables_reverse = array_reverse(array_keys($tables));
        foreach ($tables_reverse as $table_name) {
            try {
                $conn->query("DROP TABLE IF EXISTS `$table_name`");
                $success_messages[] = "Dropped table: $table_name";
            } catch (Exception $e) {
                $error_messages[] = "Error dropping table '$table_name': " . $e->getMessage();
            }
        }
    }
    
    // Create tables in correct dependency order
    foreach ($tables as $table_name => $query) {
        try {
            if ($conn->query($query) === TRUE) {
                $success_messages[] = "Table '$table_name' created successfully";
                $table_results[$table_name] = 'success';
            } else {
                $error_messages[] = "Error creating table '$table_name': " . $conn->error;
                $table_results[$table_name] = 'error';
            }
        } catch (Exception $e) {
            $error_messages[] = "Exception creating table '$table_name': " . $e->getMessage();
            $table_results[$table_name] = 'error';
        }
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // Insert default data only if no errors occurred
    if (empty($error_messages)) {
        insertDefaultData($conn, $user_id, $success_messages, $error_messages);
    }
} else {
    $success_messages[] = "All tables already exist. Use 'Force Recreate' to rebuild tables.";
    foreach ($existing_tables as $table_name) {
        $table_results[$table_name] = 'exists';
    }
}

// Function to insert default data
function insertDefaultData($conn, $user_id, &$success_messages, &$error_messages) {
    try {
        // Default room types
        $default_room_types = [
            ['Standard Room', 'Comfortable standard room with basic amenities', 2500.00, 2, '200', 'Double Bed'],
            ['Deluxe Room', 'Spacious deluxe room with enhanced amenities', 4000.00, 3, '300', 'Queen Bed'],
            ['Suite', 'Luxurious suite with separate living area', 6000.00, 4, '500', 'King Bed'],
            ['Family Room', 'Large room perfect for families with children', 4500.00, 4, '350', 'Two Double Beds']
        ];
        
        foreach ($default_room_types as $room_type) {
            $check_sql = "SELECT id FROM room_types_$user_id WHERE name = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $room_type[0]);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $insert_sql = "INSERT INTO room_types_$user_id (name, description, base_rate, max_occupancy, size_sqft, bed_type) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssdiis", $room_type[0], $room_type[1], $room_type[2], $room_type[3], $room_type[4], $room_type[5]);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
        
        // Default amenities
        $default_amenities = [
            ['Wi-Fi', 'wifi', 'Free high-speed wireless internet', 'basic'],
            ['Air Conditioning', 'snowflake', 'Air conditioned room with climate control', 'basic'],
            ['TV', 'tv', 'Flat screen television with cable channels', 'basic'],
            ['Mini Bar', 'glass-cheers', 'Mini refrigerator with beverages and snacks', 'premium'],
            ['Room Service', 'concierge-bell', '24/7 room service available', 'premium'],
            ['Safe', 'shield-alt', 'In-room electronic safety locker', 'basic'],
            ['Jacuzzi', 'hot-tub', 'Private jacuzzi bath', 'luxury'],
            ['Balcony', 'mountain', 'Private balcony with view', 'premium']
        ];
        
        foreach ($default_amenities as $amenity) {
            $check_sql = "SELECT id FROM room_amenities_$user_id WHERE name = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $amenity[0]);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $insert_sql = "INSERT INTO room_amenities_$user_id (name, icon, description, category) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssss", $amenity[0], $amenity[1], $amenity[2], $amenity[3]);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
        
        // Default tax settings
        $default_taxes = [
            ['GST', 18.00, 'percentage', 'both', 'Goods and Services Tax'],
            ['Service Charge', 10.00, 'percentage', 'room_rate', 'Hotel service charge']
        ];
        
        foreach ($default_taxes as $tax) {
            $check_sql = "SELECT id FROM tax_settings_$user_id WHERE tax_name = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $tax[0]);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $insert_sql = "INSERT INTO tax_settings_$user_id (tax_name, tax_rate, tax_type, apply_to, description) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sdsss", $tax[0], $tax[1], $tax[2], $tax[3], $tax[4]);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
        
        // Default staff members
        $default_staff = [
            ['HK001', 'Housekeeping Staff 1', 'hk1@hotel.com', '9876543210', 'housekeeping', 'Room Attendant'],
            ['RC001', 'Reception Staff 1', 'reception@hotel.com', '9876543211', 'reception', 'Front Desk Executive'],
            ['MN001', 'Maintenance Staff 1', 'maintenance@hotel.com', '9876543212', 'maintenance', 'Technician']
        ];
        
        foreach ($default_staff as $staff) {
            $check_sql = "SELECT id FROM staff_members_$user_id WHERE staff_code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $staff[0]);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $insert_sql = "INSERT INTO staff_members_$user_id (staff_code, name, email, phone, department, position) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssssss", $staff[0], $staff[1], $staff[2], $staff[3], $staff[4], $staff[5]);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
        
        $success_messages[] = "Default data (room types, amenities, taxes, staff) inserted successfully";
        
    } catch (Exception $e) {
        $error_messages[] = "Error inserting default data: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management Tables Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-exists { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .progress-container {
            height: 12px;
            background-color: #e9ecef;
            border-radius: 6px;
            margin: 20px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.5rem;
        }
        .system-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .system-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .action-btn {
            min-width: 140px;
            margin: 5px;
        }
        .dependency-badge {
            font-size: 0.7em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Main Card -->
                <div class="card system-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><i class="fas fa-hotel me-2"></i>Room Management System Setup</h3>
                            <p class="mb-0 mt-1 opacity-75">User-specific database tables for complete room management</p>
                        </div>
                        <div class="text-end">
                            <small>User ID: <?php echo $user_id; ?></small><br>
                            <small>Tables: <?php echo count($tables); ?> total</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Progress and Stats -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h5>Setup Progress</h5>
                                <div class="progress-container">
                                    <?php
                                    $success_count = count(array_filter($table_results, function($v) { return $v === 'success' || $v === 'exists'; }));
                                    $progress = ($success_count / count($tables)) * 100;
                                    ?>
                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between text-muted small">
                                    <span><?php echo $success_count; ?> of <?php echo count($tables); ?> tables ready</span>
                                    <span><?php echo round($progress); ?>% complete</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <h4 class="text-success mb-0"><?php echo count(array_filter($table_results, function($v) { return $v === 'success'; })); ?></h4>
                                            <small>Created</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <h4 class="text-info mb-0"><?php echo count(array_filter($table_results, function($v) { return $v === 'exists'; })); ?></h4>
                                            <small>Existing</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <h4 class="text-danger mb-0"><?php echo count(array_filter($table_results, function($v) { return $v === 'error'; })); ?></h4>
                                            <small>Errors</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Database Actions</h5>
                                        <div class="d-flex flex-wrap justify-content-center">
                                            <form method="POST" class="d-inline">
                                                <button type="submit" class="btn btn-success action-btn">
                                                    <i class="fas fa-plus-circle me-1"></i>Create Tables
                                                </button>
                                            </form>
                                            <a href="?force=true" class="btn btn-warning action-btn">
                                                <i class="fas fa-sync-alt me-1"></i>Force Recreate
                                            </a>
                                            <a href="room-types.php" class="btn btn-primary action-btn">
                                                <i class="fas fa-bed me-1"></i>Manage Room Types
                                            </a>
                                            <a href="manage-rooms.php" class="btn btn-info action-btn">
                                                <i class="fas fa-door-open me-1"></i>Manage Rooms
                                            </a>
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            Force recreate will drop all existing tables and create fresh ones in correct order
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if (!empty($success_messages)): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Success Messages</h5>
                                <ul class="mb-0">
                                    <?php foreach ($success_messages as $msg): ?>
                                        <li><?php echo $msg; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_messages)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Error Messages</h5>
                                <ul class="mb-0">
                                    <?php foreach ($error_messages as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- System Features -->
                        <div class="row mt-4">
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="feature-icon">
                                        <i class="fas fa-bed"></i>
                                    </div>
                                    <h6>Room Management</h6>
                                    <p class="text-muted small">Complete room inventory with status tracking</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="feature-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <h6>Booking System</h6>
                                    <p class="text-muted small">Advanced reservation and check-in system</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="feature-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <h6>Rate Management</h6>
                                    <p class="text-muted small">Dynamic pricing and seasonal rates</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <div class="feature-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h6>Guest Management</h6>
                                    <p class="text-muted small">Complete guest profiles and loyalty program</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Database Schema Details -->
                <div class="card mt-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Schema Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Description</th>
                                        <th>Dependencies</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $table_descriptions = [
                                        "room_types_$user_id" => ["Room categories and base configurations", "Independent"],
                                        "room_amenities_$user_id" => ["Room facilities and features", "Independent"],
                                        "guests_$user_id" => ["Guest information and profiles", "Independent"],
                                        "tax_settings_$user_id" => ["Tax configurations and settings", "Independent"],
                                        "discounts_$user_id" => ["Discount offers and promotions", "Independent"],
                                        "staff_members_$user_id" => ["Staff management and assignments", "Independent"],
                                        "alert_templates_$user_id" => ["Alert message templates", "Independent"],
                                        "alert_recipients_$user_id" => ["Alert recipient configurations", "Independent"],
                                        "rooms_$user_id" => ["Room inventory and status management", "room_types"],
                                        "room_rates_$user_id" => ["Seasonal pricing and special rates", "room_types"],
                                        "bookings_$user_id" => ["Reservations and booking management", "rooms, guests"],
                                        "room_maintenance_$user_id" => ["Maintenance scheduling and tracking", "rooms"],
                                        "housekeeping_$user_id" => ["Cleaning schedules and task management", "rooms"],
                                        "payments_$user_id" => ["Payment transactions and records", "bookings"],
                                        "loyalty_program_$user_id" => ["Guest loyalty and rewards program", "guests"],
                                        "extra_charges_$user_id" => ["Additional charges and services", "bookings"],
                                        "booking_alerts_$user_id" => ["Booking alert settings", "Independent"],
                                        "booking_alerts_log_$user_id" => ["Alert delivery logs", "bookings, rooms, guests"],
                                        "guest_notifications_$user_id" => ["Guest communication logs", "guests"],
                                        "audit_logs_$user_id" => ["System audit and activity logs", "Independent"]
                                    ];
                                    
                                    foreach ($tables as $table_name => $query): 
                                        $description = $table_descriptions[$table_name] ?? ['General table', 'None'];
                                    ?>
                                        <tr>
                                            <td>
                                                <code><?php echo $table_name; ?></code>
                                            </td>
                                            <td>
                                                <strong><?php echo $description[0]; ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($description[1] !== 'Independent'): ?>
                                                    <span class="badge bg-info dependency-badge"><?php echo $description[1]; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success dependency-badge">Independent</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($table_results[$table_name])): ?>
                                                    <?php if ($table_results[$table_name] === 'success'): ?>
                                                        <span class="table-status status-success">
                                                            <i class="fas fa-check me-1"></i>Created
                                                        </span>
                                                    <?php elseif ($table_results[$table_name] === 'exists'): ?>
                                                        <span class="table-status status-exists">
                                                            <i class="fas fa-database me-1"></i>Exists
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="table-status status-error">
                                                            <i class="fas fa-times me-1"></i>Error
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Start Guide -->
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Quick Start Guide</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="text-primary mb-3">
                                            <i class="fas fa-cog fa-2x"></i>
                                        </div>
                                        <h6>1. Setup Room Types</h6>
                                        <p class="small text-muted">Configure room categories, amenities, and base rates</p>
                                        <a href="room-types.php" class="btn btn-outline-primary btn-sm">Setup</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="text-success mb-3">
                                            <i class="fas fa-bed fa-2x"></i>
                                        </div>
                                        <h6>2. Add Rooms</h6>
                                        <p class="small text-muted">Create your room inventory with numbers and rates</p>
                                        <a href="manage-rooms.php" class="btn btn-outline-success btn-sm">Add Rooms</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="text-info mb-3">
                                            <i class="fas fa-calendar-plus fa-2x"></i>
                                        </div>
                                        <h6>3. Start Booking</h6>
                                        <p class="small text-muted">Begin accepting reservations and check-ins</p>
                                        <a href="add-booking.php" class="btn btn-outline-info btn-sm">Book Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect to room types after 5 seconds if successful and no errors
        <?php if (empty($error_messages) && !empty($success_messages)): ?>
            setTimeout(function() {
                window.location.href = 'room-types.php';
            }, 5000);
        <?php endif; ?>

        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Show loading state on form submission
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating Tables...';
                        submitBtn.disabled = true;
                    }
                });
            });
            
            // Add confirmation for force recreate
            const forceBtn = document.querySelector('a[href*="force=true"]');
            if (forceBtn) {
                forceBtn.addEventListener('click', function(e) {
                    if (!confirm('WARNING: This will delete ALL existing data and recreate tables. Are you sure?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>