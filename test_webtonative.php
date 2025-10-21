<?php
ob_start();
session_start();
require_once 'onesignal_config.php';
require_once 'db_connection.php';

$oneSignal = new OneSignalNotification();
$userId = $_SESSION['user_id'] ?? 28;

// Check WebToNative devices
$sql = "SELECT COUNT(*) as device_count FROM user_devices WHERE user_id = ? AND source = 'webtonative_app'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$wtnDeviceCount = $data['device_count'] ?? 0;
$stmt->close();

// Handle test notification
$testResult = [];
if ($_POST['action'] === 'test_wtn_notification') {
    $testOrderId = rand(1000, 9999);
    $testResult = $oneSignal->sendNewOrderNotification($userId, $testOrderId, 'WebToNative Test Customer', 399.99);
}

ob_end_clean();
?>
<!DOCTYPE html>
<html>
<head>
    <title>WebToNative OneSignal Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 WebToNative OneSignal Test</h1>
        
        <div class="info">
            <h3>WebToNative Status</h3>
            <p><strong>Registered WebToNative Devices:</strong> <?php echo $wtnDeviceCount; ?></p>
            <p><strong>User ID:</strong> <?php echo $userId; ?></p>
            <p><em>This test is specifically for your Android app built with WebToNative</em></p>
        </div>

        <?php if ($wtnDeviceCount === 0): ?>
        <div class="warning">
            <h3>⚠️ No WebToNative Devices Found</h3>
            <p>To register your WebToNative app:</p>
            <ol>
                <li>Open your Android app built with WebToNative</li>
                <li>Make sure you're logged in</li>
                <li>The app will automatically register with OneSignal</li>
                <li>Come back here and test notifications</li>
            </ol>
            <button onclick="checkWebToNativeRegistration()" class="btn">🔄 Check Registration</button>
        </div>
        <?php else: ?>
        <div class="success">
            <h3>✅ WebToNative App Registered</h3>
            <p>Your Android app is registered and ready to receive notifications!</p>
            
            <form method="post">
                <input type="hidden" name="action" value="test_wtn_notification">
                <button type="submit" class="btn btn-success">🚀 Send Test to WebToNative App</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($testResult)): ?>
        <div class="<?php echo $testResult['success'] ? 'success' : 'warning'; ?>">
            <h3><?php echo $testResult['success'] ? '✅ Notification Sent!' : '❌ Notification Failed'; ?></h3>
            <p><?php echo $testResult['message']; ?></p>
            <?php if (isset($testResult['player_ids'])): ?>
                <p><strong>Sent to devices:</strong> <?php echo count($testResult['player_ids']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="add_test_device.php" class="btn">📱 View All Devices</a>
            <a href="simple_test.php" class="btn">🔔 General Test</a>
        </div>
    </div>

    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    <script>
        function checkWebToNativeRegistration() {
            if (typeof WTN !== 'undefined' && WTN.OneSignal) {
                WTN.OneSignal.getPlayerId().then(function(playerId) {
                    if (playerId) {
                        alert('WebToNative Player ID: ' + playerId + '\nDevice should be registered automatically.');
                        location.reload();
                    } else {
                        alert('No player ID received from WebToNative.');
                    }
                }).catch(function(error) {
                    alert('Error: ' + error);
                });
            } else {
                alert('WebToNative not available in this browser. Please use your Android app.');
            }
        }

        // Auto-check if coming from app
        if (localStorage.getItem('wtn_device_registered')) {
            console.log('WebToNative device previously registered');
        }
    </script>
</body>
</html>