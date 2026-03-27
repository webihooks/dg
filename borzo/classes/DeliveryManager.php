<?php
// borzo/classes/DeliveryManager.php

require_once __DIR__ . '/BorzoAPI.php';

class DeliveryManager {
    private $borzoAPI;
    private $config;
    private $logger;
    
    public function __construct($config, $logger = null) {
        $this->config = $config;
        
        // Create default logger if none provided
        if ($logger === null) {
            $this->logger = new class {
                public function log($message, $data = []) {
                    $logFile = __DIR__ . '/../logs/borzo-api.log';
                    $logDir = dirname($logFile);
                    
                    if (!is_dir($logDir)) {
                        mkdir($logDir, 0755, true);
                    }
                    
                    $logEntry = date('Y-m-d H:i:s') . " - $message - " . json_encode($data) . "\n";
                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                }
            };
        } else {
            $this->logger = $logger;
        }
        
        $this->borzoAPI = new BorzoAPI($config, $this->logger);
        $this->logger->log('DeliveryManager initialized', ['environment' => $config['environment'] ?? 'unknown']);
    }
    




/**
 * Calculate delivery cost with caching
 */
public function calculateDelivery($deliveryAddress, $orderDetails) {
    $this->logger->log('calculateDelivery - Starting', ['address' => $deliveryAddress]);
    
    // Check cache first if enabled
    if ($this->config['options']['cache_delivery_rates']) {
        $cached = $this->getCachedRate($deliveryAddress, $orderDetails);
        if ($cached) {
            return $cached;
        }
    }
    
    // Clean the delivery address - remove any duplicate parts
    $cleanAddress = preg_replace('/\s+/', ' ', trim($deliveryAddress));
    
    $points = [
        [
            'address' => $this->config['store']['pickup_address'],
            'contact_person' => [
                'phone' => $this->config['store']['phone']
            ]
        ],
        [
            'address' => $cleanAddress,
            'contact_person' => [
                'phone' => $orderDetails['customer_phone']
            ]
        ]
    ];
    
    // Add COD if applicable
    if ($orderDetails['payment_method'] === 'cod' && $orderDetails['order_total'] > 0) {
        $points[1]['is_cod_cash_voucher_required'] = true;
        $points[1]['taking_amount'] = (string)$orderDetails['order_total'];
    }
    
    $requestData = [
        'matter' => $orderDetails['description'] ?? 'Food Delivery',
        'points' => $points,
        'total_weight_kg' => (int)($orderDetails['total_weight'] ?? 1),
        'is_client_notification_enabled' => $this->config['options']['enable_notifications'] ?? false,
        'is_contact_person_notification_enabled' => $this->config['options']['enable_notifications'] ?? false
    ];
    
    $this->logger->log('calculateDelivery - Request Data', $requestData);
    
    $result = $this->borzoAPI->request('/calculate-order', $requestData);
    
    $this->logger->log('calculateDelivery - Response', $result);
    
    if ($result['http_code'] == 200) {
        if (isset($result['response']['is_successful']) && $result['response']['is_successful']) {
            $response = [
                'success' => true,
                'delivery_fee' => $result['response']['order']['delivery_fee_amount'] ?? '0.00',
                'total_cost' => $result['response']['order']['payment_amount'] ?? '0.00',
                'warnings' => $result['response']['warnings'] ?? []
            ];
            
            // Cache the result
            $this->cacheRate($deliveryAddress, $orderDetails, $response, $result['response']);
            
            return $response;
        } else {
            // API returned error but with 200 status
            return [
                'success' => false,
                'errors' => $result['response']['errors'] ?? ['Unknown error'],
                'parameter_errors' => $result['response']['parameter_errors'] ?? null,
                'warnings' => $result['response']['warnings'] ?? []
            ];
        }
    }
    
    return [
        'success' => false,
        'errors' => ['API request failed with status: ' . $result['http_code']]
    ];
}



    
    /**
     * Create delivery order and link to your order
     */
    public function createOrder($orderData) {
        $this->logger->log('===== CREATE ORDER STARTED =====', ['order_id' => $orderData['order_id']]);
        $this->logger->log('Full order data received', $orderData);
        
        // Validate pickup address
        if (empty($orderData['pickup_address'])) {
            $this->logger->log('ERROR - Pickup address is empty in order data', $orderData);
            return [
                'success' => false,
                'errors' => ['Pickup address is missing from order data']
            ];
        }
        
        $this->logger->log('Using pickup address', ['address' => $orderData['pickup_address']]);
        
        $points = [
            [
                'address' => $orderData['pickup_address'],
                'contact_person' => [
                    'phone' => $orderData['store_phone'],
                    'name' => $orderData['store_name']
                ],
                'client_order_id' => (string)$orderData['order_id'],
                'note' => 'Ready for pickup'
            ],
            [
                'address' => $orderData['delivery_address'],
                'contact_person' => [
                    'phone' => $orderData['customer_phone'],
                    'name' => $orderData['customer_name']
                ],
                'note' => $orderData['delivery_instructions'] ?? ''
            ]
        ];
        
        $this->logger->log('Points array built', $points);
        
        // Validate points
        foreach ($points as $index => $point) {
            if (empty($point['address'])) {
                return $this->error("Point $index address is empty");
            }
            if (empty($point['contact_person']['phone'])) {
                return $this->error("Point $index phone is empty");
            }
            if (empty($point['contact_person']['name'])) {
                return $this->error("Point $index name is empty");
            }
        }
        
        // Add COD if applicable
        if ($orderData['payment_method'] === 'cod') {
            $points[1]['is_cod_cash_voucher_required'] = true;
            $points[1]['taking_amount'] = (string)$orderData['total_amount'];
            $this->logger->log('COD Added', ['amount' => $orderData['total_amount']]);
        }
        
        // Add package details if available
        if (!empty($orderData['items'])) {
            $points[1]['packages'] = [];
            foreach ($orderData['items'] as $item) {
                $package = [
                    'ware_code' => $item['product_id'] ?? 'CARD' . ($item['item_id'] ?? ''),
                    'description' => $item['product_name'],
                    'items_count' => (float)$item['quantity'],
                    'item_payment_amount' => (string)$item['price']
                ];
                $points[1]['packages'][] = $package;
                $this->logger->log('Package Added', $package);
            }
        }
        
        $requestData = [
            'type' => 'standard',
            'matter' => 'Order #' . $orderData['order_id'],
            'points' => $points,
            'total_weight_kg' => (int)($orderData['total_weight'] ?? 1),
            'is_client_notification_enabled' => $this->config['options']['enable_notifications'] ?? true,
            'is_contact_person_notification_enabled' => $this->config['options']['enable_notifications'] ?? true
        ];
        
        $this->logger->log('Final request data to Borzo API', $requestData);
        
        $result = $this->borzoAPI->request('/create-order', $requestData);
        $this->logger->log('Raw response from Borzo API', $result);
        
        if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
            $borzoOrder = $result['response']['order'];
            $this->logger->log('Order created successfully', $borzoOrder);
            
            // Update your order with Borzo details including tracking URLs
            $this->updateOrderWithBorzoDetails($orderData['order_id'], $borzoOrder);
            
            return [
                'success' => true,
                'borzo_order_id' => $borzoOrder['order_id'],
                'borzo_order_name' => $borzoOrder['order_name'],
                'status' => $borzoOrder['status'],
                'delivery_fee' => $borzoOrder['delivery_fee_amount'],
                'estimated_pickup' => $borzoOrder['points'][0]['required_start_datetime'] ?? null,
                'estimated_delivery' => $borzoOrder['points'][1]['required_start_datetime'] ?? null
            ];
        }
        
