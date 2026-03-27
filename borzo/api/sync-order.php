<?php
// borzo/api/sync-order.php - Manual sync with Borzo API
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
    require_once dirname(__DIR__) . '/classes/DynamicDeliveryManager.php';

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['order_id'])) {
        throw new Exception('Invalid input: order_id required');
    }
    
    $orderId = (int)$input['order_id'];

    // Verify order belongs to this user
    $sql = "SELECT user_id, borzo_order_id FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order || $order['user_id'] != $user_id) {
        throw new Exception('Order not found or access denied');
    }

    if (empty($order['borzo_order_id'])) {
        throw new Exception('No Borzo order found for this order');
    }

    $deliveryManager = new DynamicDeliveryManager($user_id, $borzoConfig);
    
    // Check if user has API key configured
    if (!$deliveryManager->hasValidApiKey()) {
        throw new Exception('Borzo API key not configured. Please add your API key in Borzo API settings.');
    }
    
    // Track order to get latest status
    $result = $deliveryManager->trackOrder($orderId);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Sync Order Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}