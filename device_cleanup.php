<?php
// device_cleanup.php - Clean up inactive devices periodically
// Run this via cron job daily

require_once 'db_connection.php';

function cleanupInactiveDevices() {
    $host = 'localhost';
    $dbname = 'doctorie_webihooks_card';
    $username = 'doctorie_webihooks';
    $password = 'S@g@r4834';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Delete devices inactive for more than 30 days
        $stmt = $conn->prepare("
            DELETE FROM user_devices 
            WHERE is_active = 0 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $stmt->execute();
        $deletedCount = $stmt->rowCount();
        
        error_log("Device cleanup: Removed $deletedCount inactive devices");
        
        return $deletedCount;
        
    } catch (PDOException $e) {
        error_log("Device cleanup error: " . $e->getMessage());
        return 0;
    }
}

// Run cleanup if called directly
if (php_sapi_name() === 'cli') {
    $cleaned = cleanupInactiveDevices();
    echo "Cleaned up $cleaned inactive devices\n";
}
?>