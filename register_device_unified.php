<?php
// register_device_unified.php - ANDROID ONLY REGISTRATION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
$username = 'root';
$password = '';

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

    // Check if device exists
    $check = $conn->query("SELECT id FROM user_devices WHERE player_id = '$playerId' AND user_id = $userId");
    
    if ($check->num_rows > 0) {
        // Update existing Android device
        $sql = "UPDATE user_devices SET 
                device_type = '$deviceType', 
                platform = '$platform', 
                source = '$source',
                is_active = 1,
                updated_at = NOW() 
                WHERE player_id = '$playerId' AND user_id = $userId";
    } else {
        // Insert new Android device
        $sql = "INSERT INTO user_devices 
                (user_id, player_id, device_type, platform, source, is_active, created_at, updated_at) 
                VALUES 
                ($userId, '$playerId', '$deviceType', '$platform', '$source', 1, NOW(), NOW())";
    }
    
    if ($conn->query($sql)) {
        error_log("SUCCESS: Android device registered/updated for user $userId");
        echo json_encode([
            'success' => true, 
            'message' => $check->num_rows > 0 ? 'Android device updated' : 'Android device registered',
            'action' => $check->num_rows > 0 ? 'updated' : 'registered',
            'user_id' => $userId,
            'player_id' => $playerId,
            'device_type' => $deviceType
        ]);
    } else {
        throw new Exception("SQL failed: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("REGISTRATION ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>