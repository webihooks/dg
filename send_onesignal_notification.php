<?php
// send_onesignal_notification.php
header('Content-Type: application/json');
require_once 'onesignal_config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['order_id'])) {
    error_log("Invalid notification request: " . print_r($input, true));
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    $userId = $input['user_id'];
    $orderId = $input['order_id'];
    $customerName = $input['customer_name'] ?? 'Customer';
    $totalAmount = $input['total_amount'] ?? 0;
    $orderType = $input['order_type'] ?? 'delivery';

    error_log("Processing OneSignal notification for user $userId, order $orderId");

    // Initialize OneSignal
    $oneSignal = new OneSignalNotification();
    
    // Send notification
    $result = $oneSignal->sendNewOrderNotification($userId, $orderId, $customerName, $totalAmount);
    
    if ($result['success']) {
        error_log("✅ Notification sent successfully for order $orderId");
        echo json_encode([
            'success' => true, 
            'message' => 'Notification sent successfully',
            'details' => $result
        ]);
    } else {
        error_log("❌ Notification failed for order $orderId: " . $result['message']);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send notification: ' . $result['message'],
            'error' => $result
        ]);
    }
    
} catch (Exception $e) {
    error_log("❌ Notification sending exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Notification failed: ' . $e->getMessage()]);
}
?>