        // Return detailed error
        $errors = $result['response']['errors'] ?? ['Failed to create order'];
        $parameterErrors = $result['response']['parameter_errors'] ?? null;
        
        $this->logger->log('Order creation failed', [
            'http_code' => $result['http_code'],
            'errors' => $errors,
            'parameter_errors' => $parameterErrors
        ]);
        
        return [
            'success' => false,
            'errors' => $errors,
            'parameter_errors' => $parameterErrors
        ];
    }
    
    /**
     * Track order and update your database
     */
    public function trackOrder($orderId) {
        $this->logger->log('trackOrder - Starting', ['order_id' => $orderId]);
        
        global $conn;
        
        $sql = "SELECT borzo_order_id FROM orders WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        
        if (!$order || empty($order['borzo_order_id'])) {
            return ['success' => false, 'error' => 'No Borzo order found'];
        }
        
        $borzoOrderId = $order['borzo_order_id'];
        
        $this->logger->log('trackOrder - Fetching Borzo order', ['borzo_order_id' => $borzoOrderId]);
        
        $result = $this->borzoAPI->request('/orders', ['order_id' => $borzoOrderId], 'GET');
        
        $this->logger->log('trackOrder - Borzo Response', $result);
        
        if ($result['http_code'] == 200 && !empty($result['response']['orders'])) {
            $borzoOrder = $result['response']['orders'][0];
            
            // Update tracking info
            $this->updateTrackingInfo($orderId, $borzoOrder);
            
            // Get courier info if available
            $courier = null;
            if ($borzoOrder['status'] === 'active') {
                $courierResult = $this->borzoAPI->request('/courier', ['order_id' => $borzoOrderId], 'GET');
                if ($courierResult['http_code'] == 200 && $courierResult['response']['courier']) {
                    $courier = $courierResult['response']['courier'];
                    $this->updateCourierInfo($orderId, $courier);
                }
            }
            
            // Extract tracking URL from points
            $trackingUrl = null;
            if (isset($borzoOrder['points']) && is_array($borzoOrder['points'])) {
                foreach ($borzoOrder['points'] as $point) {
                    if (isset($point['tracking_url'])) {
                        $trackingUrl = $point['tracking_url'];
                        break;
                    }
                }
            }
            
            // Extract estimated delivery time
            $estimatedDelivery = null;
            if (isset($borzoOrder['points'][1]['required_start_datetime'])) {
                $estimatedDelivery = $borzoOrder['points'][1]['required_start_datetime'];
            }
            
            return [
                'success' => true,
                'status' => $borzoOrder['status'],
                'status_description' => $borzoOrder['status_description'],
                'tracking_url' => $trackingUrl,
                'estimated_delivery' => $estimatedDelivery,
                'points' => $borzoOrder['points'],
                'courier' => $courier
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to track order'];
    }
    



    /**
     * Cancel Borzo delivery order
     */
    public function cancelOrder($orderId) {
        $this->logger->log('cancelOrder - Starting', ['order_id' => $orderId]);
        
        global $conn;
        
        $sql = "SELECT borzo_order_id, borzo_status FROM orders WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        
        if (!$order || empty($order['borzo_order_id'])) {
            return [
                'success' => false,
                'errors' => ['No Borzo order found for this order']
            ];
        }
        
        $borzoOrderId = $order['borzo_order_id'];
        
        $this->logger->log('cancelOrder - Cancelling Borzo order', ['borzo_order_id' => $borzoOrderId]);
        
        $result = $this->borzoAPI->request('/cancel-order', ['order_id' => $borzoOrderId]);
        
        $this->logger->log('cancelOrder - Borzo Response', $result);
        
        if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
            $sql = "UPDATE orders SET 
                    borzo_status = 'canceled',
                    borzo_last_sync = NOW()
                    WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'Borzo delivery cancelled successfully'
            ];
        }
        
        $errors = $result['response']['errors'] ?? ['Failed to cancel Borzo order'];
        
        return [
            'success' => false,
            'errors' => $errors
        ];
    }
    




