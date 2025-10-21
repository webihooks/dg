<?php
require_once 'db_connection.php';
header('Content-Type: application/json');

session_start();

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$playerId = $input['player_id'] ?? '';
$deviceType = $input['device_type'] ?? 'unknown';
$platform = $input['platform'] ?? '';

// For WebView apps, we might not have session, so check for other auth methods
$userId = null;

// Method 1: Session-based (regular web users)
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} 
// Method 2: Token-based (for app users)
else if (isset($input['user_token'])) {
    // Validate token and get user ID
    $userId = validateUserToken($input['user_token']);
}
// Method 3: Hardcoded for testing (remove in production)
else if (isset($input['test_user_id'])) {
    $userId = $input['test_user_id'];
}

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

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
        $updateSql = "UPDATE user_devices SET user_id = ?, device_type = ?, platform = ?, is_active = 1, updated_at = NOW() WHERE player_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("isss", $userId, $deviceType, $platform, $playerId);
        $updateStmt->execute();
        $updateStmt->close();
        
        $message = 'Device updated successfully';
    } else {
        // Insert new record
        $insertSql = "INSERT INTO user_devices (user_id, player_id, device_type, platform) VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("isss", $userId, $playerId, $deviceType, $platform);
        $insertStmt->execute();
        $insertStmt->close();
        
        $message = 'Device registered successfully';
    }
    
    $checkStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'user_id' => $userId,
        'player_id' => $playerId
    ]);
    
} catch (Exception $e) {
    error_log("Device registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Device registration failed: ' . $e->getMessage()]);
}

$conn->close();

// Function to validate user token (implement based on your auth system)
function validateUserToken($token) {
    // This should validate the token and return user ID
    // For now, returning null - implement based on your system
    return null;
}
?>