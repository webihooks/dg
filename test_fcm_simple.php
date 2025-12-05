<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

session_start();

echo "=== FCM Debug Test ===\n";
echo "Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";

// Test 1: Check if files exist
echo "\n1. File Check:\n";
$files = [
    'firebase-config.php',
    'fcm_notification.php', 
    'deegeecard-16-oct-25-firebase-adminsdk-fbsvc-3211ef62c3.json'
];

foreach ($files as $file) {
    echo "   $file: " . (file_exists($file) ? 'EXISTS' : 'MISSING') . "\n";
}

// Test 2: Check database connection
echo "\n2. Database Check:\n";
try {
    require_once 'config/db_connection.php';
    echo "   Database: CONNECTED\n";
    
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'fcm_tokens'");
    echo "   fcm_tokens table: " . ($result->num_rows > 0 ? 'EXISTS' : 'MISSING') . "\n";
    
} catch (Exception $e) {
    echo "   Database: ERROR - " . $e->getMessage() . "\n";
}

// Test 3: Check Firebase config
echo "\n3. Firebase Config Check:\n";
try {
    if (file_exists('firebase-config.php')) {
        require_once 'firebase-config.php';
        echo "   FirebaseConfig: LOADED\n";
        
        // Test service account file
        $serviceAccountPath = FirebaseConfig::SERVICE_ACCOUNT_FILE;
        echo "   Service Account: " . (file_exists($serviceAccountPath) ? 'EXISTS' : 'MISSING') . "\n";
        
        if (file_exists($serviceAccountPath)) {
            $content = file_get_contents($serviceAccountPath);
            $data = json_decode($content, true);
            echo "   JSON Valid: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO - ' . json_last_error_msg()) . "\n";
        }
    } else {
        echo "   FirebaseConfig: FILE NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "   FirebaseConfig: ERROR - " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>