<?php
require_once 'db_connection.php';
header('Content-Type: application/json');

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$playerId = $input['player_id'] ?? '';
$deviceType = $input['device_type'] ?? 'android_webtonative';
$platform = $input['platform'] ?? 'android';
$source = $input['source'] ?? 'webtonative_app';

// For WebToNative apps, we'll use user_id 28 (your restaurant)
$userId = 28;

if (empty($playerId)) {
    echo json_encode(['success' => false, 'message' => 'Player ID required']);
    exit;
}

try {
    // Check if device already exists
    $checkSql = "SELECT id FROM user_devices WHERE player_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $playerId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $updateSql = "UPDATE user_devices SET user_id = ?, device_type = ?, platform = ?, source = ?, is_active = 1, updated_at = NOW() WHERE player_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("issss", $userId, $deviceType, $platform, $source, $playerId);
        $updateStmt->execute();
        $updateStmt->close();
        
        $message = 'WebToNative device updated successfully';
    } else {
        // Insert new record
        $insertSql = "INSERT INTO user_devices (user_id, player_id, device_type, platform, source) VALUES (?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("issss", $userId, $playerId, $deviceType, $platform, $source);
        $insertStmt->execute();
        $insertStmt->close();
        
        $message = 'WebToNative device registered successfully';
    }
    
    $checkStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'user_id' => $userId,
        'player_id' => $playerId,
        'device_type' => $deviceType,
        'source' => $source
    ]);
    
} catch (Exception $e) {
    error_log("WebToNative registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Device registration failed: ' . $e->getMessage()]);
}

$conn->close();
?>