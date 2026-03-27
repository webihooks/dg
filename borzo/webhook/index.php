<?php
require_once dirname(__DIR__, 2) . '/db_connection.php';
require_once dirname(__DIR__) . '/config/borzo.php';

$data = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_DV_SIGNATURE'] ?? '';
$calculated = hash_hmac('sha256', $data, $borzoConfig['webhook']['secret']);
if (!hash_equals($calculated, $signature)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

$webhookData = json_decode($data, true);
// Log to file (optional)
file_put_contents(dirname(__DIR__) . '/logs/borzo-webhook.log', date('Y-m-d H:i:s') . ' ' . $data . "\n", FILE_APPEND);

// Handle events
if (isset($webhookData['event_type']) && $webhookData['event_type'] === 'order_changed') {
    $order = $webhookData['order'];
    $sql = "UPDATE orders SET borzo_status = ?, borzo_last_sync = NOW() WHERE borzo_order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $order['status'], $order['order_id']);
    $stmt->execute();
    $stmt->close();
}
http_response_code(200);
echo 'OK';