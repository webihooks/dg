<?php
// dashboard_stats.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Fetch dashboard stats
$stats = [
    'today_orders' => 0,
    'today_revenue' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'total_customers' => 0,
    'subtotal' => 0,
    'total_tax' => 0,
    'total_discounts' => 0,
    'total_delivery' => 0
];

// Today's orders count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['today_orders'] = (int)$stmt->fetch()['count'];

// Today's revenue
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['today_revenue'] = (float)($stmt->fetch()['total'] ?? 0);

// Pending orders
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'pending'");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['pending_orders'] = (int)$stmt->fetch()['count'];

// Completed orders
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['completed_orders'] = (int)$stmt->fetch()['count'];

// Total customers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND id IN (SELECT DISTINCT user_id FROM orders WHERE user_id = ?)");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_customers'] = (int)$stmt->fetch()['count'];

// Financial data
$stmt = $conn->prepare("SELECT 
    SUM(subtotal) as subtotal,
    SUM(gst_amount) as total_tax,
    SUM(discount_amount) as total_discounts,
    SUM(delivery_charge) as total_delivery
    FROM orders 
    WHERE user_id = ? 
    AND DATE(created_at) = CURDATE()");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$financialData = $stmt->fetch();
$stats['subtotal'] = (float)($financialData['subtotal'] ?? 0);
$stats['total_tax'] = (float)($financialData['total_tax'] ?? 0);
$stats['total_discounts'] = (float)($financialData['total_discounts'] ?? 0);
$stats['total_delivery'] = (float)($financialData['total_delivery'] ?? 0);

echo json_encode(['success' => true, 'data' => $stats]);
?>