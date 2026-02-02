<?php
// check_existing_order.php
// This file checks for existing orders (Pending/Confirmed) for a specific table

require 'db_connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated', 'exists' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$table_number = isset($_POST['table_number']) ? trim($_POST['table_number']) : '';

try {
    if (empty($table_number)) {
        echo json_encode(['exists' => false, 'order_id' => null, 'message' => 'No table number provided']);
        exit;
    }

    // Check for existing order for this table (Pending or Confirmed)
    $sql = "SELECT 
                order_id, 
                status, 
                customer_name,
                customer_phone,
                order_notes,
                subtotal,
                total_amount,
                created_at
            FROM orders 
            WHERE user_id = ? 
            AND table_number = ? 
            AND order_type = 'dining' 
            AND status IN ('Pending', 'Confirmed')
            ORDER BY created_at DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("is", $user_id, $table_number);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->store_result();  // CRITICAL FIX: Store result before binding
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($order_id, $status, $customer_name, $customer_phone, $order_notes, $subtotal, $total_amount, $created_at);
        $stmt->fetch();
        
        // Format the response
        $response = [
            'exists' => true,
            'order_id' => $order_id,
            'status' => $status,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'order_notes' => $order_notes,
            'subtotal' => $subtotal,
            'total_amount' => $total_amount,
            'created_at' => $created_at,
            'created_at_formatted' => date('d-m-Y h:i A', strtotime($created_at)),
            'message' => "Existing $status order #$order_id found for Table $table_number"
        ];
        
        // IMPORTANT: Free the result before running another query
        $stmt->free_result();
        
        // If needed, also fetch order items
        $items_sql = "SELECT product_name, price, quantity FROM order_items WHERE order_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        
        if ($items_stmt) {
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            
            $response['items'] = $items;
            $response['item_count'] = count($items);
            
            // IMPORTANT: Close the items statement
            $items_stmt->close();
        }
        
        // Close the main statement
        $stmt->close();
        
        echo json_encode($response);
    } else {
        // No order found, still need to close the statement
        $stmt->free_result();
        $stmt->close();
        
        echo json_encode([
            'exists' => false, 
            'order_id' => null,
            'message' => 'No existing order found for this table'
        ]);
    }
    
    $conn->close();

} catch (Exception $e) {
    error_log("Error in check_existing_order.php: " . $e->getMessage());
    
    // Clean up any open statements
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($items_stmt) && $items_stmt) {
        $items_stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
    
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'exists' => false,
        'message' => 'Error checking existing order'
    ]);
}
?>