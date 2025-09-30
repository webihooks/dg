<?php
require 'db_connection.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no'); // Important for Nginx

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "data: " . json_encode(['error' => 'Not authenticated']) . "\n\n";
    ob_flush();
    flush();
    exit;
}

$user_id = $_SESSION['user_id'];
$lastEventId = floatval(isset($_SERVER['HTTP_LAST_EVENT_ID']) ? $_SERVER['HTTP_LAST_EVENT_ID'] : 0);

// Set longer execution time
set_time_limit(0);
ignore_user_abort(true);

// Close session to allow other requests
session_write_close();

// Create order_updates table if not exists
$create_table_sql = "
CREATE TABLE IF NOT EXISTS order_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    user_id INT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    updated_by_session VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    INDEX idx_user_timestamp (user_id, timestamp)
)";
$conn->query($create_table_sql);

while (true) {
    // Check for new order updates
    $sql = "SELECT ou.id, ou.order_id, ou.new_status, o.customer_name 
            FROM order_updates ou 
            JOIN orders o ON ou.order_id = o.order_id 
            WHERE o.user_id = ? AND ou.id > ? 
            ORDER BY ou.timestamp DESC 
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $lastEventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updates = [];
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
        $lastEventId = max($lastEventId, $row['id']);
    }
    $stmt->close();
    
    foreach ($updates as $update) {
        echo "data: " . json_encode([
            'type' => 'order_update',
            'id' => $update['id'],
            'order_id' => $update['order_id'],
            'new_status' => $update['new_status'],
            'customer_name' => $update['customer_name'],
            'timestamp' => time()
        ]) . "\n\n";
        
        ob_flush();
        flush();
    }
    
    // Break if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    // Sleep for 2 seconds before checking again
    sleep(2);
}

$conn->close();
?>