<?php
// register_device_unified.php - ENHANCED FOR DEVICE REACTIVATION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Start session to validate user
session_start();

// Get input
$input = json_decode(file_get_contents('php://input'), true);
error_log("🔔 REGISTRATION ATTEMPT: User " . ($input['user_id'] ?? 'unknown') . ", Player: " . ($input['player_id'] ?? 'unknown'));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

if (empty($input['player_id']) || empty($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing player_id or user_id']);
    exit();
}

// Validate that the requesting user matches the logged-in user
if ($input['user_id'] != ($_SESSION['user_id'] ?? null)) {
    error_log("❌ REJECTED: User ID mismatch. Session: " . ($_SESSION['user_id'] ?? 'none') . ", Request: " . $input['user_id']);
    echo json_encode([
        'success' => false, 
        'message' => 'User authentication failed'
    ]);
    exit();
}

// CRITICAL: ONLY ALLOW ANDROID DEVICES
$platform = $input['platform'] ?? 'unknown';
$deviceType = $input['device_type'] ?? 'unknown';

// Reject web browser registrations
if ($platform === 'web' || $deviceType === 'web_browser') {
    error_log("❌ REJECTED: Web browser registration attempt for user " . $input['user_id']);
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
    error_log("❌ REJECTED: Non-Android device registration - Platform: $platform, Device: $deviceType");
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

    // Check if this specific device already exists for this user
    $check = $conn->query("SELECT id, is_active, updated_at FROM user_devices WHERE player_id = '$playerId' AND user_id = $userId");
    
    if ($check->num_rows > 0) {
        // Device exists - check if it's inactive and needs reactivation
        $existingDevice = $check->fetch_assoc();
        $isCurrentlyActive = $existingDevice['is_active'] == 1;
        $lastUpdated = $existingDevice['updated_at'];
        
        // ALWAYS REACTIVATE on login - regardless of current status
        $sql = "UPDATE user_devices SET 
                device_type = '$deviceType', 
                platform = '$platform', 
                source = '$source',
                is_active = 1, 
                updated_at = NOW() 
                WHERE player_id = '$playerId' AND user_id = $userId";
        
        $action = $isCurrentlyActive ? 'updated' : 'reactivated';
        $wasReactivated = !$isCurrentlyActive;
        
        error_log("🔄 Device $action for user $userId - Player: $playerId (was active: $isCurrentlyActive)");
        
    } else {
        // Insert new Android device
        $sql = "INSERT INTO user_devices 
                (user_id, player_id, device_type, platform, source, is_active, created_at, updated_at) 
                VALUES 
                ($userId, '$playerId', '$deviceType', '$platform', '$source', 1, NOW(), NOW())";
        
        $action = 'registered';
        $wasReactivated = false;
        error_log("✅ New device registered for user $userId - Player: $playerId");
    }
    
    if ($conn->query($sql)) {
        // Store player ID in session for device-specific logout
        $_SESSION['current_player_id'] = $playerId;
        $_SESSION['current_device_type'] = $deviceType;
        $_SESSION['device_registered_at'] = time();
        
        // Get count of active devices for this user
        $countResult = $conn->query("SELECT COUNT(*) as active_count FROM user_devices WHERE user_id = $userId AND is_active = 1");
        $activeCount = $countResult->fetch_assoc()['active_count'];
        
        error_log("🎯 SUCCESS: Android device $action for user $userId. Active devices: $activeCount");
        
        echo json_encode([
            'success' => true, 
            'message' => $wasReactivated ? 'Android device reactivated successfully' : 'Android device registered successfully',
            'action' => $action,
            'user_id' => $userId,
            'player_id' => $playerId,
            'device_type' => $deviceType,
            'active_devices_count' => $activeCount,
            'was_reactivated' => $wasReactivated,
            'timestamp' => time()
        ]);
    } else {
        throw new Exception("SQL failed: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("❌ REGISTRATION ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>