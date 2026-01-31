<?php
// check_existing_order.php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['exists' => false]));
}

$user_id = $_SESSION['user_id'];
$table_number = $_POST['table_number'] ?? '';

$response = ['exists' => false];

if (!empty($table_number)) {
    $sql = "SELECT order_id FROM orders 
            WHERE user_id = ? 
            AND table_number = ? 
            AND order_type = 'dining' 
            AND status IN ('Pending', 'Confirmed', 'Preparing', 'Ready')
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $table_number);
    $stmt->execute();
    $stmt->bind_result($order_id);
    
    if ($stmt->fetch()) {
        $response = [
            'exists' => true,
            'order_id' => $order_id
        ];
    }
    
    $stmt->close();
}

echo json_encode($response);
$conn->close();
?>