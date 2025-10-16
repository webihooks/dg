<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://dgcard.online');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config/db_connection.php';

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $subscriptionJson = $input['token'];
    $userId = $_SESSION['user_id'];
    
    // Decode the subscription object to get the actual FCM token
    $subscriptionData = json_decode($subscriptionJson, true);
    
    if (!$subscriptionData) {
        echo json_encode(['success' => false, 'message' => 'Invalid subscription data']);
        exit();
    }
    
    // The subscription data contains the endpoint and keys
    $endpoint = $subscriptionData['endpoint'] ?? '';
    $p256dh = $subscriptionData['keys']['p256dh'] ?? '';
    $auth = $subscriptionData['keys']['auth'] ?? '';
    
    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        echo json_encode(['success' => false, 'message' => 'Invalid subscription: missing endpoint or keys']);
        exit();
    }
    
    // For FCM V1 API, we need to use the FULL subscription object, not just a token
    // We'll store the entire subscription data and use it properly
    
    error_log("FCM: Saving subscription for user $userId");
    error_log("FCM: Endpoint: " . substr($endpoint, 0, 50) . "...");
    error_log("FCM: p256dh key: " . substr($p256dh, 0, 10) . "...");
    error_log("FCM: auth key: " . substr($auth, 0, 10) . "...");
    
    // Create a unique identifier for this subscription
    $subscriptionId = md5($endpoint . $p256dh . $auth);
    
    // Check if subscription already exists
    $checkStmt = $conn->prepare("SELECT id FROM fcm_tokens WHERE token = ?");
    $checkStmt->execute([$subscriptionId]);
    $existingToken = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingToken) {
        // Update existing subscription
        $updateStmt = $conn->prepare("UPDATE fcm_tokens SET user_id = ?, updated_at = NOW(), subscription_data = ? WHERE token = ?");
        $updateStmt->execute([$userId, $subscriptionJson, $subscriptionId]);
        error_log("FCM: Updated existing subscription");
        $action = 'updated';
    } else {
        // Insert new subscription
        $insertStmt = $conn->prepare("INSERT INTO fcm_tokens (user_id, token, device_type, subscription_data) VALUES (?, ?, 'web', ?)");
        $insertStmt->execute([$userId, $subscriptionId, $subscriptionJson]);
        error_log("FCM: Inserted new subscription");
        $action = 'inserted';
    }
    
    // Verify the subscription was saved
    $verifyStmt = $conn->prepare("SELECT COUNT(*) as count FROM fcm_tokens WHERE user_id = ?");
    $verifyStmt->execute([$userId]);
    $subscriptionCount = $verifyStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Subscription saved successfully',
        'action' => $action,
        'subscription_count' => $subscriptionCount,
        'subscription_id' => $subscriptionId
    ]);
    
} catch (Exception $e) {
    error_log("FCM Subscription save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save subscription: ' . $e->getMessage()]);
}
?>