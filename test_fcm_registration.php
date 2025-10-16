<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

session_start();
require_once 'config/db_connection.php';

echo "=== FCM Registration Debug ===\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n\n";

try {
    // Check current tokens
    $userId = $_SESSION['user_id'] ?? 28;
    
    $stmt = $conn->prepare("SELECT token, created_at, device_type FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "1. Current FCM Tokens for User $userId:\n";
    if (empty($tokens)) {
        echo "   No tokens found - FCM registration needed\n";
    } else {
        foreach ($tokens as $token) {
            echo "   - Token: " . substr($token['token'], 0, 50) . "...\n";
            echo "     Created: " . $token['created_at'] . "\n";
            echo "     Type: " . $token['device_type'] . "\n\n";
        }
    }
    
    echo "2. FCM Registration Status:\n";
    echo "   - Make sure you've refreshed menu.php to trigger FCM registration\n";
    echo "   - Check browser console for FCM logs\n";
    echo "   - Allow notifications when browser prompts\n";
    
    echo "\n=== Next Steps ===\n";
    echo "1. Open https://dgcard.online/menu.php\n";
    echo "2. Check browser console (F12 → Console)\n"; 
    echo "3. Look for FCM registration messages\n";
    echo "4. Allow notifications if prompted\n";
    echo "5. Refresh this page to see if tokens appear\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>