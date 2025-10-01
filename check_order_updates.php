<?php
require 'db_connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if order_ids parameter is provided
if (isset($_GET['order_ids'])) {
    $order_ids = isset($_GET['order_ids']) ? explode(',', $_GET['order_ids']) : [];
    $order_ids = array_map('intval', $order_ids);
    
    if (empty($order_ids)) {
        echo json_encode(['success' => true, 'updated_orders' => []]);
        exit;
    }

    try {
        $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
        $sql = "SELECT 
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
                WHERE o.order_id IN ($placeholders) AND o.user_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $types = str_repeat('i', count($order_ids)) . 'i';
        $params = array_merge($order_ids, [$user_id]);
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $updated_orders = [];

        while ($row = $result->fetch_assoc()) {
            // Also fetch order items for complete order data
            $items_sql = "SELECT 
                            product_name, 
                            price, 
                            quantity 
                          FROM order_items 
                          WHERE order_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $row['order_id']);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            
            $row['items'] = $items;
            $items_stmt->close();
            
            $updated_orders[] = $row;
        }

        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'updated_orders' => $updated_orders,
            'update_type' => 'status_check'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Database error: ' . $e->getMessage(),
            'update_type' => 'status_check'
        ]);
    }
} else {
    echo json_encode([
        'error' => 'No order IDs provided',
        'update_type' => 'status_check'
    ]);
}

$conn->close();
?>