/**
 * Update order with Borzo details after creation
 */
private function updateOrderWithBorzoDetails($orderId, $borzoOrder) {
    global $conn;
    
    $this->logger->log('Updating order with Borzo details', ['order_id' => $orderId, 'borzo_order_id' => $borzoOrder['order_id']]);
    
    // Extract tracking URLs from points
    $pickupTrackingUrl = null;
    $deliveryTrackingUrl = null;
    $trackingUrl = null;
    $geocodedAddress = null;
    
    if (isset($borzoOrder['points']) && is_array($borzoOrder['points'])) {
        foreach ($borzoOrder['points'] as $index => $point) {
            if (isset($point['tracking_url'])) {
                if ($index === 0) {
                    $pickupTrackingUrl = $point['tracking_url'];
                } else {
                    $deliveryTrackingUrl = $point['tracking_url'];
                }
            }
            // Get the geocoded address for delivery point
            if ($index === 1 && isset($point['address'])) {
                $geocodedAddress = $point['address'];
            }
        }
    }
    
    $trackingUrl = $deliveryTrackingUrl ?: $pickupTrackingUrl;
    
    $this->logger->log('Tracking URL extracted', [
        'pickup_url' => $pickupTrackingUrl,
        'delivery_url' => $deliveryTrackingUrl,
        'final_url' => $trackingUrl,
        'geocoded_address' => $geocodedAddress
    ]);
    
    $sql = "UPDATE orders SET 
            borzo_order_id = ?,
            borzo_order_name = ?,
            borzo_status = ?,
            borzo_status_description = ?,
            delivery_fee = ?,
            delivery_tracking_url = ?,
            borzo_geocoded_address = ?,
            estimated_pickup_time = ?,
            estimated_delivery_time = ?,
            borzo_last_sync = NOW()
            WHERE order_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $this->logger->log('Database prepare error', ['error' => $conn->error]);
        return false;
    }
    
    $borzoOrderId = (int)$borzoOrder['order_id'];
    $borzoOrderName = (string)$borzoOrder['order_name'];
    $borzoStatus = (string)$borzoOrder['status'];
    $borzoStatusDesc = (string)($borzoOrder['status_description'] ?? $borzoOrder['status']);
    $deliveryFee = (float)$borzoOrder['delivery_fee_amount'];
    $pickupTime = isset($borzoOrder['points'][0]['required_start_datetime']) ? (string)$borzoOrder['points'][0]['required_start_datetime'] : null;
    $deliveryTime = isset($borzoOrder['points'][1]['required_start_datetime']) ? (string)$borzoOrder['points'][1]['required_start_datetime'] : null;
    
    $stmt->bind_param(
        'isssdssssi',
        $borzoOrderId,
        $borzoOrderName,
        $borzoStatus,
        $borzoStatusDesc,
        $deliveryFee,
        $trackingUrl,
        $geocodedAddress,
        $pickupTime,
        $deliveryTime,
        $orderId
    );
    
    $result = $stmt->execute();
    if (!$result) {
        $this->logger->log('Execute error', ['error' => $stmt->error]);
    }
    $stmt->close();
    
    $this->logger->log('Order update executed', ['result' => $result]);
    
    // Add initial tracking record
    $this->addTrackingRecord($orderId, $borzoOrder, $trackingUrl);
    
    return $result;
}




    
    /**
     * Add tracking record to history - SINGLE VERSION
     */
    private function addTrackingRecord($orderId, $borzoOrder, $trackingUrl = null) {
        global $conn;
        
        $this->logger->log('Adding tracking record', [
            'order_id' => $orderId,
            'borzo_order_id' => $borzoOrder['order_id'],
            'tracking_url' => $trackingUrl
        ]);
        
        $sql = "INSERT INTO order_delivery_tracking 
                (order_id, borzo_order_id, status, status_description, tracking_url, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $this->logger->log('Tracking insert prepare error', ['error' => $conn->error]);
            return false;
        }
        
        $borzoOrderId = (int)$borzoOrder['order_id'];
        $status = (string)$borzoOrder['status'];
        $statusDesc = (string)($borzoOrder['status_description'] ?? $borzoOrder['status']);
        
        $stmt->bind_param(
            'iisss',
            $orderId,
            $borzoOrderId,
            $status,
            $statusDesc,
            $trackingUrl
        );
        
        $result = $stmt->execute();
        if (!$result) {
            $this->logger->log('Tracking insert error', ['error' => $stmt->error]);
        }
        $stmt->close();
        
        $this->logger->log('Tracking record added', ['result' => $result]);
        return $result;
    }
    
