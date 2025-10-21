<?php
require_once 'db_connection.php';
header('Content-Type: application/json');

// For testing - allows manual device registration
$input = json_decode(file_get_contents('php://input'), true);
$playerId = $input['player_id'] ?? 'test-webview-' . time();
$userId = $input['user_id'] ?? 28; // Your test user ID
$deviceType = $input['device_type'] ?? 'android_webview';

try {
    $insertSql = "INSERT INTO user_devices (user_id, player_id, device_type, platform) VALUES (?, ?, ?, 'android') 
                  ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("iss", $userId, $playerId, $deviceType);
    $insertStmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Test device registered',
        'player_id' => $playerId,
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>