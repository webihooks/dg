<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

require_once 'onesignal_config.php';
$oneSignal = new OneSignalNotification();

$userId = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 28;

ob_end_clean();
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Send Test Notification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { background: green; color: white; padding: 20px; border-radius: 10px; }
        .error { background: red; color: white; padding: 20px; border-radius: 10px; }
    </style>
</head>
<body>";

echo "<h2>🔔 Sending Test Notification</h2>";

$testOrderId = rand(1000, 9999);
$result = $oneSignal->sendNewOrderNotification($userId, $testOrderId, 'Test Customer', 399.99);

if ($result['success']) {
    echo "<div class='success'>";
    echo "<h3>✅ NOTIFICATION SENT SUCCESSFULLY!</h3>";
    echo "<p><strong>Message:</strong> {$result['message']}</p>";
    echo "<p><strong>Order #:</strong> {$testOrderId}</p>";
    echo "<p><strong>Devices notified:</strong> " . count($result['player_ids']) . "</p>";
    echo "<p>🎉 Check your devices for the notification!</p>";
    echo "</div>";
    
    // Show detailed response
    echo "<h4>Detailed Response:</h4>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<div class='error'>";
    echo "<h3>�️ FAILED TO SEND NOTIFICATION</h3>";
    echo "<p><strong>Error:</strong> {$result['message']}</p>";
    if (isset($result['http_code'])) {
        echo "<p><strong>HTTP Code:</strong> {$result['http_code']}</p>";
    }
    echo "</div>";
}

echo "<br><a href='test_final.php'>« Back to Test</a>";

echo "</body></html>";
?>