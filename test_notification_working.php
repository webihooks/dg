<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent header errors
ob_start();

// Start session
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    // Clear any output
    ob_end_clean();
    echo "❌ Please login first. User not authenticated.\n";
    echo "<br><a href='login.php'>Login here</a>";
    exit;
}

$userId = $_SESSION['user_id'];

echo "<pre>";
echo "=== OneSignal Notification Test ===\n";
echo "User ID: $userId\n\n";

try {
    // Include the fixed OneSignal class
    require_once 'onesignal_config.php';
    $oneSignal = new OneSignalNotification();
    
    echo "✅ OneSignal class loaded successfully\n\n";
    
    // Step 1: Check device count
    echo "=== Step 1: Checking Registered Devices ===\n";
    $deviceCount = $oneSignal->getUserDeviceCount($userId);
    echo "📱 Registered devices for user $userId: $deviceCount\n\n";
    
    if ($deviceCount == 0) {
        echo "❌ No devices registered!\n";
        echo "💡 Solution: You need to register devices first.\n";
        echo "   Option 1: Use the Android app (if using WebToNative)\n";
        echo "   Option 2: Add test device manually: \n";
        echo "   <a href='add_test_device.php'>Add Test Device</a>\n\n";
        
        // Show quick add form
        echo "<form method='post' action='add_test_device.php' style='border:1px solid #ccc; padding:10px;'>";
        echo "<h4>Quick Add Test Device:</h4>";
        echo "<input type='hidden' name='player_id' value='test-device-" . time() . "'>";
        echo "<input type='hidden' name='device_type' value='android'>";
        echo "<button type='submit'>Add Test Device</button>";
        echo "</form>\n\n";
        
        exit;
    }
    
    // Step 2: Test API connection
    echo "=== Step 2: Testing OneSignal API Connection ===\n";
    $connectionTest = $oneSignal->testConnection();
    echo "📡 API Response Code: " . $connectionTest['http_code'] . "\n";
    
    if ($connectionTest['http_code'] == 400) {
        echo "✅ OneSignal API is responding (400 expected for invalid player ID)\n\n";
    } else if ($connectionTest['http_code'] == 200) {
        echo "✅ OneSignal API is working perfectly\n\n";
    } else {
        echo "⚠️  OneSignal API response: " . $connectionTest['http_code'] . "\n";
        if (isset($connectionTest['response'])) {
            echo "Response: " . $connectionTest['response'] . "\n";
        }
        echo "\n";
    }
    
    // Step 3: Send test notification
    echo "=== Step 3: Sending Test Notification ===\n";
    $testOrderId = rand(1000, 9999);
    $result = $oneSignal->sendNewOrderNotification($userId, $testOrderId, 'Test Customer', 299.99);
    
    echo "🎯 Test Result:\n";
    if ($result['success']) {
        echo "✅ SUCCESS: " . $result['message'] . "\n";
        if (isset($result['player_ids'])) {
            echo "📱 Sent to devices: " . implode(', ', $result['player_ids']) . "\n";
        }
        if (isset($result['http_code'])) {
            echo "🌐 HTTP Code: " . $result['http_code'] . "\n";
        }
    } else {
        echo "❌ FAILED: " . $result['message'] . "\n";
        if (isset($result['http_code'])) {
            echo "🌐 HTTP Code: " . $result['http_code'] . "\n";
        }
        if (isset($result['response'])) {
            echo "📄 Response: " . $result['response'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Critical Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Check registered devices: <a href='add_test_device.php'>View Devices</a>\n";
echo "2. Test with different users\n";
echo "3. Check OneSignal dashboard for delivery status\n";

echo "</pre>";
ob_end_flush();
?>