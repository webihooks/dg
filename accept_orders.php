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
        // Begin transaction for data consistency
        $conn->begin_transaction();
        
        // First, get order details for WhatsApp notifications
        $select_sql = "SELECT order_id, customer_name, customer_phone, order_type 
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
                'order_type' => $row['order_type']
            ];
        }
        $select_stmt->close();
        
        if (empty($orders_data)) {
            throw new Exception('No pending orders found with the provided IDs');
        }
        
        // Update orders status
        $update_sql = "UPDATE orders SET status = ?, updated_at = NOW() 
                      WHERE order_id IN ($placeholders) AND user_id = ? AND status = 'Pending'";
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
        
        if ($business_stmt->execute()) {
            $business_result = $business_stmt->get_result();
            $business_info = $business_result->fetch_assoc() ?: [];
        }
        $business_stmt->close();
        
        // Get user phone for WhatsApp
        $user_phone = '';
        $user_sql = "SELECT phone FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        
        if ($user_stmt->execute()) {
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_phone = $user_data['phone'] ?? '';
        }
        $user_stmt->close();
        
        // Get profile URL from profile_url_details table
        $profile_url = '';
        $profile_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
        $profile_stmt = $conn->prepare($profile_sql);
        $profile_stmt->bind_param("i", $user_id);
        
        if ($profile_stmt->execute()) {
            $profile_result = $profile_stmt->get_result();
            if ($profile_data = $profile_result->fetch_assoc()) {
                $profile_url = $profile_data['profile_url'] ?? '';
            }
        }
        $profile_stmt->close();
        
        // Generate fallback profile URL if not found
        if (empty($profile_url)) {
            if (!empty($business_info['business_name'])) {
                $profile_url = strtolower(preg_replace('/[^a-z0-9]/', '', $business_info['business_name']));
            } else if (!empty($user_phone)) {
                $profile_url = 'user' . $user_id;
            } else {
                $profile_url = 'restaurant';
            }
        }

        // Log order update for real-time notifications
        $log_sql = "INSERT INTO order_updates (order_id, user_id, old_status, new_status, updated_by_session) 
                    VALUES (?, ?, 'Pending', ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $current_session_id = session_id();

        foreach ($order_ids as $order_id) {
            $log_stmt->bind_param("iiss", $order_id, $user_id, $new_status, $current_session_id);
            $log_stmt->execute();
        }
        $log_stmt->close();

        
        
        // Commit transaction
        $conn->commit();


        
        echo json_encode([
            'success' => true,
            'message' => "Updated $affected_rows order(s) to $new_status",
            'affected_rows' => $affected_rows,
            'orders_data' => $orders_data,
            'business_info' => $business_info,
            'user_phone' => $user_phone,
            'profile_url' => $profile_url,
            'redirect_url' => 'orders.php'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Accept orders error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

$conn->close();
?>