<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Include session helper for Flutter authentication
require_once 'session_helper.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in (works for both web and Flutter)
if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get parameters - simplified without pagination for now
$from_date = $_GET['from_date'] ?? date('Y-m-d');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) $to_date = date('Y-m-d');
if ($to_date < $from_date) $to_date = $from_date;

try {
    // Simplified query without pagination
    $orders_sql = "SELECT 
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
        o.updated_at,
        o.order_notes,
        COUNT(oi.item_id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 100"; // Simple limit instead of pagination

    $orders_stmt = $conn->prepare($orders_sql);
    $orders_stmt->execute([$user_id, $from_date, $to_date]);
    $result = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    $orders = [];

    foreach ($result as $order) {
        // Fetch order items
        $items_sql = "SELECT product_name, price, quantity FROM order_items WHERE order_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->execute([$order['order_id']]);
        $order['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        $items_stmt->closeCursor();
        
        // Calculate timer information
        $order_created = strtotime($order['created_at']);
        $current_time = time();
        $time_elapsed = $current_time - $order_created;
        $time_limit = 30 * 60; // 30 minutes
        $time_remaining = $time_limit - $time_elapsed;
        
        $order['timer_remaining'] = max(0, $time_remaining);
        $order['is_delayed'] = $time_elapsed > $time_limit;
        $order['is_completed_on_time'] = ($order['status'] === 'Completed' && !$order['is_delayed']);
        
        $orders[] = $order;
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total' => count($orders),
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>