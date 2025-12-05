<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
require_once 'config/db_connection.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Web Push Status</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Web Push Status Check</h1>
    
    <?php
    try {
        // Check database directly
        $stmt = $conn->prepare("SELECT id, token, subscription_data, created_at FROM fcm_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Database Status:</h3>";
        echo "<p>User ID: $userId</p>";
        echo "<p>Tokens in database: " . count($tokens) . "</p>";
        
        if (empty($tokens)) {
            echo "<p class='error'>No push notification tokens found.</p>";
            echo "<p>Please visit <a href='menu.php'>menu.php</a> to register for push notifications.</p>";
        } else {
            echo "<h3>Token Details:</h3>";
            foreach ($tokens as $token) {
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
                echo "<p><strong>Token ID:</strong> " . $token['id'] . "</p>";
                echo "<p><strong>Created:</strong> " . $token['created_at'] . "</p>";
                
                $subscription = json_decode($token['subscription_data'], true);
                if ($subscription) {
                    echo "<p><strong>Endpoint:</strong> " . substr($subscription['endpoint'] ?? 'MISSING', 0, 50) . "...</p>";
                    echo "<p><strong>Has Keys:</strong> " . (isset($subscription['keys']) ? 'YES' : 'NO') . "</p>";
                    if (isset($subscription['keys'])) {
                        echo "<p><strong>p256dh:</strong> " . substr($subscription['keys']['p256dh'] ?? 'MISSING', 0, 10) . "...</p>";
                        echo "<p><strong>auth:</strong> " . substr($subscription['keys']['auth'] ?? 'MISSING', 0, 10) . "...</p>";
                    }
                } else {
                    echo "<p class='error'>Invalid subscription data</p>";
                }
                echo "</div>";
            }
            
            echo "<h3>Test Options:</h3>";
            echo "<p><a href='web_push_simple_test.php' style='padding: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Test Web Push Notification</a></p>";
            echo "<p><a href='web_push_debug.php' style='padding: 10px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Detailed Debug</a></p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    }
    ?>
    
    <h3>Server Information:</h3>
    <pre>
PHP Version: <?php echo phpversion(); ?>
Web Push Library: Installed
VAPID Public Key: Configured
VAPID Private Key: <?php echo (strlen('itFjBUn_SazcZQKlG3aUDBoenrg1Y64nsBq8UXDDWZI') > 10 ? 'Configured' : 'NOT CONFIGURED'); ?>

Last Error Log Check:
<?php
// Show last few lines of error log
$errorLogPath = '/home4/doctorie/public_html/dgcard.online/error_log';
if (file_exists($errorLogPath)) {
    $lines = file($errorLogPath);
    $lastLines = array_slice($lines, -10); // Last 10 lines
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
} else {
    echo "Error log not found or not accessible";
}
?>
    </pre>
</body>
</html>