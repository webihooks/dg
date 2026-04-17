<?php
// borzo/api/get-order-details.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);


try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $user_id = $_SESSION['user_id'];
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    if (!$order_id) {
        throw new Exception('Order ID required');
    }
    
    require_once dirname(__DIR__, 2) . '/db_connection.php';
    
    // Get the borzo_order_id and related details from orders table
    $sql = "SELECT o.borzo_order_id, o.delivery_address, o.building, o.floor, o.flat_unit, o.landmark,
                   o.borzo_geocoded_address, o.borzo_status, o.delivery_tracking_url, 
                   o.courier_name, o.courier_phone
            FROM orders o 
            WHERE o.order_id = ? AND o.user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    if (empty($order['borzo_order_id'])) {
        throw new Exception('No Borzo order found');
    }
    
    // Try to get additional details from tracking table if available
    $tracking_data = null;
    $sql = "SELECT location_data, courier_info, tracking_url 
            FROM order_delivery_tracking 
            WHERE order_id = ? AND borzo_order_id = ? 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $order_id, $order['borzo_order_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $tracking_data = $row;
    }
    $stmt->close();
    
    // Prepare response
    $response = [
        'success' => true,
        'order_id' => $order_id,
        'borzo_order_id' => $order['borzo_order_id'],
        'original_address' => $order['delivery_address'],
        'building' => $order['building'],
        'floor' => $order['floor'],
        'flat_unit' => $order['flat_unit'],
        'landmark' => $order['landmark'],
        'borzo_geocoded_address' => $order['borzo_geocoded_address'],
        'borzo_status' => $order['borzo_status'],
        'tracking_url' => $order['delivery_tracking_url'],
        'courier_name' => $order['courier_name'],
        'courier_phone' => $order['courier_phone']
    ];
    
    // Add tracking data if available
    if ($tracking_data) {
        if ($tracking_data['location_data']) {
            $location = json_decode($tracking_data['location_data'], true);
            $response['location'] = $location;
        }
        if ($tracking_data['courier_info']) {
            $courier = json_decode($tracking_data['courier_info'], true);
            $response['courier_details'] = $courier;
        }
        if ($tracking_data['tracking_url'] && empty($response['tracking_url'])) {
            $response['tracking_url'] = $tracking_data['tracking_url'];
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get Order Details Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}