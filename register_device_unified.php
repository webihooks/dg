<?php
// register_device_unified.php - ANDROID ONLY REGISTRATION WITH SESSION VALIDATION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session for user validation
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
error_log("REGISTRATION ATTEMPT: " . print_r($input, true));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

if (empty($input['player_id']) || empty($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing player_id or user_id']);
    exit();
}

// CRITICAL: Validate that user is actually logged in and matches session
$sessionUserId = $_SESSION['user_id'] ?? null;
$requestUserId = intval($input['user_id']);

if (!$sessionUserId || $sessionUserId != $requestUserId) {
    error_log("REJECTED: User ID mismatch - Session: $sessionUserId, Request: $requestUserId");
    echo json_encode([
        'success' => false, 
        'message' => 'User not authenticated properly - please login again',
        'session_user_id' => $sessionUserId,
        'request_user_id' => $requestUserId
    ]);
    exit();
}

error_log("✅ User authentication validated - Session User ID: $sessionUserId");

// CRITICAL: ONLY ALLOW ANDROID DEVICES
$platform = $input['platform'] ?? 'unknown';
$deviceType = $input['device_type'] ?? 'unknown';

// Reject web browser registrations
if ($platform === 'web' || $deviceType === 'web_browser') {
    error_log("REJECTED: Web browser registration attempt for user " . $input['user_id']);
    echo json_encode([
        'success' => true, 
        'message' => 'Web browser registration skipped - Android only',
        'skipped' => true,
        'reason' => 'web_browser_not_allowed'
    ]);
    exit();
}

// Only allow Android devices
$allowedPlatforms = ['android', 'android_webtonative'];
if (!in_array($platform, $allowedPlatforms) && !in_array($deviceType, $allowedPlatforms)) {
    error_log("REJECTED: Non-Android device registration - Platform: $platform, Device: $deviceType");
    echo json_encode([
        'success' => true,
        'message' => 'Non-Android device registration skipped',
        'skipped' => true,
        'reason' => 'non_android_device'
    ]);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("DB connect failed: " . $conn->connect_error);
    }

    $playerId = $conn->real_escape_string($input['player_id']);
    $userId = intval($input['user_id']);
    $deviceType = $conn->real_escape_string($deviceType);
    $platform = $conn->real_escape_string($platform);
    $source = $conn->real_escape_string($input['source'] ?? 'unknown');

    // First, deactivate any existing devices for this user to prevent duplicates
    $deactivateSql = "UPDATE user_devices SET is_active = 0 WHERE user_id = $userId AND player_id != '$playerId'";
    if (!$conn->query($deactivateSql)) {
        error_log("Warning: Could not deactivate old devices for user $userId");
    }

    // Check if device exists
    $checkSql = "SELECT id, is_active FROM user_devices WHERE player_id = '$playerId' AND user_id = $userId";
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $deviceData = $checkResult->fetch_assoc();
        
        // Update existing Android device - always set as active
        $sql = "UPDATE user_devices SET 
                device_type = '$deviceType', 
                platform = '$platform', 
                source = '$source',
                is_active = 1,
                updated_at = NOW() 
                WHERE player_id = '$playerId' AND user_id = $userId";
        
        $action = 'updated';
        $wasReactivated = $deviceData['is_active'] == 0 ? true : false;
        
    } else {
        // Insert new Android device
        $sql = "INSERT INTO user_devices 
                (user_id, player_id, device_type, platform, source, is_active, created_at, updated_at) 
                VALUES 
                ($userId, '$playerId', '$deviceType', '$platform', '$source', 1, NOW(), NOW())";
        
        $action = 'registered';
        $wasReactivated = false;
    }
    
    if ($conn->query($sql)) {
        $message = $wasReactivated ? 
            "Android device reactivated" : 
            ($action === 'updated' ? 'Android device updated' : 'Android device registered');
            
        error_log("SUCCESS: $message for user $userId - Player ID: $playerId");
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'action' => $action,
            'user_id' => $userId,
            'player_id' => $playerId,
            'device_type' => $deviceType,
            'platform' => $platform,
            'reactivated' => $wasReactivated,
            'session_validated' => true
        ]);
    } else {
        throw new Exception("SQL failed: " . $conn->error);
    }
    
    if ($checkResult) {
        $checkResult->free();
    }
    
} catch (Exception $e) {
    error_log("REGISTRATION ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'session_user_id' => $sessionUserId
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>