<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    require_once 'config/db_connection.php';
    require_once 'web_push_notification.php';
    
    error_log("=== NEW ORDER TEST STARTED ===");
    error_log("Testing new order notification for user: $userId");

    // Simulate a new order with all details
    $orderId = 'TEST-' . time();
    $customerName = 'Test Customer ' . rand(1000, 9999);
    $totalAmount = rand(100, 1000) . '.' . rand(10, 99);
    
    // Random order type and address
    $orderTypes = ['delivery', 'dining'];
    $orderType = $orderTypes[array_rand($orderTypes)];
    
    if ($orderType === 'delivery') {
        $addresses = [
            '123 Main Street, Apartment 4B, New Delhi',
            '456 Park Avenue, Ground Floor, Mumbai',
            '789 Gandhi Road, Near Metro Station, Bangalore'
        ];
        $customerAddress = $addresses[array_rand($addresses)];
    } else {
        $customerAddress = 'Table: ' . rand(1, 20);
    }
    
    error_log("Test Order Details:");
    error_log(" - Order ID: $orderId");
    error_log(" - Customer: $customerName");
    error_log(" - Address: $customerAddress");
    error_log(" - Amount: ₹$totalAmount");
    error_log(" - Type: $orderType");
    
    // Send test notification
    $result = WebPushNotification::sendNewOrderNotification(
        $userId,
        $orderId,
        $customerName,
        $customerAddress,
        $totalAmount,
        $orderType
    );
    
    if ($result) {
        error_log("=== NEW ORDER TEST SUCCESS ===");
        echo json_encode([
            'success' => true, 
            'message' => 'New order notification test successful!',
            'order_details' => [
                'order_id' => $orderId,
                'customer_name' => $customerName,
                'customer_address' => $customerAddress,
                'total_amount' => $totalAmount,
                'order_type' => $orderType
            ]
        ]);
    } else {
        error_log("=== NEW ORDER TEST FAILED ===");
        echo json_encode([
            'success' => false, 
            'message' => 'New order notification test failed. Check server logs.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("=== NEW ORDER TEST ERROR: " . $e->getMessage() . " ===");
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

