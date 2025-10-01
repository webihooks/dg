<?php
require 'db_connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
error_reporting(0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    
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
        $conn->begin_transaction();
        
        // FIRST: Check which orders exist and their current status
        $check_sql = "SELECT order_id, customer_name, customer_phone, order_type, status 
                      FROM orders 
                      WHERE order_id IN ($placeholders) AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            throw new Exception('Failed to prepare check statement: ' . $conn->error);
        }
        
        $check_types = str_repeat('i', count($order_ids)) . 'i';
        $check_params = array_merge($order_ids, [$user_id]);
        $check_stmt->bind_param($check_types, ...$check_params);
        
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        $all_orders = [];
        $pending_orders = [];
        $already_processed = [];
        $not_found_orders = [];
        
        while ($row = $result->fetch_assoc()) {
            $all_orders[] = $row;
            if ($row['status'] === 'Pending') {
                $pending_orders[] = $row;
            } else {
                $already_processed[] = $row;
            }
        }
        $check_stmt->close();
        
        // Find orders that don't exist in database
        $found_order_ids = array_column($all_orders, 'order_id');
        $not_found_orders = array_diff($order_ids, $found_order_ids);
        
        // If no pending orders found, return informative response
        if (empty($pending_orders)) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'error' => 'No pending orders available',
                'details' => [
                    'total_requested' => count($order_ids),
                    'pending_orders_found' => count($pending_orders),
                    'already_processed' => $already_processed,
                    'not_found_orders' => array_values($not_found_orders),
                    'message' => 'These orders were already processed by another device or do not exist'
                ]
            ]);
            exit;
        }
        
        // Update only the pending orders
        $pending_order_ids = array_column($pending_orders, 'order_id');
        $pending_placeholders = str_repeat('?,', count($pending_order_ids) - 1) . '?';
        
        $update_sql = "UPDATE orders SET status = ?, updated_at = NOW() 
                      WHERE order_id IN ($pending_placeholders) AND user_id = ? AND status = 'Pending'";
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            throw new Exception('Failed to prepare update statement: ' . $conn->error);
        }
        
        $update_types = 's' . str_repeat('i', count($pending_order_ids)) . 'i';
        $update_params = array_merge([$new_status], $pending_order_ids, [$user_id]);
        $update_stmt->bind_param($update_types, ...$update_params);
        
        $update_stmt->execute();
        $affected_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        // Get business info
        $business_info = [];
        $business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
        $business_stmt = $conn->prepare($business_sql);
        
        if ($business_stmt) {
            $business_stmt->bind_param("i", $user_id);
            $business_stmt->execute();
            $business_result = $business_stmt->get_result();
            $business_info = $business_result->fetch_assoc() ?: [];
            $business_stmt->close();
        }
        
        // Get user phone
        $user_phone = '';
        $user_sql = "SELECT phone FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        
        if ($user_stmt) {
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_phone = $user_data['phone'] ?? '';
            $user_stmt->close();
        }
        
        // Get profile URL
        $profile_url = '';
        $profile_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
        $profile_stmt = $conn->prepare($profile_sql);
        
        if ($profile_stmt) {
            $profile_stmt->bind_param("i", $user_id);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            if ($profile_data = $profile_result->fetch_assoc()) {
                $profile_url = $profile_data['profile_url'] ?? '';
            }
            $profile_stmt->close();
        }
        
        // Log order updates
        $log_sql = "INSERT INTO order_updates (order_id, user_id, old_status, new_status, updated_by_session, update_type) 
                    VALUES (?, ?, 'Pending', ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        
        if ($log_stmt) {
            $current_session_id = session_id();
            $update_type = 'accepted';

            foreach ($pending_order_ids as $order_id) {
                $log_stmt->bind_param("iisss", $order_id, $user_id, $new_status, $current_session_id, $update_type);
                $log_stmt->execute();
            }
            $log_stmt->close();
        }
        
        $conn->commit();
        
        // Return success with detailed information
        echo json_encode([
            'success' => true,
            'message' => "Accepted $affected_rows order(s)",
            'affected_rows' => $affected_rows,
            'orders_data' => $pending_orders, // Only the orders that were actually processed
            'business_info' => $business_info,
            'user_phone' => $user_phone,
            'profile_url' => $profile_url,
            'processing_details' => [
                'total_requested' => count($order_ids),
                'successfully_processed' => count($pending_orders),
                'already_processed' => count($already_processed),
                'not_found' => count($not_found_orders)
            ]
        ]);
        
    } catch (Exception $e) {
        if (isset($conn) && $conn) {
            $conn->rollback();
        }
        error_log("Accept orders error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

exit;
?>