<?php
// borzo/api/cancel-borzo-order.php - FIXED VERSION
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);


try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $user_id = $_SESSION['user_id'];
    
    require_once dirname(__DIR__, 2) . '/db_connection.php';
    
    $configPath = dirname(__DIR__) . '/config/borzo.php';
    if (!file_exists($configPath)) {
        throw new Exception('Borzo config file not found');
    }
    
    $borzoConfig = require $configPath;
    if (!is_array($borzoConfig)) {
        throw new Exception('Borzo config is invalid');
    }
    
    require_once dirname(__DIR__) . '/classes/DynamicDeliveryManager.php';

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['order_id'])) {
        throw new Exception('Invalid input: order_id required');
    }
    
    $orderId = (int)$input['order_id'];

    // Verify order belongs to this user
    $sql = "SELECT user_id, borzo_order_id, borzo_status FROM orders WHERE order_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $orderId, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order not found or access denied');
    }

    if (empty($order['borzo_order_id'])) {
        throw new Exception('No Borzo order found for this order');
    }

    // Check if order can be cancelled
    $cancellableStatuses = ['new', 'available', 'active', 'delayed'];
    if (!in_array($order['borzo_status'], $cancellableStatuses)) {
        throw new Exception('Borzo order cannot be cancelled in current status: ' . $order['borzo_status']);
    }

    $deliveryManager = new DynamicDeliveryManager($user_id, $borzoConfig);
    
    // Check if user has API key configured
    if (!$deliveryManager->hasValidApiKey()) {
        throw new Exception('Borzo API key not configured. Please add your API key in Borzo API settings.');
    }
    
    $result = $deliveryManager->cancelOrder($orderId);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Cancel Borzo Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'errors' => [$e->getMessage()]
    ]);
}