<?php
/**
 * get-courier-location.php
 * Fetches live rider location for a given order from Borzo API.
 * Updates database and returns JSON.
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Validate order_id
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    if (!$order_id) {
        throw new Exception('Order ID is required');
    }

    // Database connection
    require_once dirname(__DIR__, 2) . '/db_connection.php';
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get user_id and borzo_status from orders table
    $sql = "SELECT user_id, borzo_order_id, borzo_status FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order not found');
    }

    $user_id = $order['user_id'];
    $borzo_status = $order['borzo_status'];

    // Default response (no rider data)
    $response = [
        'success' => true,
        'courier' => null
    ];

    // Only fetch if order is active (has an assigned rider)
    if ($borzo_status === 'active' && !empty($order['borzo_order_id'])) {
        // Load Borzo configuration
        $configPath = dirname(__DIR__) . '/config/borzo.php';
        if (!file_exists($configPath)) {
            throw new Exception('Borzo config file not found');
        }
        $borzoConfig = require $configPath;

        // Initialize DynamicDeliveryManager for this user (uses their API key)
        require_once dirname(__DIR__) . '/classes/DynamicDeliveryManager.php';
        $deliveryManager = new DynamicDeliveryManager($user_id, $borzoConfig);

        // Call trackOrder() – this will update courier_latitude/longitude in DB
        $trackResult = $deliveryManager->trackOrder($order_id);

        // Now fetch fresh rider details from DB
        $sql2 = "SELECT courier_name, courier_phone, courier_latitude, courier_longitude 
                 FROM orders WHERE order_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param('i', $order_id);
        $stmt2->execute();
        $rider = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($rider && !empty($rider['courier_latitude']) && !empty($rider['courier_longitude'])) {
            $response['courier'] = [
                'name' => $rider['courier_name'] ?? null,
                'phone' => $rider['courier_phone'] ?? null,
                'latitude' => $rider['courier_latitude'],
                'longitude' => $rider['courier_longitude']
            ];
        }
    } else {
        // Order not active – return whatever is already in DB (if any)
        $sql2 = "SELECT courier_name, courier_phone, courier_latitude, courier_longitude 
                 FROM orders WHERE order_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param('i', $order_id);
        $stmt2->execute();
        $rider = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($rider && !empty($rider['courier_latitude']) && !empty($rider['courier_longitude'])) {
            $response['courier'] = [
                'name' => $rider['courier_name'] ?? null,
                'phone' => $rider['courier_phone'] ?? null,
                'latitude' => $rider['courier_latitude'],
                'longitude' => $rider['courier_longitude']
            ];
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("get-courier-location.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>