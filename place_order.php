<?php
// place_order.php - Complete Working Version with OneSignal

// List of allowed domains
$allowedDomains = [
    'https://goldcoinrestaurant.in',
    'https://www.goldcoinrestaurant.in',
    'https://swadishtrasoi.in', 
    'https://www.swadishtrasoi.in', 
    'https://tastespecial.in',
    'https://www.tastespecial.in',
    'http://localhost:3000' // For development
];

// Get the origin of the request
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if the request origin is in the allowed list
if (in_array($requestOrigin, $allowedDomains)) {
    header("Access-Control-Allow-Origin: $requestOrigin");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400"); // 24 hours
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

error_log("Received order data: " . print_r($input, true));
header('Content-Type: application/json');
require_once 'config/db_connection.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log exactly what we receive
error_log("Raw input: " . file_get_contents('php://input'));
error_log("Discount amount received: " . (isset($input['discount_amount']) ? $input['discount_amount'] : 'NOT SET'));
error_log("Discount type received: " . (isset($input['discount_type']) ? $input['discount_type'] : 'NOT SET'));

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
    exit();
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
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
        
        // Optional: Update product stock if you have inventory management
        // $updateStockStmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE product_name = ? AND user_id = ?");
        // $updateStockStmt->execute([$item['quantity'], $item['name'], $input['user_id']]);
    }
    
    // 3. Record coupon redemption if coupon was used
    if (!empty($input['coupon_data']) && !empty($input['customer_phone'])) {
        try {
            // Get coupon details
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
                // Insert redemption record
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
                
                // Update coupon usage count
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
            // Don't fail the whole order because of redemption error
        }
    }

    // Commit the transaction
    $conn->commit();
    
    // Log successful order
    error_log("Order placed successfully. Order ID: " . $orderId . ", Total: " . $total);
    
    // =========================================================================
    // ONE SIGNAL NOTIFICATION - WORKING VERSION
    // =========================================================================
    if ($orderId) {
        // Use fast non-blocking HTTP request instead of shell_exec
        $notificationUrl = 'send_onesignal_notification.php';
        $notificationData = [
            'user_id' => $input['user_id'],
            'order_id' => $orderId,
            'customer_name' => $input['customer_name'],
            'total_amount' => $total,
            'order_type' => $input['order_type']
        ];
        
        // Use fast async request that doesn't wait for response
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($notificationData),
                'timeout' => 1, // 1 second timeout - don't wait
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        // Trigger without waiting for response
        @file_get_contents($notificationUrl, false, $context);
        
        error_log("📱 Notification triggered for order: $orderId");
    }
    // =========================================================================
    
    // Return success with order ID
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Order placed successfully',
        'order_details' => [
            'order_id' => $orderId,
            'customer_name' => $input['customer_name'],
            'total_amount' => $total,
            'order_type' => $input['order_type'],
            'status' => 'pending'
        ]
    ]);

    exit();
    
} catch (PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("Order placement error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to place order. Please try again.',
        'error' => $e->getMessage()
    ]);
    exit();
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("General order placement error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.',
        'error' => $e->getMessage()
    ]);
    exit();
}