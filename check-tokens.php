<?php
require 'db_connection.php';
$result = $conn->query("SELECT COUNT(*) as count FROM fcm_tokens WHERE is_active = 1");
$row = $result->fetch_assoc();
echo "Active FCM Tokens: " . $row['count'] . "\n";

if ($row['count'] > 0) {
    $tokens = $conn->query("SELECT id, user_id, token, created_at FROM fcm_tokens WHERE is_active = 1");
    while($token = $tokens->fetch_assoc()) {
        echo "\n=== Token Found ===\n";
        echo "ID: " . $token['id'] . "\n";
        echo "User ID: " . $token['user_id'] . "\n";
        echo "Token: " . $token['token'] . "\n";
        echo "Created: " . $token['created_at'] . "\n";
    }
} else {
    echo "\nNo active FCM tokens found.\n";
    echo "You need to open the DGCard Android app and allow notifications first.\n";
}
$conn->close();
?>
