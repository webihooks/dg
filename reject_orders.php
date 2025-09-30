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
    $new_status = $input['new_status'] ?? 'Cancelled';
    $rejection_reason = $input['rejection_reason'] ?? 'Order cancelled by restaurant';
    
    if (empty($order_ids)) {
        echo json_encode(['error' => 'No order IDs provided']);
        exit;
    }
    
    // Convert order IDs to integers and create placeholders
    $order_ids = array_map('intval', $order_ids);
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    try {
        // First, get order details before updating (for WhatsApp notifications)
        $select_sql = "SELECT order_id, customer_name, customer_phone, order_type, total_amount 
                      FROM orders 
                      WHERE order_id IN ($placeholders) AND user_id = ? AND status = 'Pending'";
        $select_stmt = $conn->prepare($select_sql);
        
        $select_types = str_repeat('i', count($order_ids)) . 'i';
        $select_params = array_merge($order_ids, [$user_id]);
        $select_stmt->bind_param($select_types, ...$select_params);
        
        $select_stmt->execute();
        $result = $select_stmt->get_result();
        $orders_data = [];
        
        while ($row = $result->fetch_assoc()) {
            $orders_data[] = [
                'order_id' => $row['order_id'],
                'customer_name' => $row['customer_name'],
                'customer_phone' => $row['customer_phone'],
                'order_type' => $row['order_type'],
                'total_amount' => $row['total_amount']
            ];
        }
        $select_stmt->close();
        
        // Update orders status
        $update_sql = "UPDATE orders SET status = ? WHERE order_id IN ($placeholders) AND user_id = ? AND status = 'Pending'";
        $update_stmt = $conn->prepare($update_sql);
        
        $update_types = 's' . str_repeat('i', count($order_ids)) . 'i';
        $update_params = array_merge([$new_status], $order_ids, [$user_id]);
        $update_stmt->bind_param($update_types, ...$update_params);
        
        $update_stmt->execute();
        $affected_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        // Get business info for WhatsApp messages
        $business_info = [];
        $business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
        $business_stmt = $conn->prepare($business_sql);
        $business_stmt->bind_param("i", $user_id);
        $business_stmt->execute();
        $business_result = $business_stmt->get_result();
        $business_info = $business_result->fetch_assoc() ?: [];
        $business_stmt->close();
        
        // Get user phone for WhatsApp
        $user_phone = '';
        $user_sql = "SELECT phone FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_phone = $user_data['phone'] ?? '';
        $user_stmt->close();
        
        // Get profile URL from profile_url_details table
        $profile_url = '';
        $profile_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
        $profile_stmt = $conn->prepare($profile_sql);
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $profile_result = $profile_stmt->get_result();
        
        if ($profile_result && $profile_data = $profile_result->fetch_assoc()) {
            $profile_url = $profile_data['profile_url'] ?? '';
        }
        $profile_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Rejected $affected_rows order(s)",
            'affected_rows' => $affected_rows,
            'orders_data' => $orders_data, // This is needed for WhatsApp notifications
            'business_info' => $business_info, // This is needed for WhatsApp notifications
            'user_phone' => $user_phone, // This is needed for WhatsApp notifications
            'profile_url' => $profile_url, // This is needed for WhatsApp notifications
            'rejection_reason' => $rejection_reason // This is needed for WhatsApp notifications
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

$conn->close();
?>