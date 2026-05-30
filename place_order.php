<?php
// place_order.php - With Google Customer Update & Dynamic Loyalty Points

session_start(); // Start session for Google login

// List of allowed domains
$allowedDomains = [
    'https://goldcoinrestaurant.in',
    'www.goldcoinrestaurant.in',
    'goldcoinrestaurant.in',
    'https://swadishtrasoi.in', 
    'www.swadishtrasoi.in',
    'swadishtrasoi.in',
    'https://tastespecial.in',
    'www.tastespecial.in',
    'tastespecial.in',
    'http://localhost:3000'
];

// Get the origin of the request
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Parse the origin to get the domain
$parsedUrl = parse_url($requestOrigin);
$originDomain = $parsedUrl['host'] ?? '';

// Check if the request origin is in the allowed list
$isAllowed = false;
foreach ($allowedDomains as $domain) {
    // Remove https:// or http:// from comparison
    $cleanDomain = preg_replace('#^https?://#', '', $domain);
    if ($originDomain === $cleanDomain || strpos($requestOrigin, $domain) !== false) {
        $isAllowed = true;
        break;
    }
}

if ($isAllowed) {
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

// Include Google authentication functions (for updateCustomerDetails)
require_once 'includes/google_login_authentication.php';

// Include loyalty helper for dynamic settings
require_once 'includes/loyalty_helper.php';

// Get JSON input
$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);

// Clear any buffered output
ob_clean();

// Debug logging - log the entire input to see what's coming
error_log("=== ORDER PLACEMENT DEBUG ===");
error_log("Raw input received: " . $jsonInput);
error_log("Decoded input: " . print_r($input, true));

