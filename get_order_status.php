<?php
// get_order_status.php
require_once 'config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit();
}

$order_id = $_GET['order_id'];

try {
    $order_sql = "SELECT status FROM orders WHERE order_id = ?";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo json_encode([
            'success' => true,
            'status' => $order['status'],
            'is_cancelled' => $order['status'] === 'cancelled'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>