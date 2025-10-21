<?php
// send_onesignal_notification.php - ENHANCED VERSION
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log that we received the request
error_log("🔔 Notification endpoint called");

// Get input from POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
} else {
    error_log("❌ Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    exit();
}

// Log the received data
error_log("📨 Notification data received: " . print_r($input, true));

if (!$input || !isset($input['user_id']) || !isset($input['order_id'])) {
    error_log("❌ Invalid notification request - missing user_id or order_id");
    exit();
}

try {
    require_once 'onesignal_config.php';
    
    $userId = $input['user_id'];
    $orderId = $input['order_id'];
    $customerName = $input['customer_name'] ?? 'Customer';
    $totalAmount = $input['total_amount'] ?? 0;
    $orderType = $input['order_type'] ?? 'delivery';

    error_log("🔔 Processing notification - User: $userId, Order: #$orderId");

    // Initialize OneSignal
    $oneSignal = new OneSignalNotification();
    
    // Send notification
    $result = $oneSignal->sendNewOrderNotification($userId, $orderId, $customerName, $totalAmount);
    
    if ($result['success']) {
        error_log("✅ NOTIFICATION SENT SUCCESS - Order #$orderId to user $userId");
        echo json_encode([
            'success' => true, 
            'message' => 'Notification sent successfully',
            'devices_count' => count($result['player_ids'] ?? [])
        ]);
    } else {
        error_log("❌ NOTIFICATION FAILED - Order #$orderId: " . $result['message']);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send notification: ' . $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("❌ NOTIFICATION EXCEPTION: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Notification system error: ' . $e->getMessage()]);
}
?>