<?php
// place_order.php - Fixed Version

// List of allowed domains
$allowedDomains = [
    'https://goldcoinrestaurant.in',
    'https://www.goldcoinrestaurant.in',
    'https://swadishtrasoi.in', 
    'https://www.swadishtrasoi.in', 
    'https://tastespecial.in',
    'https://www.tastespecial.in',
    'http://localhost:3000'
];

// Get the origin of the request
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if the request origin is in the allowed list
if (in_array($requestOrigin, $allowedDomains)) {
    header("Access-Control-Allow-Origin: $requestOrigin");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Set content type FIRST and suppress unexpected output
header('Content-Type: application/json');
ob_start(); // Start output buffering to catch any unexpected output

// Now include your database connection
require_once 'config/db_connection.php';

// Get JSON input
$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);

// Clear any buffered output
ob_clean();

// Debug logging (but don't output to response)
error_log("Raw input received: " . $jsonInput);

if (!$input || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input: ' . json_last_error_msg(),
        'received_data' => $jsonInput
    ]);
    exit();
}

// Validate required fields
$requiredFields = ['user_id', 'order_type', 'customer_name', 'customer_phone', 'items'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit();
    }
}

try {
    $conn->beginTransaction();

    // 1. Insert the order
    $orderSql = "INSERT INTO orders (
        user_id, 
        order_type, 
        customer_name, 
        customer_phone, 
        delivery_address, 
        table_number, 
        order_notes, 
        subtotal, 
        discount_amount, 
        discount_type,
        gst_amount, 
        delivery_charge, 
        total_amount,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
    
    // Calculate order totals
    $subtotal = array_reduce($input['items'], function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
    
    // Get discount data from request
    $discountAmount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0;
    $discountType = isset($input['discount_type']) ? $input['discount_type'] : '';
    
    $amountAfterDiscount = $subtotal - $discountAmount;
    if ($amountAfterDiscount < 0) {
        $amountAfterDiscount = 0;
    }
    
    // Calculate GST
    $gstPercent = isset($input['gst_percent']) ? floatval($input['gst_percent']) : 0;
    $gstAmount = ($amountAfterDiscount * $gstPercent) / 100;
    
    // Calculate delivery charges
    $deliveryCharge = 0;
    if (isset($input['order_type']) && $input['order_type'] === 'delivery') {
        $freeDeliveryMin = isset($input['free_delivery_min']) ? floatval($input['free_delivery_min']) : 0;
        $deliveryChargeAmount = isset($input['delivery_charge']) ? floatval($input['delivery_charge']) : 0;
        
        if ($freeDeliveryMin == 0 || $amountAfterDiscount < $freeDeliveryMin) {
            $deliveryCharge = $deliveryChargeAmount;
        }
    }
    
    $total = $amountAfterDiscount + $gstAmount + $deliveryCharge;
    
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->execute([
        $input['user_id'],
        $input['order_type'],
        $input['customer_name'],
        $input['customer_phone'],
        isset($input['delivery_address']) ? $input['delivery_address'] : null,
        isset($input['table_number']) ? $input['table_number'] : null,
        isset($input['order_notes']) ? $input['order_notes'] : null,
        $subtotal,
        $discountAmount,
        $discountType,
        $gstAmount,
        $deliveryCharge,
        $total
    ]);
    
    $orderId = $conn->lastInsertId();
    
    // 2. Insert order items
    $itemSql = "INSERT INTO order_items (order_id, user_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)";
    $itemStmt = $conn->prepare($itemSql);

    foreach ($input['items'] as $item) {
        $itemStmt->execute([
            $orderId,
            $input['user_id'],
            $item['name'],
            $item['price'],
            $item['quantity']
        ]);
    }
    
    // 3. Record coupon redemption if coupon was used
    if (!empty($input['coupon_data']) && !empty($input['customer_phone'])) {
        try {
            $couponStmt = $conn->prepare("
                SELECT id, usage_limit, times_used 
                FROM coupons 
                WHERE user_id = ? AND coupon_code = ? AND (usage_limit IS NULL OR times_used < usage_limit)
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ");
            $couponStmt->execute([
                $input['user_id'],
                $input['coupon_data']['code']
            ]);
            $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

            if ($coupon) {
                $redemptionStmt = $conn->prepare("
                    INSERT INTO coupon_redemptions (
                        coupon_id,
                        user_id,
                        customer_phone,
                        order_id,
                        discount_amount,
                        redeemed_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $redemptionStmt->execute([
                    $coupon['id'],
                    $input['user_id'],
                    $input['customer_phone'],
                    $orderId,
                    $discountAmount
                ]);
                
                $updateCouponStmt = $conn->prepare("
                    UPDATE coupons 
                    SET times_used = times_used + 1 
                    WHERE id = ? AND user_id = ?
                ");
                $updateCouponStmt->execute([
                    $coupon['id'],
                    $input['user_id']
                ]);
            }
        } catch (PDOException $e) {
            error_log("Coupon redemption error: " . $e->getMessage());
        }
    }

    // Commit the transaction
    $conn->commit();
    
    error_log("Order placed successfully. Order ID: " . $orderId . ", Total: " . $total);
    
    // Send notification (non-blocking)
    if ($orderId) {
        $notificationUrl = 'https://deegeecard.com/send_onesignal_notification.php';
        $notificationData = [
            'user_id' => $input['user_id'],
            'order_id' => $orderId,
            'customer_name' => $input['customer_name'],
            'total_amount' => $total,
            'order_type' => $input['order_type']
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($notificationData),
                'timeout' => 1,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        @file_get_contents($notificationUrl, false, $context);
        error_log("📱 Notification triggered for order: $orderId");
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Order placed successfully',
        'order_details' => [
            'order_id' => $orderId,
            'customer_name' => $input['customer_name'],
            'total_amount' => $total,
            'order_type' => $input['order_type'],
            'status' => 'Pending'
        ]
    ]);

} catch (PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("Order placement error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: Failed to place order. Please try again.',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("General order placement error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.',
        'error' => $e->getMessage()
    ]);
}

// Ensure no extra output
ob_end_flush();
exit();