<?php
// api/hourly_sales.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Hourly sales data for today
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
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $today);
$stmt->execute();
$result = $stmt->get_result();

$sales_data = [];
while ($row = $result->fetch_assoc()) {
    $sales_data[] = $row;
}

echo json_encode([
    'success' => true,
    'sales_data' => $sales_data
]);

$conn = null;
?>