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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_ids = $input['order_ids'] ?? [];
    $new_status = $input['new_status'] ?? 'Confirmed';
    
    if (empty($order_ids)) {
        echo json_encode(['error' => 'No order IDs provided']);
        exit;
    }
    
    // Convert order IDs to integers and create placeholders
    $order_ids = array_map('intval', $order_ids);
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    try {
        // Update orders status
        $sql = "UPDATE orders SET status = ? WHERE order_id IN ($placeholders) AND user_id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        
        // Bind parameters: status + order_ids + user_id
        $types = 's' . str_repeat('i', count($order_ids)) . 'i';
        $params = array_merge([$new_status], $order_ids, [$user_id]);
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Updated $affected_rows order(s) to $new_status",
            'affected_rows' => $affected_rows,
            'redirect_url' => 'orders.php' // Add redirect URL for client-side
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

$conn->close();
?>