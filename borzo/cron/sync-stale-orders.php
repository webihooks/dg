<?php
// borzo/cron/sync-stale-orders.php
// Run this script every 15 minutes via cron job
// No web output - suitable for command line

// Disable HTML error output for CLI
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

echo "Starting stale orders sync at " . date('Y-m-d H:i:s') . "\n";

try {
    // Define base path
    define('BASE_PATH', dirname(__DIR__, 2));
    
    // Load database connection
    require_once dirname(__DIR__) . '/config/db_cli.php';
    
    // Load Borzo config - THIS WAS MISSING
    $configPath = dirname(__DIR__) . '/config/borzo.php';
    if (!file_exists($configPath)) {
        throw new Exception('Borzo config file not found at: ' . $configPath);
    }
    
    $borzoConfig = require $configPath;
    if (!is_array($borzoConfig)) {
        throw new Exception('Borzo config is invalid');
    }
    
    // Load required classes
    require_once dirname(__DIR__) . '/classes/DynamicDeliveryManager.php';

    // Find orders that haven't been synced in the last hour and are in active states
    $sql = "SELECT DISTINCT o.order_id, o.user_id, o.borzo_order_id 
            FROM orders o
            WHERE o.borzo_order_id IS NOT NULL 
            AND o.borzo_status IN ('new', 'available', 'active', 'delayed')
            AND (o.borzo_last_sync IS NULL OR o.borzo_last_sync < DATE_SUB(NOW(), INTERVAL 1 HOUR))
            LIMIT 50";
    
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    
    $staleOrders = [];
    while ($row = $result->fetch_assoc()) {
        $staleOrders[] = $row;
    }
    
    echo "Found " . count($staleOrders) . " stale orders to sync\n";
    
    $synced = 0;
    $failed = 0;
    
    foreach ($staleOrders as $order) {
        echo "Syncing order #{$order['order_id']} (Borzo ID: {$order['borzo_order_id']})... ";
        
        try {
            // Create delivery manager with user's API key
            $deliveryManager = new DynamicDeliveryManager($order['user_id'], $borzoConfig);
            
            if (!$deliveryManager->hasValidApiKey()) {
                echo "SKIPPED (No API key configured for user)\n";
                continue;
            }
            
            $result = $deliveryManager->trackOrder($order['order_id']);
            
            if ($result && isset($result['success']) && $result['success']) {
                $status = $result['status'] ?? 'unknown';
                echo "SUCCESS (Status: {$status})\n";
                $synced++;
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                echo "FAILED ({$errorMsg})\n";
                $failed++;
            }
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            $failed++;
        }
        
        // Brief pause to avoid rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    echo "\nSync completed at " . date('Y-m-d H:i:s') . "\n";
    echo "Total: " . count($staleOrders) . ", Synced: $synced, Failed: $failed\n";
    
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}