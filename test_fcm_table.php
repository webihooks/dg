<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

session_start();
require_once 'config/db_connection.php';

echo "=== FCM Table Test ===\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n\n";

try {
    // Test 1: Check if table exists
    echo "1. Checking fcm_tokens table:\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'fcm_tokens'");
    $tableExists = $stmt->rowCount() > 0;
    
    echo "   Table exists: " . ($tableExists ? 'YES' : 'NO') . "\n";
    
    if (!$tableExists) {
        echo "   ERROR: fcm_tokens table does not exist. Please run the SQL creation script.\n";
        exit;
    }
    
    // Test 2: Check table structure
    echo "\n2. Table structure:\n";
    $stmt = $conn->query("DESCRIBE fcm_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Test 3: Test insert
    echo "\n3. Testing token insertion:\n";
    $testToken = 'test-token-' . time();
    $testUserId = $_SESSION['user_id'] ?? 28;
    
    $insertStmt = $conn->prepare("INSERT INTO fcm_tokens (user_id, token, device_type) VALUES (?, ?, 'web')");
    $result = $insertStmt->execute([$testUserId, $testToken]);
    
    echo "   Insert test: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    
    if ($result) {
        // Test 4: Test select
        $selectStmt = $conn->prepare("SELECT COUNT(*) as count FROM fcm_tokens WHERE user_id = ?");
        $selectStmt->execute([$testUserId]);
        $count = $selectStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "   Tokens for user $testUserId: $count\n";
        
        // Cleanup test data
        $deleteStmt = $conn->prepare("DELETE FROM fcm_tokens WHERE token = ?");
        $deleteStmt->execute([$testToken]);
        echo "   Cleanup: COMPLETED\n";
    }
    
    echo "\n=== Table Test Complete ===\n";
    echo "If all tests passed, your FCM database setup is working correctly!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>