<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

$conn = new mysqli($host, $username, $password, $dbname);

echo "<h1>📱 Push Notification Monitor</h1>";

// Get stats
$usersStmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as total_users FROM user_devices WHERE is_active = 1");
$usersStmt->execute();
$usersResult = $usersStmt->get_result();
$totalUsers = $usersResult->fetch_assoc()['total_users'];

$devicesStmt = $conn->prepare("SELECT COUNT(*) as total_devices FROM user_devices WHERE is_active = 1");
$devicesStmt->execute();
$devicesResult = $devicesStmt->get_result();
$totalDevices = $devicesResult->fetch_assoc()['total_devices'];

echo "<div style='display: flex; gap: 20px; margin: 20px 0;'>";
echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 10px; flex: 1;'>";
echo "<h3>👥 Registered Users</h3>";
echo "<p style='font-size: 24px; font-weight: bold;'>$totalUsers</p>";
echo "</div>";

echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 10px; flex: 1;'>";
echo "<h3>📱 Active Devices</h3>";
echo "<p style='font-size: 24px; font-weight: bold;'>$totalDevices</p>";
echo "</div>";
echo "</div>";

// Show all registered devices
echo "<h2>Registered Devices</h2>";
$devicesStmt = $conn->prepare("
    SELECT ud.*, u.Email 
    FROM user_devices ud 
    LEFT JOIN users u ON ud.user_id = u.id 
    WHERE ud.is_active = 1 
    ORDER BY ud.updated_at DESC
");
$devicesStmt->execute();
$devicesResult = $devicesStmt->get_result();

echo "<table border='1' cellpadding='10' cellspacing='0' style='width: 100%;'>";
echo "<tr style='background: #f5f5f5;'>
        <th>User</th>
        <th>Email</th>
        <th>Device Type</th>
        <th>Player ID</th>
        <th>Last Updated</th>
        <th>Status</th>
      </tr>";

while ($device = $devicesResult->fetch_assoc()) {
    $playerIdShort = substr($device['player_id'], 0, 8) . '...';
    $status = $device['is_active'] ? '🟢 Active' : '🔴 Inactive';
    
    echo "<tr>
            <td>{$device['user_id']}</td>
            <td>{$device['Email']}</td>
            <td>{$device['device_type']}</td>
            <td title='{$device['player_id']}'>{$playerIdShort}</td>
            <td>{$device['updated_at']}</td>
            <td>{$status}</td>
          </tr>";
}
echo "</table>";

$conn->close();
?>