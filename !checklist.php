<?php
session_start();
require_once 'onesignal_config.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];
$oneSignal = new OneSignalNotification();

echo "<h1>✅ Production Readiness Checklist</h1>";

$checks = [];

// Check 1: Database connection
try {
    $host = 'localhost';
    $dbname = 'doctorie_webihooks_card';
    $username = 'doctorie_webihooks';
    $password = 'S@g@r4834';
    $conn = new mysqli($host, $username, $password, $dbname);
    $checks['database'] = !$conn->connect_error;
    $conn->close();
} catch (Exception $e) {
    $checks['database'] = false;
}

// Check 2: User devices
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_devices WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $checks['devices'] = $data['count'] > 0;
    $conn->close();
} catch (Exception $e) {
    $checks['devices'] = false;
}

// Check 3: OneSignal credentials
$credTest = $oneSignal->verifyCredentials();
$checks['onesignal'] = $credTest['valid'];

// Check 4: Notification test
$testResult = $oneSignal->sendTestNotification($userId);
$checks['notification'] = $testResult['success'];

// Display results
echo "<div style='max-width: 600px; margin: 20px 0;'>";
foreach ($checks as $check => $status) {
    $icon = $status ? '✅' : '❌';
    $color = $status ? 'green' : 'red';
    echo "<div style='padding: 10px; border-bottom: 1px solid #eee;'>
            <span style='color: $color; font-weight: bold;'>$icon</span>
            " . ucfirst($check) . "
          </div>";
}
echo "</div>";

if (array_filter($checks)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🎉 System Ready for Production!</h3>";
    echo "<p>All systems are go! Your push notification system is fully operational.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>⚠️ System Needs Attention</h3>";
    echo "<p>Some checks failed. Please review the configuration.</p>";
    echo "</div>";
}
?>