if (!$input || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON input: ' . json_last_error_msg()
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

    // Calculate order totals
    $subtotal = 0;
    foreach ($input['items'] as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    
    // Get discount data from request
    $discountAmount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0;
    $discountType = isset($input['discount_type']) ? $input['discount_type'] : '';
    
    // Get loyalty points redemption data
    $loyaltyPointsRedeemed = isset($input['loyalty_points_redeemed']) ? intval($input['loyalty_points_redeemed']) : 0;
    $loyaltyPointsValue = isset($input['loyalty_points_value']) ? floatval($input['loyalty_points_value']) : 0;
    
    // Calculate amount after discount
    $amountAfterDiscount = $subtotal - $discountAmount;
    if ($amountAfterDiscount < 0) {
        $amountAfterDiscount = 0;
    }
    
    // Apply loyalty discount
    $amountAfterLoyalty = $amountAfterDiscount - $loyaltyPointsValue;
    if ($amountAfterLoyalty < 0) {
        $amountAfterLoyalty = 0;
    }
    
    // Calculate GST on amount after loyalty discount
    $gstPercent = isset($input['gst_percent']) ? floatval($input['gst_percent']) : 0;
    $gstAmount = ($amountAfterLoyalty * $gstPercent) / 100;
    
    // Calculate delivery charges
    $deliveryCharge = 0;
    if (isset($input['order_type']) && $input['order_type'] === 'delivery') {
        $freeDeliveryMin = isset($input['free_delivery_min']) ? floatval($input['free_delivery_min']) : 0;
        $deliveryChargeAmount = isset($input['delivery_charge']) ? floatval($input['delivery_charge']) : 0;
        
        if ($freeDeliveryMin == 0 || $amountAfterLoyalty < $freeDeliveryMin) {
            $deliveryCharge = $deliveryChargeAmount;
        }
    }
    
    // Calculate final total
    $total = $amountAfterLoyalty + $gstAmount + $deliveryCharge;

    // Extract address components
    $building = null;
    $floor = null;
    $flatUnit = null;
    $landmark = null;
    
    // Check if address_components exists and is an array
    if (isset($input['address_components']) && is_array($input['address_components'])) {
        $building = isset($input['address_components']['building']) ? $input['address_components']['building'] : null;
        $floor = isset($input['address_components']['floor']) ? $input['address_components']['floor'] : null;
        $flatUnit = isset($input['address_components']['flat_unit']) ? $input['address_components']['flat_unit'] : null;
        $landmark = isset($input['address_components']['landmark']) ? $input['address_components']['landmark'] : null;
        
        // Log the extracted values
        error_log("Extracted address components:");
        error_log("building: " . ($building ?? 'null'));
        error_log("floor: " . ($floor ?? 'null'));
        error_log("flat_unit: " . ($flatUnit ?? 'null'));
        error_log("landmark: " . ($landmark ?? 'null'));
    } else {
        error_log("No address_components found in input");
    }

    // 1. Insert the order with new fields including loyalty points
    $orderSql = "INSERT INTO orders (
        user_id, 
        order_type, 
        customer_name, 
        customer_phone, 
        delivery_address,
        building,
        floor,
        flat_unit,
        landmark,
        table_number, 
        order_notes, 
        subtotal, 
        discount_amount, 
        discount_type,
        loyalty_points_redeemed,
        loyalty_points_value,
        gst_amount, 
        delivery_charge, 
        total_amount,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
    
    $orderStmt = $conn->prepare($orderSql);
    
    $params = [
        $input['user_id'],
        $input['order_type'],
        $input['customer_name'],
        $input['customer_phone'],
        isset($input['delivery_address']) ? $input['delivery_address'] : null,
        $building,
        $floor,
        $flatUnit,
        $landmark,
        isset($input['table_number']) ? $input['table_number'] : null,
        isset($input['order_notes']) ? $input['order_notes'] : null,
        $subtotal,
        $discountAmount,
        $discountType,
        $loyaltyPointsRedeemed,
        $loyaltyPointsValue,
        $gstAmount,
        $deliveryCharge,
        $total
    ];
    
    // Log the parameters being sent to database
    error_log("SQL Parameters: " . print_r($params, true));
    
    $orderStmt->execute($params);
    
    $orderId = $conn->lastInsertId();
    
    error_log("Order inserted with ID: " . $orderId);
    
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
    if (!empty($input['coupon_data']) && !empty($input['customer_phone']) && isset($input['coupon_data']['code'])) {
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
                
                error_log("Coupon redemption recorded for order: " . $orderId);
            }
        } catch (PDOException $e) {
            error_log("Coupon redemption error: " . $e->getMessage());
        }
    }

    // Commit the transaction
    $conn->commit();
    
    error_log("Order placed successfully. Order ID: " . $orderId . ", Total: " . $total);
    
    // ==================== UPDATE CUSTOMER GOOGLE ACCOUNT & LOYALTY POINTS ====================
    // Check if customer is logged in via Google session
    if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true && 
        isset($_SESSION['customer_restaurant_id']) && $_SESSION['customer_restaurant_id'] == $input['user_id'] &&
        isset($_SESSION['customer_id'])) {
        
        $customer_id = $_SESSION['customer_id'];
        $customer_phone = $input['customer_phone'];
        
        // Prepare address data from order
        $address_data = [
            'building' => $building ?? '',
            'flat_unit' => $flatUnit ?? '',
            'landmark' => $landmark ?? ''
        ];
        
        // Also include floor if needed (optional)
        if (!empty($floor)) {
            $address_data['floor'] = $floor;
        }
        
        error_log("Updating customer_google_accounts: customer_id=$customer_id, phone=$customer_phone, address=" . json_encode($address_data));
        
        // Call the update function (already included via google_login_authentication.php)
        $updateResult = updateCustomerDetails($conn, $input['user_id'], $customer_id, $customer_phone, $address_data);
        
        if ($updateResult) {
            error_log("✅ Customer Google account updated successfully for customer_id: $customer_id");
        } else {
            error_log("❌ Failed to update customer Google account for customer_id: $customer_id");
        }
        
        // ==================== LOYALTY POINTS PROCESSING ====================
        // Get loyalty settings for this restaurant
        $loyalty_settings = getLoyaltySettings($conn, $input['user_id']);
        $earn_points_per_currency = $loyalty_settings['earn_points_per_currency'];
        $redemption_points = $loyalty_settings['redemption_points'];
        $redemption_amount = $loyalty_settings['redemption_currency_amount'];
        
        // Start a new transaction for loyalty points updates
        try {
            $conn->beginTransaction();
            
            // 1. Deduct redeemed points if any
            if ($loyaltyPointsRedeemed > 0) {
                $deductStmt = $conn->prepare("
                    UPDATE customer_google_accounts 
                    SET loyalty_points = loyalty_points - ?,
                        updated_at = NOW()
                    WHERE id = ? AND restaurant_user_id = ? AND loyalty_points >= ?
                ");
                $deductStmt->execute([$loyaltyPointsRedeemed, $customer_id, $input['user_id'], $loyaltyPointsRedeemed]);
                
                $deductRowsAffected = $deductStmt->rowCount();
                if ($deductRowsAffected > 0) {
                    error_log("✅ Loyalty points deducted: $loyaltyPointsRedeemed points from customer_id: $customer_id");
                    
                    // Record points deduction in loyalty_points_history table (if exists)
                    try {
                        $historyStmt = $conn->prepare("
                            INSERT INTO loyalty_points_history 
                            (customer_id, restaurant_user_id, points, transaction_type, order_id, description, created_at)
                            VALUES (?, ?, ?, 'redeemed', ?, ?, NOW())
                        ");
                        $historyStmt->execute([
                            $customer_id,
                            $input['user_id'],
                            -$loyaltyPointsRedeemed,
                            $orderId,
                            "Redeemed $loyaltyPointsRedeemed points for " . number_format($loyaltyPointsValue, 2)
                        ]);
                        error_log("✅ Loyalty points deduction recorded in history");
                    } catch (PDOException $e) {
                        error_log("Note: Could not record points history (table may not exist): " . $e->getMessage());
                    }
                } else {
                    error_log("⚠️ Failed to deduct loyalty points. Customer may not have sufficient points.");
                }
            }
            
            // 2. Calculate and add earned points using dynamic earn rate
            // Points earned = total amount * points per currency
            $earnedPoints = floor($total * $earn_points_per_currency);
            
            if ($earnedPoints > 0) {
                $earnStmt = $conn->prepare("
                    UPDATE customer_google_accounts 
                    SET loyalty_points = loyalty_points + ?,
                        updated_at = NOW()
                    WHERE id = ? AND restaurant_user_id = ?
                ");
                $earnStmt->execute([$earnedPoints, $customer_id, $input['user_id']]);
                
                error_log("✅ Loyalty points earned: $earnedPoints points (based on total $total, earn rate: $earn_points_per_currency pts per currency) for customer_id: $customer_id");
                
                // Record points earning in loyalty_points_history table (if exists)
                try {
                    $historyStmt = $conn->prepare("
                        INSERT INTO loyalty_points_history 
                        (customer_id, restaurant_user_id, points, transaction_type, order_id, description, created_at)
                        VALUES (?, ?, ?, 'earned', ?, ?, NOW())
                    ");
                    $historyStmt->execute([
                        $customer_id,
                        $input['user_id'],
                        $earnedPoints,
                        $orderId,
                        "Earned $earnedPoints points from order #$orderId (Total: " . number_format($total, 2) . ", Rate: $earn_points_per_currency pts per unit)"
                    ]);
                    error_log("✅ Loyalty points earning recorded in history");
                } catch (PDOException $e) {
                    error_log("Note: Could not record points history (table may not exist): " . $e->getMessage());
                }
            }
            
            // 3. Store earned points in orders table for record
            $updateOrderStmt = $conn->prepare("UPDATE orders SET loyalty_points_earned = ? WHERE order_id = ?");
            $updateOrderStmt->execute([$earnedPoints, $orderId]);
            error_log("✅ Loyalty points earned ($earnedPoints) saved in orders table for order_id: $orderId");
            
            // Commit loyalty points transaction
            $conn->commit();
            error_log("✅ Loyalty points transaction committed successfully");
            
            // Update session with new points
            // Fetch updated points to store in session
            $pointsStmt = $conn->prepare("SELECT loyalty_points FROM customer_google_accounts WHERE id = ?");
            $pointsStmt->execute([$customer_id]);
            $newPoints = $pointsStmt->fetch(PDO::FETCH_ASSOC);
            if ($newPoints) {
                $_SESSION['loyalty_points'] = $newPoints['loyalty_points'];
                error_log("✅ Session loyalty points updated to: " . $newPoints['loyalty_points']);
            }
            
        } catch (PDOException $e) {
            // Rollback loyalty points transaction on error
            if (isset($conn)) {
                $conn->rollBack();
            }
            error_log("❌ Loyalty points processing error: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            // Note: We don't throw exception here as order is already placed successfully
            // Just log the error for debugging
        }
    } else {
        error_log("Customer not logged in via Google - skipping customer_google_accounts update and loyalty points");
        error_log("Session data: " . print_r($_SESSION, true));
    }
    // ==================== END LOYALTY POINTS PROCESSING ====================
    
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
    
    // Return success with loyalty points info
    $responseData = [
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
    ];
    
    // Add loyalty points info to response if customer is logged in
    if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
        $responseData['loyalty_points'] = [
            'redeemed' => $loyaltyPointsRedeemed,
            'redeemed_value' => $loyaltyPointsValue,
            'earned' => isset($earnedPoints) ? $earnedPoints : 0,
            'current_points' => isset($newPoints) ? $newPoints['loyalty_points'] : 0,
            'earn_rate' => isset($earn_points_per_currency) ? $earn_points_per_currency : 1,
            'redemption_points' => isset($redemption_points) ? $redemption_points : 1000,
            'redemption_amount' => isset($redemption_amount) ? $redemption_amount : 10
        ];
    }
    
    echo json_encode($responseData);

} catch (PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("Order placement error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: Failed to place order. Please try again.'
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    error_log("General order placement error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.'
    ]);
}

// Ensure no extra output
ob_end_flush();
exit();
?>