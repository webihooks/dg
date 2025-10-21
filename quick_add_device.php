<?php
require_once 'db_connection.php';

// Get input
$userId = $_POST['user_id'] ?? 28;
$playerId = $_POST['player_id'] ?? 'test-device-' . time();
$deviceType = $_POST['device_type'] ?? 'android';

try {
    // Add the device
    $sql = "INSERT INTO user_devices (user_id, player_id, device_type, platform) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $userId, $playerId, $deviceType, $deviceType);
    $stmt->execute();
    
    // Redirect back to test
    header("Location: test_final.php?success=1&device_added=1");
    exit;
    
} catch (Exception $e) {
    echo "Error adding device: " . $e->getMessage();
}
?>