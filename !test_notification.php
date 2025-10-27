<?php
session_start();
require_once 'onesignal_config.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];
$oneSignal = new OneSignalNotification();

echo "<h1>Testing OneSignal Notifications</h1>";
echo "<p>User ID: $userId</p>";

// Test credentials
echo "<h2>1. Testing Credentials</h2>";
$credTest = $oneSignal->verifyCredentials();
echo "<pre>" . print_r($credTest, true) . "</pre>";

// Simple test first
echo "<h2>2. Simple Test (No Channel ID)</h2>";
$simpleTest = $oneSignal->sendSimpleTest($userId);
echo "<pre>" . print_r($simpleTest, true) . "</pre>";

// Full test notification
echo "<h2>3. Full Test Notification</h2>";
$testResult = $oneSignal->sendTestNotification($userId);
echo "<pre>" . print_r($testResult, true) . "</pre>";

// Show registered devices
echo "<h2>4. Registered Devices</h2>";
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

$conn = new mysqli($host, $username, $password, $dbname);
$stmt = $conn->prepare("SELECT * FROM user_devices WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$devices = [];
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

echo "<p>Found " . count($devices) . " devices:</p>";
echo "<pre>" . print_r($devices, true) . "</pre>";

$conn->close();
?>