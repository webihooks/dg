<?php
session_start();
require_once 'onesignal_config.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];
$oneSignal = new OneSignalNotification();

// Get player IDs
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

$conn = new mysqli($host, $username, $password, $dbname);
$stmt = $conn->prepare("SELECT player_id FROM user_devices WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$playerIds = [];
while ($row = $result->fetch_assoc()) {
    $playerIds[] = $row['player_id'];
}
$conn->close();

echo "<h1>Direct OneSignal Test</h1>";
echo "<p>User ID: $userId</p>";
echo "<p>Player IDs: " . implode(', ', $playerIds) . "</p>";

if (empty($playerIds)) {
    die("No player IDs found");
}

// Test direct API call
$testData = [
    'app_id' => "9d512a16-1b7c-4d2c-ae9f-07c36c963086",
    'include_player_ids' => $playerIds,
    'contents' => ['en' => 'Direct test notification'],
    'headings' => ['en' => 'Test from DeeGeeCard'],
    'data' => ['test' => true, 'type' => 'test_notification']
];

echo "<h2>API Payload:</h2>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Send directly
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Bearer os_v2_app_tvisufq3prgszlu7a7bwzfrqq3wmhbl53lmem2fmf2cqjrkae2izj4uohbajanp2dnpyxhcmbtru53c5jkczqqovrathaohvyoxhpxq'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Direct API Response:</h2>";
echo "<p>HTTP Code: $httpCode</p>";
echo "<pre>" . $response . "</pre>";
?>