/**
 * Update tracking information
 */
private function updateTrackingInfo($orderId, $borzoOrder) {
    global $conn;
    
    // If no database connection, return
    if (!$conn) {
        $this->logger->log('No database connection in updateTrackingInfo');
        return false;
    }
    
    $trackingUrl = null;
    if (isset($borzoOrder['points']) && is_array($borzoOrder['points'])) {
        foreach ($borzoOrder['points'] as $point) {
            if (isset($point['tracking_url'])) {
                $trackingUrl = $point['tracking_url'];
                break;
            }
        }
    }
    
    // Update orders table
    $sql = "UPDATE orders SET 
            borzo_status = ?,
            borzo_status_description = ?,
            delivery_tracking_url = COALESCE(?, delivery_tracking_url),
            borzo_last_sync = NOW()
            WHERE order_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $this->logger->log('Prepare failed in updateTrackingInfo', ['error' => $conn->error]);
        return false;
    }
    
    // FIX: Use variables, not direct values in bind_param
    $status = $borzoOrder['status'] ?? '';
    $statusDesc = $borzoOrder['status_description'] ?? ($borzoOrder['status'] ?? '');
    
    $stmt->bind_param('sssi', 
        $status,
        $statusDesc,
        $trackingUrl,
        $orderId
    );
    
    $result = $stmt->execute();
    if (!$result) {
        $this->logger->log('Execute failed in updateTrackingInfo', ['error' => $stmt->error]);
    }
    $stmt->close();
    
    // Add to tracking history if status changed
    $sql = "INSERT INTO order_delivery_tracking 
            (order_id, borzo_order_id, status, status_description, tracking_url, created_at) 
            SELECT ?, ?, ?, ?, ?, NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM order_delivery_tracking 
                WHERE order_id = ? AND status = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            )";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $borzoOrderId = $borzoOrder['order_id'] ?? 0;
        $stmt->bind_param('iisssis',
            $orderId,
            $borzoOrderId,
            $status,
            $statusDesc,
            $trackingUrl,
            $orderId,
            $status
        );
        $stmt->execute();
        $stmt->close();
    }
    
    return true;
}
    
    /**
     * Update courier information
     */
    private function updateCourierInfo($orderId, $courier) {
        global $conn;
        
        $sql = "UPDATE orders SET 
                courier_name = CONCAT(?, ' ', ?),
                courier_phone = ?,
                courier_latitude = ?,
                courier_longitude = ?,
                borzo_last_sync = NOW()
                WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sssssi',
            $courier['name'] ?? '',
            $courier['surname'] ?? '',
            $courier['phone'] ?? null,
            $courier['latitude'] ?? null,
            $courier['longitude'] ?? null,
            $orderId
        );
        $stmt->execute();
        $stmt->close();
        
        $sql = "UPDATE order_delivery_tracking 
                SET courier_info = ? 
                WHERE order_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $courierJson = json_encode($courier);
        $stmt->bind_param('si', $courierJson, $orderId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Get cached delivery rate
     */
    private function getCachedRate($deliveryAddress, $orderDetails) {
        preg_match('/\b\d{6}\b/', $deliveryAddress, $matches);
        $deliveryPincode = $matches[0] ?? null;
        
        if (!$deliveryPincode) {
            return null;
        }
        
        global $conn;
        
        $sql = "SELECT delivery_fee, total_amount FROM delivery_rate_cache 
                WHERE delivery_pincode = ? AND total_weight = ? 
                AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $deliveryPincode, $orderDetails['total_weight'] ?? 1);
        $stmt->execute();
        $result = $stmt->get_result();
        $cached = $result->fetch_assoc();
        $stmt->close();
        
        if ($cached) {
            return [
                'success' => true,
                'delivery_fee' => $cached['delivery_fee'],
                'total_cost' => $cached['total_amount'],
                'cached' => true
            ];
        }
        
        return null;
    }
    
    /**
     * Cache delivery rate
     */
    private function cacheRate($deliveryAddress, $orderDetails, $response, $rawResponse) {
        preg_match('/\b\d{6}\b/', $deliveryAddress, $matches);
        $deliveryPincode = $matches[0] ?? null;
        
        if (!$deliveryPincode) {
            return;
        }
        
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['options']['cache_expiry']);
        
        global $conn;
        
        $sql = "INSERT INTO delivery_rate_cache 
                (delivery_pincode, total_weight, delivery_fee, total_amount, raw_response, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $rawJson = json_encode($rawResponse);
        $stmt->bind_param(
            'siddss',
            $deliveryPincode,
            $orderDetails['total_weight'] ?? 1,
            $response['delivery_fee'],
            $response['total_cost'],
            $rawJson,
            $expiresAt
        );
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Error helper
     */
    private function error($message) {
        $this->logger->log('ERROR', ['message' => $message]);
        return [
            'success' => false,
            'errors' => [$message]
        ];
    }
}