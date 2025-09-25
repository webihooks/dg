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

// Get parameters with validation
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
        $sql = "SELECT order_id, customer_name, total_amount, status, created_at 
                FROM orders 
                WHERE user_id = ? AND DATE(created_at) = ? 
                ORDER BY order_id DESC 
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $current_date);
    } else {
        // Same day - normal polling (orders after last_order_id AND after page load time)
        $sql = "SELECT order_id, customer_name, total_amount, status, created_at 
                FROM orders 
                WHERE user_id = ? AND order_id > ? AND created_at > FROM_UNIXTIME(?) 
                ORDER BY order_id DESC 
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $user_id, $last_order_id, $page_load_time);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $new_orders = [];
    while ($row = $result->fetch_assoc()) {
        $new_orders[] = $row;
    }
    
    $stmt->close();
    
    // Return additional debug info for troubleshooting
    echo json_encode([
        'success' => true,
        'new_orders' => $new_orders,
        'debug' => [
            'last_order_id_received' => $last_order_id,
            'page_load_time' => $page_load_time,
            'page_load_date' => $page_load_date,
            'current_date' => $current_date,
            'current_time' => $current_time,
            'orders_found' => count($new_orders),
            'query_type' => ($page_load_date !== $current_date) ? 'date_based' : 'normal'
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'last_order_id' => $last_order_id,
            'page_load_time' => $page_load_time
        ]
    ]);
}

$conn->close();
?>