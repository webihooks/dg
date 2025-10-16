<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Not authenticated\n";
    exit();
}

require_once 'config/db_connection.php';
require_once 'fcm_notification.php';

$userId = $_SESSION['user_id'];

echo "=== Simple FCM Test ===\n";
echo "User ID: $userId\n\n";

// Test database connection
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "FCM Subscriptions in database: " . $result['count'] . "\n\n";
    
    // Show subscription details
    $stmt = $conn->prepare("SELECT token, subscription_data FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subscriptions as $sub) {
        $data = json_decode($sub['subscription_data'], true);
        echo "Subscription ID: " . $sub['token'] . "\n";
        echo "Endpoint: " . ($data['endpoint'] ?? 'N/A') . "\n";
        echo "Has Keys: " . (isset($data['keys']) ? 'YES' : 'NO') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit();
}

// Test FCM send
echo "\nSending test notification...\n";
$success = FCMNotification::sendNewOrderNotification(
    $userId,
    'SIMPLE-TEST-' . time(),
    'Simple Test Customer',
    '199.00'
);

echo "Result: " . ($success ? "SUCCESS" : "FAILED") . "\n";
echo "Check error_log for detailed information.\n";
?>