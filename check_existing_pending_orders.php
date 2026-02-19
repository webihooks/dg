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

try {
    // Get all pending orders for this user from today
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
            AND o.status = 'Pending'
            ORDER BY o.order_id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $pending_orders = [];
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
        
        $pending_orders[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'pending_orders' => $pending_orders,
        'count' => count($pending_orders),
        'message' => count($pending_orders) > 0 ? 'Found pending orders' : 'No pending orders'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'success' => false
    ]);
}
?>