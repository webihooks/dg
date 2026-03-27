<?php
// borzo/api/create-order.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

function formatPhoneNumber($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return '91' . $phone;
    }
    if (strlen($phone) == 12 && substr($phone, 0, 2) == '91') {
        return $phone;
    }
    return ltrim($phone, '+');
}

function formatFullAddress($order) {
    $addressParts = [];
    
    if (!empty($order['building'])) {
        $addressParts[] = trim($order['building']);
    }
    if (!empty($order['floor'])) {
        $addressParts[] = 'Floor ' . trim($order['floor']);
    }
    if (!empty($order['flat_unit'])) {
        $addressParts[] = 'Unit ' . trim($order['flat_unit']);
    }
    if (!empty($order['landmark'])) {
        $addressParts[] = 'Near ' . trim($order['landmark']);
    }
    if (!empty($order['delivery_address'])) {
        $addressParts[] = trim($order['delivery_address']);
    }
    
    return implode(', ', $addressParts);
}

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $user_id = $_SESSION['user_id'];
    
    require_once dirname(__DIR__, 2) . '/db_connection.php';
    
    $configPath = dirname(__DIR__) . '/config/borzo.php';
    if (!file_exists($configPath)) {
        throw new Exception('Borzo config file not found');
    }
    
    $borzoConfig = require $configPath;
    require_once dirname(__DIR__) . '/classes/DynamicDeliveryManager.php';

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['order_id'])) {
        throw new Exception('Invalid input: order_id required');
    }
    
    $orderId = (int)$input['order_id'];

    // CRITICAL FIX: Fetch ALL address fields from database
    $sql = "SELECT o.*, 
            o.building,
            o.floor,
            o.flat_unit,
            o.landmark,
            o.delivery_address,
            bi.business_name as store_name,
            bi.business_address as store_address,
            u.phone as store_phone
            FROM orders o 
            LEFT JOIN business_info bi ON o.user_id = bi.user_id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.order_id = ? AND o.user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $orderId, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order not found: ' . $orderId);
    }

    error_log("Order #$orderId - Address Components:");
    error_log("Building: " . ($order['building'] ?? 'NOT SET'));
    error_log("Floor: " . ($order['floor'] ?? 'NOT SET'));
    error_log("Flat/Unit: " . ($order['flat_unit'] ?? 'NOT SET'));
    error_log("Landmark: " . ($order['landmark'] ?? 'NOT SET'));
    error_log("Delivery Address: " . ($order['delivery_address'] ?? 'NOT SET'));

    $fullDeliveryAddress = formatFullAddress($order);
    
    if (empty($fullDeliveryAddress)) {
        $fullDeliveryAddress = $order['delivery_address'];
        error_log("Using fallback address: " . $fullDeliveryAddress);
    } else {
        error_log("Formatted full address: " . $fullDeliveryAddress);
    }

    if (empty($order['store_address'])) {
        if (empty($borzoConfig['store']['pickup_address'])) {
            throw new Exception('Business address not found. Please update your business information.');
        }
        $order['store_address'] = $borzoConfig['store']['pickup_address'];
        $order['store_name'] = $borzoConfig['store']['name'];
        $order['store_phone'] = $borzoConfig['store']['phone'];
    }

    if ($order['order_type'] !== 'delivery') {
        throw new Exception('Only delivery orders can be booked. Order type: ' . $order['order_type']);
    }

    if (!empty($order['borzo_order_id'])) {
        throw new Exception('Order already has a Borzo delivery: ' . $order['borzo_order_id']);
    }

    // Get items
    $sql = "SELECT * FROM order_items WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $customerPhone = formatPhoneNumber($order['customer_phone']);
    $storePhone = formatPhoneNumber($order['store_phone'] ?? $borzoConfig['store']['phone']);

    $orderData = [
        'order_id' => $order['order_id'],
        'pickup_address' => $order['store_address'],
        'store_name' => $order['store_name'] ?? $borzoConfig['store']['name'],
        'store_phone' => $storePhone,
        'delivery_address' => $fullDeliveryAddress,
        'building' => $order['building'] ?? '',
        'floor' => $order['floor'] ?? '',
        'flat_unit' => $order['flat_unit'] ?? '',
        'landmark' => $order['landmark'] ?? '',
        'customer_name' => $order['customer_name'],
        'customer_phone' => $customerPhone,
        'payment_method' => (isset($order['payment_method']) && $order['payment_method'] === 'cod') ? 'cod' : 'cash',
        'total_amount' => $order['total_amount'],
        'total_weight' => count($items) ?: 1,
        'delivery_instructions' => $order['order_notes'] ?? '',
        'items' => $items
    ];

    error_log("Final order data being sent: " . json_encode([
        'order_id' => $orderData['order_id'],
        'pickup_address' => $orderData['pickup_address'],
        'delivery_address' => $orderData['delivery_address'],
        'customer_name' => $orderData['customer_name']
    ]));

    $deliveryManager = new DynamicDeliveryManager($user_id, $borzoConfig);
    
    if (!$deliveryManager->hasValidApiKey()) {
        throw new Exception('Borzo API key not configured. Please add your API key in Borzo API settings.');
    }
    
    $result = $deliveryManager->createOrder($orderData);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Exception in create-order.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'errors' => [$e->getMessage()],
        'error' => $e->getMessage()
    ]);
}