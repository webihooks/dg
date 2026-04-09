<?php
// vegetable_helper.php

function ensureVegetableSellerTables($user_id, $conn) {
    // Check if tables exist (by trying to select)
    $orders_table = "vegetable_orders_{$user_id}";
    $slots_table = "vegetable_time_slots_{$user_id}";
    $settings_table = "vegetable_settings_{$user_id}";
    
    $tables_exist = false;
    $result = $conn->query("SHOW TABLES LIKE '$orders_table'");
    if ($result && $result->num_rows > 0) {
        $tables_exist = true;
    }
    
    if (!$tables_exist) {
        // Create orders table
        $sql_orders = "
        CREATE TABLE IF NOT EXISTS `$orders_table` (
            `order_id` int NOT NULL AUTO_INCREMENT,
            `customer_name` varchar(100) NOT NULL,
            `customer_phone` varchar(20) NOT NULL,
            `order_type` enum('delivery','takeaway') NOT NULL DEFAULT 'delivery',
            `delivery_address` text,
            `time_slot_id` int DEFAULT NULL,
            `is_instant` tinyint(1) DEFAULT '0',
            `instant_charge` decimal(10,2) DEFAULT '0.00',
            `order_date` date NOT NULL,
            `order_time` time NOT NULL,
            `scheduled_date` date DEFAULT NULL,
            `scheduled_time_slot` varchar(50) DEFAULT NULL,
            `items` json NOT NULL,
            `subtotal` decimal(10,2) NOT NULL,
            `tax_amount` decimal(10,2) DEFAULT '0.00',
            `total_amount` decimal(10,2) NOT NULL,
            `status` enum('pending','confirmed','preparing','ready','completed','cancelled') DEFAULT 'pending',
            `notes` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`order_id`),
            KEY `idx_customer_phone` (`customer_phone`),
            KEY `idx_order_date` (`order_date`),
            KEY `idx_status` (`status`)
        )";
        $conn->query($sql_orders);
        
        // Create time slots table
        $sql_slots = "
        CREATE TABLE IF NOT EXISTS `$slots_table` (
            `id` int NOT NULL AUTO_INCREMENT,
            `slot_name` varchar(50) NOT NULL,
            `start_time` time NOT NULL,
            `end_time` time NOT NULL,
            `is_active` tinyint(1) DEFAULT '1',
            `display_order` int DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `idx_active` (`is_active`)
        )";
        $conn->query($sql_slots);
        
        // Create settings table
        $sql_settings = "
        CREATE TABLE IF NOT EXISTS `$settings_table` (
            `id` int NOT NULL AUTO_INCREMENT,
            `instant_delivery_enabled` tinyint(1) DEFAULT '0',
            `instant_delivery_charge` decimal(10,2) DEFAULT '0.00',
            `delivery_charge` decimal(10,2) DEFAULT '0.00',
            `tax_rate` decimal(5,2) DEFAULT '0.00',
            `business_name` varchar(255) DEFAULT '',
            `business_phone` varchar(20) DEFAULT '',
            `business_address` text,
            PRIMARY KEY (`id`)
        )";
        $conn->query($sql_settings);
        
        // Insert default time slots (24 hourly slots)
        for ($hour = 0; $hour < 24; $hour++) {
            $next_hour = $hour + 1;
            $start = sprintf("%02d:00:00", $hour);
            $end = sprintf("%02d:00:00", $next_hour);
            $slot_name = date("gA", strtotime($start)) . " - " . date("gA", strtotime($end));
            $stmt = $conn->prepare("INSERT INTO `$slots_table` (slot_name, start_time, end_time, display_order) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $slot_name, $start, $end, $hour);
            $stmt->execute();
        }
        
        // Insert default settings
        $conn->query("INSERT INTO `$settings_table` (instant_delivery_enabled, instant_delivery_charge, delivery_charge, tax_rate) VALUES (0, 0.00, 0.00, 0.00)");
    }
}
?>