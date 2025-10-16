<?php
// Add strict no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();

date_default_timezone_set('Asia/Kolkata');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get user details
    $user_sql = "SELECT name, role, is_trial, trial_end FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Check subscription
    $subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
    $subscription_stmt = $conn->prepare($subscription_sql);
    $subscription_stmt->execute([$user_id]);
    $has_active_subscription = ($subscription_stmt->rowCount() > 0);

    // Today's date
    $today = date('Y-m-d');

    // Today's sales summary
    $summary_sql = "SELECT 
                      COUNT(*) as total_orders,
                      SUM(total_amount) as total_sales,
                      SUM(subtotal) as subtotal,
                      SUM(discount_amount) as total_discounts,
                      SUM(gst_amount) as total_tax,
                      SUM(delivery_charge) as total_delivery,
                      AVG(total_amount) as avg_order_value
                    FROM orders 
                    WHERE user_id = ? 
                    AND status != 'cancelled'
                    AND DATE(created_at) = ?";
                    
    $stmt = $conn->prepare($summary_sql);
    $stmt->execute([$user_id, $today]);
    $summary_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Hourly sales data
    $hourly_sales_sql = "SELECT 
                        HOUR(created_at) as sale_hour,
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_sales
                      FROM orders 
                      WHERE user_id = ? 
                      AND status != 'cancelled'
                      AND DATE(created_at) = ?
                      GROUP BY HOUR(created_at)
                      ORDER BY sale_hour ASC";
                      
    $stmt = $conn->prepare($hourly_sales_sql);
    $stmt->execute([$user_id, $today]);
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user' => [
            'name' => $user['name'] ?? 'User',
            'role' => $user['role'] ?? 'user',
            'is_trial' => isset($user['is_trial']) ? (bool)$user['is_trial'] : false,
            'trial_end' => $user['trial_end'] ?? null,
            'has_active_subscription' => $has_active_subscription,
        ],
        'summary' => [
            'total_orders' => $summary_data['total_orders'] ?? 0,
            'total_sales' => $summary_data['total_sales'] ?? 0,
            'subtotal' => $summary_data['subtotal'] ?? 0,
            'total_discounts' => $summary_data['total_discounts'] ?? 0,
            'total_tax' => $summary_data['total_tax'] ?? 0,
            'total_delivery' => $summary_data['total_delivery'] ?? 0,
            'avg_order_value' => $summary_data['avg_order_value'] ?? 0,
        ],
        'hourly_sales' => $sales_data,
        'date' => $today
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>