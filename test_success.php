<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'onesignal_config.php';
$oneSignal = new OneSignalNotification();

echo "<h2>🎉 OneSignal Notification Test</h2>";

// Verify credentials first
echo "<h3>1. Credential Verification:</h3>";
$credCheck = $oneSignal->verifyCredentials();

if ($credCheck['valid']) {
    echo "<p style='color: green; font-size: 18px;'>{$credCheck['message']}</p>";
} else {
    echo "<p style='color: red; font-size: 18px;'>{$credCheck['message']}</p>";
    exit;
}

// Check devices
$userId = $_SESSION['user_id'] ?? 28;
$deviceCount = $oneSignal->getUserDeviceCount($userId);
echo "<h3>2. Device Check:</h3>";
echo "<p>📱 Registered devices: <strong>{$deviceCount}</strong></p>";

if ($deviceCount > 0) {
    // Send actual notification
    echo "<h3>3. Sending Live Notification:</h3>";
    $testOrderId = rand(1000, 9999);
    $result = $oneSignal->sendNewOrderNotification($userId, $testOrderId, 'Test Customer', 299.99);
    
    if ($result['success']) {
        echo "<div style='background: green; color: white; padding: 20px; border-radius: 10px;'>";
        echo "<h3>✅ SUCCESS!</h3>";
        echo "<p><strong>Message:</strong> {$result['message']}</p>";
        echo "<p><strong>Order ID:</strong> #{$testOrderId}</p>";
        echo "<p><strong>Devices notified:</strong> " . count($result['player_ids']) . "</p>";
        echo "<p>🎉 Your OneSignal integration is working perfectly!</p>";
        echo "</div>";
        
        // Show response details
        echo "<h4>OneSignal Response:</h4>";
        echo "<pre>" . json_encode($result['onesignal_response'], JSON_PRETTY_PRINT) . "</pre>";
        
    } else {
        echo "<div style='background: red; color: white; padding: 20px; border-radius: 10px;'>";
        echo "<h3>❌ FAILED</h3>";
        echo "<p><strong>Error:</strong> {$result['message']}</p>";
        if (isset($result['http_code'])) {
            echo "<p><strong>HTTP Code:</strong> {$result['http_code']}</p>";
        }
        echo "</div>";
    }
} else {
    echo "<p style='color: orange;'>No devices registered. <a href='add_test_device.php'>Add test devices first</a></p>";
}

echo "<hr>";
echo "<p><a href='test_notification_working.php'>Test Again</a> | <a href='notification_status.php'>View Status</a></p>";
?>