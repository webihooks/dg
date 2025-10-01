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

// Check if this is for order status updates (Solution 4)
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
    // Original functionality for new order polling
    $last_order_id = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;
    $page_load_time = isset($_GET['page_load_time']) ? (int)$_GET['page_load_time'] : time();

    // Handle midnight transition
    $current_time = time();
    $page_load_date = date('Y-m-d', $page_load_time);
    $current_date = date('Y-m-d');

    try {
        // Strategy: If we're on a different date than when the page loaded,
        // we need to get orders from the new date regardless of page_load_time
        if ($page_load_date !== $current_date) {
            // Midnight has passed - get all orders from today (new date)
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
                        o.order_notes
                    FROM orders o
                    WHERE o.user_id = ? 
                    AND DATE(o.created_at) = ? 
                    AND o.status = 'Pending'
                    ORDER BY o.order_id DESC 
                    LIMIT 100";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $current_date);
        } else {
            // Same day - normal polling (orders after last_order_id AND after page load time)
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
                        o.order_notes
                    FROM orders o
                    WHERE o.user_id = ? 
                    AND o.order_id > ? 
                    AND o.created_at > FROM_UNIXTIME(?) 
                    AND o.status = 'Pending'
                    ORDER BY o.order_id DESC 
                    LIMIT 100";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $user_id, $last_order_id, $page_load_time);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $new_orders = [];
        while ($row = $result->fetch_assoc()) {
            // Fetch order items for each order
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
            
            $new_orders[] = $row;
        }
        
        $stmt->close();
        
        // Return the complete order data with items
        echo json_encode([
            'success' => true,
            'new_orders' => $new_orders,
            'update_type' => 'new_orders_check',
            'debug' => [
                'last_order_id_received' => $last_order_id,
                'page_load_time' => $page_load_time,
                'page_load_date' => $page_load_date,
                'current_date' => $current_date,
                'current_time' => $current_time,
                'orders_found' => count($new_orders),
                'query_type' => ($page_load_date !== $current_date) ? 'date_based' : 'normal',
                'sample_order' => count($new_orders) > 0 ? [
                    'order_id' => $new_orders[0]['order_id'],
                    'items_count' => count($new_orders[0]['items']),
                    'has_items' => !empty($new_orders[0]['items'])
                ] : null
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Database error: ' . $e->getMessage(),
            'update_type' => 'new_orders_check',
            'debug' => [
                'last_order_id' => $last_order_id,
                'page_load_time' => $page_load_time
            ]
        ]);
    }
}

$conn->close();
?>