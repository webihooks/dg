<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'onesignal_config.php';
$oneSignal = new OneSignalNotification();

$userId = $_SESSION['user_id'] ?? 28;

// Handle test actions
$action = $_POST['action'] ?? '';
$testResult = [];

if ($action === 'test_notification') {
    $testOrderId = rand(1000, 9999);
    $testResult = $oneSignal->sendNewOrderNotification($userId, $testOrderId, 'Test Customer', 299.99);
}

ob_end_clean();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Test - OneSignal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .btn { display: inline-block; padding: 12px 24px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn-success { background: #28a745; }
        .btn-test { background: #6f42c1; }
        .log { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Quick OneSignal Test</h1>
        
        <div class="log" id="testLog">
            <div>Test Log:</div>
            <div id="logContent"></div>
        </div>

        <?php
        // Check credentials
        $credCheck = $oneSignal->verifyCredentials();
        $deviceCount = $oneSignal->getUserDeviceCount($userId);
        ?>
        
        <div class="success">
            <h3>✅ System Status</h3>
            <p><strong>Credentials:</strong> <?php echo $credCheck['message']; ?></p>
            <p><strong>Registered Devices:</strong> <?php echo $deviceCount; ?></p>
            <p><strong>User ID:</strong> <?php echo $userId; ?></p>
        </div>

        <?php if ($deviceCount === 0): ?>
        <div class="warning">
            <h3>⚠️ No Devices Found</h3>
            <p>You need to register devices first. Add the JavaScript code to your website or add test devices manually.</p>
            <a href="add_test_device.php" class="btn">📱 Add Test Devices</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($testResult)): ?>
        <div class="<?php echo $testResult['success'] ? 'success' : 'error'; ?>">
            <h3><?php echo $testResult['success'] ? '✅ Notification Sent!' : '❌ Notification Failed'; ?></h3>
            <p><?php echo $testResult['message']; ?></p>
            <?php if (isset($testResult['http_code'])): ?>
                <p><strong>HTTP Code:</strong> <?php echo $testResult['http_code']; ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin: 20px 0;">
            <form method="post" style="display: inline-block;">
                <input type="hidden" name="action" value="test_notification">
                <button type="submit" class="btn btn-test" <?php echo $deviceCount === 0 ? 'disabled' : ''; ?>>
                    🚀 Test Push Notification
                </button>
            </form>
            
            <button onclick="testRingSound()" class="btn btn-success">
                🔔 Test Ring Sound
            </button>
            
            <a href="test_clean.php" class="btn">🔄 Full Test</a>
        </div>

        <?php if ($deviceCount === 0): ?>
        <div class="warning">
            <h3>Quick Fix: Add Test Device</h3>
            <form method="post" action="quick_add_device.php">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                <input type="hidden" name="player_id" value="quick-test-<?php echo time(); ?>">
                <input type="hidden" name="device_type" value="android">
                <button type="submit" class="btn btn-success">➕ Add Test Device Now</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const logContent = document.getElementById('logContent');
        
        function log(message) {
            const timestamp = new Date().toLocaleTimeString();
            logContent.innerHTML += `<div>[${timestamp}] ${message}</div>`;
            logContent.scrollTop = logContent.scrollHeight;
        }

        function testRingSound() {
            log('🎯 Testing ring sound...');
            log('🔔 Playing continuous ring sound...');
            
            // Create audio context for better mobile support
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.type = 'sine';
            oscillator.frequency.value = 800;
            gainNode.gain.value = 0.1;
            
            oscillator.start();
            
            log('✅ Ring sound playing...');
            
            // Stop after 3 seconds
            setTimeout(() => {
                oscillator.stop();
                log('⏹️ Ring sound stopped');
            }, 3000);
        }

        // Initial log
        log('Quick test page loaded successfully.');
        log('Click buttons to test push notifications and ring sound.');
        log('Ring will play for 3 seconds when tested.');
        
        <?php if ($deviceCount === 0): ?>
            log('⚠️ No devices registered. Add test devices first.');
        <?php else: ?>
            log('✅ Ready to test notifications!');
        <?php endif; ?>
    </script>
</body>
</html>