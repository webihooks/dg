<?php
// check_existing_pending_orders.php
// This file checks for ALL pending orders (both Pending and Confirmed status)

require 'db_connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated', 'success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get all pending AND confirmed orders for this user from today
    // Changed to include both 'Pending' and 'Confirmed' status
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
            AND DATE(o.created_at) = CURDATE()
            AND o.status IN ('Pending', 'Confirmed')  // CHANGED: Include both statuses
            ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }

    $pending_orders = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for better display
        $row['created_at_formatted'] = date('d-m-Y h:i A', strtotime($row['created_at']));
        
        // Format amounts
        $row['subtotal_formatted'] = '₹' . number_format($row['subtotal'], 2, '.', '');
        $row['gst_amount_formatted'] = '₹' . number_format($row['gst_amount'], 2, '.', '');
        $row['delivery_charge_formatted'] = '₹' . number_format($row['delivery_charge'], 2, '.', '');
        $row['total_amount_formatted'] = '₹' . number_format($row['total_amount'], 2, '.', '');
        
        // Fetch order items for each order
        $items_sql = "SELECT 
                        product_name, 
                        price, 
                        quantity,
                        (price * quantity) as item_total
                      FROM order_items 
                      WHERE order_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        
        if ($items_stmt) {
            $items_stmt->bind_param("i", $row['order_id']);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            $total_items = 0;
            while ($item = $items_result->fetch_assoc()) {
                $item['price_formatted'] = '₹' . number_format($item['price'], 2, '.', '');
                $item['item_total_formatted'] = '₹' . number_format($item['item_total'], 2, '.', '');
                $items[] = $item;
                $total_items += $item['quantity'];
            }
            
            $row['items'] = $items;
            $row['total_items'] = $total_items;
            $items_stmt->close();
        }
        
        $pending_orders[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'pending_orders' => $pending_orders,
        'count' => count($pending_orders),
        'message' => count($pending_orders) > 0 ? 
                    'Found ' . count($pending_orders) . ' pending order(s)' : 
                    'No pending orders found'
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error in check_existing_pending_orders.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'success' => false,
        'pending_orders' => [],
        'count' => 0,
        'message' => 'Error fetching orders'
    ]);
}
?>