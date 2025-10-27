<?php
session_start();
require_once 'onesignal_config.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];
$oneSignal = new OneSignalNotification();

echo "<h1>Final OneSignal Test</h1>";

// Test 1: Simple notification
echo "<h2>Test 1: Simple Notification</h2>";
$result1 = $oneSignal->sendSimpleTest($userId);
echo "<pre>" . print_r($result1, true) . "</pre>";

// Test 2: WebToNative compatible
echo "<h2>Test 2: WebToNative Compatible</h2>";
$result2 = $oneSignal->sendWebToNativeNotification($userId, "TEST-" . time(), "Test Customer", "199.00");
echo "<pre>" . print_r($result2, true) . "</pre>";

// Test 3: Check if any succeeded
echo "<h2>Summary</h2>";
if ($result1['success'] || $result2['success']) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Notifications are working!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Check the errors above</p>";
}
?>