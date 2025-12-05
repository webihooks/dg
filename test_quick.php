<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Not authenticated");

require_once 'config/db_connection.php';
require_once 'web_push_notification.php';

$userId = $_SESSION['user_id'];

echo "Quick Test - User: $userId<br>";

// Direct call to send notification
$result = WebPushNotification::sendNewOrderNotification(
    $userId,
    'QUICK-TEST-' . time(),
    'Quick Test Customer',
    '99.00'
);

echo "Result: " . ($result ? "SUCCESS" : "FAILED");
echo "<br>Check error_log for details.";
?>