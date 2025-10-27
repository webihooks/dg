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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated. Please login first.']);
    exit;
}

echo "<pre>";
echo "=== OneSignal Test (Fixed Version) ===\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";

try {
    // Check if required files exist
    $required_files = [
        'onesignal_config.php' => 'OneSignal Configuration',
        'db_connection.php' => 'Database Connection'
    ];
    
    foreach ($required_files as $file => $description) {
        if (file_exists($file)) {
            echo "✅ $description file exists\n";
        } else {
            echo "❌ $description file NOT FOUND\n";
            throw new Exception("Required file missing: $file");
        }
    }
    
    // Test database connection
    echo "\n=== Database Connection Test ===\n";
    require_once 'db_connection.php';
    
    if ($conn && $conn->ping()) {
        echo "✅ Database connection successful\n";
        
        // Check if user_devices table exists and has data
        $userId = $_SESSION['user_id'];
        
        // Count user devices
        $deviceCheck = $conn->prepare("SELECT COUNT(*) as device_count FROM user_devices WHERE user_id = ? AND is_active = 1");
        $deviceCheck->bind_param("i", $userId);
        $deviceCheck->execute();
        $deviceResult = $deviceCheck->get_result();
        $deviceData = $deviceResult->fetch_assoc();
        $deviceCount = $deviceData['device_count'];
        
        echo "📱 Registered devices for user: " . $deviceCount . "\n";
        
        if ($deviceCount == 0) {
            echo "⚠️  No devices registered for this user\n";
            echo "   Make sure your Android app is calling register_device.php\n";
        }
        
        $deviceCheck->close();
        
    } else {
        throw new Exception("Database connection failed");
    }
    
    // Test OneSignal configuration
    echo "\n=== OneSignal Configuration Test ===\n";
    
    require_once 'onesignal_config.php';
    
    $oneSignal = new OneSignalNotification();
    echo "✅ OneSignalNotification class instantiated\n";
    
    // Test API connection first
    echo "🔌 Testing OneSignal API connection...\n";
    $connectionTest = $oneSignal->testConnection();
    
    if ($connectionTest['http_code'] == 400) {
        echo "✅ OneSignal API is responding (400 expected for test data)\n";
    } else {
        echo "📡 OneSignal API Response Code: " . $connectionTest['http_code'] . "\n";
    }
    
    // Test with current user
    $userId = $_SESSION['user_id'];
    echo "🧪 Sending test notification for user ID: $userId\n";
    
    $result = $oneSignal->sendNewOrderNotification($userId, 9999, 'Test Customer', 250.00);
    
    echo "\n=== Test Result ===\n";
    if ($result['success']) {
        echo "✅ SUCCESS: " . $result['message'] . "\n";
        if (isset($result['player_ids'])) {
            echo "📱 Sent to devices: " . implode(', ', $result['player_ids']) . "\n";
        }
    } else {
        echo "❌ FAILED: " . $result['message'] . "\n";
        
        // Provide helpful suggestions
        if (strpos($result['message'], 'No registered devices') !== false) {
            echo "\n💡 SOLUTION: You need to register devices first.\n";
            echo "   1. Make sure your Android app is calling register_device.php\n";
            echo "   2. Or add a test device manually using add_test_device.php\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
ob_end_flush();
?>