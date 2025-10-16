<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://deegeecard.com'); // IMPORTANT for CORS if SW fetch from a different subdomain/origin
header('Cache-Control: no-cache, no-store, must-revalidate'); // Ensure fresh data
header('Pragma: no-cache');
header('Expires: 0');

// Database connection details (same as in login.php, or use a shared config)
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Implement your logic here to get the 'latest status' ---
    // This is just an example. You'll need to define what 'latest status' means.
    // For instance, fetch pending orders, new messages, etc.
    // Assuming you have an 'orders' table and want to check for new pending orders for a specific user/restaurant.
    // In a real scenario, the service worker might include a user ID in the fetch request or rely on a generic check.
    // For simplicity here, let's assume it just checks for a general system update or counts pending orders.

    // Example: Get count of new pending orders (you'd need to adapt this for your schema and actual user context)
    // This example assumes some way to identify "new" orders since the last check,
    // or just provides a current count of pending orders.
    $stmt = $conn->prepare("SELECT COUNT(*) AS new_orders_count FROM orders WHERE status = 'pending' AND created_at > (NOW() - INTERVAL 1 HOUR)"); // Example: orders in last hour
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newOrdersCount = $result['new_orders_count'];

    // You might also check for messages, announcements, etc.
    // For now, let's just send a simple status.

    $statusData = [
        'timestamp' => time(),
        'message' => 'System check successful.',
        'new_orders' => $newOrdersCount,
        'is_urgent' => $newOrdersCount > 0 // Example condition
    ];

    echo json_encode($statusData);

} catch (PDOException $e) {
    // Log the error for debugging, but return a generic error to the client
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred.']);
}
?>