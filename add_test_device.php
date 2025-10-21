<?php
// Enable error reporting at the VERY TOP
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent header errors
ob_start();

// Start session
session_start();

if (!isset($_SESSION['user_id'])) {
    // Clear buffer and redirect or show error
    ob_end_clean();
    die("Please login first");
}

// Include database connection AFTER output buffering
require_once 'db_connection.php';

echo "<h3>Add Test Device</h3>";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            echo "<p style='color: orange;'>⚠️ Device already exists</p>";
        } else {
            // Insert new device
            $insertSql = "INSERT INTO user_devices (user_id, player_id, device_type) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iss", $userId, $playerId, $deviceType);
            
            if ($insertStmt->execute()) {
                echo "<p style='color: green;'>✅ Test device added successfully!</p>";
                echo "<p><strong>Player ID:</strong> $playerId</p>";
                echo "<p><strong>Device Type:</strong> $deviceType</p>";
                echo "<p><strong>User ID:</strong> $userId</p>";
            } else {
                throw new Exception("Failed to insert device: " . $insertStmt->error);
            }
            
            $insertStmt->close();
        }
        
        $checkStmt->close();
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// Show current devices
$userId = $_SESSION['user_id'];
$deviceSql = "SELECT player_id, device_type, created_at FROM user_devices WHERE user_id = ? ORDER BY created_at DESC";
$deviceStmt = $conn->prepare($deviceSql);
$deviceStmt->bind_param("i", $userId);
$deviceStmt->execute();
$deviceResult = $deviceStmt->get_result();

echo "<h4>Current Devices for User ID: $userId</h4>";
if ($deviceResult->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
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
    echo "<p style='color: orange;'>No devices registered yet.</p>";
}
$deviceStmt->close();

?>

<div style="margin-top: 30px; padding: 20px; border: 2px solid #007bff; border-radius: 10px;">
    <h4>Quick Add Test Device:</h4>
    <form method="post">
        <div style="margin: 10px 0;">
            <label><strong>Player ID:</strong></label><br>
            <input type="text" name="player_id" value="test-device-<?php echo time(); ?>" 
                   style="width: 300px; padding: 8px;" required>
        </div>
        
        <div style="margin: 10px 0;">
            <label><strong>Device Type:</strong></label><br>
            <select name="device_type" style="padding: 8px;">
                <option value="android">Android</option>
                <option value="ios">iOS</option>
                <option value="web">Web Browser</option>
                <option value="webview">WebView App</option>
            </select>
        </div>
        
        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            ➕ Add Test Device
        </button>
    </form>
</div>

<div style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 5px;">
    <h4>💡 Important:</h4>
    <p>After adding test devices, go to <a href="test_success.php">Test Success Page</a> to send actual notifications.</p>
</div>

<p style="margin-top: 20px;">
    <a href="test_success.php">🎯 Test Notifications</a> | 
    <a href="notification_status.php">📊 View Status</a>
</p>

<?php
// Flush the output buffer
ob_end_flush();
?>