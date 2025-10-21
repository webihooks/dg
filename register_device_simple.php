<?php
require_once 'db_connection.php';

// Simple device registration - no session required for testing
$input = json_decode(file_get_contents('php://input'), true);
$playerId = $input['player_id'] ?? '';

if (empty($playerId)) {
    echo 'No player ID';
    exit;
}

// Use user_id 28 for testing
$userId = 28;

try {
    $sql = "INSERT INTO user_devices (user_id, player_id, device_type, platform) 
            VALUES (?, ?, 'web_browser', 'web') 
            ON DUPLICATE KEY UPDATE is_active=1, updated_at=NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $playerId);
    $stmt->execute();
    
    echo 'Device registered: ' . $playerId;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>