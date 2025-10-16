<?php
// fcm-token-handler.php
session_start();
require 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit();
}

$token = $input['token'];
$device_type = $input['device_type'] ?? 'web';

try {
    // Insert or update token
    $sql = "INSERT INTO fcm_tokens (user_id, token, device_type) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            updated_at = CURRENT_TIMESTAMP, 
            is_active = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $token, $device_type);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'FCM token saved successfully'
        ]);
    } else {
        throw new Exception('Failed to save token');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving token: ' . $e->getMessage()
    ]);
}

$conn->close();
?>