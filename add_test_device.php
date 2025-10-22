<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

require_once 'db_connection.php';

echo "<h3>Add Test Device</h3>";

if ($_POST) {
    $userId = $_SESSION['user_id'];
    $playerId = $_POST['player_id'] ?? 'test-device-' . time();
    $deviceType = $_POST['device_type'] ?? 'android';
    
    try {
        // Check if device already exists
        $checkSql = "SELECT id FROM user_devices WHERE player_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $playerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p style='color: orange;'>Device already exists</p>";
        } else {
            // Insert new device
            $insertSql = "INSERT INTO user_devices (user_id, player_id, device_type) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iss", $userId, $playerId, $deviceType);
            
            if ($insertStmt->execute()) {
                echo "<p style='color: green;'>✅ Test device added successfully!</p>";
                echo "Player ID: $playerId<br>";
                echo "Device Type: $deviceType<br>";
            } else {
                throw new Exception("Failed to insert device");
            }
            
            $insertStmt->close();
        }
        
        $checkStmt->close();
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// Show current devices
$userId = $_SESSION['user_id'];
$deviceSql = "SELECT player_id, device_type, created_at FROM user_devices WHERE user_id = ? ORDER BY created_at DESC";
$deviceStmt = $conn->prepare($deviceSql);
$deviceStmt->bind_param("i", $userId);
$deviceStmt->execute();
$deviceResult = $deviceStmt->get_result();

echo "<h4>Current Devices:</h4>";
if ($deviceResult->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Player ID</th><th>Device Type</th><th>Registered</th></tr>";
    while ($row = $deviceResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['player_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No devices registered yet.</p>";
}
$deviceStmt->close();

?>

<form method="post" style="margin-top: 20px; padding: 20px; border: 1px solid #ccc;">
    <h4>Add New Test Device:</h4>
    <div>
        <label>Player ID:</label><br>
        <input type="text" name="player_id" value="test-device-<?php echo time(); ?>" size="50">
    </div>
    <div style="margin-top: 10px;">
        <label>Device Type:</label><br>
        <select name="device_type">
            <option value="android">Android</option>
            <option value="ios">iOS</option>
            <option value="web">Web</option>
        </select>
    </div>
    <div style="margin-top: 15px;">
        <button type="submit">Add Test Device</button>
    </div>
</form>

<p style="margin-top: 20px;">
    <a href="test_notification_fixed.php">← Back to Test Notification</a>
</p>