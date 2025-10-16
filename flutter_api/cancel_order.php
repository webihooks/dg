<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user_id'])) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? '';
    
    try {
        // Verify order belongs to user and can be cancelled
        $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$order_id]);
        $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order || $order['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit();
        }
        
        if (!in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])) {
            echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
            exit();
        }
        
        // Cancel order
        $update_sql = "UPDATE orders SET status = 'Cancelled', updated_at = NOW() WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $success = $update_stmt->execute([$order_id]);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Order cancelled successfully' : 'Failed to cancel order'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>