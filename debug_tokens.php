<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

session_start();
require_once 'config/db_connection.php';

$userId = $_SESSION['user_id'] ?? 28;

echo "=== FCM Tokens Debug ===\n";
echo "User ID: $userId\n\n";

try {
    // Get all tokens for this user
    $stmt = $conn->prepare("SELECT id, token, device_type, created_at, subscription_data FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($tokens) . " tokens:\n\n";
    
    foreach ($tokens as $token) {
        echo "Token ID: " . $token['id'] . "\n";
        echo "Device Type: " . $token['device_type'] . "\n";
        echo "Created: " . $token['created_at'] . "\n";
        echo "Token: " . $token['token'] . "\n";
        
        // Decode subscription data
        if (!empty($token['subscription_data'])) {
            $subscription = json_decode($token['subscription_data'], true);
            echo "Endpoint: " . ($subscription['endpoint'] ?? 'N/A') . "\n";
            echo "Keys: " . (isset($subscription['keys']) ? 'PRESENT' : 'MISSING') . "\n";
        }
        
        echo "---\n";
    }
    
    // Test FCM send with first token
    if (!empty($tokens)) {
        echo "\n=== Testing FCM Send ===\n";
        require_once 'fcm_notification.php';
        
        $result = FCMNotification::sendNewOrderNotification(
            $userId,
            'DEBUG-' . time(),
            'Debug Customer',
            '299.00'
        );
        
        echo "FCM Send Result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>