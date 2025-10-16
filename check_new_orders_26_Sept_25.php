<?php
require 'db_connection.php';

header('Content-Type: application/json');

// Get parameters
$last_order_id = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;
$current_page = isset($_GET['current_page']) ? (int)$_GET['current_page'] : 1;
$selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d');
$page_load_time = isset($_GET['page_load_time']) ? $_GET['page_load_time'] : time();

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Only get orders created AFTER the page loaded
$sql = "SELECT order_id, customer_name, total_amount, status 
        FROM orders 
        WHERE user_id = ? AND order_id > ? AND DATE(created_at) = ? AND created_at > FROM_UNIXTIME(?)
        ORDER BY order_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iisi", $user_id, $last_order_id, $selected_date, $page_load_time);
$stmt->execute();
$result = $stmt->get_result();

$new_orders = [];
while ($row = $result->fetch_assoc()) {
    $new_orders[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'new_orders' => $new_orders,
    'last_order_id' => $last_order_id,
    'current_page' => $current_page,
    'selected_date' => $selected_date
]);
?>