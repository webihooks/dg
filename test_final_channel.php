<?php
session_start();
require_once 'onesignal_config.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];
$oneSignal = new OneSignalNotification();

echo "<h1>Final Channel Test</h1>";
echo "<p><strong>User ID:</strong> $userId</p>";
echo "<p><strong>Channel ID:</strong> 98231266-7763-4604-9817-fd3871a27ca5</p>";

// Test the notification
echo "<h2>Test Result:</h2>";
$result = $oneSignal->sendTestNotification($userId);

echo "<pre>" . print_r($result, true) . "</pre>";

if ($result['success']) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #c3e6cb;'>";
    echo "<h3 style='margin: 0;'>🎉 SUCCESS! Push Notifications are WORKING! 🎉</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Check your Android app - you should receive a notification in the 'New Orders' channel</p>";
    echo "</div>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ul>";
    echo "<li>✅ Device registration: Working</li>";
    echo "<li>✅ Channel configuration: Complete</li>";
    echo "<li>✅ Notification sending: Working</li>";
    echo "<li>🚀 Your system is ready for real orders!</li>";
    echo "</ul>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #f5c6cb;'>";
    echo "<h3 style='margin: 0;'>❌ Test Failed</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Error: " . $result['message'] . "</p>";
    echo "</div>";
}

// Show registered devices
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

$conn = new mysqli($host, $username, $password, $dbname);
$stmt = $conn->prepare("SELECT player_id, device_type, created_at FROM user_devices WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$devices = [];
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

echo "<h3>Registered Devices:</h3>";
echo "<p>Found " . count($devices) . " devices</p>";
echo "<pre>" . print_r($devices, true) . "</pre>";

$conn->close();
?>