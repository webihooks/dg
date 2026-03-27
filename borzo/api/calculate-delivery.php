<?php
// borzo/api/calculate-delivery.php - NO FALLBACKS, just pass through API response
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    error_log("Calculate Delivery Input: " . json_encode($input));
    
    $required = ['delivery_address', 'customer_phone'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'error' => "Missing $field"]);
            exit;
        }
    }
    
    // Format phone number
    function formatPhoneNumber($phone) {
        if (empty($phone)) return '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10) {
            return '91' . $phone;
        }
        if (strlen($phone) == 12 && substr($phone, 0, 2) == '91') {
            return $phone;
        }
        return $phone;
    }
    
    $formattedPhone = formatPhoneNumber($input['customer_phone']);
    
    $orderDetails = [
        'description' => 'Food Delivery',
        'customer_phone' => $formattedPhone,
        'order_total' => floatval($input['order_total'] ?? 0),
        'payment_method' => $input['payment_method'] ?? 'cash',
        'total_weight' => intval($input['total_weight'] ?? 1)
    ];
    
    $deliveryManager = new DynamicDeliveryManager($user_id, $borzoConfig);
    
    if (!$deliveryManager->hasValidApiKey()) {
        echo json_encode([
            'success' => false,
            'error' => 'Borzo API key not configured'
        ]);
        exit;
    }
    
    // Pass through the API response exactly as received - no modifications
    $result = $deliveryManager->calculateDelivery($input['delivery_address'], $orderDetails);
    
    error_log("Calculate Delivery Result: " . json_encode($result));
    
    // Return the exact response from the API
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Calculate Delivery Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}