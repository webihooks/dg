<?php
ob_start();
session_start();
require_once 'onesignal_config.php';

$oneSignal = new OneSignalNotification();
$userId = $_SESSION['user_id'] ?? 28;

// Add a test device automatically
$playerId = 'auto-test-' . time();
require_once 'db_connection.php';
$sql = "INSERT INTO user_devices (user_id, player_id, device_type) VALUES (?, ?, 'web') 
        ON DUPLICATE KEY UPDATE is_active=1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $userId, $playerId);
$stmt->execute();
$stmt->close();

// Send test notification
$testOrderId = rand(1000, 9999);
$result = $oneSignal->sendNewOrderNotification($userId, $testOrderId, 'Test Customer', 199.99);

ob_end_clean();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .success { background: green; color: white; padding: 30px; border-radius: 10px; margin: 20px auto; max-width: 500px; }
        .error { background: red; color: white; padding: 30px; border-radius: 10px; margin: 20px auto; max-width: 500px; }
        .btn { display: inline-block; padding: 15px 30px; margin: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 18px; }
    </style>
</head>
<body>
    <h1>🔔 OneSignal Simple Test</h1>
    
    <?php if ($result['success']): ?>
    <div class="success">
        <h2>✅ SUCCESS!</h2>
        <p><strong>Notification Sent Successfully!</strong></p>
        <p>Order #<?php echo $testOrderId; ?> - Test Customer - ₹199.99</p>
        <p>Check your devices for the push notification</p>
        <p><em>If you don't receive it, make sure push notifications are enabled for your app/browser</em></p>
    </div>
    <?php else: ?>
    <div class="error">
        <h2>❌ FAILED</h2>
        <p><strong>Error:</strong> <?php echo $result['message']; ?></p>
        <p><strong>Details:</strong> <?php echo $result['response'] ?? 'No details'; ?></p>
    </div>
    <?php endif; ?>
    
    <div>
        <a href="simple_test.php" class="btn">🔄 Test Again</a>
        <a href="test_clean.php" class="btn">📊 Detailed Test</a>
        <a href="add_test_device.php" class="btn">📱 Manage Devices</a>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; max-width: 500px; margin: 20px auto;">
        <h3>What should happen:</h3>
        <p>✅ Your phone/device should receive a push notification with:</p>
        <ul style="text-align: left;">
            <li>Title: "🆕 New Order Received!"</li>
            <li>Message: "Order #<?php echo $testOrderId; ?> from Test Customer - ₹199.99"</li>
        </ul>
    </div>
</body>
</html>