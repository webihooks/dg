<?php
// send_onesignal_notification.php - PRODUCTION READY
header('Content-Type: application/json');
require_once 'onesignal_config.php';

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['order_id'])) {
    error_log("❌ Invalid notification request");
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
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
        error_log("✅ NOTIFICATION SENT - Order #$orderId to user $userId");
        echo json_encode([
            'success' => true, 
            'message' => 'Notification sent successfully',
            'devices_count' => count($result['player_ids'] ?? [])
        ]);
    } else {
        error_log("❌ NOTIFICATION FAILED - Order #$orderId: " . $result['message']);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send notification',
            'error' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("❌ NOTIFICATION EXCEPTION: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Notification system error']);
}
?>