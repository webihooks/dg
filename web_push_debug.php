<?php
// Debug version - shows raw output
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    echo "NOT_AUTHENTICATED";
    exit();
}

$userId = $_SESSION['user_id'];

echo "<pre>";
echo "=== WEB PUSH DEBUG ===\n";
echo "User ID: $userId\n\n";

try {
    require_once 'config/db_connection.php';
    require_once 'web_push_notification.php';
    
    echo "Database connection: OK\n\n";
    
    // Test getting subscriptions using the public method
    $subscriptions = WebPushNotification::getUserWebPushSubscriptions($userId);
    echo "Subscriptions found: " . count($subscriptions) . "\n\n";
    
    foreach ($subscriptions as $index => $sub) {
        echo "Subscription $index:\n";
        echo "  Endpoint: " . htmlspecialchars(substr($sub['endpoint'], 0, 50)) . "...\n";
        echo "  p256dh: " . htmlspecialchars(substr($sub['keys']['p256dh'] ?? 'MISSING', 0, 10)) . "...\n";
        echo "  auth: " . htmlspecialchars(substr($sub['keys']['auth'] ?? 'MISSING', 0, 10)) . "...\n";
        echo "\n";
    }
    
    if (empty($subscriptions)) {
        echo "No valid subscriptions found in database.\n";
        echo "Make sure you've registered for push notifications by visiting menu.php\n";
    } else {
        echo "Attempting to send notification...\n\n";
        
        $result = WebPushNotification::sendNewOrderNotification(
            $userId,
            'DEBUG-' . time(),
            'Debug Customer',
            '199.00'
        );
        
        echo "Send result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        echo "\nCheck server error_log for detailed information about the send attempt.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>