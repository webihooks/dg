<?php
require 'db_connection.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    // Fetch order details from orders table (restaurant orders)
    $order_sql = "SELECT 
        o.order_id, 
        o.customer_name, 
        o.customer_phone, 
        o.order_type, 
        o.delivery_address, 
        o.table_number, 
        o.status, 
        o.subtotal, 
        o.discount_amount, 
        o.discount_type, 
        o.gst_amount, 
        o.delivery_charge, 
        o.total_amount, 
        o.created_at,
        o.order_notes,
        o.updated_at
    FROM orders o
    WHERE o.order_id = ? AND o.user_id = ?";
    
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("ii", $order_id, $user_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    
    if ($order_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    $order = $order_result->fetch_assoc();
    $order_stmt->close();
    
    // Fetch order items
    $items_sql = "SELECT 
        product_name, 
        price, 
        quantity 
    FROM order_items 
    WHERE order_id = ?";
    
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    $items_stmt->close();
    
    $order['items'] = $items;
    
    echo json_encode([
        'success' => true, 
        'order' => $order
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>