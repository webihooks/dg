<?php
// check_table_order.php
session_start();
error_reporting(0);

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['exists' => false]);
    exit();
}

// Database connection
require 'db_connection.php';
$user_id = $_SESSION['user_id'];

if (isset($_POST['table_number']) && is_numeric($_POST['table_number'])) {
    $table_number = intval($_POST['table_number']);
    $exclude_order_id = isset($_POST['exclude_order_id']) ? intval($_POST['exclude_order_id']) : 0;
    
    // Check for active order on this table (not paid or cancelled)
    $sql = "SELECT order_id, total_amount, status FROM orders 
            WHERE user_id = ? AND table_number = ? 
            AND status IN ('Confirmed', 'In Progress')";
    
    if ($exclude_order_id > 0) {
        $sql .= " AND order_id != ?";
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if ($exclude_order_id > 0) {
        $stmt->bind_param("iii", $user_id, $table_number, $exclude_order_id);
    } else {
        $stmt->bind_param("ii", $user_id, $table_number);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        echo json_encode([
            'exists' => true,
            'order_id' => $order['order_id'],
            'total_amount' => $order['total_amount'],
            'status' => $order['status']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['exists' => false]);
}
?>