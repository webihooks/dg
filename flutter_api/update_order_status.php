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
    $new_status = $_POST['new_status'] ?? '';
    
    $allowed_statuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    try {
        // Verify order belongs to user
        $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$order_id]);
        $order_user = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order_user || $order_user['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit();
        }
        
        // Update status
        $update_sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $success = $update_stmt->execute([$new_status, $order_id]);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Order status updated successfully' : 'Failed to update status'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>